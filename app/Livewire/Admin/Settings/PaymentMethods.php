<?php

namespace App\Livewire\Admin\Settings;

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

    public ?string $message = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->syncFields();
    }

    private function syncFields(): void
    {
        $this->names = Methods::all()->where('is_builtin', false)->pluck('label', 'id')->all();

        $rate = Methods::tokenRate();
        $this->rate = $rate == floor($rate) ? (string) (int) $rate : rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }

    /** Rulează o acțiune din service; mesajele DomainException ajung în alertă. */
    private function run(callable $action, ?string $success = null): void
    {
        $this->message = $this->error = null;

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
            'circulation' => \App\Services\TokenLedger::circulation(),
            'creditsEnabled' => Methods::isEnabled(Methods::CREDIT),
            'creditsPurchasable' => Methods::creditsPurchasable(),
        ]);
    }
}
