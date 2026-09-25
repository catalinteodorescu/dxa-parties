<?php

namespace App\Models;

use App\Services\ActivityLogger;
use App\Services\ReceptionSummary;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * DXA: adaugat (Recepție - raportări). Raportarea de recepție = închiderea casei unei sesiuni de recepție.
 *
 * Draft (autosave): admin-ul introduce fondul de casă, cash-ul predat/scos (cu notă), cash-ul numărat și note pe metode;
 * încasările (intrări + tokeni) se calculează live din sesiune. La finalizare valorile așteptate se ÎNGHEAȚĂ
 * (cash_entries, cash_tokens, expected_cash, cash_diff, `snapshot`) și sesiunea se închide, în aceeași tranzacție.
 * Doar cash-ul se numără; celelalte metode apar ca totaluri așteptate. Diferența de casă se salvează, fără mișcări automate.
 * O raportare finalizată nu se mai modifică și nu se șterge.
 */
class ReceptionReport extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'finalized' => 'Finalizat',
    ];

    protected $fillable = [
        'reception_session_id',
        'party_id',
        'date',    // fixată la crearea draftului (ziua în care a fost pornită raportarea) — needitabilă
        'status',
        'note',
        'opening_float',
        'handed_over',
        'handed_note',
        'counted_cash',
        'method_notes',
        'cash_entries',
        'cash_tokens',
        'expected_cash',
        'cash_diff',
        'snapshot',
        'finalized_by',
        'finalized_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'opening_float' => 'decimal:2',
            'handed_over' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'cash_entries' => 'decimal:2',
            'cash_tokens' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'cash_diff' => 'decimal:2',
            'method_notes' => 'array',
            'snapshot' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ReceptionSession::class, 'reception_session_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'finalized_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    /** Numărul documentului, ca la StockReport: „22 / 23.09.2026” (id-ul din DB + data raportării). */
    public function number(): string
    {
        return $this->id.' / '.$this->date->format('d.m.Y');
    }

    /** „Raportare de recepție nr. 22 / 23.09.2026” */
    public function title(): string
    {
        return 'Raportare de recepție nr. '.$this->number();
    }

    /**
     * Draftul sesiunii (îl creează dacă nu există) — „Închide casa”. Doar pentru o sesiune deschisă.
     * Fondul de casă se propune din cash-ul rămas după raportarea anterioară a petrecerii (numărat − predat).
     */
    public static function startFor(ReceptionSession $session, ?int $adminId = null): self
    {
        if ($existing = static::query()->where('reception_session_id', $session->id)->first()) {
            return $existing;
        }
        if (! $session->isOpen()) {
            throw new DomainException('Sesiunea e deja închisă.');
        }

        $report = static::create([
            'reception_session_id' => $session->id,
            'party_id' => $session->party_id,
            'date' => now()->toDateString(),
            'status' => 'draft',
            'opening_float' => static::suggestedFloat($session->party_id),
            'created_by' => $adminId,
        ]);

        ActivityLogger::log('reception.report_started', sprintf(
            'A început raportarea de recepție nr. %s la „%s”.',
            $report->number(),
            $session->party?->name ?? '—'
        ));

        return $report;
    }

    /** Cash-ul rămas în casă după ultima raportare finalizată a petrecerii (numărat − predat), sau null. */
    public static function suggestedFloat(int $partyId): ?float
    {
        $last = static::query()->where('party_id', $partyId)->where('status', 'finalized')
            ->whereNotNull('counted_cash')->orderByDesc('finalized_at')->orderByDesc('id')->first();

        return $last ? max(0.0, round((float) $last->counted_cash - (float) ($last->handed_over ?? 0), 2)) : null;
    }

    /**
     * Cifrele raportării: pentru draft, calculate live din sesiune + ce a introdus adminul; pentru finalizată, cele
     * ÎNGHEȚATE. Aceeași formă în ambele cazuri (ecran, PDF, statistici).
     *
     * @return object{finalized: bool, opening_float: float, cash_entries: float, cash_tokens: float, handed_over: float, expected_cash: float, counted_cash: ?float, cash_diff: ?float, methods: array<int, array{key: string, label: string, amount: float, note: ?string}>, other_methods: array, entries_count: int, entries_free: int, entries_revenue: float, entries_cancelled: int, tickets: array, tokens_sold: int, tokens_amount: float, token_sales: int, token_sales_cancelled: int}
     */
    public function figures(): object
    {
        if ($this->isFinalized() && is_array($this->snapshot)) {
            $s = $this->snapshot;

            return (object) [
                'finalized' => true,
                'opening_float' => (float) ($this->opening_float ?? 0),
                'cash_entries' => (float) $this->cash_entries,
                'cash_tokens' => (float) $this->cash_tokens,
                'handed_over' => (float) ($this->handed_over ?? 0),
                'expected_cash' => (float) $this->expected_cash,
                'counted_cash' => $this->counted_cash !== null ? (float) $this->counted_cash : null,
                'cash_diff' => $this->cash_diff !== null ? (float) $this->cash_diff : null,
                'methods' => $s['methods'] ?? [],
                'other_methods' => array_values(array_filter($s['methods'] ?? [], fn ($m) => $m['key'] !== PaymentMethods::CASH)),
                'entries_count' => (int) ($s['entries_count'] ?? 0),
                'entries_free' => (int) ($s['entries_free'] ?? 0),
                'entries_revenue' => (float) ($s['entries_revenue'] ?? 0),
                'entries_cancelled' => (int) ($s['entries_cancelled'] ?? 0),
                'tickets' => $s['tickets'] ?? [],
                'tokens_sold' => (int) ($s['tokens_sold'] ?? 0),
                'tokens_amount' => (float) ($s['tokens_amount'] ?? 0),
                'token_sales' => (int) ($s['token_sales'] ?? 0),
                'token_sales_cancelled' => (int) ($s['token_sales_cancelled'] ?? 0),
            ];
        }

        return $this->liveFigures(ReceptionSummary::for($this->session));
    }

    private function liveFigures(object $sum): object
    {
        $notes = $this->method_notes ?? [];
        $methods = [];
        foreach ($sum->methods as $key => $amount) {
            $methods[] = ['key' => $key, 'label' => PaymentMethods::label($key), 'amount' => $amount, 'note' => ($notes[$key] ?? '') !== '' ? $notes[$key] : null];
        }

        $float = (float) ($this->opening_float ?? 0);
        $handed = (float) ($this->handed_over ?? 0);
        $expected = round($float + $sum->cash_entries + $sum->cash_tokens - $handed, 2);
        $counted = $this->counted_cash !== null ? (float) $this->counted_cash : null;

        return (object) [
            'finalized' => false,
            'opening_float' => $float,
            'cash_entries' => $sum->cash_entries,
            'cash_tokens' => $sum->cash_tokens,
            'handed_over' => $handed,
            'expected_cash' => $expected,
            'counted_cash' => $counted,
            'cash_diff' => $counted !== null ? round($counted - $expected, 2) : null,
            'methods' => $methods,
            'other_methods' => array_values(array_filter($methods, fn ($m) => $m['key'] !== PaymentMethods::CASH)),
            'entries_count' => $sum->entries_count,
            'entries_free' => $sum->entries_free,
            'entries_revenue' => $sum->entries_revenue,
            'entries_cancelled' => $sum->entries_cancelled,
            'tickets' => $sum->tickets,
            'tokens_sold' => $sum->tokens_sold,
            'tokens_amount' => $sum->tokens_amount,
            'token_sales' => $sum->token_sales,
            'token_sales_cancelled' => $sum->token_sales_cancelled,
        ];
    }

    /**
     * Finalizează: îngheață valorile așteptate, salvează diferența de casă și închide sesiunea (tranzacție). Ireversibil.
     * Cere cash-ul numărat (poate fi 0).
     */
    public function finalize(int $adminId): void
    {
        DB::transaction(function () use ($adminId) {
            $this->refresh();

            if (! $this->isDraft()) {
                throw new DomainException('Raportarea e deja finalizată.');
            }
            if ($this->counted_cash === null) {
                throw new DomainException('Introdu cash-ul numărat ca să poți finaliza raportarea (poate fi 0).');
            }

            $session = ReceptionSession::query()->lockForUpdate()->findOrFail($this->reception_session_id);
            if (! $session->isOpen()) {
                throw new DomainException('Sesiunea de recepție e deja închisă.');
            }

            $fig = $this->liveFigures(ReceptionSummary::for($session));

            $this->forceFill([
                'status' => 'finalized',
                'cash_entries' => $fig->cash_entries,
                'cash_tokens' => $fig->cash_tokens,
                'expected_cash' => $fig->expected_cash,
                'cash_diff' => $fig->cash_diff,
                'snapshot' => [
                    'entries_count' => $fig->entries_count,
                    'entries_free' => $fig->entries_free,
                    'entries_revenue' => $fig->entries_revenue,
                    'entries_cancelled' => $fig->entries_cancelled,
                    'tickets' => $fig->tickets,
                    'tokens_sold' => $fig->tokens_sold,
                    'tokens_amount' => $fig->tokens_amount,
                    'token_sales' => $fig->token_sales,
                    'token_sales_cancelled' => $fig->token_sales_cancelled,
                    'methods' => $fig->methods,
                    'session_opened_at' => $session->created_at?->toDateTimeString(),
                ],
                'finalized_by' => $adminId,
                'finalized_at' => now(),
            ])->save();

            $session->close();
        });

        $diff = (float) $this->cash_diff;
        ActivityLogger::log('reception.report_finalized', sprintf(
            'A finalizat raportarea de recepție nr. %s la „%s” (așteptat %s lei, numărat %s lei, diferență %s lei).',
            $this->number(),
            $this->party?->name ?? '—',
            number_format((float) $this->expected_cash, 2, ',', '.'),
            number_format((float) $this->counted_cash, 2, ',', '.'),
            ($diff > 0 ? '+' : '').number_format($diff, 2, ',', '.')
        ));
    }
}
