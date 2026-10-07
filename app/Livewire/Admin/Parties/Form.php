<?php

namespace App\Livewire\Admin\Parties;

use App\Models\Admin;
use App\Models\Party;
use App\Models\PartyDiscountCode;
use App\Models\Promoter;
use App\Services\ActivityLogger;
use App\Services\DiscountCodes;
use App\Services\LoyaltyLedger;
use App\Support\Branding;
use App\Support\HandlesImageUploads;
use App\Support\PartyDescriptionTexts;
use App\Support\PaymentMethods;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    // Plata: cheile metodelor alese (din Setari > Metode de plata; lista goala = toate cele active)
    public array $payment_methods = [];

    // DXA: adaugat (Card de fidelitate). Acordă ștampile la intrare ȘI acceptă plata „Beneficiu" (bonusul de
    // fidelitate); vezi App\Services\LoyaltyLedger::partyEligible().
    public bool $loyalty_eligible = true;

    // DXA: adaugat (runda 13). Vânzare bilete și capacitate (vezi migrarea 2024_07_25). Câmpurile numerice sunt șiruri (input-uri goale = nelimitat).
    public bool $online_sales = true;

    public string $max_tickets_per_order = '';

    public string $tickets_for_sale = '';

    public string $max_participants = '';

    // Contact (mai multe persoane)
    public array $contacts = [];       // [['admin_id','name','phone','note'], ...]

    /** DXA: adaugat (Coduri de reducere). Vezi emptyDiscountCode() pentru câmpuri. */
    public array $discount_codes = [];

    // Extra
    public array $custom_fields = [];

    public array $links = [];

    // Plasare / stare
    public string $audience = 'all';

    public bool $in_carousel = true;

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
                    foreach (['until', 'enter_until'] as $key) {
                        $v = (string) ($d[$key] ?? '');
                        $this->ticket_types[$ti]['discounts'][$di][$key] = $v !== '' && mb_strlen($v) <= 10 ? $v.'T23:59' : $v;
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

            $this->payment_methods = array_values(array_filter($party->payment_methods ?? [], 'is_string'));
            $this->loyalty_eligible = (bool) $party->loyalty_eligible;
            $this->online_sales = (bool) $party->online_sales;
            $this->max_tickets_per_order = $party->max_tickets_per_order !== null ? (string) $party->max_tickets_per_order : '';
            $this->tickets_for_sale = $party->tickets_for_sale !== null ? (string) $party->tickets_for_sale : '';
            $this->max_participants = $party->max_participants !== null ? (string) $party->max_participants : '';

            $this->discount_codes = $party->discountCodes->map(fn (PartyDiscountCode $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'promoter_id' => $c->promoter_id ? (string) $c->promoter_id : '',
                'type' => $c->type,
                'value' => $c->value !== null ? rtrim(rtrim((string) $c->value, '0'), '.') : '',
                'tier_label' => (string) $c->tier_label,
                'ticket_types' => array_values((array) $c->ticket_types),
                'valid_from' => $c->valid_from?->format('Y-m-d\TH:i') ?? '',
                'valid_until' => $c->valid_until?->format('Y-m-d\TH:i') ?? '',
                'max_uses' => $c->max_uses !== null ? (string) $c->max_uses : '',
                'max_uses_per_participant' => $c->max_uses_per_participant !== null ? (string) $c->max_uses_per_participant : '',
                'is_active' => (bool) $c->is_active,
                'note' => (string) $c->note,
            ])->all();
        } else {
            $this->start_date = now()->next(Carbon::SATURDAY)->format('Y-m-d');
            $this->start_time = '21:00';
            $this->end_time = '03:00';
            $this->ticket_types = [$this->emptyTicketType()];
            $this->contacts = [$this->emptyContact()];
            $this->fillSchoolVenue();
            $this->payment_methods = array_keys(PaymentMethods::enabled()); // precompletat cu metodele active din Setari
        }

        // Ciclu muzical: dacă nu s-a completat încă (petrecere nouă sau una veche,
        // dinainte de acest câmp), precompletăm cu ciclul implicit al școlii.
        if (empty($this->music_styles)) {
            $this->music_styles = $this->defaultMusicStyles();
        }
    }

    private function emptyTicketType(): array
    {
        return ['name' => '', 'price' => '', 'limit' => '', 'discounts' => [], 'qty_tiers' => [], 'combos' => []];
    }

    private function emptyDiscountCode(): array
    {
        return [
            'id' => null, 'code' => '', 'promoter_id' => '', 'type' => 'percent', 'value' => '', 'tier_label' => '',
            'ticket_types' => [], 'valid_from' => '', 'valid_until' => '', 'max_uses' => '',
            'max_uses_per_participant' => '1', 'is_active' => true, 'note' => '',
        ];
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
            'ticket_types.*.discounts.*.enter_until' => ['nullable', 'date'],
            'ticket_types.*.limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'ticket_types.*.qty_tiers' => ['nullable', 'array'],
            'ticket_types.*.qty_tiers.*.label' => ['nullable', 'string', 'max:60'],
            'ticket_types.*.qty_tiers.*.price' => ['nullable', 'numeric', 'min:0'],
            'ticket_types.*.qty_tiers.*.first' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'ticket_types.*.combos' => ['nullable', 'array'],
            'ticket_types.*.combos.*.buy' => ['nullable', 'integer', 'min:1', 'max:50'],
            'ticket_types.*.combos.*.free' => ['nullable', 'integer', 'min:1', 'max:50'],

            'payment_methods' => ['array'],
            'payment_methods.*' => ['string', 'max:60'],
            'loyalty_eligible' => ['boolean'],
            'online_sales' => ['boolean'],
            'max_tickets_per_order' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'tickets_for_sale' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:100000'],

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
            'ticket_types.*.limit.integer' => 'Limita de bilete trebuie să fie un număr întreg.',
            'ticket_types.*.limit.min' => 'Limita de bilete trebuie să fie cel puțin 1.',
            'ticket_types.*.qty_tiers.*.first.integer' => 'Numărul de bilete al treptei trebuie să fie un număr întreg.',
            'ticket_types.*.qty_tiers.*.first.min' => 'Numărul de bilete al treptei trebuie să fie cel puțin 1.',
            'ticket_types.*.combos.*.buy.integer' => 'La combo, numărul de bilete plătite trebuie să fie un număr întreg.',
            'ticket_types.*.combos.*.buy.min' => 'La combo, trebuie plătit cel puțin un bilet.',
            'ticket_types.*.combos.*.free.integer' => 'La combo, numărul de bilete gratuite trebuie să fie un număr întreg.',
            'ticket_types.*.combos.*.free.min' => 'La combo, trebuie oferit cel puțin un bilet.',
            'ticket_types.*.qty_tiers.*.price.numeric' => 'Prețul treptei trebuie să fie un număr.',
            'max_tickets_per_order.integer' => 'Biletele per comandă trebuie să fie un număr întreg.',
            'max_tickets_per_order.min' => 'Biletele per comandă: cel puțin 1 (sau lasă gol = oricâte).',
            'tickets_for_sale.integer' => 'Numărul de bilete la vânzare trebuie să fie un număr întreg.',
            'tickets_for_sale.min' => 'Numărul de bilete la vânzare trebuie să fie cel puțin 1 (sau lasă gol = nelimitat).',
            'max_participants.integer' => 'Numărul maxim de participanți trebuie să fie un număr întreg.',
            'max_participants.min' => 'Numărul maxim de participanți trebuie să fie cel puțin 1 (sau lasă gol).',
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

    public function addCustomField(): void
    {
        $this->custom_fields[] = ['label' => '', 'value' => ''];
    }

    public function removeCustomField(int $i): void
    {
        unset($this->custom_fields[$i]);
        $this->custom_fields = array_values($this->custom_fields);
    }

    public function addLink(): void
    {
        $this->links[] = ['label' => '', 'url' => ''];
    }

    public function removeLink(int $i): void
    {
        unset($this->links[$i]);
        $this->links = array_values($this->links);
    }

    // ---- Repeatere: bilete ----------------------------------------------

    public function addTicketType(): void
    {
        $this->ticket_types[] = $this->emptyTicketType();
    }

    public function removeTicketType(int $i): void
    {
        unset($this->ticket_types[$i]);
        $this->ticket_types = array_values($this->ticket_types);
    }

    public function addTicketDiscount(int $ti): void
    {
        $this->ticket_types[$ti]['discounts'][] = ['label' => '', 'price' => '', 'until' => '', 'enter_until' => ''];
    }

    /** DXA: adaugat (runda 13). Treaptă de preț după numărul de bilete vândute („primele N la prețul X”). */
    public function addQtyTier(int $ti): void
    {
        $this->ticket_types[$ti]['qty_tiers'][] = ['label' => '', 'price' => '', 'first' => ''];
    }

    /** DXA: adaugat (runda 26). Combo „plătești N, primești M gratis”, per tip de bilet. */
    public function addCombo(int $ti): void
    {
        $this->ticket_types[$ti]['combos'][] = ['buy' => '', 'free' => ''];
    }

    public function removeCombo(int $ti, int $ci): void
    {
        unset($this->ticket_types[$ti]['combos'][$ci]);
        $this->ticket_types[$ti]['combos'] = array_values($this->ticket_types[$ti]['combos'] ?? []);
    }

    /** Copiază combo-urile unui tip pe toate celelalte tipuri de bilet (înlocuiește ce aveau). */
    public function copyCombosToAll(int $ti): void
    {
        $combos = array_values($this->ticket_types[$ti]['combos'] ?? []);
        foreach (array_keys($this->ticket_types) as $i) {
            $this->ticket_types[$i]['combos'] = $combos;
        }
        $this->dispatch('toast', message: 'Combo-urile au fost copiate pe toate tipurile de bilet.', type: 'ok');
    }

    public function removeQtyTier(int $ti, int $qi): void
    {
        unset($this->ticket_types[$ti]['qty_tiers'][$qi]);
        $this->ticket_types[$ti]['qty_tiers'] = array_values($this->ticket_types[$ti]['qty_tiers']);
    }

    public function removeTicketDiscount(int $ti, int $di): void
    {
        unset($this->ticket_types[$ti]['discounts'][$di]);
        $this->ticket_types[$ti]['discounts'] = array_values($this->ticket_types[$ti]['discounts']);
    }

    // ---- Repeatere: contacte --------------------------------------------

    public function addContact(): void
    {
        $this->contacts[] = $this->emptyContact();
    }

    public function removeContact(int $i): void
    {
        unset($this->contacts[$i]);
        $this->contacts = array_values($this->contacts);
    }

    // ---- Repeatere: coduri de reducere ----------------------------------

    public function addDiscountCode(): void
    {
        $this->discount_codes[] = $this->emptyDiscountCode();
    }

    public function removeDiscountCode(int $i): void
    {
        $id = $this->discount_codes[$i]['id'] ?? null;

        // Un cod deja folosit nu se șterge (istoricul intrărilor îl referă): se dezactivează.
        if ($id && $this->party && ($c = $this->party->discountCodes()->whereKey($id)->first()) && $c->usesCount() > 0) {
            $this->addError('discount_codes.'.$i.'.code', 'Codul a fost deja folosit și nu se poate șterge. Dezactivează-l (debifează „Activ").');

            return;
        }

        unset($this->discount_codes[$i]);
        $this->discount_codes = array_values($this->discount_codes);
    }

    // ---- Promotori (popup „+" lângă selectul de promotor) ----------------

    /** Rândul de cod pentru care e deschis popup-ul „Promotor nou" (null = închis). */
    public ?int $promoterModalRow = null;

    public string $newPromoterName = '';

    public string $newPromoterPhone = '';

    public string $newPromoterNote = '';

    public function openPromoterModal(int $row): void
    {
        $this->reset('newPromoterName', 'newPromoterPhone', 'newPromoterNote');
        $this->resetErrorBag(['newPromoterName', 'newPromoterPhone', 'newPromoterNote']);
        $this->promoterModalRow = $row;
    }

    public function closePromoterModal(): void
    {
        $this->promoterModalRow = null;
    }

    /** Adaugă promotorul (sau îl reutilizează dacă numele există deja) și îl alege în rândul de cod. */
    public function savePromoter(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        $this->validate([
            'newPromoterName' => ['required', 'string', 'max:120'],
            'newPromoterPhone' => ['nullable', 'string', 'max:40'],
            'newPromoterNote' => ['nullable', 'string', 'max:255'],
        ], [
            'newPromoterName.required' => 'Scrie numele promotorului.',
        ]);

        $name = trim($this->newPromoterName);
        $promoter = Promoter::findByName($name);
        if (! $promoter) {
            $promoter = Promoter::create([
                'name' => $name,
                'phone' => trim($this->newPromoterPhone) ?: null,
                'note' => trim($this->newPromoterNote) ?: null,
                'is_active' => true,
            ]);
            ActivityLogger::log('promoter.created', 'A adăugat promotorul „'.$promoter->name.'".');
        } elseif (! $promoter->is_active) {
            $promoter->update(['is_active' => true]);
        }

        if ($this->promoterModalRow !== null && isset($this->discount_codes[$this->promoterModalRow])) {
            $this->discount_codes[$this->promoterModalRow]['promoter_id'] = (string) $promoter->id;
        }
        $this->promoterModalRow = null;
    }

    /** Un cod scurt, fără caractere ușor de confundat (0/O, 1/I), unic pe petrecere. */
    public function generateDiscountCode(int $i): void
    {
        if (! isset($this->discount_codes[$i])) {
            return;
        }

        $taken = collect($this->discount_codes)->pluck('code')->map(fn ($c) => DiscountCodes::normalize($c))->all();
        do {
            $code = '';
            for ($n = 0; $n < 6; $n++) {
                $code .= 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)];
            }
        } while (in_array($code, $taken, true));

        $this->discount_codes[$i]['code'] = $code;
        $this->resetErrorBag('discount_codes.'.$i.'.code');
    }

    /** Numele tipurilor de bilet, cum le vede Recepția („Bilet" când n-au nume), pentru restricția unui cod. */
    private function ticketNames(): array
    {
        return collect($this->cleanTicketTypes())->map(fn ($t) => $t['name'] ?: 'Bilet')->unique()->values()->all();
    }

    /** Etichetele reducerilor (trepte) din tipurile de bilet: ce poate debloca un cod „treaptă". */
    private function tierLabels(): array
    {
        return collect($this->cleanTicketTypes())->flatMap(fn ($t) => collect($t['discounts'])->pluck('label'))
            ->filter()->unique(fn ($l) => mb_strtolower($l))->values()->all();
    }

    /**
     * Validează și curăță codurile de reducere. Întoarce rândurile curate sau null dacă există erori
     * (adăugate pe `discount_codes.{i}.{câmp}`).
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function cleanDiscountCodes(): ?array
    {
        $names = $this->ticketNames();
        $labels = array_map('mb_strtolower', $this->tierLabels());
        $clean = [];
        $seen = [];
        $ok = true;

        foreach ($this->discount_codes as $i => $r) {
            $fail = function (string $field, string $msg) use ($i, &$ok) {
                $this->addError('discount_codes.'.$i.'.'.$field, $msg);
                $ok = false;
            };

            $code = DiscountCodes::normalize($r['code'] ?? '');
            $blank = $code === '' && trim((string) ($r['promoter_id'] ?? '')) === '' && trim((string) ($r['value'] ?? '')) === '' && empty($r['id']);
            if ($blank) {
                continue;
            }

            if (! preg_match('/^[A-Z0-9_-]{3,40}$/u', $code)) {
                $fail('code', 'Codul are 3–40 de caractere: litere, cifre, - sau _ (fără spații).');
            } elseif (isset($seen[$code])) {
                $fail('code', 'Codul „'.$code.'" apare de două ori la această petrecere.');
            }
            $seen[$code] = true;

            $promoterId = null;
            $rawPromoter = trim((string) ($r['promoter_id'] ?? ''));
            if ($rawPromoter !== '') {
                if (ctype_digit($rawPromoter) && Promoter::query()->whereKey((int) $rawPromoter)->exists()) {
                    $promoterId = (int) $rawPromoter;
                } else {
                    $fail('promoter_id', 'Promotorul ales nu mai există. Alege altul.');
                }
            }

            $type = (string) ($r['type'] ?? '');
            if (! isset(PartyDiscountCode::TYPES[$type])) {
                $fail('type', 'Alege tipul reducerii.');
            }

            $value = null;
            $tier = null;
            if ($type === 'percent' || $type === 'amount') {
                $raw = str_replace(',', '.', trim((string) ($r['value'] ?? '')));
                if (! is_numeric($raw) || (float) $raw <= 0) {
                    $fail('value', 'Introdu o valoare mai mare ca 0.');
                } elseif ($type === 'percent' && (float) $raw > 100) {
                    $fail('value', 'Procentul nu poate depăși 100.');
                } elseif ($type === 'amount' && (float) $raw > 10000) {
                    $fail('value', 'Suma nu poate depăși 10.000 lei.');
                } else {
                    $value = round((float) $raw, 2);
                }
            } elseif ($type === 'tier') {
                $tier = trim((string) ($r['tier_label'] ?? ''));
                if ($tier === '') {
                    $fail('tier_label', 'Alege treapta pe care o deblochează codul.');
                } elseif (! in_array(mb_strtolower($tier), $labels, true)) {
                    $fail('tier_label', 'Treapta „'.$tier.'" nu mai există la niciun bilet (ai redenumit-o sau ai șters-o?). Alege alta sau corectează treapta.');
                }
            }

            $only = array_values(array_unique(array_filter((array) ($r['ticket_types'] ?? []), fn ($n) => is_string($n) && $n !== '')));
            foreach ($only as $n) {
                if (! in_array($n, $names, true)) {
                    $fail('ticket_types', 'Biletul „'.$n.'" nu mai există (l-ai redenumit sau șters?). Bifează din nou biletele codului.');
                    break;
                }
            }

            $dates = [];
            foreach (['valid_from', 'valid_until'] as $field) {
                $raw = trim((string) ($r[$field] ?? ''));
                $dates[$field] = null;
                if ($raw === '') {
                    continue;
                }
                try {
                    $dates[$field] = Carbon::parse($raw);
                } catch (\Throwable) {
                    $fail($field, 'Data nu este validă.');
                }
            }
            [$from, $until] = [$dates['valid_from'], $dates['valid_until']];
            if ($from && $until && $until->lessThanOrEqualTo($from)) {
                $fail('valid_until', 'Data „până la" trebuie să fie după data „de la".');
            }

            $ints = [];
            foreach (['max_uses' => 'Limita totală', 'max_uses_per_participant' => 'Limita per participant'] as $field => $label) {
                $raw = trim((string) ($r[$field] ?? ''));
                if ($raw === '') {
                    $ints[$field] = null;
                } elseif (! ctype_digit($raw) || (int) $raw < 1 || (int) $raw > 100000) {
                    $fail($field, $label.' trebuie să fie un număr întreg de la 1 în sus (gol = nelimitat).');
                } else {
                    $ints[$field] = (int) $raw;
                }
            }

            $clean[] = [
                'id' => $r['id'] ?? null,
                'code' => $code,
                'promoter_id' => $promoterId,
                'type' => $type,
                'value' => $value,
                'tier_label' => $tier,
                'ticket_types' => $only ?: null,
                'valid_from' => $from,
                'valid_until' => $until,
                'max_uses' => $ints['max_uses'] ?? null,
                'max_uses_per_participant' => $ints['max_uses_per_participant'] ?? null,
                'is_active' => (bool) ($r['is_active'] ?? true),
                'note' => trim((string) ($r['note'] ?? '')) ?: null,
            ];
        }

        return $ok ? $clean : null;
    }

    /** Salvează codurile: actualizează, creează; cele scoase din listă se șterg (nefolosite) sau se dezactivează (folosite). */
    private function syncDiscountCodes(Party $party, array $rows): void
    {
        DB::transaction(function () use ($party, $rows) {
            $existing = $party->discountCodes()->get()->keyBy('id');
            $keep = [];

            foreach ($rows as $r) {
                $attrs = collect($r)->except('id')->all();
                $model = $existing->get((int) ($r['id'] ?? 0));

                if ($model) {
                    $model->update($attrs);
                } else {
                    $model = $party->discountCodes()->create($attrs);
                }
                $keep[] = $model->id;
            }

            foreach ($existing->except($keep) as $gone) {
                $gone->usesCount() > 0 ? $gone->update(['is_active' => false]) : $gone->delete();
            }
        });
    }

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

    public function addMusicStyle(): void
    {
        $this->music_styles[] = $this->emptyMusicStyle();
    }

    public function removeMusicStyle(int $i): void
    {
        unset($this->music_styles[$i]);
        $this->music_styles = array_values($this->music_styles);
    }

    public function clearGuestPhoto(int $i): void
    {
        unset($this->guestPhotos[$i]);
        if (isset($this->guests[$i])) {
            $this->guests[$i]['photo_path'] = null;
        }
    }

    // ---- Ajutoare -------------------------------------------------------

    public function clearImage(): void
    {
        $this->reset('image');
        $this->removeImage = true;
    }

    /** Completeaza locatia cu datele organizatorului din Setari > Organizatie (nume, adresa, link harta). */
    public function fillSchoolVenue(): void
    {
        $this->location_name = Branding::name();
        $this->location_address = Branding::address();
        $this->location_url = Branding::mapsUrl();
    }

    /**
     * Metodele de plata de salvat, in ordinea din Setari. Se accepta doar metodele active si cele deja
     * salvate pe petrecere (chiar daca intre timp s-au dezactivat); cheile necunoscute se ignora.
     *
     * @return array<int, string>
     */
    private function paymentMethodsList(): array
    {
        $saved = $this->party?->payment_methods ?? [];
        $enabled = array_keys(PaymentMethods::enabled());

        return PaymentMethods::all()
            ->pluck('key')
            ->filter(fn ($key) => in_array($key, $this->payment_methods, true) && (in_array($key, $enabled, true) || in_array($key, $saved, true)))
            ->values()
            ->all();
    }

    /** Etichetele metodelor active alese (pentru textul generat al anuntului). */
    /** @param  array<string, string>  $names  traduceri pentru metodele predefinite (gol = etichetele din Setări) */
    private function paymentLabels(array $names = []): array
    {
        $enabled = PaymentMethods::enabled();

        return array_values(array_map(
            fn ($k) => $names[$k] ?? $enabled[$k],
            array_filter($this->paymentMethodsList(), fn ($k) => isset($enabled[$k])),
        ));
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
                    'enter_until' => ($d['enter_until'] ?? '') ?: null,
                ];
            }

            // Trepte după numărul de bilete vândute: „primele N la prețul X”; ordonate crescător după N.
            $tiers = [];
            foreach ($t['qty_tiers'] ?? [] as $q) {
                if (! isset($q['price'], $q['first']) || $q['price'] === '' || $q['first'] === '' || ! is_numeric($q['price']) || ! is_numeric($q['first'])) {
                    continue;
                }
                $tiers[] = [
                    'label' => trim($q['label'] ?? '') ?: null,
                    'price' => (float) $q['price'],
                    'first' => (int) $q['first'],
                ];
            }
            usort($tiers, fn ($a, $b) => $a['first'] <=> $b['first']);

            // Combo-uri „plătești N, primești M gratis”; fără dubluri.
            $combos = [];
            foreach ($t['combos'] ?? [] as $c) {
                if (! isset($c['buy'], $c['free']) || ! is_numeric($c['buy']) || ! is_numeric($c['free']) || (int) $c['buy'] < 1 || (int) $c['free'] < 1) {
                    continue;
                }
                $combos[(int) $c['buy'].'+'.(int) $c['free']] = ['buy' => (int) $c['buy'], 'free' => (int) $c['free']];
            }

            $limit = isset($t['limit']) && $t['limit'] !== '' && is_numeric($t['limit']) ? (int) $t['limit'] : null;

            $types[] = [
                'name' => $name ?: null,
                'price' => $priceSet ? (float) $t['price'] : null,
                'limit' => $limit,
                'discounts' => $discounts,
                'qty_tiers' => $tiers,
                'combos' => array_values($combos),
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

    /** DXA: adaugat (runda 29). Bifa de lângă „Generează descriere”: adaugă disclaimerul „INFORMAȚII IMPORTANTE” la finalul fiecărei limbi. */
    public bool $add_disclaimer = true;

    /** Bifa „Și în engleză și spaniolă” (implicit nebifată): doar atunci descrierea are și blocurile EN/ES, iar fiecare bloc poartă steagul. */
    public bool $other_langs = false;

    /** Implicit doar în română (fără marcaj). Cu „Și în EN/ES” bifat: câte un bloc pe limbă, marcat cu steag + [RO] / [EN] / [ES]. */
    public function generateDescription(): void
    {
        $blocks = [];
        $langs = $this->other_langs ? PartyDescriptionTexts::LANGS : ['ro'];
        foreach ($langs as $lang) {
            $t = PartyDescriptionTexts::labels($lang);
            $title = $this->name ?: $t['default_party'].' '.Branding::name();
            $tag = $this->other_langs ? PartyDescriptionTexts::FLAGS[$lang].' ['.strtoupper($lang).'] ' : '';
            $block = $tag.$title."\n\n".implode("\n", $this->descriptionLines($lang, $t));
            if ($this->add_disclaimer) {
                $block .= "\n\n".PartyDescriptionTexts::disclaimer($lang);
            }
            $blocks[] = rtrim($block);
        }

        $this->description = implode("\n\n———\n\n", $blocks);
    }

    /** @return array<int, string> */
    private function descriptionLines(string $lang, array $t): array
    {
        $lines = [];
        $money = function ($value) use ($t): string {
            $v = (float) $value;

            return ($v == floor($v) ? number_format($v, 0, ',', '.') : number_format($v, 2, ',', '.')).' '.$t['currency'];
        };

        // Când
        if ($this->kind === 'festival') {
            $days = $this->cleanDays();
            if ($days) {
                $d1 = Carbon::parse($days[0]['date']);
                $d2 = Carbon::parse($days[count($days) - 1]['date']);
                $when = count($days) > 1
                    ? $d1->format('d.m.Y').' – '.$d2->format('d.m.Y')
                    : $d1->format('d.m.Y');
                $lines[] = '🗓️ '.$t['when'].': '.$when;
            }
        } elseif ($this->start_date) {
            $d = Carbon::parse($this->start_date);
            $when = $t['days'][$d->dayOfWeek].', '.$d->format('d.m.Y');
            if ($this->start_time) {
                $when .= ', '.$this->start_time;
                if ($this->end_time) {
                    $when .= '–'.$this->end_time;
                }
            }
            $lines[] = '🗓️ '.$t['when'].': '.$when;
        }

        // Locație
        if ($this->location_name) {
            $loc = $this->location_name;
            if ($this->location_address) {
                $loc .= ' ('.$this->location_address.')';
            }
            $lines[] = '📍 '.$t['where'].': '.$loc;
        }

        // Dresscode (la simpla)
        if ($this->kind !== 'festival' && $this->dresscode) {
            $lines[] = '👗 '.$t['dress'].': '.$this->dresscode;
        }

        // Invitați (la festival)
        if ($this->kind === 'festival') {
            $guests = array_values(array_filter(
                array_map(fn ($g) => trim($g['name'] ?? ''), $this->guests),
                fn ($n) => $n !== '',
            ));
            if ($guests) {
                $lines[] = '🎤 '.$t['guests'].': '.implode(', ', $guests);
            }
        }

        // Prețuri
        if ($this->is_free) {
            $lines[] = '💰 '.$t['entry'].': '.$t['free'];
        } else {
            $typeTexts = [];
            foreach ($this->cleanTicketTypes() as $tt) {
                if ($tt['price'] === null) {
                    continue;
                }
                $txt = ($tt['name'] ?: $t['default_type']).' '.$money($tt['price']);
                $disc = [];
                foreach ($tt['discounts'] as $d) {
                    $dt = ($d['label'] ?: $t['offer']).' '.$money($d['price']);
                    if ($d['until']) {
                        $dt .= ' '.$t['buy_until'].' '.Party::formatUntil($d['until']);
                    }
                    if (! empty($d['enter_until'])) {
                        $dt .= ' '.$t['enter_until'].' '.Party::formatUntil($d['enter_until']);
                    }
                    $disc[] = $dt;
                }
                if ($disc) {
                    $txt .= ' ('.implode('; ', $disc).')';
                }
                $typeTexts[] = $txt;
            }
            if ($typeTexts) {
                $lines[] = '💰 '.$t['price'].': '.implode(' · ', $typeTexts);
            }
        }

        // Plată
        $pm = $this->paymentLabels($t['pay_names']);
        if ($pm) {
            $lines[] = '💳 '.$t['pay'].': '.implode(', ', $pm);
        }

        // Contact
        $contactTexts = [];
        foreach ($this->cleanContacts() as $c) {
            $ct = rtrim(trim(($c['name'] ?? '').' '.($c['phone'] ? '– '.$c['phone'] : '')), ' –');
            if ($ct !== '') {
                $contactTexts[] = $ct;
            }
        }
        if ($contactTexts) {
            $lines[] = '☎️ '.$t['contact'].': '.implode('; ', $contactTexts);
        }

        return $lines;
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

        // DXA: adaugat (runda 13). Treptele după număr de bilete: fiecare „primele N” distinct, altfel prețul nu e ambiguu.
        if (! $this->is_free) {
            foreach ($this->ticket_types as $ti => $type) {
                $firsts = [];
                foreach ($type['qty_tiers'] ?? [] as $q) {
                    if (isset($q['price'], $q['first']) && $q['price'] !== '' && $q['first'] !== '' && is_numeric($q['first'])) {
                        $firsts[] = (int) $q['first'];
                    }
                }
                if (count($firsts) !== count(array_unique($firsts))) {
                    $this->addError('ticket_types.'.$ti.'.qty_tiers', 'Două trepte au același număr de bilete. Fiecare „primele N” trebuie să fie diferit.');
                    $this->dispatch('scroll-to-error');

                    return;
                }
            }
        }

        // DXA: adaugat (runda 26). Combo-urile: același „N+M” nu se repetă în același tip.
        if (! $this->is_free) {
            foreach ($this->ticket_types as $ti => $type) {
                $keys = [];
                foreach ($type['combos'] ?? [] as $c) {
                    if (isset($c['buy'], $c['free']) && is_numeric($c['buy']) && is_numeric($c['free'])) {
                        $keys[] = (int) $c['buy'].'+'.(int) $c['free'];
                    }
                }
                if (count($keys) !== count(array_unique($keys))) {
                    $this->addError('ticket_types.'.$ti.'.combos', 'Același combo apare de două ori la acest tip de bilet.');
                    $this->dispatch('scroll-to-error');

                    return;
                }
            }
        }

        // DXA: adaugat (Coduri de reducere). La petrecere gratuită codurile nu se ating.
        $discountRows = null;
        if (! $this->is_free) {
            $discountRows = $this->cleanDiscountCodes();
            if ($discountRows === null) {
                $this->dispatch('scroll-to-error');

                return;
            }
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
            'loyalty_eligible' => $this->loyalty_eligible,
            'online_sales' => $this->online_sales,
            'max_tickets_per_order' => $this->max_tickets_per_order !== '' ? (int) $this->max_tickets_per_order : null,
            'tickets_for_sale' => $this->tickets_for_sale !== '' ? (int) $this->tickets_for_sale : null,
            'max_participants' => $this->max_participants !== '' ? (int) $this->max_participants : null,

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
            if ($discountRows !== null) {
                $this->syncDiscountCodes($this->party, $discountRows);
            }
            ActivityLogger::log('party.updated', 'A modificat petrecerea „'.$this->party->name.'".');
            session()->flash('status', 'Petrecerea a fost actualizată.');
        } else {
            $payload['created_by'] = Auth::guard('admin')->id();
            $party = Party::create($payload);
            if ($discountRows !== null) {
                $this->syncDiscountCodes($party, $discountRows);
            }
            ActivityLogger::log('party.created', 'A creat petrecerea „'.$party->name.'".');
            session()->flash('status', 'Petrecerea a fost creată.');
        }

        $this->redirectRoute('admin.parties.index', navigate: true);
    }

    /** @return array<string, string> id promotor => nume (activi + cei deja aleși în rânduri) */
    private function promoterOptions(): array
    {
        $chosen = collect($this->discount_codes)->pluck('promoter_id')->filter()->map(fn ($v) => (int) $v)->all();

        $options = ['' => 'Fără promotor'];
        foreach (Promoter::query()->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $chosen))->orderBy('name')->get() as $p) {
            $options[(string) $p->id] = $p->name.($p->is_active ? '' : ' (inactiv)');
        }

        return $options;
    }

    /** @return array<int, int> id cod => bilete cu reducere care l-au folosit */
    private function codeUses(): array
    {
        if (! $this->party || ! $this->party->exists) {
            return [];
        }

        return $this->party->discountCodes()->get()->mapWithKeys(fn (PartyDiscountCode $c) => [$c->id => $c->usesCount()])->all();
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
            'paymentChoices' => PaymentMethods::partyChoices($this->party?->payment_methods ?? []),
            'loyaltyEnabled' => LoyaltyLedger::enabled(),
            'codeTicketNames' => $this->ticketNames(),
            'codeTierLabels' => $this->tierLabels(),
            'codeUses' => $this->codeUses(),
            'promoterOptions' => $this->promoterOptions(),
        ]);
    }
}
