<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\CreditTopup;
use App\Models\Order;
use App\Models\ReceptionReport;
use App\Models\ReceptionSession;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Models\StockReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (runda 75). Cardul „Atenție” din dashboard: lucrurile care cer o acțiune, strânse într-un singur loc, cu link direct.
 * Fiecare alertă se arată doar dacă adminul are acces la secțiunea de care ține; fără alerte, cardul nu apare.
 *
 *  - stoc sub minim (produse cu `min_stock` atins);
 *  - sesiuni de Recepție / Bar uitate deschise (peste STALE_HOURS, fără raportare depusă);
 *  - diferențe de casă la rapoartele finalizate în ultimele CASH_DAYS zile;
 *  - plăți cu cardul rămase în așteptare: rezervări / încărcări al căror termen a trecut de mai mult de GRACE_MINUTES. Se închid singure
 *    (`dxa:expire-payments`, din cron, la 15 minute): dacă totuși au rămas, cron-ul serverului nu rulează.
 *
 * Fiecare alertă are `help`: ce înseamnă și ce se face (se arată la „Ce înseamnă?”).
 */
class DashboardAlerts
{
    public const CASH_DAYS = 7;

    public const GRACE_MINUTES = 30;

    /**
     * @return Collection<int, array{key: string, level: string, title: string, detail: string, help: string, href: string, count: int}>
     */
    public static function for(Admin $admin): Collection
    {
        $alerts = collect();

        if ($admin->permits('stocks')) {
            $low = StockItem::query()->active()->whereNotNull('min_stock')->whereColumn('stock_qty', '<=', 'min_stock')->orderBy('name')->get();
            if ($low->isNotEmpty()) {
                $alerts->push(self::alert('low_stock', 'warning', 'Stoc sub minim', self::names($low->pluck('name')), route('admin.stock-items.index'), $low->count(),
                    'Stocul curent al produselor de mai sus a ajuns la pragul minim setat. Fă un necesar / o comandă către furnizor (Stocuri › Necesare) sau, dacă pragul nu mai e potrivit, schimbă-l din fișa produsului.'));
            }
        }

        if ($admin->permits('reception_reports')) {
            $stale = ReceptionSession::staleOpen();
            if ($stale->isNotEmpty()) {
                $alerts->push(self::alert('stale_reception', 'warning', 'Sesiune de recepție uitată deschisă', 'de peste '.ReceptionSession::STALE_HOURS.' ore, fără raportare depusă', route('admin.reception.reports.index'), $stale->count(),
                    'O casă de recepție a rămas deschisă după petrecere. Intră în Recepție › Raportări, pornește raportarea sesiunii, numără casa, finalizeaz-o și depune-o: sesiunea se închide și alerta dispare. Dacă a fost o sesiune de test, închide-o la fel.'));
            }

            $diffs = ReceptionReport::query()->where('status', 'finalized')
                ->where('finalized_at', '>=', now()->subDays(self::CASH_DAYS))->get()
                ->filter(fn (ReceptionReport $r) => $r->cash_diff !== null && abs((float) $r->cash_diff) >= 0.005);
            if ($diffs->isNotEmpty()) {
                $alerts->push(self::alert(
                    'cash_reception', 'warning', 'Diferență de casă la Recepție',
                    $diffs->map(fn ($r) => self::lei((float) $r->cash_diff))->take(3)->implode(' · ').' · ultimele '.self::CASH_DAYS.' zile',
                    $diffs->count() === 1 ? route('admin.reception.reports.show', $diffs->first()) : route('admin.reception.reports.index'),
                    $diffs->count(),
                    'Casa numărată nu a ieșit exact cu cât era așteptat (+ = mai mulți bani, − = lipsă). Nu se „rezolvă”, e o constatare: deschide raportarea, verifică dacă s-a uitat o încasare sau a fost un rest greșit și, la nevoie, notează motivul. Alerta dispare singură după '.self::CASH_DAYS.' zile.',
                ));
            }
        }

        if ($admin->permits('stock_reports')) {
            $stale = SalesGroup::staleOpen();
            if ($stale->isNotEmpty()) {
                $alerts->push(self::alert('stale_bar', 'warning', 'Sesiune de bar uitată deschisă', 'de peste '.SalesGroup::STALE_HOURS.' ore, fără raportare depusă', route('admin.stock-reports.index'), $stale->count(),
                    'O sesiune de bar a rămas deschisă după petrecere. Intră în Stocuri › Raportări, pornește raportarea sesiunii, numără casa și stocul, finalizeaz-o: sesiunea se închide și alerta dispare.'));
            }

            $diffs = StockReport::query()->where('status', 'finalized')->where('finalized_at', '>=', now()->subDays(self::CASH_DAYS))
                ->with('counts')->get()
                ->filter(fn (StockReport $r) => ($d = $r->closingSummary()?->cash_diff) !== null && abs((float) $d) >= 0.005);
            if ($diffs->isNotEmpty()) {
                $alerts->push(self::alert(
                    'cash_bar', 'warning', 'Diferență de casă la Bar',
                    $diffs->map(fn ($r) => self::lei((float) $r->closingSummary()->cash_diff))->take(3)->implode(' · ').' · ultimele '.self::CASH_DAYS.' zile',
                    $diffs->count() === 1 ? route('admin.stock-reports.edit', $diffs->first()) : route('admin.stock-reports.index'),
                    $diffs->count(),
                    'Casa numărată la bar nu a ieșit exact cu cât era așteptat (+ = mai mulți bani, − = lipsă). Deschide raportarea și verifică vânzările și numărătoarea. Nu se „rezolvă”, e o constatare; alerta dispare singură după '.self::CASH_DAYS.' zile.',
                ));
            }
        }

        $cutoff = now()->subMinutes(self::GRACE_MINUTES);

        if ($admin->permits('tickets')) {
            $n = Order::query()->where('payment_status', Order::PAY_CARD_PENDING)->where('payment_expires_at', '<', $cutoff)->count();
            if ($n > 0) {
                $alerts->push(self::alert('stuck_orders', 'danger', 'Rezervare cu cardul rămasă deschisă', 'termen depășit de peste '.self::GRACE_MINUTES.' min, nu s-a închis singură', route('admin.tickets.index', ['plata' => 'card']), $n,
                    'Rezervarea unor bilete cu cardul a depășit termenul și ar fi trebuit închisă automat (locurile se eliberează). Că a rămas deschisă înseamnă, de regulă, că cron-ul serverului nu rulează (comanda dxa:expire-payments, din schedule:run). Verifică cron-ul. Dacă un participant spune că a plătit, caută plata în Stripe › Payments după sumă și oră.'));
            }
        }

        if ($admin->permits('credits')) {
            $n = CreditTopup::query()->where('status', CreditTopup::PENDING)->where('expires_at', '<', $cutoff)->count();
            if ($n > 0) {
                $alerts->push(self::alert('stuck_topups', 'danger', 'Încărcare cu cardul rămasă deschisă', 'termen depășit de peste '.self::GRACE_MINUTES.' min, nu s-a închisă singură', route('admin.credits.topups', ['stare' => 'pending']), $n,
                    'O încărcare de credite cu cardul a depășit termenul și ar fi trebuit închisă automat. Că a rămas deschisă înseamnă, de regulă, că cron-ul serverului nu rulează (comanda dxa:expire-payments, din schedule:run). Verifică cron-ul. Dacă un participant spune că a plătit și nu are creditele, găsește încărcarea în lista din pagină și caut-o în Stripe › Payments după referință.'));
            }
        }

        // Cele grave întâi.
        return $alerts->sortBy(fn ($a) => $a['level'] === 'danger' ? 0 : 1)->values();
    }

    private static function alert(string $key, string $level, string $title, string $detail, string $href, int $count, string $help): array
    {
        return compact('key', 'level', 'title', 'detail', 'help', 'href', 'count');
    }

    private static function names(Collection $names): string
    {
        $shown = $names->take(3)->map(fn ($n) => Str::limit((string) $n, 24))->implode(', ');

        return $names->count() > 3 ? $shown.' și încă '.($names->count() - 3) : $shown;
    }

    private static function lei(float $n): string
    {
        return ($n > 0 ? '+' : '−').number_format(abs($n), 2, ',', '.').' lei';
    }
}
