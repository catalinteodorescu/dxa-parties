<?php

namespace App\Livewire\Reception;

use App\Livewire\Reception\Concerns\UsesReceptionParty;
use App\Services\ReceptionScan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * DXA: adaugat (runda 47 - Scanner Recepție). Butonul „Scanează” de pe ecranul principal: camera rămâne deschisă între scanări,
 * bilet valid fără sumă de încasat = intrare directă, QR personal cu mai multe bilete = alegere, restul = formularul „Intrare”.
 * Logica stă în App\Services\ReceptionScan. Fără stare pe server: alegerea biletelor e ținută de ecran (Alpine) și trimisă la „Intră”.
 */
class QuickScan extends Component
{
    use UsesReceptionParty;

    /** @return array{status: string, message: string, url?: string, tickets?: array<int, array{id: int, label: string, due: float}>, participant?: string} */
    public function scan(string $code): array
    {
        $party = $this->currentParty();
        if (! $party) {
            return ['status' => ReceptionScan::ERROR, 'message' => 'Alege o petrecere.'];
        }

        return $this->present(ReceptionScan::resolve($party, mb_substr($code, 0, 200), Auth::guard('admin')->id()));
    }

    /**
     * @param  array<int, mixed>  $ids  biletele bifate (reverificate pe server)
     * @return array{status: string, message: string, url?: string}
     */
    public function enterSelected(array $ids, ?string $participant = null): array
    {
        $party = $this->currentParty();
        if (! $party) {
            return ['status' => ReceptionScan::ERROR, 'message' => 'Alege o petrecere.'];
        }

        $result = ReceptionScan::enterTickets($party, $ids, Auth::guard('admin')->id());
        if ($result['status'] === ReceptionScan::OPEN && $participant && ctype_digit($participant)) {
            $result['query']['participant'] = $participant;
        }

        return $this->present($result);
    }

    /** Rezultatul serviciului, așa cum îl primește ecranul (JS): status, mesaj, eventual adresa formularului sau biletele de ales. */
    private function present(array $result): array
    {
        $out = ['status' => $result['status'], 'message' => $result['message']];
        if ($result['status'] === ReceptionScan::OPEN) {
            $out['url'] = route('receptie.entry', $result['query'] ?? []);
        }
        if ($result['status'] === ReceptionScan::CHOOSE) {
            $out['tickets'] = $result['tickets'];
            $out['participant'] = $result['participant'] ?? '';
        }

        return $out;
    }

    public function render()
    {
        return view('livewire.reception.quick-scan');
    }
}
