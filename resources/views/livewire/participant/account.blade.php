<div>
    @include('livewire.participant._flash')

    <div x-data="{ profile: false, password: false, preview: null }"
         @password-changed.window="password = false"
         @keydown.escape.window="profile = false; password = false">
        @if ($me)
            <div class="pa-section" style="margin-top: .5rem; display: flex; flex-direction: column; align-items: center; text-align: center; gap: .6rem">
                @include('livewire.participant._avatar', ['who' => $me, 'size' => 'lg'])
                <div>
                    <div class="pa-eyebrow">Contul meu</div>
                    <h1 class="pa-h1">{{ $me->name }}</h1>
                </div>
                <div style="display: flex; gap: .5rem; flex-wrap: wrap; justify-content: center">
                    <button type="button" class="pa-btn pa-btn-ghost pa-btn-sm" @click="profile = true">Editează profilul</button>
                    <button type="button" class="pa-btn pa-btn-ghost pa-btn-sm" @click="password = true">Schimbă parola</button>
                </div>
            </div>

            {{-- Dialog: editare profil (nume + poză). Poza se taie pătrat și se micșorează în browser înainte de încărcare. --}}
            <div x-show="profile" x-cloak class="pa-modal-bg" @click.self="profile = false" role="dialog" aria-modal="true" aria-label="Editează profilul">
                <form wire:submit="saveProfile" class="pa-modal pa-stack" style="gap: 1rem" novalidate
                      x-data="{
                          pick(e) {
                              const f = e.target.files[0]; if (! f) return;
                              const img = new Image();
                              img.onload = () => {
                                  const c = document.createElement('canvas'); c.width = c.height = 512;
                                  const side = Math.min(img.width, img.height);
                                  c.getContext('2d').drawImage(img, (img.width - side) / 2, (img.height - side) / 2, side, side, 0, 0, 512, 512);
                                  c.toBlob((b) => {
                                      if (! b) return;
                                      preview = c.toDataURL('image/jpeg', .8);
                                      $wire.upload('photo', new File([b], 'poza.jpg', { type: 'image/jpeg' }), () => {}, () => {});
                                  }, 'image/jpeg', .85);
                                  URL.revokeObjectURL(img.src);
                              };
                              img.src = URL.createObjectURL(f);
                          },
                      }">
                    <h2 class="pa-h2" style="margin: 0">Editează profilul</h2>
                    @if ($profileError)
                        <div class="pa-alert pa-alert-err" role="alert">{{ $profileError }}</div>
                    @endif
                    <div style="display: flex; flex-direction: column; align-items: center; gap: .6rem">
                        <template x-if="preview"><img :src="preview" alt="" class="pa-avatar pa-avatar-lg"></template>
                        <template x-if="! preview">@include('livewire.participant._avatar', ['who' => $me, 'size' => 'lg'])</template>
                        <label class="pa-btn pa-btn-ghost pa-btn-sm" style="cursor: pointer">
                            Alege o poză
                            <input type="file" accept="image/jpeg,image/png,image/webp" @change="pick($event)" style="display: none">
                        </label>
                        <div wire:loading wire:target="photo" class="pa-soft" style="font-size: .85rem">Se încarcă poza…</div>
                        @error('photo') <p class="pa-err">{{ $message }}</p> @enderror
                        @if ($me->hasAvatar())
                            <button type="button" class="pa-link" style="font-size: .85rem" wire:click="removePhoto">Șterge poza actuală</button>
                        @endif
                    </div>
                    <div>
                        <label for="pa-name" class="pa-label">Nume</label>
                        <input type="text" id="pa-name" wire:model="name" maxlength="120" autocomplete="name" class="pa-input">
                    </div>
                    <div class="pa-soft" style="font-size: .8rem">Telefonul ({{ $me->phone }}) nu se poate schimba din aplicație.</div>
                    <div style="display: flex; gap: .5rem">
                        <button type="button" class="pa-btn pa-btn-ghost" style="flex: 1" @click="profile = false">Renunță</button>
                        <button type="submit" class="pa-btn" style="flex: 1" wire:loading.attr="disabled" wire:target="photo,saveProfile">Salvează</button>
                    </div>
                </form>
            </div>

            {{-- Dialog: schimbare parolă --}}
            <div x-show="password" x-cloak class="pa-modal-bg" @click.self="password = false" role="dialog" aria-modal="true" aria-label="Schimbă parola">
                <form wire:submit="changePassword" class="pa-modal pa-stack" style="gap: 1rem" novalidate>
                    <h2 class="pa-h2" style="margin: 0">Schimbă parola</h2>
                    @if ($passwordError)
                        <div class="pa-alert pa-alert-err" role="alert">{{ $passwordError }}</div>
                    @endif
                    <div>
                        <label for="pa-cur" class="pa-label">Parola curentă</label>
                        <input type="password" id="pa-cur" wire:model="currentPassword" autocomplete="current-password" class="pa-input">
                    </div>
                    <div>
                        <label for="pa-new" class="pa-label">Parola nouă (minim {{ \App\Services\ParticipantAccounts::MIN_PASSWORD }} caractere)</label>
                        <input type="password" id="pa-new" wire:model="newPassword" autocomplete="new-password" class="pa-input">
                    </div>
                    <div>
                        <label for="pa-new2" class="pa-label">Repetă parola nouă</label>
                        <input type="password" id="pa-new2" wire:model="newPasswordConfirmation" autocomplete="new-password" class="pa-input">
                    </div>
                    <div style="display: flex; gap: .5rem">
                        <button type="button" class="pa-btn pa-btn-ghost" style="flex: 1" @click="password = false">Renunță</button>
                        <button type="submit" class="pa-btn" style="flex: 1" wire:loading.attr="disabled">Schimbă</button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    @if ($me)
        <section class="pa-section">
            {{-- Fidelitate --}}
            @if ($card)
                @include('livewire.participant._loyalty-card', ['me' => $me, 'card' => $card, 'stamps' => $stamps])
            @elseif ($loyaltyOn)
                <div class="pa-glass pa-pad pa-stack">
                    <div class="pa-label" style="margin: 0">Card de fidelitate</div>
                    <div style="font-size: .95rem; font-weight: 700">Vrei să beneficiezi de oferte și de o intrare gratis?</div>
                    <div class="pa-soft" style="font-size: .9rem">Înscrie-te la cardul de fidelitate: primești o ștampilă la fiecare petrecere la care ești prezent, iar după ce strângi {{ \App\Services\LoyaltyLedger::stampsRequired() }} ștampile urmează o intrare gratis.</div>
                    <button type="button" class="pa-btn pa-btn-block" wire:click="enrollLoyalty" wire:loading.attr="disabled" wire:target="enrollLoyalty">Aplică pentru card</button>
                </div>
            @endif

            {{-- Intrările mele: ultimele 5 --}}
            <div class="pa-glass pa-pad pa-stack">
                <div class="pa-label" style="margin: 0">Intrările mele</div>
                @forelse ($entries as $e)
                    <div wire:key="ent-{{ $e->id }}">@include('livewire.participant._entry-row', ['e' => $e])</div>
                @empty
                    <div class="pa-soft" style="font-size: .9rem">Nicio intrare înregistrată pe contul tău încă.</div>
                @endforelse
                @if ($entriesMore)
                    <a href="{{ route('app.entries.all') }}" wire:navigate class="pa-link" style="text-align: center; padding-top: .25rem">Vezi tot</a>
                @endif
            </div>

            {{-- Consumațiile de la bar: ultimele 5 --}}
            <div class="pa-glass pa-pad pa-stack">
                <div class="pa-label" style="margin: 0">Consumații la bar</div>
                @forelse ($barSales as $s)
                    <div wire:key="bar-{{ $s->id }}">@include('livewire.participant._bar-row', ['s' => $s])</div>
                @empty
                    <div class="pa-soft" style="font-size: .9rem">Nicio consumație la bar pe contul tău încă.</div>
                @endforelse
                @if ($barMore)
                    <a href="{{ route('app.bar.all') }}" wire:navigate class="pa-link" style="text-align: center; padding-top: .25rem">Vezi tot</a>
                @endif
            </div>

            <div class="pa-glass pa-pad pa-stack">
                <div><div class="pa-label" style="margin: 0">Telefon</div><div style="font-weight: 800">{{ $me->phone }}</div></div>
            </div>
            <form action="{{ route('app.logout') }}" method="POST">
                @csrf
                <button type="submit" class="pa-btn pa-btn-ghost pa-btn-block">Deconectare</button>
            </form>
        </section>
    @endif
</div>
