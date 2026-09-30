{{-- DXA: adaugat (Aplicația participanților). Înregistrează service worker-ul de la rădăcină (scope „/”); el ignoră /admin, /receptie și /bar (vezi participant/sw.blade.php). --}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('{{ route('app.sw', [], false) }}', { scope: '/' }).catch(() => {});
        });
    }
</script>
