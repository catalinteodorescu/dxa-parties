<?php

namespace App\Livewire\Admin\Push;

use App\Models\Participant;
use App\Models\PushDelivery;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Services\Push\Notifier;
use App\Support\Settings\Settings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * DXA: adaugat (runda 70). Ultimele notificări (doar citire), deschisă din Setări › Aplicație participanți › Notificări: câți participanți au activat
 * notificările, ce s-a trimis și cât a ajuns pe dispozitive, plus starea configurării (chei VAPID) și ultima rulare a trimiterilor programate.
 * Tipurile pornite/oprite se schimbă din componenta NotificationTypes (în Setări).
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'tip')]
    public string $kind = '';

    public function updatedKind(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $since = now()->subDays(7);

        return view('livewire.admin.push.index', [
            'enabled' => Notifier::enabled(),
            'lastRun' => Settings::get('push_last_run'),
            'subscribers' => PushSubscription::query()->whereNotNull('participant_id')->distinct()->count('participant_id'),
            'devices' => PushSubscription::query()->whereNotNull('participant_id')->count(),   // runda 77: fără abonările personalului
            'totalParticipants' => Participant::query()->whereNull('anonymized_at')->whereNotNull('password')->count(),
            'sent7' => PushNotification::query()->whereNull('admin_id')->where('created_at', '>=', $since)->count(),
            'delivered7' => PushDelivery::query()->whereNotNull('delivered_at')->where('sent_at', '>=', $since)->whereHas('notification', fn ($q) => $q->whereNull('admin_id'))->count(),
            'byKind' => PushNotification::query()->whereNull('admin_id')->where('created_at', '>=', $since)->selectRaw('kind, count(*) as n')->groupBy('kind')->pluck('n', 'kind')->all(),
            'kindOptions' => ['' => 'Toate tipurile'] + PushNotification::KIND_LABELS,
            'items' => PushNotification::query()->whereNull('admin_id')->with('participant')->withCount(['deliveries', 'deliveries as delivered_count' => fn ($q) => $q->whereNotNull('delivered_at')])
                ->when($this->kind !== '', fn ($q) => $q->where('kind', $this->kind))->orderByDesc('id')->paginate(25),
        ]);
    }
}
