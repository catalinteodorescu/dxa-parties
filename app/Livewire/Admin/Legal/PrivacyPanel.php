<?php

namespace App\Livewire\Admin\Legal;

use App\Models\PrivacyVersion;
use App\Services\PrivacyPolicy;
use DomainException;
use Livewire\Component;

/**
 * DXA: adaugat (runda 68). Panou în pagina Legal: Politica de confidențialitate (un singur text, în română). „Publică” creează o versiune nouă
 * (App\Services\PrivacyPolicy::publish). Spre deosebire de Termeni, nu se acceptă: nu există „cere acceptare”.
 */
class PrivacyPanel extends Component
{
    public string $body = '';

    public string $message = '';

    public string $error = '';

    public function mount(): void
    {
        $this->body = PrivacyPolicy::current()?->body ?? PrivacyPolicy::sample();
    }

    public function publish(): void
    {
        $this->message = $this->error = '';

        try {
            $version = PrivacyPolicy::publish($this->body, auth('admin')->user());
        } catch (DomainException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->message = 'Versiunea '.$version->id.' e publicată.';
    }

    public function render()
    {
        return view('livewire.admin.legal.privacy-panel', [
            'current' => PrivacyPolicy::current(),
            'history' => PrivacyVersion::query()->with('publisher:id,name')->orderByDesc('id')->limit(6)->get(),
        ]);
    }
}
