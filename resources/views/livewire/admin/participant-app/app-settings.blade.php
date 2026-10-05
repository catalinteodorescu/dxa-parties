@php
    $card = 'rounded-2xl border border-border bg-surface p-5';
    $input = 'mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary';
@endphp
<div class="max-w-2xl">
    <div class="mb-5">
        <h2 class="text-xl font-semibold text-ink">{{ $heading }}</h2>
        <p class="mt-1 text-sm text-ink-soft">Identitatea aplicației participanților (nume, logo, iconița de pe telefon), textele din Acasă, regulile conturilor, contactul, linkurile legale și mesajele SMS.</p>
    </div>

    <x-flash class="mb-4" />

    <form wire:submit="save" class="space-y-4">

        @include('livewire.admin._pwa-identity')

        @foreach ($groups as $group)
            <div class="{{ $card }} space-y-4" wire:key="group-{{ $loop->index }}">
                <div>
                    <h3 class="text-sm font-semibold text-ink">{{ $group['title'] }}</h3>
                    <p class="mt-1 text-xs text-ink-soft">{{ $group['description'] }}</p>
                </div>

                @foreach ($group['fields'] as $f)
                    <div wire:key="f-{{ $f['key'] }}">
                        @if ($f['type'] === 'bool')
                            <label class="flex items-start gap-3 cursor-pointer">
                                <span x-data="{ on: @entangle('values.'.$f['key']).live }" @click="on = ! on"
                                      class="mt-0.5 relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors"
                                      :class="on ? 'bg-primary' : 'bg-border'">
                                    <span class="inline-block h-[18px] w-[18px] transform rounded-full bg-white shadow transition-transform"
                                          :class="on ? 'translate-x-6' : 'translate-x-1'"></span>
                                </span>
                                <span>
                                    <span class="block text-sm font-medium text-ink">{{ $f['label'] }}</span>
                                    @if (! empty($f['help']))
                                        <span class="block text-xs text-ink-soft mt-0.5">{{ $f['help'] }}</span>
                                    @endif
                                </span>
                            </label>
                        @else
                            <label for="f-{{ $f['key'] }}" class="block text-sm font-medium text-ink">{{ $f['label'] }}</label>
                            @if ($f['type'] === 'textarea')
                                <textarea id="f-{{ $f['key'] }}" wire:model.live.debounce.300ms="values.{{ $f['key'] }}" rows="3" maxlength="{{ $f['max'] }}" class="{{ $input }}"></textarea>
                                @php $sms = $this->smsPreview($f['key'], $f['sample']); @endphp
                                <div class="mt-2 rounded-lg bg-bg border border-border px-3 py-2 text-sm text-ink" data-sms-preview="{{ $f['key'] }}">{{ $sms['text'] }}</div>
                                <p class="mt-1 text-[11px] text-ink-soft">
                                    {{ $sms['length'] }} caractere
                                    @if ($sms['unicode'])
                                        · conține diacritice: se trimite ca SMS Unicode (70 de caractere pe mesaj, în loc de 160). Fără diacritice costă mai puțin.
                                    @endif
                                </p>
                            @else
                                <div class="flex items-center gap-2">
                                    <input type="{{ $f['type'] === 'number' ? 'number' : 'text' }}" id="f-{{ $f['key'] }}" wire:model="values.{{ $f['key'] }}"
                                           @if ($f['type'] === 'number') min="{{ $f['min'] }}" max="{{ $f['max'] }}" step="1" inputmode="numeric" @else maxlength="{{ $f['max'] }}" @endif
                                           placeholder="{{ $f['placeholder'] ?? '' }}"
                                           class="{{ $input }} {{ $f['type'] === 'number' ? 'max-w-[8rem]' : '' }}">
                                    @if (! empty($f['suffix']))
                                        <span class="mt-1.5 text-sm text-ink-soft">{{ $f['suffix'] }}</span>
                                    @endif
                                </div>
                            @endif
                            @if (! empty($f['help']))
                                <p class="mt-1.5 text-xs text-ink-soft">{{ $f['help'] }}</p>
                            @endif
                            @if ($group['title'] === 'Contact' && $f['key'] === 'app_contact_website' && $schoolAddress)
                                <p class="mt-1.5 text-xs text-ink-soft">Adresa afișată: {{ $schoolAddress }}</p>
                            @endif
                            @error('values.'.$f['key']) <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach

        <div>
            <x-btn variant="primary" type="submit">Salvează</x-btn>
        </div>
    </form>
</div>
