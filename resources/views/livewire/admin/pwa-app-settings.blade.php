@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $shownName = trim($name) !== '' ? trim($name) : $pwa::DEFAULT_NAME;
@endphp
<div class="max-w-2xl">
    <div class="mb-5">
        <h2 class="text-xl font-semibold text-ink">{{ $heading }}</h2>
        <p class="mt-1 text-sm text-ink-soft">Cum arată {{ str_replace('aplicației', 'aplicația', $appPhrase) }} de pe telefon: numele, logo-ul și culoarea temei. Tema colorează și iconița de pe ecranul telefonului.</p>
    </div>

    <x-flash class="mb-4" />

    <form wire:submit="save" class="space-y-4">

        {{-- Nume + logo --}}
        <div class="{{ $card }} space-y-5">
            <div>
                <label for="app-name" class="block text-sm font-medium text-ink">Numele aplicației</label>
                <input type="text" id="app-name" wire:model.live.debounce.300ms="name" maxlength="40" placeholder="{{ $pwa::DEFAULT_NAME }}"
                       class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                <p class="mt-1.5 text-xs text-ink-soft">Apare în antet, sub iconița de pe ecranul telefonului și în titlul aplicației. Lasă gol pentru „{{ $pwa::DEFAULT_NAME }}”.</p>
                @error('name') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-ink">Logo</label>
                <div class="mt-2 flex items-center justify-center rounded-xl border border-border p-5" style="background: linear-gradient(135deg, {{ $preview['bright'] }}, {{ $preview['primary'] }}, {{ $preview['dark'] }})">
                    <img src="{{ $logo['url'] }}" alt="Logo" class="h-14 w-auto max-w-full">
                </div>
                <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <label class="inline-flex items-center rounded-lg border border-border bg-white px-3.5 py-2 text-sm font-medium text-ink hover:bg-bg cursor-pointer">
                        {{ $logo['custom'] ? 'Schimbă logo-ul' : 'Încarcă logo' }}
                        <input type="file" wire:model="logoUpload" accept="image/png,image/jpeg" class="sr-only">
                    </label>
                    @if ($logo['custom'])
                        <button type="button" wire:click="removeLogo" class="text-xs font-medium text-ink-soft hover:text-danger">Revino la logo-ul școlii</button>
                    @endif
                    <span wire:loading wire:target="logoUpload" class="text-xs text-ink-soft">Se încarcă…</span>
                </div>
                <p class="mt-1.5 text-xs text-ink-soft">Varianta deschisă/albă, PNG cu fundal transparent: apare pe culoarea temei, în antet și în iconiță. Implicit se folosește logo-ul școlii pentru fundal colorat.</p>
                @error('logoUpload') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Tema --}}
        <div class="{{ $card }}">
            <label class="block text-sm font-medium text-ink mb-2">Culoarea temei</label>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                @foreach ($presets as $key => $p)
                    @php $selected = $theme === $key; @endphp
                    <button type="button" wire:key="theme-{{ $key }}" wire:click="selectTheme('{{ $key }}')" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                            class="flex items-center gap-2.5 rounded-xl border px-3 py-2.5 text-left text-sm transition-colors {{ $selected ? 'border-ink bg-bg font-semibold text-ink' : 'border-border bg-white text-ink-soft hover:border-ink-soft/40' }}">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full shrink-0" style="background: linear-gradient(135deg, {{ $p['bright'] }}, {{ $p['primary'] }}, {{ $p['dark'] }})">
                            @if ($selected)
                                <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            @endif
                        </span>
                        <span class="truncate">{{ $p['label'] }}</span>
                    </button>
                @endforeach
            </div>
            @if (! $pwa::hasCustomTheme())
                <p class="mt-2 text-xs text-ink-soft">Până alegi și salvezi o temă, aplicația folosește tema panoului admin.</p>
            @endif
            @error('theme') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        {{-- Previzualizare --}}
        <div class="{{ $card }}">
            <h3 class="text-sm font-semibold text-ink">Previzualizare</h3>
            <div class="mt-3 flex flex-wrap items-end gap-6">
                <div class="text-center">
                    <div class="w-20 h-20 rounded-[22%] flex items-center justify-center shadow-sm" style="background: linear-gradient(135deg, {{ $preview['bright'] }}, {{ $preview['primary'] }}, {{ $preview['dark'] }})">
                        <img src="{{ $logo['url'] }}" alt="" class="w-[62%] h-auto max-h-[62%] object-contain">
                    </div>
                    <div class="mt-1.5 text-xs text-ink max-w-20 truncate">{{ \Illuminate\Support\Str::limit($shownName, 12, '') }}</div>
                    <div class="text-[11px] text-ink-soft">iconița</div>
                </div>

                <div class="flex-1 min-w-[12rem]" style="{{ \App\Support\Theme::inlineStyle($theme) }}">
                    <div class="rounded-xl overflow-hidden border border-border">
                        <div class="bg-primary text-white h-11 px-3 flex items-center gap-2">
                            <img src="{{ $logo['url'] }}" alt="" class="h-6 w-auto">
                            <span class="text-sm font-semibold tracking-wide">{{ $shownName }}</span>
                        </div>
                        <div class="bg-bg p-3">
                            <span class="inline-flex items-center rounded-lg bg-primary text-white text-xs font-medium px-3 py-1.5">Buton</span>
                            <span class="ml-2 inline-flex items-center rounded-full bg-primary-soft text-primary text-[11px] font-medium px-2 py-0.5">Etichetă</span>
                        </div>
                    </div>
                    <div class="mt-1.5 text-[11px] text-ink-soft">antetul aplicației</div>
                </div>
            </div>
        </div>

        <div>
            <x-btn variant="primary" type="submit">Salvează</x-btn>
        </div>
    </form>
</div>
