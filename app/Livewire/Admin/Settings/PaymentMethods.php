<?php

namespace App\Livewire\Admin\Settings;

use App\Services\CreditBonus;
use App\Services\TokenLedger;
use App\Support\PaymentMethods as Methods;
use DomainException;
use Livewire\Component;

/**
 * DXA: adaugat (Setări - Metode de plată).
 *
 * Panou inclus în pagina de Setări, cu salvare imediată (independent de butonul „Salvează setările"):
 *  - metodele predefinite (cash mereu activ) și cele custom (adăugare, redenumire, ștergere doar dacă nu sunt
 *    folosite, altfel dezactivare);
 *  - tokeni: 3 stări (Activ / Doar încasare / Oprit) + cursul token → lei;
 *  - credite: „acceptă credite la plată" + „participanții pot cumpăra credite".
 * Regulile stau în App\Support\PaymentMethods; aici doar interfața și mesajele.
 */
class PaymentMethods extends Component
{
    /** @var array<int, string> id metodă custom => nume din input (editabil inline) */
    public array $names = [];

    public string $newLabel = '';

    public string $rate = '';

    /** Runda 51: praguri de bonus la încărcare (rânduri {min, percent} ca text), sume rapide și limite. */
    public array $tiers = [];

    public string $presets = '';

    public string $topupMin = '';

    public string $topupMax = '';

    /** Mesajele din secțiunea „Încărcare credite” (sub butoanele Salvează): succes / eroare, separat pe bonus și pe valori. */
    public ?string $bonusMessage = null;

    public ?string $bonusError = null;

    public ?string $limitsMessage = null;

    public ?string $limitsError = null;

    public ?string $message = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->syncFields();
    }

    private function syncFields(): void
    {
        $this->names = Methods::all()->where('is_builtin', false)->pluck('label', 'id')->all();

        $this->tiers = array_map(fn ($t) => ['min' => self::num($t['min']), 'percent' => self::num($t['percent'])], CreditBonus::tiers());
        $this->presets = implode(', ', CreditBonus::presets());
        $this->topupMin = self::num(CreditBonus::min());
        $this->topupMax = self::num(CreditBonus::max());

        $rate = Methods::tokenRate();
        $this->rate = $rate == floor($rate) ? (string) (int) $rate : rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }

    private static function num(float $n): string
    {
        return $n == floor($n) ? (string) (int) $n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    public function addTier(): void
    {
        $this->tiers[] = ['min' => '', 'percent' => ''];
    }

    public function removeTier(int $i): void
    {
        unset($this->tiers[$i]);
        $this->tiers = array_values($this->tiers);
    }

    public function saveBonus(): void
    {
        $this->saveCredit(fn () => CreditBonus::saveTiers($this->tiers), 'bonus', 'Bonusul a fost salvat. Se aplică încărcărilor noi.');
    }

    public function saveLimits(): void
    {
        $this->saveCredit(fn () => CreditBonus::saveLimits($this->presets, $this->topupMin, $this->topupMax), 'limits', 'Valorile de încărcare au fost salvate.');
    }

    /**
     * Salvările din secțiunea „Încărcare credite”: mesajul de succes / eroare apare chiar sub butonul apăsat (nu sus în panou, unde nu se vede),
     * iar la eroare rămân valorile scrise de admin, ca să le poată corecta.
     */
    private function saveCredit(callable $action, string $scope, string $success): void
    {
        $this->message = $this->error = null;
        $this->bonusMessage = $this->bonusError = $this->limitsMessage = $this->limitsError = null;

        try {
            $action();
            $this->{$scope.'Message'} = $success;
            $this->syncFields();
        } catch (DomainException $e) {
            $this->{$scope.'Error'} = $e->getMessage();
        }
    }

    /** Rulează o acțiune din service; mesajele DomainException ajung în alertă. */
    private function run(callable $action, ?string $success = null): void
    {
        $this->message = $this->error = null;
        $this->bonusMessage = $this->bonusError = $this->limitsMessage = $this->limitsError = null;

        try {
            $action();
            $this->message = $success;
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }

        $this->syncFields();
    }

    public function toggle(string $key): void
    {
        $this->run(fn () => Methods::setActive($key, ! Methods::isEnabled($key)));
    }

    public function toggleCreditsPurchasable(): void
    {
        $this->run(fn () => Methods::setCreditsPurchasable(! Methods::creditsPurchasable()));
    }

    public function setTokenMode(string $mode): void
    {
        $this->run(fn () => Methods::setTokenMode($mode));
    }

    public function saveRate(): void
    {
        $raw = str_replace(',', '.', trim($this->rate));

        if (! is_numeric($raw)) {
            $this->message = null;
            $this->error = 'Cursul token → lei trebuie să fie un număr.';

            return;
        }

        $this->run(fn () => Methods::setTokenRate((float) $raw), 'Cursul a fost salvat.');
    }

    public function add(): void
    {
        $label = $this->newLabel;

        $this->run(function () use ($label) {
            Methods::create($label);
            $this->newLabel = '';
        }, 'Metoda a fost adăugată.');
    }

    public function rename(int $id): void
    {
        $label = (string) ($this->names[$id] ?? '');

        $this->run(fn () => Methods::rename($id, $label));
    }

    public function delete(int $id): void
    {
        $this->run(fn () => Methods::delete($id), 'Metoda a fost ștearsă.');
    }

    public function render()
    {
        $methods = Methods::all();

        return view('livewire.admin.settings.payment-methods', [
            'methods' => $methods,
            'used' => $methods->where('is_builtin', false)->mapWithKeys(fn ($m) => [$m->id => Methods::isUsed($m->key)])->all(),
            'creditBlock' => Methods::isEnabled(Methods::CREDIT) ? Methods::blockReason(Methods::CREDIT) : null,
            'tokenBlock' => Methods::tokenMode() !== Methods::TOKEN_OFF ? Methods::blockReason(Methods::TOKEN) : null,
            'tokenMode' => Methods::tokenMode(),
            'circulation' => TokenLedger::circulation(),
            'creditsEnabled' => Methods::isEnabled(Methods::CREDIT),
            'creditsPurchasable' => Methods::creditsPurchasable(),
        ]);
    }
}
