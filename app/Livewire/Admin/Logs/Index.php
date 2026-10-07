<?php

namespace App\Livewire\Admin\Logs;

use App\Models\AdminActivityLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * DXA: Jurnal activitate cu filtre (runda 42): căutare text (descriere, persoană, subiect, cod acțiune),
 * categorie (prefixul codului „entitate.eveniment”), persoana și interval de date. Filtrele rămân în URL.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    /** Prefixul codului de acțiune => etichetă. Categoriile necunoscute apar cu prefixul ca etichetă. */
    public const CATEGORY_LABELS = [
        'admin' => 'Conturi admin',
        'announcement' => 'Anunțuri',
        'bar' => 'Bar (raportări)',
        'credits' => 'Credite',
        'entries' => 'Intrări',
        'loyalty' => 'Card de fidelitate',
        'menu' => 'Meniu bar',
        'participants' => 'Participanți',
        'party' => 'Petreceri',
        'promoter' => 'Promotori',
        'reception' => 'Recepție',
        'sales' => 'Vânzări bar',
        'settings' => 'Setări',
        'stock' => 'Stocuri',
        'tickets' => 'Bilete',
    ];

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'categorie')]
    public string $category = '';

    #[Url(as: 'cine')]
    public string $actor = '';

    #[Url(as: 'de_la')]
    public string $dateFrom = '';

    #[Url(as: 'pana_la')]
    public string $dateTo = '';

    public function mount(): void
    {
        abort_unless(Auth::guard('admin')->user()->permits('logs'), 403); // DXA: runda 46 — permisiune, nu doar superadmin
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'category', 'actor', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'category', 'actor', 'dateFrom', 'dateTo');
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->category !== '' || $this->actor !== '' || $this->dateFrom !== '' || $this->dateTo !== '';
    }

    private static function date(string $value): ?Carbon
    {
        try {
            return $value === '' ? null : Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Escapează % și _ pentru LIKE (caracter de escape „!”, la fel în MySQL și SQLite). */
    private static function like(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    public function render()
    {
        $from = self::date($this->dateFrom);
        $to = self::date($this->dateTo);
        $search = trim($this->search);

        $logs = AdminActivityLog::query()
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.self::like($search).'%';
                $q->where(fn ($w) => $w
                    ->whereRaw("description like ? escape '!'", [$like])
                    ->orWhereRaw("actor_label like ? escape '!'", [$like])
                    ->orWhereRaw("subject_label like ? escape '!'", [$like])
                    ->orWhereRaw("action like ? escape '!'", [$like]));
            })
            ->when($this->category !== '', fn ($q) => $q->whereRaw("action like ? escape '!'", [self::like($this->category).'.%']))
            ->when($this->actor !== '', fn ($q) => $q->where('actor_label', $this->actor))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->latest('created_at')->latest('id')
            ->paginate(20);

        $prefixes = AdminActivityLog::query()->pluck('action')
            ->map(fn ($a) => strstr($a, '.', true) ?: $a)->unique()->sort()->values();
        $categoryOptions = ['' => 'Toate categoriile'];
        foreach ($prefixes as $p) {
            $categoryOptions[$p] = self::CATEGORY_LABELS[$p] ?? $p;
        }

        $actorOptions = ['' => 'Toate persoanele'] + AdminActivityLog::query()->whereNotNull('actor_label')
            ->distinct()->orderBy('actor_label')->pluck('actor_label', 'actor_label')->all();

        return view('livewire.admin.logs.index', [
            'logs' => $logs,
            'categoryOptions' => $categoryOptions,
            'actorOptions' => $actorOptions,
            'hasFilters' => $this->hasFilters(),
        ]);
    }
}
