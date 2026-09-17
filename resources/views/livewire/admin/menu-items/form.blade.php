<div>
    <div class="max-w-2xl">
    @php $isEditing = $menuItem && $menuItem->exists; @endphp

    <div class="mb-6">
        <h2 class="text-lg font-semibold text-ink">{{ $isEditing ? 'Editează produsul' : 'Produs nou' }}</h2>
        <p class="mt-1 text-sm text-ink-soft leading-relaxed">
            Produsele apar în barul general al locației, grupate pe categorie.
        </p>
    </div>

    @php
        $categoryOptions = $categories->mapWithKeys(fn ($c) => [$c->id => $c->name.($c->is_active ? '' : ' (ascunsă)')])->all();
    @endphp

    <form wire:submit="save" class="bg-surface border border-border rounded-2xl p-6 space-y-5">

        {{-- Nume --}}
        <div>
            <label for="name" class="block text-sm font-medium text-ink">Nume</label>
            <input type="text" id="name" wire:model="name" autofocus placeholder="ex. Mojito"
                   class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
            @error('name') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        {{-- Categorie --}}
        <div>
            <label class="block text-sm font-medium text-ink mb-1.5">Categorie</label>
            <div class="flex items-start gap-2">
                <div class="flex-1">
                    <x-select wire:model="menu_category_id" placeholder="Alege categoria…"
                              :options="$categoryOptions" />
                </div>
                <button type="button" wire:click="openNewCategoryModal" title="Categorie nouă"
                        class="shrink-0 inline-flex items-center justify-center h-[42px] w-[42px] rounded-lg border border-border bg-white text-ink-soft hover:bg-bg hover:text-ink transition-colors">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                </button>
            </div>
            @error('menu_category_id') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        {{-- Cantitate + Preț --}}
        <div class="grid grid-cols-1 {{ $usesTokens ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }} gap-4">
            <div>
                <label for="quantity" class="block text-sm font-medium text-ink">Cantitate <span class="text-ink-soft/60 font-normal">(opțional)</span></label>
                <input type="text" id="quantity" wire:model="quantity" placeholder="ex. 330 ml, 50 ml, 1 buc"
                       class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('quantity') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            @if ($usesTokens)
                <div>
                    <label for="tokens" class="block text-sm font-medium text-ink">Preț <span class="text-ink-soft/60 font-normal">(tokeni)</span></label>
                    <input type="number" id="tokens" wire:model.live.blur="tokens" step="0.5" min="0" placeholder="0"
                           class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                    @error('tokens') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label for="price" class="block text-sm font-medium text-ink">
                    Preț <span class="text-ink-soft/60 font-normal">(lei)</span>
                    @if ($usesTokens)
                        <span class="text-ink-soft/60 font-normal">— automat</span>
                    @endif
                </label>
                <input type="number" id="price" wire:model.live.blur="price" step="0.01" min="0" placeholder="0.00"
                       @if ($usesTokens) disabled @endif
                       class="mt-1.5 w-full rounded-lg border border-border px-3.5 py-2.5 text-sm placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary
                              {{ $usesTokens ? 'bg-bg text-ink-soft cursor-not-allowed' : 'bg-white text-ink' }}">
                @if ($usesTokens)
                    <p class="mt-1.5 text-xs text-ink-soft/60">curs: 1 tk = {{ $tokenRate }} lei — schimbă-l din <a href="{{ route('admin.settings.index') }}" wire:navigate class="underline hover:text-ink">Setări</a></p>
                @endif
                @error('price') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Imagine --}}
        <div>
            <label class="block text-sm font-medium text-ink">Imagine <span class="text-ink-soft/60 font-normal">(opțional)</span></label>

            <div class="mt-1.5 flex items-start gap-4">
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

        {{-- Descriere / rețetă --}}
        <div>
            <label for="description" class="block text-sm font-medium text-ink">Descriere <span class="text-ink-soft/60 font-normal">(opțional — ingrediente, rețetă)</span></label>
            <textarea id="description" wire:model="description" rows="5"
                      class="mt-1.5 w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary"
                      placeholder="ex. 50ml rom alb, 25ml zeamă de lime, 2 lgț zahăr, mentă, sifon…"></textarea>
            @error('description') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        {{-- Vizibil --}}
        <label class="flex items-center gap-2.5 text-sm text-ink">
            <input type="checkbox" wire:model="is_active" class="w-4 h-4 rounded border-border accent-primary">
            Vizibil în meniu <span class="text-ink-soft/70">— debifat = ascuns temporar (ex. stoc epuizat)</span>
        </label>

        {{-- Acțiuni --}}
        <div class="flex items-center gap-4 pt-2">
            <x-btn variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                {{ $isEditing ? 'Salvează modificările' : 'Creează produsul' }}
            </x-btn>
            <a href="{{ route('admin.menu-items.index') }}" wire:navigate class="text-sm text-ink-soft hover:text-ink">
                Anulează
            </a>
        </div>

    </form>
    </div>

    {{-- Modal: adaugă categorie rapid, fără să părăsești formularul de produs --}}
    <div x-show="$wire.newCategoryModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" wire:click="closeNewCategoryModal"></div>
        <div class="relative bg-surface rounded-2xl border border-border shadow-lg max-w-sm w-full p-6">
            <h3 class="text-base font-semibold text-ink">Categorie nouă</h3>

            <div class="mt-4">
                <label class="block text-sm font-medium text-ink mb-1">Denumire</label>
                <input type="text" wire:model="newCategoryName" wire:keydown.enter="saveNewCategory" placeholder="ex. Cocktailuri"
                       class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink placeholder:text-ink-soft/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                @error('newCategoryName') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" wire:click="closeNewCategoryModal" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2">Anulează</button>
                <x-btn type="button" variant="primary" wire:click="saveNewCategory">Salvează</x-btn>
            </div>
        </div>
    </div>
</div>
