<div class="max-w-2xl">
    @php $isEditing = $announcement && $announcement->exists; @endphp

    <div class="mb-6">
        <h2 class="text-lg font-semibold text-ink">{{ $isEditing ? 'Editează anunțul' : 'Anunț nou' }}</h2>
        <p class="mt-1 text-sm text-ink-soft leading-relaxed">
            Poate apărea în carusel (vizual) și/sau în zona de anunțuri (cu detalii). Alege cel puțin o plasare.
        </p>
    </div>

    <form wire:submit="save" class="bg-surface border border-border rounded-2xl p-6 space-y-5">

        {{-- Titlu --}}
        <div>
            <label for="title" class="block text-sm font-medium text-ink">Titlu</label>
            <input type="text" id="title" wire:model="title" autofocus
                   class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            @error('title') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        {{-- Text scurt --}}
        <div>
            <label for="body" class="block text-sm font-medium text-ink">Text scurt <span class="text-ink-soft/60 font-normal">(opțional)</span></label>
            <textarea id="body" wire:model="body" rows="3"
                      class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary"></textarea>
            @error('body') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        {{-- Imagine --}}
        <div>
            <label class="block text-sm font-medium text-ink">Imagine <span class="text-ink-soft/60 font-normal">(opțional, recomandat orizontală pentru carusel)</span></label>

            <div class="mt-1.5 flex items-start gap-4">
                {{-- Preview --}}
                <div class="shrink-0">
                    @if ($image)
                        <img src="{{ $image->temporaryUrl() }}" alt="" class="w-28 h-28 object-cover rounded-xl border border-border">
                    @elseif ($existingImage && ! $removeImage)
                        <img src="{{ asset('storage/'.$existingImage) }}" alt="" class="w-28 h-28 object-cover rounded-xl border border-border">
                    @else
                        <div class="w-28 h-28 rounded-xl border border-dashed border-border bg-bg flex items-center justify-center text-ink-soft/40">
                            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                <path d="M21 15l-5-5L5 21"/>
                            </svg>
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <input type="file" id="image" wire:model="image" accept="image/*"
                           class="block w-full text-sm text-ink-soft
                                  file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2
                                  file:text-sm file:font-medium file:text-white hover:file:bg-primary-hover file:cursor-pointer">

                    <div wire:loading wire:target="image" class="mt-2 text-xs text-ink-soft">Se încarcă imaginea…</div>

                    @if (($existingImage && ! $removeImage) || $image)
                        <button type="button" wire:click="clearImage"
                                class="mt-2 text-xs font-medium text-danger hover:underline">
                            Elimină imaginea
                        </button>
                    @endif

                    @error('image') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Link --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="url" class="block text-sm font-medium text-ink">Link <span class="text-ink-soft/60 font-normal">(opțional)</span></label>
                <input type="url" id="url" wire:model="url" placeholder="https://…"
                       class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('url') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="url_label" class="block text-sm font-medium text-ink">Etichetă buton</label>
                <input type="text" id="url_label" wire:model="url_label" placeholder="Vezi detalii"
                       class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('url_label') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Audiență --}}
        <div>
            <label class="block text-sm font-medium text-ink mb-1.5">Audiență</label>
            <x-select wire:model="audience" :options="['all' => 'Toți (inclusiv nelogați)', 'auth' => 'Doar utilizatorii logați']" />
        </div>

        {{-- Plasare --}}
        <div>
            <span class="block text-sm font-medium text-ink">Plasare</span>
            <div class="mt-2 space-y-2">
                <label class="flex items-center gap-2.5 text-sm text-ink">
                    <input type="checkbox" wire:model="in_carousel" class="w-4 h-4 rounded border-border accent-primary">
                    Carusel <span class="text-ink-soft/70">— vizual, în partea de sus a app-ului</span>
                </label>
                <label class="flex items-center gap-2.5 text-sm text-ink">
                    <input type="checkbox" wire:model="in_list" class="w-4 h-4 rounded border-border accent-primary">
                    Zona de anunțuri <span class="text-ink-soft/70">— card cu detalii</span>
                </label>
            </div>
            @error('in_list') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        {{-- Interval --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="starts_at" class="block text-sm font-medium text-ink">Începe</label>
                <input type="datetime-local" id="starts_at" wire:model="starts_at"
                       class="accent-primary [color-scheme:light] mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('starts_at') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ends_at" class="block text-sm font-medium text-ink">Se termină <span class="text-ink-soft/60 font-normal">(opțional)</span></label>
                <input type="datetime-local" id="ends_at" wire:model="ends_at"
                       class="accent-primary [color-scheme:light] mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('ends_at') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Stare --}}
        <div>
            <label class="block text-sm font-medium text-ink mb-1.5">Stare</label>
            <x-select wire:model="status" class="sm:max-w-xs"
                      :options="['published' => 'Publicat', 'draft' => 'Ciornă (nu apare în app)']" />
        </div>

        {{-- Vizibil --}}
        <label class="flex items-center gap-2.5 text-sm text-ink">
            <input type="checkbox" wire:model="is_active" class="w-4 h-4 rounded border-border accent-primary">
            Vizibil în app <span class="text-ink-soft/70">— pentru anunțurile publicate; debifat = ascuns temporar</span>
        </label>

        {{-- Acțiuni --}}
        <div class="flex items-center gap-4 pt-2">
            <x-btn variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                {{ $isEditing ? 'Salvează modificările' : 'Creează anunțul' }}
            </x-btn>
            <a href="{{ route('admin.announcements.index') }}" wire:navigate class="text-sm text-ink-soft hover:text-ink">
                Anulează
            </a>
        </div>

    </form>
</div>
