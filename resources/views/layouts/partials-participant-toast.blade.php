{{-- DXA: adaugat (Aplicația participanților - runda 12). Mesaje de succes/eroare ca popup care dispare singur după câteva secunde.
     Sursa: flash-ul de sesiune „status” (după redirect) sau evenimentul de browser „toast” ({message, type: ok|err}),
     trimis din componente cu $this->dispatch('toast', message: '...', type: 'ok'). Mesajul rămâne și în atributul data-flash. --}}
@php $flash = session()->pull('status'); @endphp
<div x-data="{
        items: [],
        n: 0,
        add(d) {
            if (! d || ! d.message) return;
            const id = ++this.n;
            this.items.push({ id, message: d.message, type: d.type === 'err' ? 'err' : 'ok' });
            setTimeout(() => this.close(id), d.type === 'err' ? 6000 : 4000);
        },
        close(id) { this.items = this.items.filter((i) => i.id !== id); },
     }"
     x-init="$el.dataset.flash && add({ message: $el.dataset.flash, type: 'ok' })"
     @toast.window="add($event.detail)"
     data-flash="{{ $flash }}"
     class="pa-toasts" aria-live="polite" aria-atomic="false">
    <template x-for="t in items" :key="t.id">
        <div class="pa-toast" :class="t.type === 'err' ? 'pa-toast-err' : 'pa-toast-ok'" role="status" @click="close(t.id)"
             x-transition:enter="pa-toast-in" x-transition:leave="pa-toast-out">
            <span class="pa-toast-ico" aria-hidden="true">
                <svg x-show="t.type !== 'err'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                <svg x-show="t.type === 'err'" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M12 8v5M12 17h.01"/></svg>
            </span>
            <span class="pa-toast-msg" x-text="t.message"></span>
            <span class="pa-toast-bar" :style="'animation-duration:' + (t.type === 'err' ? 6 : 4) + 's'"></span>
        </div>
    </template>
</div>
