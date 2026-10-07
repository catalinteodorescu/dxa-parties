{{-- DXA: adaugat (runda 35). Anunțul întreg. Variabilă: $announcement. --}}
<div>
    <div class="pa-section" style="margin-top: .5rem">
        <a href="{{ route('app.announcements') }}" wire:navigate class="pa-link" style="font-size: .9rem">← Anunțuri</a>
    </div>

    <section class="pa-section" style="margin-top: .75rem">
        <div class="pa-glass pa-stack" style="overflow: hidden; gap: 0">
            @if ($announcement->imageUrl())
                <img src="{{ $announcement->imageUrl() }}" alt="" style="width: 100%; max-height: 16rem; object-fit: cover; display: block">
            @endif
            <div class="pa-pad pa-stack" style="gap: .6rem">
                <h1 class="pa-h1" style="font-size: 1.5rem">{{ $announcement->title }}</h1>
                @include('livewire.participant._announcement-date', ['announcement' => $announcement])
                @if ($announcement->body)
                    <div class="pa-prose">{{ $announcement->body }}</div>
                @endif
                @php $cta = \App\Support\AnnouncementLink::resolve($announcement, auth('participant')->check()); @endphp
                @if ($cta)
                    <div>
                        @if ($cta->external)
                            <a href="{{ $cta->href }}" target="_blank" rel="noopener" class="pa-btn pa-btn-sm" data-track-action="a:{{ $announcement->id }}" data-announcement-cta>{{ $cta->label }}</a>
                        @else
                            <a href="{{ $cta->href }}" wire:navigate class="pa-btn pa-btn-sm" data-track-action="a:{{ $announcement->id }}" data-announcement-cta>{{ $cta->label }}</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
