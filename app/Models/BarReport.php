<?php

namespace App\Models;

use App\Services\ActivityLogger;
use App\Services\BarSummary;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DXA: adaugat (PWA Bar - raportare). Raportarea de casă a barmanului, o singură dată per sesiune de vânzări (SalesGroup):
 * fondul de casă, banii scoși din casă (cu notă), cash-ul numărat și notele. Încasările se calculează live din sesiune
 * (BarSummary). „Trimite” marchează raportarea ca trimisă (blochează vânzările și anulările din sesiune); adminul o
 * redeschide din web, sau închide sesiunea prin Raportarea de stoc. Nu mută stoc și nu postează nimic: e doar predarea casei.
 */
class BarReport extends Model
{
    protected $fillable = [
        'sales_group_id',
        'party_id',
        'date',
        'note',
        'opening_float',
        'handed_over',
        'handed_note',
        'counted_cash',
        'counted_tokens',
        'method_notes',
        'submitted_at',
        'submitted_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'opening_float' => 'decimal:2',
            'handed_over' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'counted_tokens' => 'integer',
            'method_notes' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SalesGroup::class, 'sales_group_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'submitted_by');
    }

    /** Trimisă de barman din aplicație: așteaptă adminul (redeschidere sau închiderea sesiunii). */
    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function number(): string
    {
        return $this->id.' / '.$this->date->format('d.m.Y');
    }

    /** „Raportare de bar nr. 7 / 25.09.2026” */
    public function title(): string
    {
        return 'Raportare de bar nr. '.$this->number();
    }

    /** Trimise de barman, cât sesiunea de vânzări e încă deschisă: așteaptă adminul. */
    public function scopeAwaitingAdmin($query)
    {
        return $query->whereNotNull('submitted_at')->whereHas('group', fn ($g) => $g->where('status', 'open'));
    }

    /** Text -> lei (virgulă sau punct); '' = necompletat (null). Aceleași reguli ca la recepție. @throws DomainException */
    public static function parseMoney(string $value, string $label): ?float
    {
        return ReceptionReport::parseMoney($value, $label);
    }

    /** Draftul sesiunii (îl creează dacă nu există). Doar pentru o sesiune deschisă. @throws DomainException */
    public static function startFor(SalesGroup $group, ?int $adminId = null): self
    {
        if ($existing = static::query()->where('sales_group_id', $group->id)->first()) {
            return $existing;
        }
        if (! $group->isOpen()) {
            throw new DomainException('Sesiunea e deja închisă.');
        }

        $report = static::create([
            'sales_group_id' => $group->id,
            'party_id' => $group->party_id,
            'date' => now()->toDateString(),
            'opening_float' => static::suggestedFloat($group->party_id),
            'created_by' => $adminId,
        ]);

        ActivityLogger::log('bar.report_started', sprintf(
            'A început raportarea de bar nr. %s la „%s”.',
            $report->number(),
            $group->party?->name ?? '—'
        ));

        return $report;
    }

    /** Cash-ul rămas în casă după ultima raportare trimisă a petrecerii (numărat − scos), sau null. */
    public static function suggestedFloat(?int $partyId): ?float
    {
        if ($partyId === null) {
            return null;
        }

        $last = static::query()->where('party_id', $partyId)->whereNotNull('submitted_at')
            ->whereNotNull('counted_cash')->orderByDesc('submitted_at')->orderByDesc('id')->first();

        return $last ? max(0.0, round((float) $last->counted_cash - (float) ($last->handed_over ?? 0), 2)) : null;
    }

    /** Barmanul trimite raportarea. Cere cash-ul numărat (poate fi 0). @throws DomainException */
    public function submit(int $adminId): void
    {
        $this->refresh();

        if ($this->submitted_at !== null) {
            throw new DomainException('Raportarea a fost deja trimisă.');
        }
        if ($this->counted_cash === null) {
            throw new DomainException('Introdu cash-ul numărat ca să poți trimite raportarea (poate fi 0).');
        }

        $this->forceFill(['submitted_at' => now(), 'submitted_by' => $adminId])->save();

        ActivityLogger::log('bar.report_submitted', sprintf(
            'A trimis raportarea de bar nr. %s la „%s” din aplicație (numărat %s lei).',
            $this->number(),
            $this->party?->name ?? '—',
            number_format((float) $this->counted_cash, 2, ',', '.')
        ));
    }

    /** Adminul redeschide o raportare trimisă (ex. o greșeală): barul poate din nou să vândă / anuleze. @throws DomainException */
    public function reopen(int $adminId): void
    {
        $this->refresh();

        if (! $this->isSubmitted()) {
            throw new DomainException('Raportarea nu este în starea „trimisă”.');
        }

        $this->forceFill(['submitted_at' => null, 'submitted_by' => null])->save();

        ActivityLogger::log('bar.report_reopened', sprintf(
            'A redeschis raportarea de bar nr. %s la „%s” (trimisă din aplicație).',
            $this->number(),
            $this->party?->name ?? '—'
        ));
    }

    /**
     * Cifrele raportării, calculate live din sesiune + ce a introdus barmanul.
     *
     * @return object{opening_float: float, cash_sales: float, handed_over: float, expected_cash: float, counted_cash: ?float, cash_diff: ?float, methods: array<int, array{key: string, label: string, amount: float, tokens: ?int, note: ?string}>, other_methods: array, sales_count: int, cancelled_count: int, revenue: float}
     */
    public function figures(): object
    {
        $sum = BarSummary::for($this->group);
        $notes = $this->method_notes ?? [];

        $methods = [];
        foreach ($sum->methods as $key => $row) {
            $methods[] = [
                'key' => $key,
                'label' => PaymentMethods::label($key),
                'amount' => $row['amount'],
                'tokens' => $row['tokens'],
                'note' => ($notes[$key] ?? '') !== '' ? $notes[$key] : null,
            ];
        }

        $float = (float) ($this->opening_float ?? 0);
        $handed = (float) ($this->handed_over ?? 0);
        $expected = round($float + $sum->cash_sales - $handed, 2);
        $counted = $this->counted_cash !== null ? (float) $this->counted_cash : null;

        $tokensReceived = (int) ($sum->methods[PaymentMethods::TOKEN]['tokens'] ?? 0);
        $countedTokens = $this->counted_tokens !== null ? (int) $this->counted_tokens : null;

        return (object) [
            'tokens_received' => $tokensReceived,
            'counted_tokens' => $countedTokens,
            'tokens_diff' => $countedTokens !== null ? $countedTokens - $tokensReceived : null,
            'opening_float' => $float,
            'cash_sales' => $sum->cash_sales,
            'handed_over' => $handed,
            'expected_cash' => $expected,
            'counted_cash' => $counted,
            'cash_diff' => $counted !== null ? round($counted - $expected, 2) : null,
            'methods' => $methods,
            'other_methods' => array_values(array_filter($methods, fn ($m) => $m['key'] !== PaymentMethods::CASH)),
            'sales_count' => $sum->sales_count,
            'cancelled_count' => $sum->cancelled_count,
            'revenue' => $sum->revenue,
        ];
    }
}
