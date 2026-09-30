<?php

namespace App\Livewire\Participant;

use App\Models\CreditTransaction;
use App\Models\PartyEntry;
use App\Services\CreditLedger;
use App\Services\LoyaltyLedger;
use App\Services\ParticipantAccounts;
use App\Services\ParticipantAvatar;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * DXA: adaugat (Aplicația participanților - runda 2). Contul meu: cod QR personal (același conținut ca în fișa din admin),
 * soldul de credite cu ultimele mișcări, cardul de fidelitate și intrările mele. Doar citire — nimic de aici nu modifică date.
 */
#[Layout('layouts.participant', ['title' => 'Cont'])]
class Account extends Component
{
    use WithFileUploads;

    public string $name = '';

    /** Poza nouă (încărcată din browser, deja tăiată pătrat) până la „Salvează”. */
    public $photo = null;

    public string $profileError = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public string $passwordError = '';

    public function mount(): void
    {
        $this->name = (string) auth('participant')->user()?->name;
    }

    /** Salvează numele și (dacă s-a ales una) poza; reîncarcă pagina ca antetul să arate noua poză și să apară popup-ul de succes. */
    public function saveProfile(): void
    {
        $this->profileError = '';
        $me = auth('participant')->user();

        try {
            $this->validate(['photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.ParticipantAvatar::MAX_KB]], [
                'photo.image' => 'Fișierul ales nu e o imagine.',
                'photo.mimes' => 'Folosește o poză JPG, PNG sau WebP.',
                'photo.max' => 'Poza e prea mare (maxim 8 MB).',
            ]);
            ParticipantAccounts::updateName($me, $this->name);
            if ($this->photo instanceof TemporaryUploadedFile) {
                ParticipantAvatar::store($me, $this->photo);
            }
        } catch (DomainException $e) {
            $this->profileError = $e->getMessage();

            return;
        }

        $this->photo = null;
        session()->flash('status', 'Profilul a fost salvat.');
        $this->redirectRoute('app.account', navigate: true);
    }

    public function removePhoto(): void
    {
        ParticipantAvatar::delete(auth('participant')->user());
        session()->flash('status', 'Poza a fost ștearsă.');
        $this->redirectRoute('app.account', navigate: true);
    }

    public function changePassword(): void
    {
        $this->passwordError = '';

        if ($this->newPassword !== $this->newPasswordConfirmation) {
            $this->passwordError = 'Parolele noi nu coincid.';

            return;
        }

        try {
            ParticipantAccounts::changePassword(auth('participant')->user(), $this->currentPassword, $this->newPassword);
        } catch (DomainException $e) {
            $this->passwordError = $e->getMessage();

            return;
        }

        $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');
        $this->dispatch('password-changed');
        $this->dispatch('toast', message: 'Parola a fost schimbată.', type: 'ok');
    }

    /** Înrolare la cardul de fidelitate direct din aplicație (înainte se făcea doar la recepție). */
    public function enrollLoyalty(): void
    {
        $me = auth('participant')->user();

        try {
            if (! LoyaltyLedger::enabled()) {
                throw new DomainException('Programul de fidelitate nu este activ momentan.');
            }
            LoyaltyLedger::enroll($me, null, 0, 'Înrolare din aplicație');
        } catch (DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'err');

            return;
        }

        $this->dispatch('toast', message: 'Ești înscris! Cardul tău de fidelitate e gata.', type: 'ok');
    }

    public function render()
    {
        $me = auth('participant')->user();

        $card = $me ? LoyaltyLedger::activeCard($me) : null;
        $stamps = $card ? $card->stamps()->whereNull('voided_at')->orderBy('stamped_at')->orderBy('id')->get() : collect();

        return view('livewire.participant.account', [
            'me' => $me,
            'qr' => $me?->qrPayload(),
            'balance' => $me ? CreditLedger::balance($me) : 0.0,
            'creditTx' => $me
                ? CreditTransaction::query()->where('participant_id', $me->id)->whereNull('cancelled_at')
                    ->orderByDesc('occurred_at')->orderByDesc('id')->limit(10)->get()
                : collect(),
            'loyaltyOn' => LoyaltyLedger::enabled(),
            'card' => $card,
            'stamps' => $stamps,
            'entries' => $me
                ? PartyEntry::query()->active()->where('participant_id', $me->id)->with('party')
                    ->orderByDesc('entered_at')->orderByDesc('id')->limit(15)->get()
                : collect(),
        ]);
    }
}
