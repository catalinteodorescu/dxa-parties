<?php

namespace App\Livewire\Admin\Parties;

use App\Models\Admin;
use App\Models\Party;
use App\Services\ActivityLogger;
use App\Support\HandlesImageUploads;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class Form extends Component
{
    use HandlesImageUploads;
    use WithFileUploads;

    /** Numarul maxim de zile al unui festival (intervalul din Când). */
    public const MAX_FESTIVAL_DAYS = 31;

    public ?Party $party = null;

    // Identitate
    public string $name = '';
    public string $kind = 'basic';

    // Timp (simpla)
    public ?string $start_date = null;
    public ?string $start_time = null;
    public ?string $end_time = null;

    // Timp (festival): intervalul (start_date .. end_date) decide zilele din „Program pe zile"
    public ?string $end_date = null;

    public array $days = [];

    // Zile scoase din interval care aveau continut (program/ore/dresscode): se pastreaza aici ca sa
    // revina daca intervalul se extinde la loc (nu se pierd datele la o schimbare de moment a datei).
    public array $stashedDays = [];

    // Invitați (festival)
    public array $guests = [];
    public array $guestPhotos = [];

    // Stiluri muzică — ciclul de redare (ex. 3 bachata, 3 salsa, 2 kizomba, se reia)
    public array $music_styles = [];

    // Locatie
    public ?string $location_name = null;
    public ?string $location_address = null;
    public ?string $location_url = null;

    // Dresscode (simpla; la festival e pe zi)
    public ?string $dresscode = null;

    // Pret
    public bool $is_free = false;
    public array $ticket_types = [];   // [['name','price','discounts'=>[['label','price','until'],...]], ...]

    // Plata
    public array $payment_predefined = [];
    public array $payment_custom = [];

    // Contact (mai multe persoane)
    public array $contacts = [];       // [['admin_id','name','phone','note'], ...]

    // Extra
    public array $custom_fields = [];
    public array $links = [];

    // Plasare / stare
    public string $audience = 'all';
    public bool $in_carousel = false;
    public bool $is_active = true;
    public string $status = 'published';

    // Descriere
    public ?string $description = null;

    // Imagine
    public $image = null;
    public ?string $existingImage = null;
    public bool $removeImage = false;

    public function mount(?Party $party = null): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        foreach (array_keys(Party::PAYMENT_METHODS) as $key) {
            $this->payment_predefined[$key] = false;
        }

        if ($party && $party->exists) {
            $this->party = $party;

            $this->name = $party->name;
            $this->kind = $party->kind;
            $this->start_date = $party->start_date?->format('Y-m-d');
            $this->start_time = $party->start_time ? substr($party->start_time, 0, 5) : null;
            $this->end_time = $party->end_time ? substr($party->end_time, 0, 5) : null;
            $this->days = $party->days ?? [];
            $lastDay = $this->days ? (string) (end($this->days)['date'] ?? '') : '';
            $this->end_date = $party->end_date?->format('Y-m-d') ?? ($lastDay !== '' ? $lastDay : null);
            $this->guests = $party->guests ?? [];
            $this->music_styles = $party->music_styles ?? [];
            $this->location_name = $party->location_name;
            $this->location_address = $party->location_address;
            $this->location_url = $party->location_url;
            $this->dresscode = $party->dresscode;
            $this->is_free = $party->is_free;
            $this->custom_fields = $party->custom_fields ?? [];
            $this->links = $party->links ?? [];
            $this->audience = $party->audience;
            $this->in_carousel = $party->in_carousel;
            $this->is_active = $party->is_active;
            $this->status = $party->status;
            $this->description = $party->description;
            $this->existingImage = $party->image_path;

            // Tipuri de bilet (compat cu vechiul price/price_tiers).
            $this->ticket_types = $party->ticket_types ?? [];
            if (empty($this->ticket_types) && $party->price !== null) {
                $this->ticket_types = [[
                    'name' => '',
                    'price' => rtrim(rtrim((string) $party->price, '0'), '.'),
                    'discounts' => $party->price_tiers ?? [],
                ]];
            }
            if (empty($this->ticket_types)) {
                $this->ticket_types = [$this->emptyTicketType()];
            }

            // Reducerile vechi au doar data (fara ora): le aratam ca „pana la sfarsitul zilei"
            // ca sa apara in campul data+ora (datetime-local), fara sa le schimbam sensul.
            foreach ($this->ticket_types as $ti => $type) {
                foreach ($type['discounts'] ?? [] as $di => $d) {
                    $until = (string) ($d['until'] ?? '');
                    if ($until !== '' && mb_strlen($until) <= 10) {
                        $this->ticket_types[$ti]['discounts'][$di]['until'] = $until.'T23:59';
                    }
                }
            }

            // Contacte (compat cu vechile coloane single).
            $this->contacts = $party->contacts ?? [];
            if (empty($this->contacts) && ($party->contact_name || $party->contact_phone || $party->contact_admin_id)) {
                $this->contacts = [[
                    'admin_id' => $party->contact_admin_id ? (string) $party->contact_admin_id : null,
                    'name' => $party->contact_name,
                    'phone' => $party->contact_phone,
                    'note' => $party->contact_note,
                ]];
            }
            if (empty($this->contacts)) {
                $this->contacts = [$this->emptyContact()];
            }

            $known = array_keys(Party::PAYMENT_METHODS);
            foreach ($party->payment_methods ?? [] as $m) {
                if (in_array($m, $known, true)) {
                    $this->payment_predefined[$m] = true;
                } else {
                    $this->payment_custom[] = $m;
                }
            }
        } else {
            $this->start_date = now()->next(Carbon::SATURDAY)->format('Y-m-d');
            $this->start_time = '21:00';
            $this->end_time = '03:00';
            $this->ticket_types = [$this->emptyTicketType()];
            $this->contacts = [$this->emptyContact()];
        }

        // Ciclu muzical: dacă nu s-a completat încă (petrecere nouă sau una veche,
        // dinainte de acest câmp), precompletăm cu ciclul implicit al școlii.
        if (empty($this->music_styles)) {
            $this->music_styles = $this->defaultMusicStyles();
        }
    }

    private function emptyTicketType(): array
    {
        return ['name' => '', 'price' => '', 'discounts' => []];
    }

    private function emptyContact(): array
    {
        return ['admin_id' => null, 'name' => '', 'phone' => '', 'note' => ''];
    }

    private function emptyMusicStyle(): array
    {
        return ['style' => '', 'frequency' => ''];
    }

    /** Ciclul muzical implicit: 3 bachata, 3 salsa, 2 kizomba, apoi se reia. */
    private function defaultMusicStyles(): array
    {
        return [
            ['style' => 'Bachata', 'frequency' => 3],
            ['style' => 'Salsa', 'frequency' => 3],
            ['style' => 'Kizomba', 'frequency' => 2],
        ];
    }

    /**
     * La comutarea tipului, transfera datele de timp intre cele doua moduri,
     * ca sa nu se piarda ce a completat utilizatorul.
     */
    public function updatedKind($value): void
    {
        if ($value === 'festival') {
            if (empty($this->days)) {
                $this->start_date = $this->start_date ?: now()->format('Y-m-d');
                // Un festival are cel putin doua zile: propunem ziua urmatoare ca sfarsit (se poate schimba).
                if (! $this->end_date || $this->end_date < $this->start_date) {
                    $this->end_date = Carbon::parse($this->start_date)->addDay()->format('Y-m-d');
                }

                $this->syncDaysWithRange();

                // Orele si dresscode-ul de la petrecerea simpla trec pe prima zi.
                if (! empty($this->days)) {
                    $this->days[0]['start_time'] = $this->start_time ?: '';
                    $this->days[0]['end_time'] = $this->end_time ?: '';
                    $this->days[0]['dresscode'] = $this->dresscode ?: '';
                }
            }
        } else { // basic — preia din prima zi
            if (! empty($this->days)) {
                $first = $this->days[0];
                $this->start_date = $first['date'] ?? $this->start_date;
                $this->start_time = ($first['start_time'] ?? '') ?: $this->start_time;
                $this->end_time = ($first['end_time'] ?? '') ?: $this->end_time;
                if (empty($this->dresscode) && ! empty($first['dresscode'])) {
                    $this->dresscode = $first['dresscode'];
                }
            }
        }
    }

    /** Data de inceput s-a schimbat: la festival, sfarsitul nu poate ramane inainte de inceput; apoi resincronizam zilele. */
    public function updatedStartDate($value): void
    {
        if ($this->kind !== 'festival') {
            return;
        }

        if ($value && (! $this->end_date || $this->end_date < $value)) {
            $this->end_date = $value;
        }

        $this->syncDaysWithRange();
    }

    public function updatedEndDate(): void
    {
        if ($this->kind === 'festival') {
            $this->syncDaysWithRange();
        }
    }

    /**
     * „Program pe zile" = zilele dintre start_date si end_date (inclusiv). Zilele care exista deja pastreaza
     * ce s-a completat; zilele noi apar goale; cele scoase din interval, daca aveau continut, se pastreaza
     * in $stashedDays si revin daca intervalul se extinde la loc. Un interval invalid (sfarsit inainte de
     * inceput) sau prea lung (> 31 de zile) nu modifica nimic - il semnaleaza validarea la salvare.
     */
    private function syncDaysWithRange(): void
    {
        if ($this->kind !== 'festival' || ! $this->start_date) {
            return;
        }

        $start = Carbon::parse($this->start_date)->startOfDay();
        $end = Carbon::parse($this->end_date ?: $this->start_date)->startOfDay();

        if ($end->lessThan($start) || (int) $start->diffInDays($end) > self::MAX_FESTIVAL_DAYS - 1) {
            return;
        }

        $current = [];
        foreach ($this->days as $d) {
            if (! empty($d['date'])) {
                $current[$d['date']] = $d;
            }
        }

        $new = [];
        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addDay()) {
            $key = $cursor->format('Y-m-d');
            $new[] = $current[$key] ?? $this->stashedDays[$key] ?? $this->blankDay($key);
            unset($this->stashedDays[$key], $current[$key]);
        }

        // Ce a ramas in $current e in afara intervalului.
        foreach ($current as $key => $day) {
            if ($this->dayHasContent($day)) {
                $this->stashedDays[$key] = $day;
            }
        }

        $this->days = $new;
    }

    private function blankDay(string $date): array
    {
        return ['date' => $date, 'start_time' => '', 'end_time' => '', 'dresscode' => '', 'program' => []];
    }

    private function dayHasContent(array $day): bool
    {
        return ! empty($day['start_time']) || ! empty($day['end_time']) || trim((string) ($day['dresscode'] ?? '')) !== ''
            || ! empty($day['program']);
    }

    protected function rules(): array
    {
        $styles = implode(',', array_keys(Party::GUEST_STYLES));

        return [
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', 'in:basic,festival'],

            'start_date' => ['required', 'date'],
            'end_date' => [
                'required_if:kind,festival', 'nullable', 'date', 'after_or_equal:start_date',
                function ($attribute, $value, $fail) {
                    if ($this->kind === 'festival' && $value && $this->start_date
                        && (int) Carbon::parse($this->start_date)->diffInDays(Carbon::parse($value)) > self::MAX_FESTIVAL_DAYS - 1) {
                        $fail('Un festival poate avea cel mult '.self::MAX_FESTIVAL_DAYS.' de zile.');
                    }
                },
            ],
            'start_time' => ['required_if:kind,basic', 'nullable', 'date_format:H:i'],
            'end_time' => ['required_if:kind,basic', 'nullable', 'date_format:H:i'],
            'dresscode' => ['nullable', 'string', 'max:200'],

            'days' => ['required_if:kind,festival', 'array'],
            'days.*.date' => ['required_if:kind,festival', 'nullable', 'date'],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.end_time' => ['nullable', 'date_format:H:i'],
            'days.*.dresscode' => ['nullable', 'string', 'max:200'],
            'days.*.program' => ['nullable', 'array'],
            'days.*.program.*.start' => ['nullable', 'date_format:H:i'],
            'days.*.program.*.end' => ['nullable', 'date_format:H:i'],
            'days.*.program.*.title' => ['nullable', 'string', 'max:150'],
            'days.*.program.*.type' => ['nullable', 'in:workshop,social,show,other'],
            'days.*.program.*.guest' => ['nullable', 'string', 'max:120'],
            'days.*.program.*.room' => ['nullable', 'string', 'max:80'],

            'guests' => ['nullable', 'array'],
            'guests.*.name' => ['nullable', 'string', 'max:120'],
            'guests.*.country' => ['nullable', 'string', 'max:60'],
            'guests.*.style' => ['nullable', 'in:'.$styles],
            'guests.*.style_other' => ['nullable', 'string', 'max:60'],
            'guests.*.url' => ['nullable', 'url', 'max:2048'],
            'guestPhotos.*' => ['nullable', 'image', 'max:8192'],

            'music_styles' => ['array'],
            'music_styles.*.style' => ['nullable', 'string', 'max:60'],
            'music_styles.*.frequency' => ['nullable', 'integer', 'min:1', 'max:50'],

            'location_name' => ['nullable', 'string', 'max:150'],
            'location_address' => ['nullable', 'string', 'max:255'],
            'location_url' => ['nullable', 'url', 'max:2048'],

            'is_free' => ['boolean'],
            'ticket_types' => ['array'],
            'ticket_types.*.name' => ['nullable', 'string', 'max:80'],
            'ticket_types.*.price' => ['nullable', 'numeric', 'min:0'],
            'ticket_types.*.discounts' => ['nullable', 'array'],
            'ticket_types.*.discounts.*.label' => ['nullable', 'string', 'max:60'],
            'ticket_types.*.discounts.*.price' => ['nullable', 'numeric', 'min:0'],
            'ticket_types.*.discounts.*.until' => ['nullable', 'date'],

            'payment_custom.*' => ['nullable', 'string', 'max:60'],

            'contacts' => ['array'],
            'contacts.*.admin_id' => ['nullable', 'exists:admins,id'],
            'contacts.*.name' => ['nullable', 'string', 'max:120'],
            'contacts.*.phone' => ['nullable', 'string', 'max:40'],
            'contacts.*.note' => ['nullable', 'string', 'max:200'],

            'custom_fields' => ['array'],
            'custom_fields.*.label' => ['nullable', 'string', 'max:60'],
            'custom_fields.*.value' => ['nullable', 'string', 'max:200'],
            'links' => ['array'],
            'links.*.label' => ['nullable', 'string', 'max:60'],
            'links.*.url' => ['nullable', 'url', 'max:2048'],

            'audience' => ['required', 'in:all,auth'],
            'in_carousel' => ['boolean'],
            'is_active' => ['boolean'],
            'status' => ['required', 'in:draft,published'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'max:8192'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Denumirea este obligatorie.',
            'start_date.required' => 'Alege data petrecerii (la festival: data de început).',
            'end_date.required_if' => 'Alege data de sfârșit a festivalului.',
            'end_date.after_or_equal' => 'Data de sfârșit nu poate fi înainte de data de început.',
            'start_time.required_if' => 'Ora de început este obligatorie.',
            'end_time.required_if' => 'Ora de sfârșit este obligatorie.',
            'days.required_if' => 'Adaugă cel puțin o zi pentru festival.',
            'days.*.date.required_if' => 'Fiecare zi are nevoie de o dată.',
            'location_url.url' => 'Linkul locației nu pare valid (începe cu https://).',
            'ticket_types.*.price.numeric' => 'Prețul biletului trebuie să fie un număr.',
            'music_styles.*.frequency.integer' => 'Numărul de melodii trebuie să fie întreg.',
            'music_styles.*.frequency.min' => 'Numărul de melodii trebuie să fie cel puțin 1.',
            'ticket_types.*.discounts.*.price.numeric' => 'Prețul reducerii trebuie să fie un număr.',
            'links.*.url.url' => 'Unul dintre linkuri nu pare valid (începe cu https://).',
            'guests.*.url.url' => 'Linkul unui invitat nu pare valid (începe cu https://).',
            'image.image' => 'Fișierul trebuie să fie o imagine.',
            'image.max' => 'Imaginea nu poate depăși 8 MB.',
        ];
    }

    // ---- Repeatere: comune ----------------------------------------------

    public function addCustomField(): void { $this->custom_fields[] = ['label' => '', 'value' => '']; }
    public function removeCustomField(int $i): void { unset($this->custom_fields[$i]); $this->custom_fields = array_values($this->custom_fields); }

    public function addLink(): void { $this->links[] = ['label' => '', 'url' => '']; }
    public function removeLink(int $i): void { unset($this->links[$i]); $this->links = array_values($this->links); }

    public function addPaymentCustom(): void { $this->payment_custom[] = ''; }
    public function removePaymentCustom(int $i): void { unset($this->payment_custom[$i]); $this->payment_custom = array_values($this->payment_custom); }

    // ---- Repeatere: bilete ----------------------------------------------

    public function addTicketType(): void { $this->ticket_types[] = $this->emptyTicketType(); }
    public function removeTicketType(int $i): void { unset($this->ticket_types[$i]); $this->ticket_types = array_values($this->ticket_types); }

    public function addTicketDiscount(int $ti): void
    {
        $this->ticket_types[$ti]['discounts'][] = ['label' => '', 'price' => '', 'until' => ''];
    }

    public function removeTicketDiscount(int $ti, int $di): void
    {
        unset($this->ticket_types[$ti]['discounts'][$di]);
        $this->ticket_types[$ti]['discounts'] = array_values($this->ticket_types[$ti]['discounts']);
    }

    // ---- Repeatere: contacte --------------------------------------------

    public function addContact(): void { $this->contacts[] = $this->emptyContact(); }
    public function removeContact(int $i): void { unset($this->contacts[$i]); $this->contacts = array_values($this->contacts); }

    // ---- Repeatere: festival --------------------------------------------

    public function addProgramItem(int $dayIndex): void
    {
        $this->days[$dayIndex]['program'][] = ['start' => '', 'end' => '', 'title' => '', 'type' => 'workshop', 'guest' => '', 'room' => ''];
    }

    public function removeProgramItem(int $dayIndex, int $itemIndex): void
    {
        unset($this->days[$dayIndex]['program'][$itemIndex]);
        $this->days[$dayIndex]['program'] = array_values($this->days[$dayIndex]['program']);
    }

    public function addGuest(): void
    {
        $this->guests[] = ['name' => '', 'country' => '', 'style' => 'bachata', 'style_other' => '', 'photo_path' => null, 'url' => ''];
    }

    public function removeGuest(int $i): void
    {
        unset($this->guests[$i]);
        $this->guests = array_values($this->guests);

        $new = [];
        foreach ($this->guestPhotos as $k => $file) {
            if ($k === $i) {
                continue;
            }
            $new[$k > $i ? $k - 1 : $k] = $file;
        }
        $this->guestPhotos = $new;
    }

    // ---- Repeatere: stiluri muzică ---------------------------------------

    public function addMusicStyle(): void { $this->music_styles[] = $this->emptyMusicStyle(); }
    public function removeMusicStyle(int $i): void { unset($this->music_styles[$i]); $this->music_styles = array_values($this->music_styles); }

    public function clearGuestPhoto(int $i): void
    {
        unset($this->guestPhotos[$i]);
        if (isset($this->guests[$i])) {
            $this->guests[$i]['photo_path'] = null;
        }
    }

    // ---- Ajutoare -------------------------------------------------------

    public function clearImage(): void { $this->reset('image'); $this->removeImage = true; }

    public function fillDxaVenue(): void { $this->location_name = 'Dance Xplosion Academy'; }

    private function paymentMethodsList(): array
    {
        $known = array_keys(array_filter($this->payment_predefined));
        $custom = array_values(array_filter(array_map('trim', $this->payment_custom), fn ($v) => $v !== ''));

        return array_values(array_unique(array_merge($known, $custom)));
    }

    private function paymentLabels(): array
    {
        return array_map(fn ($m) => Party::PAYMENT_METHODS[$m] ?? $m, $this->paymentMethodsList());
    }

    private function fmtPrice($value): string
    {
        $v = (float) $value;
        $n = $v == floor($v) ? number_format($v, 0, ',', '.') : number_format($v, 2, ',', '.');

        return $n.' lei';
    }

    private function cleanTicketTypes(): array
    {
        $types = [];

        foreach ($this->ticket_types as $t) {
            $name = trim($t['name'] ?? '');
            $priceSet = isset($t['price']) && $t['price'] !== '' && is_numeric($t['price']);
            if ($name === '' && ! $priceSet) {
                continue;
            }

            $discounts = [];
            foreach ($t['discounts'] ?? [] as $d) {
                if (! isset($d['price']) || $d['price'] === '' || ! is_numeric($d['price'])) {
                    continue;
                }
                $discounts[] = [
                    'label' => trim($d['label'] ?? '') ?: null,
                    'price' => (float) $d['price'],
                    'until' => ($d['until'] ?? '') ?: null,
                ];
            }

            $types[] = [
                'name' => $name ?: null,
                'price' => $priceSet ? (float) $t['price'] : null,
                'discounts' => $discounts,
            ];
        }

        return $types;
    }

    private function cleanContacts(): array
    {
        $contacts = [];

        foreach ($this->contacts as $c) {
            $adminId = ($c['admin_id'] ?? null) ?: null;

            if ($adminId) {
                $admin = Admin::find($adminId);
                if ($admin) {
                    $contacts[] = [
                        'admin_id' => (int) $adminId,
                        'name' => $admin->name,
                        'phone' => $admin->phone,
                        'note' => trim($c['note'] ?? '') ?: null,
                    ];

                    continue;
                }
            }

            $name = trim($c['name'] ?? '');
            $phone = trim($c['phone'] ?? '');
            $note = trim($c['note'] ?? '');
            if ($name === '' && $phone === '' && $note === '') {
                continue;
            }

            $contacts[] = ['admin_id' => null, 'name' => $name ?: null, 'phone' => $phone ?: null, 'note' => $note ?: null];
        }

        return $contacts;
    }

    private function cleanLinks(): array
    {
        $links = array_filter($this->links, fn ($l) => ! empty(trim($l['url'] ?? '')));

        return array_values(array_map(fn ($l) => [
            'label' => trim($l['label'] ?? '') ?: null,
            'url' => trim($l['url']),
        ], $links));
    }

    private function cleanCustomFields(): array
    {
        $fields = array_filter($this->custom_fields, fn ($c) => trim($c['label'] ?? '') !== '' || trim($c['value'] ?? '') !== '');

        return array_values(array_map(fn ($c) => [
            'label' => trim($c['label'] ?? ''),
            'value' => trim($c['value'] ?? ''),
        ], $fields));
    }

    private function cleanMusicStyles(): array
    {
        $styles = [];

        foreach ($this->music_styles as $s) {
            $style = trim($s['style'] ?? '');
            $freq = $s['frequency'] ?? '';
            if ($style === '' || $freq === '' || ! is_numeric($freq) || (int) $freq < 1) {
                continue;
            }

            $styles[] = ['style' => $style, 'frequency' => (int) $freq];
        }

        return $styles;
    }

    private function cleanDays(): array
    {
        $days = [];

        foreach ($this->days as $d) {
            $date = $d['date'] ?? null;
            if (! $date) {
                continue;
            }

            $program = [];
            foreach ($d['program'] ?? [] as $it) {
                $title = trim($it['title'] ?? '');
                $hasTime = ! empty($it['start']) || ! empty($it['end']);
                if ($title === '' && ! $hasTime) {
                    continue;
                }
                $program[] = [
                    'start' => ($it['start'] ?? '') ?: null,
                    'end' => ($it['end'] ?? '') ?: null,
                    'title' => $title ?: null,
                    'type' => ($it['type'] ?? '') ?: null,
                    'guest' => trim($it['guest'] ?? '') ?: null,
                    'room' => trim($it['room'] ?? '') ?: null,
                ];
            }

            $days[] = [
                'date' => $date,
                'start_time' => ($d['start_time'] ?? '') ?: null,
                'end_time' => ($d['end_time'] ?? '') ?: null,
                'dresscode' => trim($d['dresscode'] ?? '') ?: null,
                'program' => $program,
            ];
        }

        usort($days, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $days;
    }

    private function cleanGuests(): array
    {
        $oldPhotos = collect($this->party?->guests ?? [])->pluck('photo_path')->filter()->all();

        $guests = [];
        foreach ($this->guests as $i => $g) {
            $name = trim($g['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $photo = $g['photo_path'] ?? null;
            if (isset($this->guestPhotos[$i]) && $this->guestPhotos[$i]) {
                $photo = $this->storeUploadedImage($this->guestPhotos[$i], 'parties/guests', 1000);
            }

            $style = ($g['style'] ?? '') ?: null;

            $guests[] = [
                'name' => $name,
                'country' => trim($g['country'] ?? '') ?: null,
                'style' => $style,
                'style_other' => $style === 'other' ? (trim($g['style_other'] ?? '') ?: null) : null,
                'photo_path' => $photo,
                'url' => trim($g['url'] ?? '') ?: null,
            ];
        }

        $newPhotos = collect($guests)->pluck('photo_path')->filter()->all();
        foreach (array_diff($oldPhotos, $newPhotos) as $gone) {
            Storage::disk('public')->delete($gone);
        }

        return $guests;
    }

    // ---- Generare descriere ---------------------------------------------

    public function generateDescription(): void
    {
        $zile = ['duminică', 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă'];
        $lines = [];

        $title = $this->name ?: 'Petrecere Dance Xplosion Academy';

        // Când
        if ($this->kind === 'festival') {
            $days = $this->cleanDays();
            if ($days) {
                $d1 = Carbon::parse($days[0]['date']);
                $d2 = Carbon::parse($days[count($days) - 1]['date']);
                $when = count($days) > 1
                    ? $d1->format('d.m.Y').' – '.$d2->format('d.m.Y')
                    : $d1->format('d.m.Y');
                $lines[] = '🗓️ Când: '.$when;
            }
        } elseif ($this->start_date) {
            $d = Carbon::parse($this->start_date);
            $when = $zile[$d->dayOfWeek].', '.$d->format('d.m.Y');
            if ($this->start_time) {
                $when .= ', '.$this->start_time;
                if ($this->end_time) {
                    $when .= '–'.$this->end_time;
                }
            }
            $lines[] = '🗓️ Când: '.$when;
        }

        // Locație
        if ($this->location_name) {
            $loc = $this->location_name;
            if ($this->location_address) {
                $loc .= ' ('.$this->location_address.')';
            }
            $lines[] = '📍 Locație: '.$loc;
        }

        // Dresscode (la simpla)
        if ($this->kind !== 'festival' && $this->dresscode) {
            $lines[] = '👗 Dresscode: '.$this->dresscode;
        }

        // Invitați (la festival)
        if ($this->kind === 'festival') {
            $guests = array_values(array_filter(
                array_map(fn ($g) => trim($g['name'] ?? ''), $this->guests),
                fn ($n) => $n !== '',
            ));
            if ($guests) {
                $lines[] = '🎤 Invitați: '.implode(', ', $guests);
            }
        }

        // Prețuri
        if ($this->is_free) {
            $lines[] = '💰 Intrare: gratuită';
        } else {
            $typeTexts = [];
            foreach ($this->cleanTicketTypes() as $t) {
                if ($t['price'] === null) {
                    continue;
                }
                $txt = ($t['name'] ?: 'Intrare').' '.$this->fmtPrice($t['price']);
                $disc = [];
                foreach ($t['discounts'] as $d) {
                    $dt = ($d['label'] ?: 'ofertă').' '.$this->fmtPrice($d['price']);
                    if ($d['until']) {
                        $dt .= ' până la '.\App\Models\Party::formatUntil($d['until']);
                    }
                    $disc[] = $dt;
                }
                if ($disc) {
                    $txt .= ' ('.implode('; ', $disc).')';
                }
                $typeTexts[] = $txt;
            }
            if ($typeTexts) {
                $lines[] = '💰 Prețuri: '.implode(' · ', $typeTexts);
            }
        }

        // Plată
        $pm = $this->paymentLabels();
        if ($pm) {
            $lines[] = '💳 Plată: '.implode(', ', $pm);
        }

        // Contact
        $contactTexts = [];
        foreach ($this->cleanContacts() as $c) {
            $t = trim(($c['name'] ?? '').' '.($c['phone'] ? '– '.$c['phone'] : ''));
            $t = rtrim($t, ' –');
            if ($t !== '') {
                $contactTexts[] = $t;
            }
        }
        if ($contactTexts) {
            $lines[] = '☎️ Contact: '.implode('; ', $contactTexts);
        }

        $this->description = $title."\n\n".implode("\n", $lines);
    }

    // ---- Salvare --------------------------------------------------------

    public function save(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        // Normalizeaza admin_id gol ('' din select) la null, ca sa treaca de 'exists'.
        foreach ($this->contacts as $i => $c) {
            if (($c['admin_id'] ?? null) === '') {
                $this->contacts[$i]['admin_id'] = null;
            }
        }

        try {
            $data = $this->validate();
        } catch (ValidationException $e) {
            $this->dispatch('scroll-to-error');

            throw $e;
        }

        $isEditing = $this->party && $this->party->exists;

        // Imagine principala.
        $imagePath = $this->existingImage;
        if ($this->removeImage && $imagePath) {
            Storage::disk('public')->delete($imagePath);
            $imagePath = null;
        }
        if ($this->image) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $this->storeUploadedImage($this->image, 'parties');
        }

        $contacts = $this->cleanContacts();
        $first = $contacts[0] ?? null;

        $payload = [
            'name' => $data['name'],
            'kind' => $this->kind,
            'image_path' => $imagePath,

            'location_name' => $this->location_name ?: null,
            'location_address' => $this->location_address ?: null,
            'location_url' => $this->location_url ?: null,

            'is_free' => $this->is_free,
            'ticket_types' => $this->is_free ? [] : $this->cleanTicketTypes(),
            'price' => null,          // deprecat
            'price_tiers' => [],      // deprecat

            'payment_methods' => $this->paymentMethodsList(),

            'contacts' => $contacts,
            // Compat: primul contact in coloanele vechi.
            'contact_admin_id' => $first['admin_id'] ?? null,
            'contact_name' => $first['name'] ?? null,
            'contact_phone' => $first['phone'] ?? null,
            'contact_note' => $first['note'] ?? null,

            'custom_fields' => $this->cleanCustomFields(),
            'links' => $this->cleanLinks(),
            'music_styles' => $this->cleanMusicStyles(),

            'audience' => $this->audience,
            'in_carousel' => $this->in_carousel,
            'is_active' => $this->is_active,
            'status' => $this->status,

            'description' => $this->description ?: null,
        ];

        if ($this->kind === 'festival') {
            $days = $this->cleanDays();

            if (empty($days)) {
                $this->addError('days', 'Adaugă cel puțin o zi cu dată pentru festival.');
                $this->dispatch('scroll-to-error');

                return;
            }

            $payload += [
                'start_date' => $days[0]['date'],
                'end_date' => $days[count($days) - 1]['date'],
                'start_time' => null,
                'end_time' => null,
                'dresscode' => null,
                'days' => $days,
                'guests' => $this->cleanGuests(),
            ];
        } else {
            $payload += [
                'start_date' => $this->start_date,
                'end_date' => null,
                'start_time' => $this->start_time,
                'end_time' => $this->end_time,
                'dresscode' => $this->dresscode ?: null,
                'days' => [],
                'guests' => [],
            ];
        }

        if ($isEditing) {
            $this->party->update($payload);
            ActivityLogger::log('party.updated', 'A modificat petrecerea „'.$this->party->name.'".');
            session()->flash('status', 'Petrecerea a fost actualizată.');
        } else {
            $payload['created_by'] = Auth::guard('admin')->id();
            $party = Party::create($payload);
            ActivityLogger::log('party.created', 'A creat petrecerea „'.$party->name.'".');
            session()->flash('status', 'Petrecerea a fost creată.');
        }

        $this->redirectRoute('admin.parties.index', navigate: true);
    }

    public function render()
    {
        $admins = Admin::orderBy('name')->get();

        $contactOptions = ['' => 'Altul (completez manual)'];
        foreach ($admins as $a) {
            $contactOptions[(string) $a->id] = ($a->name ?: 'Fără nume').' — '.$a->phone;
        }

        $contactAdmins = $admins->mapWithKeys(fn ($a) => [
            (string) $a->id => ['name' => $a->name, 'phone' => $a->phone],
        ]);

        return view('livewire.admin.parties.form', [
            'contactOptions' => $contactOptions,
            'contactAdmins' => $contactAdmins,
        ]);
    }
}
