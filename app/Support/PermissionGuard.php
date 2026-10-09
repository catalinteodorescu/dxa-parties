<?php

namespace App\Support;

use App\Models\Admin;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;

use function Livewire\on;

/**
 * DXA: adaugat (runda 46 — permisiuni utilizatori). Paza ACȚIUNILOR din componentele Livewire ale panoului: paginile
 * se păzesc din rute (middleware `admin.can`), iar fiecare apel de metodă (wire:click, formulare) trece pe aici,
 * ca un utilizator cu doar „vizualizare” să nu poată modifica nimic nici trimițând cereri direct.
 *
 *   - metoda e sensibilă în secțiune (Permissions::ACTIONS) → cere bifa acțiunii sensibile;
 *   - metoda e de ștergere → cere nivelul „ștergere” al secțiunii;
 *   - metoda e doar de navigare/UI (READ_ONLY) → cere „vizualizare”;
 *   - orice altă metodă → cere „modificare”.
 * O componentă din panou fără secțiune declarată (nici în COMPONENTS, nici în EXEMPT) e refuzată: așa o componentă
 * nouă nu rămâne nepăzită din greșeală (testul PermissionsTest verifică acoperirea).
 */
final class PermissionGuard
{
    private const NS = 'App\\Livewire\\Admin\\';

    /** Clasa (relativă la App\Livewire\Admin) => secțiunea păzită. */
    public const COMPONENTS = [
        'Announcements\\Form' => 'announcements',
        'Announcements\\Index' => 'announcements',
        'Bar\\PartyLive' => 'bar_stats',
        'Bar\\Stats' => 'bar_stats',
        'BarApp\\AppSettings' => 'settings',
        'BarReports\\Index' => 'bar_reports',
        'BarReports\\Show' => 'bar_reports',
        'Credits\\Index' => 'credits',
        'Logs\\Index' => 'logs',
        'Loyalty\\Cards' => 'loyalty',
        'MenuItems\\Categories' => 'menu',
        'MenuItems\\Form' => 'menu',
        'MenuItems\\Index' => 'menu',
        'ParticipantApp\\AppSettings' => 'settings',
        'Participants\\Index' => 'participants',
        'Participants\\Show' => 'participants',
        'Participants\\Stats' => 'participants',
        'Parties\\Attendees' => 'parties',
        'Parties\\Form' => 'parties',
        'Parties\\Index' => 'parties',
        'Parties\\OnlineSales' => 'party_stats',
        'Parties\\Overview' => 'party_stats',
        'Parties\\Show' => 'parties',
        'Parties\\Stats' => 'party_stats',
        'Promoters\\Index' => 'promoters',
        'Tickets\\Index' => 'tickets',   // runda 66
        'Tickets\\Show' => 'tickets',
        'PwaAppSettings' => 'settings',
        'Reception\\CreditSale' => 'reception',
        'Reception\\Form' => 'reception',
        'Reception\\Index' => 'reception',
        'Reception\\Stats' => 'reception_stats',
        'Reception\\TokenSale' => 'reception',
        'Reception\\Tokens' => 'reception_tokens',
        'ReceptionApp\\AppSettings' => 'settings',
        'ReceptionReports\\Form' => 'reception_reports',
        'ReceptionReports\\Index' => 'reception_reports',
        'Reconciliation\\Index' => 'reconciliation',
        'Sales\\Form' => 'sales',
        'Sales\\Index' => 'sales',
        'Settings\\DashboardSections' => 'settings',
        'Settings\\Index' => 'settings',
        'Settings\\PaymentMethods' => 'settings',
        'Settings\\TermsPanel' => 'settings',
        'StockReports\\Form' => 'stock_reports',
        'StockReports\\Index' => 'stock_reports',
        'StockRequisitions\\Form' => 'requisitions',
        'StockRequisitions\\Index' => 'requisitions',
        'Stocks\\Index' => 'stocks',
        'Users\\Create' => 'users',
        'Users\\Index' => 'users',
        'Users\\Permissions' => 'users',
    ];

    /** Pagini ale panoului care nu cer permisiune de secțiune (contul propriu, dashboard, autentificare). */
    public const EXEMPT = [
        'Account\\Edit',
        'Dashboard',
        'ForgotPassword',
        'Invite\\Complete',
        'Login',
        'ResetPassword',
        'SetupPhone',
    ];

    public const DELETE_METHODS = ['delete', 'deleteAdmin', 'askDelete'];

    /** Metode de navigare/UI care nu schimbă nimic. */
    public const READ_ONLY = [
        'clearFilters', 'toggleExpand', 'loadMoreMovements', 'setSort', 'closeModal', 'closeCostModal', 'closeQtyModal',
        'closeForm', 'closeNewCategoryModal', 'closeNewStockItemModal', 'closePromoterModal', 'cancelDelete',
        'cancelReopen', 'cancelFinalize', 'cancelWriteOff', 'showLoyaltyCard', 'closeLoyaltyCard', 'refresh',
        'refreshTokens', 'refreshCredits', 'toggleAdd', 'imagePreview', 'logoPreview', 'smsPreview',
    ];

    /** Secțiunea păzită pentru o clasă de componentă (null = nu e o componentă a panoului sau e scutită). */
    public static function sectionFor(string $class): ?string
    {
        if (! str_starts_with($class, self::NS)) {
            return null;
        }

        $relative = substr($class, strlen(self::NS));

        if (in_array($relative, self::EXEMPT, true)) {
            return null;
        }

        return self::COMPONENTS[$relative] ?? '';
    }

    /** Ce cere o metodă: ['level', 1|2|3] sau ['action', cheie] sau null (nu se verifică, ex. magice Livewire). */
    public static function requirement(string $section, string $method): ?array
    {
        if ($method === '' || $method[0] === '$' || $method[0] === '_') {
            return null;
        }

        // Metodă sensibilă în această secțiune (definită în Permissions::ACTIONS) → cere bifa ei.
        if ($action = Permissions::actionForMethod($section, $method)) {
            return ['action', $action];
        }

        if (in_array($method, self::DELETE_METHODS, true)) {
            return ['level', Permissions::DELETE];
        }

        if (in_array($method, self::READ_ONLY, true)) {
            return ['level', Permissions::VIEW];
        }

        return ['level', Permissions::EDIT];
    }

    /** Are utilizatorul voie să apeleze metoda în secțiune? */
    public static function allows(Admin $admin, string $section, string $method): bool
    {
        if ($admin->isSuperAdmin()) {
            return true;
        }

        $need = self::requirement($section, $method);

        if ($need === null) {
            return true;
        }

        // Pentru orice apel, secțiunea trebuie măcar vizibilă.
        if (! $admin->permits($section, Permissions::VIEW)) {
            return false;
        }

        return $need[0] === 'action' ? $admin->permitsAction($need[1]) : $admin->permits($section, $need[1]);
    }

    public static function register(): void
    {
        on('call', function ($component, $method) {
            $section = self::sectionFor($component::class);

            if ($section === null) {
                return;
            }

            $admin = Auth::guard('admin')->user();

            if (! $admin instanceof Admin) {
                return;
            }

            if ($section === '' || ! self::allows($admin, $section, (string) $method)) {
                ActivityLogger::log(
                    'admin.permission.denied',
                    'Acțiune refuzată (fără permisiune): '.class_basename($component).'::'.$method.'.',
                    $admin,
                    actor: null,
                );

                abort(403, 'Nu ai permisiunea pentru această acțiune.');
            }
        });
    }
}
