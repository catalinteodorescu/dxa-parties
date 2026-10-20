<?php

namespace App\Livewire\Concerns;

use App\Services\Push\Inbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Runda 91. Pagina „Notificări” (participant, Recepție, Bar): lista cu cele mai noi primele, încărcare treptată, „citit” la atingere
 * (apoi deschide adresa notificării, doar dacă e din aplicație) și „Marchează toate ca citite”. Componenta dă `inboxQuery()` și `fallbackUrl()`.
 */
trait ListsNotifications
{
    public int $limit = 20;

    abstract protected function inboxQuery(): Builder;

    abstract protected function fallbackUrl(): string;

    public function more(): void
    {
        $this->limit += 20;
    }

    public function open(int $id): void
    {
        $n = Inbox::markRead($this->inboxQuery(), $id);
        if (! $n) {
            return;
        }

        $this->redirect(Inbox::safeUrl($n->url, $this->fallbackUrl()), navigate: true);
    }

    public function markAll(): void
    {
        Inbox::markAllRead($this->inboxQuery());
    }

    /** @return array{items: Collection, groups: Collection, hasMore: bool, unread: int} */
    protected function inboxData(): array
    {
        $rows = $this->inboxQuery()->orderByDesc('id')->limit($this->limit + 1)->get();

        return [
            'items' => $rows->take($this->limit),
            'groups' => $rows->take($this->limit)->groupBy(fn ($n) => Inbox::dayLabel($n)),
            'hasMore' => $rows->count() > $this->limit,
            'unread' => Inbox::unread($this->inboxQuery()),
        ];
    }
}
