<?php

namespace App\Livewire\Admin\Owner;

use App\Models\Admin;
use App\Models\License;
use App\Models\Plan;
use App\Services\ActivityLogger;
use App\Services\Plan\PlanGate;
use App\Services\Plan\SmsUsage;
use App\Support\Features;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (runda 79). Pagina proprietarului (furnizorul aplicației): ce include fiecare plan, planul activ al instalării,
 * limitele (conturi, SMS incluse). Doar rolul `owner` o vede (alții primesc 404); fiecare acțiune
 * reverifică, pentru că componenta e scutită de paza pe secțiuni.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    /** Planul activ al instalării. */
    public string $licensePlan = '';

    /** Panoul „conturi de dezactivat” la coborârea pe un plan mai mic (apare când conturile active nu încap). */
    public bool $showDowngrade = false;

    /** @var array<int|string, bool> id cont => bifat pentru dezactivare */
    public array $deactivate = [];

    /** @var array<string, array{name: string, price_monthly: string, price_yearly_monthly: string, features: array<string, bool>, limits: array<string, string>}> */
    public array $plans = [];

    public function mount(): void
    {
        $this->authorizeOwner();
        $this->load();
    }

    private function authorizeOwner(): Admin
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin instanceof Admin && $admin->isOwner(), 404);

        return $admin;
    }

    private function load(): void
    {
        PlanGate::flush();
        $license = License::query()->first();
        $this->licensePlan = $license?->plan_key ?? 'pro';

        $this->plans = [];
        foreach (Plan::query()->orderBy('id')->get() as $plan) {
            $features = [];
            foreach (Features::keys() as $key) {
                $features[$key] = in_array($key, (array) $plan->features, true);
            }
            $limits = [];
            foreach (array_keys(Features::LIMITS) as $lk) {
                $v = ((array) $plan->limits)[$lk] ?? null;
                $limits[$lk] = $v === null ? '' : (string) $v;
            }
            $this->plans[$plan->key] = [
                'name' => $plan->name,
                'price_monthly' => $plan->price_monthly === null ? '' : (string) (float) $plan->price_monthly,
                'price_yearly_monthly' => $plan->price_yearly_monthly === null ? '' : (string) (float) $plan->price_yearly_monthly,
                'sms_overage_eur' => (string) (float) $plan->sms_overage_eur,
                'features' => $features,
                'limits' => $limits,
            ];
        }
    }

    /** Salvează planurile (conținut, limite, prețuri). */
    public function savePlans(): void
    {
        $owner = $this->authorizeOwner();

        $this->validate([
            'plans.*.name' => ['required', 'string', 'max:60'],
            'plans.*.price_monthly' => ['nullable', 'numeric', 'min:0'],
            'plans.*.price_yearly_monthly' => ['nullable', 'numeric', 'min:0'],
            'plans.*.sms_overage_eur' => ['required', 'numeric', 'min:0', 'max:10'],
            'plans.*.limits.*' => ['nullable', 'integer', 'min:0'],
        ], [
            'plans.*.name.required' => 'Fiecare plan are nevoie de un nume.',
            'plans.*.price_monthly.numeric' => 'Prețul trebuie să fie un număr.',
            'plans.*.price_yearly_monthly.numeric' => 'Prețul trebuie să fie un număr.',
            'plans.*.sms_overage_eur.required' => 'Completează prețul unui SMS peste cotă.',
            'plans.*.sms_overage_eur.numeric' => 'Prețul unui SMS trebuie să fie un număr.',
            'plans.*.limits.*.integer' => 'Limitele sunt numere întregi (gol = nelimitat).',
        ]);

        $changes = [];
        foreach (Plan::query()->get() as $plan) {
            $in = $this->plans[$plan->key] ?? null;
            if (! $in) {
                continue;
            }
            $features = array_values(array_filter(Features::keys(), fn ($k) => (bool) ($in['features'][$k] ?? false)));
            $limits = [];
            foreach (array_keys(Features::LIMITS) as $lk) {
                $v = $in['limits'][$lk] ?? '';
                $limits[$lk] = $v === '' || $v === null ? null : (int) $v;
            }

            $added = array_diff($features, (array) $plan->features);
            $removed = array_diff((array) $plan->features, $features);
            if ($added) {
                $changes[] = $plan->name.': + '.implode(', ', array_map(Features::label(...), $added));
            }
            if ($removed) {
                $changes[] = $plan->name.': − '.implode(', ', array_map(Features::label(...), $removed));
            }
            if ((array) $plan->limits !== $limits) {
                $changes[] = $plan->name.': limite '.json_encode($limits);
            }

            $plan->update([
                'name' => $in['name'],
                'price_monthly' => $in['price_monthly'] === '' ? null : $in['price_monthly'],
                'price_yearly_monthly' => $in['price_yearly_monthly'] === '' ? null : $in['price_yearly_monthly'],
                'sms_overage_eur' => str_replace(',', '.', (string) $in['sms_overage_eur']),
                'features' => $features,
                'limits' => $limits,
            ]);
        }

        ActivityLogger::log('owner.plans.updated', 'Proprietar: a salvat planurile'.($changes ? ' ('.implode('; ', $changes).')' : '').'.', $owner);
        $this->load();
        session()->flash('status', 'Planurile au fost salvate.');
    }

    /** Cât depășesc conturile active limitele planului dat: rol => câte trebuie dezactivate (doar rolurile care depășesc). */
    private function excessFor(Plan $plan): array
    {
        $limits = (array) $plan->limits;
        $out = [];
        foreach (['superadmin' => 'max_superadmins', 'admin' => 'max_admins'] as $role => $lk) {
            $limit = $limits[$lk] ?? null;
            $used = PlanGate::accounts($role);
            if ($limit !== null && $used > (int) $limit) {
                $out[$role] = $used - (int) $limit;
            }
        }

        return $out;
    }

    /** Planul activ al instalării + suprascrierile pe funcție. */
    public function saveLicense(): void
    {
        $owner = $this->authorizeOwner();

        $plan = Plan::query()->where('key', $this->licensePlan)->first();
        if (! $plan) {
            $this->addError('licensePlan', 'Alege un plan existent.');

            return;
        }

        // Conturile active nu încap în planul ales: arătăm lista, ca proprietarul să aleagă pe care le dezactivează (nu ștergem nimic singuri).
        if ($this->excessFor($plan) !== []) {
            $this->showDowngrade = true;
            $this->deactivate = [];
            session()->flash('error', 'Conturile active nu încap în planul '.$plan->name.'. Alege mai jos pe care le dezactivezi.');

            return;
        }

        $this->showDowngrade = false;
        $this->persistLicense($plan, $owner);
    }

    public function cancelDowngrade(): void
    {
        $this->authorizeOwner();
        $this->showDowngrade = false;
        $this->deactivate = [];
    }

    /** Dezactivează conturile bifate (cel puțin cât cere planul, pe fiecare rol), apoi aplică planul. */
    public function confirmDowngrade(): void
    {
        $owner = $this->authorizeOwner();

        $plan = Plan::query()->where('key', $this->licensePlan)->first();
        if (! $plan) {
            return;
        }

        $selectedIds = array_keys(array_filter($this->deactivate));
        $targets = Admin::query()->withoutOwner()->where('is_active', true)->whereIn('id', $selectedIds)->get();

        foreach ($this->excessFor($plan) as $role => $need) {
            $have = $targets->where('role', $role)->count();
            if ($have < $need) {
                session()->flash('error', 'Alege cel puțin '.$need.' '.($role === 'admin' ? 'admini' : 'superadmini').' de dezactivat (ai ales '.$have.').');

                return;
            }
        }

        foreach ($targets as $target) {
            $target->update(['is_active' => false]);
            ActivityLogger::log('admin.status.updated', 'Proprietar: a dezactivat contul lui '.ActivityLogger::label($target).' (trecere pe planul '.$plan->name.').', $target);
        }

        $this->showDowngrade = false;
        $this->deactivate = [];
        $this->persistLicense($plan, $owner);
    }

    private function persistLicense(Plan $plan, Admin $owner): void
    {
        $license = License::query()->first() ?? new License;
        $before = $license->exists ? $license->plan_key : '—';
        $license->fill(['plan_key' => $plan->key, 'overrides' => [], 'updated_by' => $owner->id])->save();

        ActivityLogger::log('owner.license.updated', 'Proprietar: planul instalării '.$before.' → '.$plan->key.'.', $owner);
        $this->load();
        session()->flash('status', 'Licența a fost salvată.');
    }

    public function render()
    {
        $this->authorizeOwner();
        PlanGate::flush();

        $targetPlan = $this->showDowngrade ? Plan::query()->where('key', $this->licensePlan)->first() : null;

        return view('livewire.admin.owner.index', [
            'excess' => $targetPlan ? $this->excessFor($targetPlan) : [],
            'accounts' => $this->showDowngrade ? Admin::query()->withoutOwner()->where('is_active', true)->orderBy('role')->orderBy('name')->get() : collect(),
            'targetPlanName' => $targetPlan?->name,
            'planOptions' => collect($this->plans)->map(fn ($p) => $p['name'])->all(),
            'groups' => Features::grouped(),
            'sms' => SmsUsage::report(),
            'smsHistory' => SmsUsage::history(),
            'activeKey' => PlanGate::planKey() ?? $this->licensePlan,
            'usage' => [
                'superadmin' => PlanGate::accounts('superadmin'),
                'admin' => PlanGate::accounts('admin'),
            ],
        ]);
    }
}
