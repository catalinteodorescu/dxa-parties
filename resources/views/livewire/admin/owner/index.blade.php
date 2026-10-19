<div class="max-w-5xl space-y-8" data-owner-page>
    <div>
        <h2 class="text-lg font-semibold text-ink">Proprietar</h2>
        <p class="mt-1 text-sm text-ink-soft">Planurile aplicației și licența acestei instalări. Pagina nu e vizibilă pentru client.</p>
    </div>

    <x-flash />

    {{-- ========== Instalare ========== --}}
    <section class="bg-surface border border-border rounded-2xl p-5 space-y-4">
        <h3 class="font-semibold text-ink">Instalarea</h3>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-ink mb-1">Plan activ</label>
                <x-select wire:model="licensePlan" data-license-plan class="max-w-xs" :options="$planOptions" />
                @error('licensePlan') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="text-sm text-ink-soft space-y-1">
                <p>Superadmini activi: <strong class="text-ink">{{ $usage['superadmin'] }}</strong></p>
                <p>Admini activi: <strong class="text-ink">{{ $usage['admin'] }}</strong></p>
                <p>SMS trimise luna aceasta: <strong class="text-ink">{{ $sms['sent'] }}</strong>{{ $sms['included'] === null ? ' (cotă nelimitată)' : ' din '.$sms['included'].' incluse' }}</p>
            </div>
        </div>

        <p class="text-xs text-ink-soft">Planul ales mai sus se aplică doar după „Salvează licența”.</p>
    </section>

    {{-- ========== SMS: raport lunar ========== --}}
    <section class="bg-surface border border-border rounded-2xl p-5 space-y-4" data-sms-report>
        <div>
            <h3 class="font-semibold text-ink">SMS-uri trimise</h3>
            <p class="mt-1 text-sm text-ink-soft">Fiecare SMS plecat din aplicație se numără (activări, resetări de parolă, invitații, bilete trimise). Cota e „moale”: peste ea SMS-urile pleacă în continuare, iar depășirea se facturează lunar, la prețul din plan.</p>
        </div>

        @if ($smsCredit !== null)
            <p class="text-sm {{ $smsCredit < (float) config('sms.smso.low_credit') ? 'text-danger font-medium' : 'text-ink-soft' }}" data-sms-credit>
                Credit SMSO rămas (contul comun, toate instalările): <strong>{{ number_format($smsCredit, 2, ',', '.') }}</strong>{{ $smsCredit < (float) config('sms.smso.low_credit') ? ' — alimentează contul, altfel SMS-urile nu mai pleacă la niciun client.' : '' }}
            </p>
        @endif

        <dl class="grid gap-3 sm:grid-cols-5">
            <div class="rounded-xl border border-border p-3"><dt class="text-xs text-ink-soft">Trimise luna aceasta</dt><dd class="mt-0.5 text-xl font-semibold text-ink" data-sms-sent>{{ $sms['sent'] }}</dd></div>
            <div class="rounded-xl border border-border p-3"><dt class="text-xs text-ink-soft">Incluse în plan</dt><dd class="mt-0.5 text-xl font-semibold text-ink">{{ $sms['included'] ?? 'nelimitat' }}</dd></div>
            <div class="rounded-xl border border-border p-3"><dt class="text-xs text-ink-soft">Peste cotă</dt><dd class="mt-0.5 text-xl font-semibold {{ $sms['overage'] > 0 ? 'text-warning' : 'text-ink' }}" data-sms-overage>{{ $sms['overage'] }}</dd></div>
            <div class="rounded-xl border border-border p-3"><dt class="text-xs text-ink-soft">Cost furnizor (real)</dt><dd class="mt-0.5 text-xl font-semibold text-ink" data-sms-cost>{{ number_format($sms['cost'], 2, ',', '.') }} €</dd></div>
            <div class="rounded-xl border border-border p-3"><dt class="text-xs text-ink-soft">De facturat (fără TVA)</dt><dd class="mt-0.5 text-xl font-semibold text-ink" data-sms-amount>{{ number_format($sms['amount'], 2, ',', '.') }} €</dd></div>
        </dl>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-ink-soft border-b border-border">
                        <th class="py-2 pr-3 font-semibold">Luna</th>
                        <th class="py-2 px-3 font-semibold text-right">Trimise</th>
                        <th class="py-2 px-3 font-semibold text-right">Incluse</th>
                        <th class="py-2 px-3 font-semibold text-right">Peste cotă</th>
                        <th class="py-2 px-3 font-semibold text-right">Cost furnizor</th>
                        <th class="py-2 pl-3 font-semibold text-right">De facturat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($smsHistory as $row)
                        <tr class="border-b border-border/60" wire:key="sms-{{ $row['period'] }}">
                            <td class="py-2 pr-3 text-ink">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $row['period'])->locale('ro')->translatedFormat('F Y') }}</td>
                            <td class="py-2 px-3 text-right">{{ $row['sent'] }}</td>
                            <td class="py-2 px-3 text-right">{{ $row['included'] ?? '—' }}</td>
                            <td class="py-2 px-3 text-right">{{ $row['overage'] }}</td>
                            <td class="py-2 px-3 text-right">{{ number_format($row['cost'], 2, ',', '.') }} €</td>
                            <td class="py-2 pl-3 text-right">{{ number_format($row['amount'], 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- ========== Coborâre: ce conturi dezactivezi ========== --}}
    @if ($showDowngrade)
        <section class="bg-surface border border-warning rounded-2xl p-5 space-y-4" data-downgrade-panel>
            <div>
                <h3 class="font-semibold text-ink">Conturile active nu încap în planul {{ $targetPlanName }}</h3>
                <p class="mt-1 text-sm text-ink-soft">
                    Bifează conturile pe care le dezactivezi (nu se șterg; le poți reactiva după ce treci pe un plan mai mare).
                    @foreach ($excess as $role => $need)
                        Mai trebuie dezactivați cel puțin <strong class="text-ink">{{ $need }} {{ $role === 'admin' ? 'admini' : 'superadmini' }}</strong>.
                    @endforeach
                </p>
            </div>

            <div class="divide-y divide-border rounded-xl border border-border">
                @foreach ($accounts as $account)
                    <div class="flex items-center gap-3 px-4 py-2.5" wire:key="dg-{{ $account->id }}">
                        <x-checkbox wire:model.live="deactivate.{{ $account->id }}" data-deactivate="{{ $account->id }}" aria-label="Dezactivează {{ $account->name ?: $account->phone }}" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-ink truncate">{{ $account->name ?: 'Fără nume' }}</p>
                            <p class="text-xs text-ink-soft">{{ $account->phone }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs {{ $account->role === 'superadmin' ? 'bg-primary text-white' : 'bg-bg text-ink-soft border border-border' }}">{{ $account->role === 'superadmin' ? 'Superadmin' : 'Admin' }}</span>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <x-btn type="button" variant="neutral" wire:click="cancelDowngrade">Renunț</x-btn>
                <x-btn type="button" wire:click="confirmDowngrade" data-confirm-downgrade>Dezactivează conturile bifate și trece pe {{ $targetPlanName }}</x-btn>
            </div>
        </section>
    @endif

    {{-- ========== Matricea funcțiilor ========== --}}
    <section class="bg-surface border border-border rounded-2xl p-5 space-y-5">
        <div>
            <h3 class="font-semibold text-ink">Funcții pe planuri</h3>
            <p class="mt-1 text-sm text-ink-soft">
                Bifele din coloanele planurilor spun ce include fiecare plan. Coloana planului activ pe această instalare este evidențiată.
            </p>
        </div>

        <div class="overflow-x-auto md:overflow-visible">
            <table class="w-full text-sm" data-plan-matrix>
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-ink-soft border-b border-border">
                        <th class="py-2 pr-3 font-semibold">Funcție</th>
                        @foreach ($plans as $pkey => $plan)
                            <th class="py-2 px-3 font-semibold text-center {{ $pkey === $activeKey ? 'bg-primary-soft text-primary rounded-t-lg' : '' }}" @if ($pkey === $activeKey) data-active-plan @endif>{{ $plan['name'] }}@if ($pkey === $activeKey)<span class="block text-[10px] normal-case tracking-normal font-medium">plan activ</span>@endif</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $groupLabel => $features)
                        <tr><td colspan="{{ 1 + count($plans) }}" class="pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-ink-soft/70">{{ $groupLabel }}</td></tr>
                        @foreach ($features as $key => $label)
                            <tr class="border-b border-border/60" wire:key="feat-{{ $key }}">
                                <td class="py-2 pr-3 text-ink">
                                    {{ $label }}
                                    @if (! \App\Support\Features::hasAppPart($key))
                                        <span class="ml-1 text-[11px] text-ink-soft">(doar panoul admin)</span>
                                    @endif
                                </td>
                                @foreach ($plans as $pkey => $plan)
                                    <td class="py-2 px-3 text-center {{ $pkey === $activeKey ? 'bg-primary-soft/40' : '' }}">
                                        <x-checkbox wire:model="plans.{{ $pkey }}.features.{{ $key }}" data-feature="{{ $pkey }}.{{ $key }}" class="justify-center" aria-label="{{ $plan['name'] }}: {{ $label }}" />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex justify-end gap-2">
            <x-btn type="button" variant="neutral" wire:click="savePlans" data-save-plans>Salvează planurile</x-btn>
            <x-btn type="button" wire:click="saveLicense" data-save-license>Salvează licența</x-btn>
        </div>
        <p class="text-xs text-ink-soft text-right">„Salvează planurile” păstrează ce include fiecare plan, limitele și prețurile; „Salvează licența” aplică planul ales pe această instalare.</p>
    </section>

    {{-- ========== Planuri: nume, prețuri, limite ========== --}}
    <section class="bg-surface border border-border rounded-2xl p-5 space-y-5">
        <h3 class="font-semibold text-ink">Planuri: nume, prețuri, limite</h3>

        <div class="grid gap-5 md:grid-cols-2">
            @foreach ($plans as $key => $plan)
                <div class="rounded-xl border border-border p-4 space-y-3" wire:key="plan-{{ $key }}">
                    <div>
                        <label class="block text-xs font-medium text-ink-soft mb-1">Nume</label>
                        <input type="text" wire:model="plans.{{ $key }}.name" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                        @error("plans.$key.name") <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-ink-soft mb-1">Preț lunar (lei, fără TVA)</label>
                            <input type="text" inputmode="decimal" wire:model="plans.{{ $key }}.price_monthly" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-ink-soft mb-1">Preț pe lună la plata anuală</label>
                            <input type="text" inputmode="decimal" wire:model="plans.{{ $key }}.price_yearly_monthly" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-soft mb-1">Preț SMS peste cotă (euro, fără TVA)</label>
                        <input type="text" inputmode="decimal" wire:model="plans.{{ $key }}.sms_overage_eur" data-sms-price="{{ $key }}" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                        @error("plans.$key.sms_overage_eur") <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    @foreach (\App\Support\Features::LIMITS as $lk => [$lLabel, $unit])
                        <div>
                            <label class="block text-xs font-medium text-ink-soft mb-1">{{ $lLabel }} ({{ $unit }}; gol = nelimitat)</label>
                            <input type="text" inputmode="numeric" wire:model="plans.{{ $key }}.limits.{{ $lk }}" data-limit="{{ $key }}.{{ $lk }}" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                            @error("plans.$key.limits.$lk") <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
        <p class="text-xs text-ink-soft text-right">Se salvează cu „Salvează planurile” de mai sus.</p>
    </section>
</div>
