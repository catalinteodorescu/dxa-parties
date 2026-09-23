<div class="max-w-2xl">
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
                                       @if ($disabled) disabled @endif
                                       class="mt-1.5 w-full max-w-xs rounded-lg border border-border px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary
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
