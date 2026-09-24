<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StockRequisition extends Model
{
    public const STATUSES = [
        'open' => 'Deschis',
        'fulfilled' => 'Rezolvat',
        'closed' => 'Închis',
    ];

    protected $fillable = [
        'label',
        'status',
        'party_id',
        'created_by',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequisitionItem::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /** DXA: adaugat — numarul necesarului, ca la facturi: "5 / 23.09.2026" (id-ul din DB + data crearii). */
    public function number(): string
    {
        return $this->id.' / '.$this->created_at->format('d.m.Y');
    }

    /** "Necesar nr. 5 / 23.09.2026" */
    public function title(): string
    {
        return 'Necesar nr. '.$this->number();
    }

    /**
     * Denumirea implicita a unui necesar nou: chiar numarul lui, "5 / 24.09.2026" (precompletata in formular).
     * Fara argumente = numarul urmator (estimare pentru afisare); la salvare se recalculeaza cu id-ul real.
     */
    public static function defaultLabel(?int $id = null, ?Carbon $date = null): string
    {
        return ($id ?? static::nextId()).' / '.($date ?? now())->format('d.m.Y');
    }

    /** Id-ul urmator (fara a crea nimic): din secventa tabelului unde exista, altfel max(id) + 1. */
    public static function nextId(): int
    {
        $table = (new static)->getTable();
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $seq = DB::table('sqlite_sequence')->where('name', $table)->value('seq');

            if ($seq !== null) {
                return (int) $seq + 1;
            }
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $status = DB::selectOne('SHOW TABLE STATUS LIKE ?', [$table]);

            if ($status && isset($status->Auto_increment)) {
                return (int) $status->Auto_increment;
            }
        }

        return (int) static::query()->max('id') + 1;
    }

    /** Denumirea e chiar numarul ("5 / 24.09.2026")? Atunci nu il mai repetam langa ea. */
    public function hasNumberInLabel(): bool
    {
        return str_starts_with(trim((string) $this->label), $this->id.' / ');
    }

    /** Pentru afisare langa un text ("din necesar ..."): "nr. 5 / 24.09.2026" sau nr. 5 „Bere sambata" */
    public function numberedLabel(): string
    {
        return $this->hasNumberInLabel()
            ? 'nr. '.trim($this->label)
            : 'nr. '.$this->id.' „'.$this->label.'"';
    }

    /** Titlu pentru PDF / WhatsApp: "Necesar nr. 5 / 24.09.2026" sau "Necesar nr. 5 / 24.09.2026 — Bere sambata". */
    public function displayName(): string
    {
        return $this->hasNumberInLabel()
            ? 'Necesar nr. '.trim($this->label)
            : $this->title().' — '.$this->label;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Rezolvat complet = toate liniile au primit cel putin cat s-a cerut. */
    public function isFullyReceived(): bool
    {
        return $this->items->every(fn (StockRequisitionItem $item) => (float) $item->qty_received >= (float) $item->qty_requested
        );
    }

    /**
     * Actualizeaza statusul dupa o raportare: fulfilled daca toate liniile
     * sunt acoperite, altfel ramane open (acoperire partiala).
     */
    public function refreshStatus(): void
    {
        $this->load('items');

        if ($this->status !== 'closed') {
            $this->status = $this->isFullyReceived() ? 'fulfilled' : 'open';
            $this->save();
        }
    }

    /** Cantitate formatata (virgula zecimala, fara zerouri de prisos) — acelasi stil ca in PDF-uri. */
    private function plain(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, ',', '.'), '0'), ',');
    }

    /**
     * DXA: adaugat (Bar - necesare) — text formatat pt. trimis pe WhatsApp
     * (buton „Trimite pe WhatsApp" din listă și din formular, wa.me/?text=...
     * fără număr, ca să aleagă adminul contactul/furnizorul direct din
     * WhatsApp). Doar rezumatul text — wa.me nu suportă atașamente, deci
     * PDF-ul se exportă/trimite separat daca e nevoie de el ca fișier.
     */
    public function whatsAppMessage(): string
    {
        $lines = $this->items->sortBy(fn (StockRequisitionItem $item) => mb_strtolower($item->stockItem->name));

        $text = $this->displayName()."\n";

        if ($this->party) {
            $text .= 'Petrecere: '.$this->party->name."\n";
        }

        $text .= "\n";

        foreach ($lines as $item) {
            $text .= '- '.$item->stockItem->name.': '.$this->plain((float) $item->qty_requested).' '.$item->stockItem->unit;

            if ($item->stockItem->hasPackage()) {
                $package = $item->stockItem->packageDisplayFor((float) $item->qty_requested);

                if ($package) {
                    $text .= ' ('.$package.')';
                }
            }

            $text .= "\n";
        }

        $text .= "\nDance Xplosion Academy — Panou admin";

        return $text;
    }

    public function whatsAppUrl(): string
    {
        return 'https://wa.me/?text='.rawurlencode($this->whatsAppMessage());
    }
}
