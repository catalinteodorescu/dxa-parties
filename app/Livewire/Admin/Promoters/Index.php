<?php

namespace App\Livewire\Admin\Promoters;

use App\Models\Party;
use App\Models\PartyDiscountCode;
use App\Models\Promoter;
use App\Services\ActivityLogger;
use App\Services\DiscountCodeStats;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * DXA: adaugat (Coduri de reducere - promotori). Evidența promotorilor (adaugă / editează / dezactivează / șterge dacă n-au coduri)
 * și clasamentul lor între petreceri: cine a adus cei mai mulți participanți (noi și reveniți) cu codurile lui.
 * Cifrele vin din App\Services\DiscountCodeStats::overall().
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    /** Perioada clasamentului: all | last5 | last10 (ultimele petreceri cu coduri folosite). */
    #[Url(as: 'perioada')]
    public string $period = 'all';

    /** Popup adăugare/editare: null = închis, 0 = promotor nou, altfel id-ul editat. */
    public ?int $editing = null;

    public string $name = '';

    public string $phone = '';

    public string $note = '';

    /** Popup de confirmare ștergere (id promotor) sau null. */
    public ?int $deleting = null;

    public function mount(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);
    }

    public function openForm(int $id = 0): void
    {
        $this->resetErrorBag();
        $this->editing = $id;
        $p = $id ? Promoter::find($id) : null;
        $this->name = $p?->name ?? '';
        $this->phone = $p?->phone ?? '';
        $this->note = $p?->note ?? '';
    }

    public function closeForm(): void
    {
        $this->editing = null;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['name.required' => 'Scrie numele promotorului.']);

        $id = (int) $this->editing;
        $name = trim($this->name);

        if (Promoter::findByName($name, $id ?: null)) {
            $this->addError('name', 'Există deja un promotor cu acest nume.');

            return;
        }

        $data = ['name' => $name, 'phone' => trim($this->phone) ?: null, 'note' => trim($this->note) ?: null];

        if ($id && ($p = Promoter::find($id))) {
            $p->update($data);
            ActivityLogger::log('promoter.updated', 'A modificat promotorul „'.$p->name.'".');
        } else {
            $p = Promoter::create($data + ['is_active' => true]);
            ActivityLogger::log('promoter.created', 'A adăugat promotorul „'.$p->name.'".');
        }

        $this->editing = null;
    }

    public function toggleActive(int $id): void
    {
        if ($p = Promoter::find($id)) {
            $p->update(['is_active' => ! $p->is_active]);
        }
    }

    public function askDelete(int $id): void
    {
        $this->deleting = $id;
    }

    public function cancelDelete(): void
    {
        $this->deleting = null;
    }

    /** Șterge doar promotorii fără coduri (cei cu coduri se dezactivează: istoricul îi referă). */
    public function delete(): void
    {
        $p = $this->deleting ? Promoter::find($this->deleting) : null;
        $this->deleting = null;

        if (! $p) {
            return;
        }
        if (PartyDiscountCode::query()->where('promoter_id', $p->id)->exists()) {
            session()->flash('error', 'Promotorul are coduri la petreceri și nu se poate șterge. Dezactivează-l.');

            return;
        }

        $p->delete();
        ActivityLogger::log('promoter.deleted', 'A șters promotorul „'.$p->name.'".');
    }

    public function render()
    {
        $partyIds = null;
        if (in_array($this->period, ['last5', 'last10'], true)) {
            $withCodes = PartyDiscountCode::query()->select('party_id')->distinct();
            $partyIds = Party::query()->whereIn('id', $withCodes)->orderByDesc('starts_at')->orderByDesc('id')
                ->limit($this->period === 'last5' ? 5 : 10)->pluck('id')->all();
        }

        $stats = DiscountCodeStats::overall($partyIds);
        $byId = $stats->promoters->filter(fn ($r) => $r->promoter_id)->keyBy('promoter_id');
        $codeCounts = PartyDiscountCode::query()->selectRaw('promoter_id, COUNT(*) as n')->whereNotNull('promoter_id')->groupBy('promoter_id')->pluck('n', 'promoter_id');

        // Evidența: toți promotorii (și cei fără utilizări), cu cifrele lor pe perioada aleasă.
        $promoters = Promoter::query()->orderBy('name')->get()->map(fn (Promoter $p) => (object) [
            'model' => $p,
            'codes' => (int) ($codeCounts[$p->id] ?? 0),
            'stat' => $byId->get($p->id),
        ]);

        return view('livewire.admin.promoters.index', [
            'stats' => $stats,
            'ranking' => $stats->promoters,
            'promoters' => $promoters,
            'periodOptions' => ['all' => 'Toate petrecerile', 'last5' => 'Ultimele 5 petreceri cu coduri', 'last10' => 'Ultimele 10 petreceri cu coduri'],
        ]);
    }
}
