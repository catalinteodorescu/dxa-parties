<div class="max-w-2xl"
     x-data
     x-on:theme-changed.window="Object.entries($event.detail.vars).forEach(([k, v]) => document.documentElement.style.setProperty(k, v))">
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-ink">Setări</h2>
        <p class="mt-1 text-sm text-ink-soft">Configurări generale ale aplicației.</p>
    </div>

    <x-flash class="mb-4" />

    {{-- DXA: adaugat (Setări - categorii meniu bar): panou cu salvare imediată, separat de formularul de mai sus --}}
    <div class="mb-5">
        @livewire(\App\Livewire\Admin\Settings\MenuCategories::class)
    </div>

    <form wire:submit="save" class="space-y-5">
        @foreach ($this->sections() as $section)
            <div class="bg-surface border border-border rounded-2xl p-6">
                <h3 class="text-sm font-semibold text-ink">{{ $section['label'] }}</h3>
                @if (! empty($section['description']))
                    <p class="mt-1 text-sm text-ink-soft leading-relaxed">{{ $section['description'] }}</p>
                @endif

                <div class="mt-5 space-y-5">
                    @foreach ($section['fields'] as $field)
                        @php $disabled = $this->fieldDisabled($field); @endphp

                        <div class="{{ $disabled ? 'opacity-50' : '' }}">
                            @if ($field['type'] === 'bool')
                                {{-- Toggle --}}
                                <label class="flex items-start gap-3 {{ $disabled ? '' : 'cursor-pointer' }}">
                                    <span x-data="{ on: @entangle('values.'.$field['key']).live }" @click="! {{ $disabled ? 'true' : 'false' }} && (on = ! on)"
                                          class="mt-0.5 relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors"
                                          :class="on ? 'bg-primary' : 'bg-border'">
                                        <span class="inline-block h-[18px] w-[18px] transform rounded-full bg-white shadow transition-transform"
                                              :class="on ? 'translate-x-6' : 'translate-x-1'"></span>
                                    </span>
                                    <span>
                                        <span class="block text-sm font-medium text-ink">{{ $field['label'] }}</span>
                                        @if (! empty($field['help']))
                                            <span class="block text-xs text-ink-soft mt-0.5">{{ $field['help'] }}</span>
                                        @endif
                                    </span>
                                </label>
                            @elseif ($field['type'] === 'number')
                                <label class="block text-sm font-medium text-ink">{{ $field['label'] }}</label>
                                <div class="mt-1.5 flex items-center gap-2 max-w-xs">
                                    <input type="number"
                                           wire:model="values.{{ $field['key'] }}"
                                           step="{{ $field['step'] ?? 1 }}"
                                           min="{{ $field['min'] ?? 0 }}"
                                           @if ($disabled) disabled @endif
                                           class="w-full rounded-lg border border-border px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary
                                                  {{ $disabled ? 'bg-bg text-ink-soft cursor-not-allowed' : 'bg-white text-ink' }}">
                                    @if (! empty($field['suffix']))
                                        <span class="text-sm text-ink-soft whitespace-nowrap">{{ $field['suffix'] }}</span>
                                    @endif
                                </div>
                                @if (! empty($field['help']))
                                    <p class="mt-1.5 text-xs text-ink-soft">{{ $field['help'] }}</p>
                                @endif
                                @error('values.'.$field['key']) <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                            @elseif ($field['type'] === 'image')
                                {{-- Logo: previzualizare pe fundalul pe care apare + upload --}}
                                @php $preview = $this->imagePreview($field); @endphp
                                <label class="block text-sm font-medium text-ink">{{ $field['label'] }}</label>
                                <div class="mt-2 flex items-center justify-center rounded-xl border border-border p-5 {{ ($field['preview'] ?? '') === 'primary' ? 'bg-primary' : 'bg-white' }}">
                                    <img src="{{ $preview['url'] }}" alt="{{ $field['label'] }}" class="h-14 w-auto max-w-full">
                                </div>
                                <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-2">
                                    <label class="inline-flex items-center rounded-lg border border-border bg-white px-3.5 py-2 text-sm font-medium text-ink hover:bg-bg cursor-pointer">
                                        {{ $preview['custom'] ? 'Schimbă logo-ul' : 'Încarcă logo' }}
                                        <input type="file" wire:model="uploads.{{ $field['key'] }}" accept="image/png,image/jpeg" class="sr-only">
                                    </label>
                                    @if ($preview['custom'])
                                        <button type="button" wire:click="removeUpload('{{ $field['key'] }}')" class="text-xs font-medium text-ink-soft hover:text-danger">Revino la logo-ul implicit</button>
                                    @endif
                                    <span wire:loading wire:target="uploads.{{ $field['key'] }}" class="text-xs text-ink-soft">Se încarcă…</span>
                                </div>
                                @if (! empty($field['help']))
                                    <p class="mt-1.5 text-xs text-ink-soft">{{ $field['help'] }} Se aplică după „Salvează setările”.</p>
                                @endif
                                @error('uploads.'.$field['key']) <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                            @elseif ($field['type'] === 'swatches')
                                {{-- Culori prestabilite (ex. culoarea temei) --}}
                                <label class="block text-sm font-medium text-ink mb-2">{{ $field['label'] }}</label>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                                    @foreach ($field['options'] as $optKey => $opt)
                                        @php $selected = ($values[$field['key']] ?? null) === $optKey; @endphp
                                        <button type="button" wire:click="$set('values.{{ $field['key'] }}', '{{ $optKey }}')"
                                                aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                                class="flex items-center gap-2.5 rounded-xl border px-3 py-2.5 text-left text-sm transition-colors {{ $selected ? 'border-ink bg-bg font-semibold text-ink' : 'border-border bg-white text-ink-soft hover:border-ink-soft/40' }}">
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full shrink-0" style="background-color: {{ $opt['color'] }}">
                                                @if ($selected)
                                                    <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                @endif
                                            </span>
                                            <span class="truncate">{{ $opt['label'] }}</span>
                                        </button>
                                    @endforeach
                                </div>

                                {{-- Previzualizare izolata: variabilele CSS sunt puse doar pe acest container --}}
                                @if ($field['key'] === 'theme_color')
                                    <div class="mt-3 rounded-xl border border-border bg-bg p-4" style="{{ \App\Support\Theme::inlineStyle($values[$field['key']] ?? null) }}">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <span class="inline-flex items-center rounded-lg bg-primary text-white text-sm font-medium px-4 py-2">Buton principal</span>
                                            <span class="inline-flex items-center rounded-full bg-primary-soft text-primary text-xs font-medium px-2.5 py-1">Etichetă</span>
                                            <span class="text-sm text-primary font-medium">Link</span>
                                            <span class="inline-flex h-6 w-11 items-center rounded-full bg-primary"><span class="ml-[22px] inline-block h-[18px] w-[18px] rounded-full bg-white shadow"></span></span>
                                        </div>
                                        <p class="mt-2.5 text-[11px] text-ink-soft/80">Previzualizare — se aplică după „Salvează setările”.</p>
                                    </div>
                                @endif
                                @if (! empty($field['help']))
                                    <p class="mt-2 text-xs text-ink-soft">{{ $field['help'] }}</p>
                                @endif
                                @error('values.'.$field['key']) <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                            @elseif ($field['type'] === 'select')
                                <label class="block text-sm font-medium text-ink mb-1.5">{{ $field['label'] }}</label>
                                <x-select wire:model="values.{{ $field['key'] }}" class="max-w-xs" :options="$field['options'] ?? []" />
                                @if (! empty($field['help']))
                                    <p class="mt-1.5 text-xs text-ink-soft">{{ $field['help'] }}</p>
                                @endif
                            @else
                                {{-- text (implicit) --}}
                                <label class="block text-sm font-medium text-ink">{{ $field['label'] }}</label>
                                <input type="text"
                                       wire:model="values.{{ $field['key'] }}"
                                       @if (! empty($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif
                                       @if ($disabled) disabled @endif
                                       class="mt-1.5 w-full {{ ! empty($field['wide']) ? '' : 'max-w-xs' }} rounded-lg border border-border px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary
                                              {{ $disabled ? 'bg-bg text-ink-soft cursor-not-allowed' : 'bg-white text-ink' }}">
                                @if (! empty($field['help']))
                                    <p class="mt-1.5 text-xs text-ink-soft">{{ $field['help'] }}</p>
                                @endif
                                @error('values.'.$field['key']) <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex items-center gap-4">
            <x-btn variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                Salvează setările
            </x-btn>
        </div>
    </form>

</div>
