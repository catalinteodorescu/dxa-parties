<?php

namespace App\Livewire\Admin\Reception;

use App\Models\TokenTransaction;
use App\Services\TokenLedger;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Recepție - tokeni). Tokenii în circulație, ledgerul lor și acțiunile de administrare:
 *  - ajustare (stoc inițial pentru tokenii vânduți înainte de acest modul, sau corecție) cu semn și motiv;
 *  - casare a tokenilor rămași în circulație (motiv obligatoriu), necesară ca să poți opri tokenii.
 * Vânzarea la recepție e în ecranul Recepție (TokenSale). Logica stă în App\Services\TokenLedger.
 */
#[Layout('layouts.admin')]
class Tokens extends Component
{
    public string $adjustTokens = '';

    public string $adjustReason = '';

    public string $writeOffReason = '';

    public ?string $message = null;

    public ?string $error = null;

    private function run(callable $action, string $success): void
    {
        $this->message = $this->error = null;

        try {
            $action();
            $this->message = $success;
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function adjust(): void
    {
        $raw = trim(str_replace(['+', ' '], '', $this->adjustTokens));

        if ($raw === '' || ! preg_match('/^-?\d+$/', $raw)) {
            $this->message = null;
            $this->error = 'Scrie un număr întreg de tokeni (negativ pentru scădere).';

            return;
        }

        $this->run(function () use ($raw) {
            TokenLedger::adjust((int) $raw, $this->adjustReason, Auth::guard('admin')->id());
            $this->adjustTokens = '';
            $this->adjustReason = '';
        }, 'Ajustarea a fost înregistrată.');
    }

    public function writeOff(): void
    {
        $this->run(function () {
            TokenLedger::writeOff($this->writeOffReason, Auth::guard('admin')->id());
            $this->writeOffReason = '';
        }, 'Tokenii rămași în circulație au fost casați.');
    }

    public function render()
    {
        return view('livewire.admin.reception.tokens', [
            'issued' => TokenLedger::issued(),
            'collected' => TokenLedger::collected(),
            'circulation' => TokenLedger::circulation(),
            'mode' => PaymentMethods::tokenMode(),
            'rate' => PaymentMethods::tokenRate(),
            'transactions' => TokenTransaction::query()
                ->with(['party', 'creator', 'payments', 'participant'])
                ->orderByDesc('occurred_at')->orderByDesc('id')
                ->limit(100)->get(),
            'methodLabels' => PaymentMethods::labels(),
        ]);
    }
}
