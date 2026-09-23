<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

        $text = 'Necesar: '.$this->label."\n";

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
