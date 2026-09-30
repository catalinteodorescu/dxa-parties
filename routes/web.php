<?php

use App\Http\Controllers\BarReportPdfController;
use App\Http\Controllers\PwaController;
use App\Livewire\Admin\Account\Edit as AccountEdit;
use App\Livewire\Admin\Announcements\Form as AnnouncementForm; // DXA: adaugat (Bar - statistici agregate)
use App\Livewire\Admin\Announcements\Index as AnnouncementsIndex;
use App\Livewire\Admin\Bar\Stats as BarStatsPage;
use App\Livewire\Admin\BarApp\AppSettings as BarAppSettings;
use App\Livewire\Admin\BarReports\Index as BarReportsIndex;
use App\Livewire\Admin\BarReports\Show as BarReportShow;
use App\Livewire\Admin\Credits\Index as CreditsIndex; // DXA: adaugat (PWA Bar - setări aplicație) // DXA: adaugat (Credite - pagina Credite)
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\ForgotPassword;
use App\Livewire\Admin\Invite\Complete as InviteComplete;
use App\Livewire\Admin\Login;
use App\Livewire\Admin\Logs\Index as LogsIndex;
use App\Livewire\Admin\Loyalty\Cards as LoyaltyCardsPage;          // DXA: adaugat (Card de fidelitate - pagina Carduri)
use App\Livewire\Admin\MenuItems\Form as MenuItemForm;
use App\Livewire\Admin\MenuItems\Index as MenuItemsIndex; // DXA: adaugat (PWA Recepție - setări aplicație)             // DXA: adaugat (Meniu bar - produse)
use App\Livewire\Admin\Participants\Index as ParticipantsIndex;          // DXA: adaugat (Meniu bar - produse)
use App\Livewire\Admin\Participants\Show as ParticipantsShow;         // DXA: adaugat (Petreceri)
use App\Livewire\Admin\Participants\Stats as ParticipantsStats;     // DXA: adaugat (Petreceri)
use App\Livewire\Admin\Parties\Form as PartyForm;   // DXA: adaugat (Participanți - statistici)
use App\Livewire\Admin\Parties\Index as PartiesIndex;       // DXA: adaugat (Petreceri - view single)
use App\Livewire\Admin\Parties\Overview as PartiesOverviewPage;     // DXA: adaugat (Petreceri - statistici)
use App\Livewire\Admin\Parties\Show as PartiesShow; // DXA: adaugat (Petreceri - statistici agregate)
use App\Livewire\Admin\Parties\Stats as PartiesStats;
use App\Livewire\Admin\Promoters\Index as PromotersIndex; // DXA: adaugat (Coduri de reducere - promotori)
use App\Livewire\Admin\Reception\Form as ReceptionForm; // DXA: adaugat (Setari)
use App\Livewire\Admin\Reception\Index as ReceptionIndex;
use App\Livewire\Admin\Reception\Stats as ReceptionStatsPage;       // DXA: adaugat (Bar - raportari)
use App\Livewire\Admin\Reception\Tokens as ReceptionTokens;  // DXA: adaugat (Receptie - statistici agregate)
use App\Livewire\Admin\ReceptionApp\AppSettings as ReceptionAppSettings;    // DXA: adaugat (Bar - raportari)
use App\Livewire\Admin\ReceptionReports\Form as ReceptionReportForm;      // DXA: adaugat (Participanți)
use App\Livewire\Admin\ReceptionReports\Index as ReceptionReportsIndex;        // DXA: adaugat (Participanți)
use App\Livewire\Admin\Reconciliation\Index as ReconciliationIndex;              // DXA: adaugat (Recepție - intrări = adăugare)
use App\Livewire\Admin\ResetPassword;            // DXA: adaugat (Recepție - intrări = listă)
use App\Livewire\Admin\Sales\Form as SaleForm;          // DXA: adaugat (Recepție - tokeni)
use App\Livewire\Admin\Sales\Index as SalesIndex;    // DXA: adaugat (Recepție - raportări)
use App\Livewire\Admin\Settings\Index as SettingsIndex; // DXA: adaugat (Recepție - raportări)
use App\Livewire\Admin\SetupPhone;                         // DXA: adaugat (Bar - vanzari)
use App\Livewire\Admin\StockReports\Form as StockReportForm;                      // DXA: adaugat (Bar - vanzari)
use App\Livewire\Admin\StockReports\Index as StockReportsIndex;   // DXA: adaugat (Bar - necesare)
use App\Livewire\Admin\StockRequisitions\Form as StockRequisitionForm; // DXA: adaugat (Bar - necesare)
use App\Livewire\Admin\StockRequisitions\Index as StockRequisitionsIndex; // DXA: adaugat (Bar - stocuri)
use App\Livewire\Admin\Stocks\Index as StockItemsIndex;
use App\Livewire\Admin\Users\Create as UsersCreate;
use App\Livewire\Admin\Users\Index as UsersIndex; // DXA: adaugat (PWA Recepție)
use App\Livewire\Bar\Home as BarHome; // DXA: adaugat (PWA Bar)
use App\Livewire\Bar\Login as BarLogin;
use App\Livewire\Bar\PartyPicker as BarPartyPicker;
use App\Livewire\Bar\Recent as BarRecent;
use App\Livewire\Bar\Report as BarReportPage;
use App\Livewire\Bar\Sale as BarSale;
use App\Livewire\Participant\Account as ParticipantAccount;
use App\Livewire\Participant\Announcements as ParticipantAnnouncements;
use App\Livewire\Participant\ForgotPassword as ParticipantForgotPassword;
use App\Livewire\Participant\History as ParticipantHistory;
use App\Livewire\Participant\Home as ParticipantHome;
use App\Livewire\Participant\Login as ParticipantLogin;
use App\Livewire\Participant\Parties as ParticipantParties; // DXA: adaugat (PWA Recepție)
use App\Livewire\Participant\PartyShow as ParticipantPartyShow;
use App\Livewire\Participant\Register as ParticipantRegister;
use App\Livewire\Participant\ResetPassword as ParticipantResetPassword;
use App\Livewire\Participant\Tickets as ParticipantTickets;
use App\Livewire\Participant\Verify as ParticipantVerify; // DXA: adaugat (PWA Recepție) // DXA: adaugat (PWA Recepție)
use App\Livewire\Participant\Wallet as ParticipantWallet;
use App\Livewire\Reception\CreditSale as ReceptieCreditSale;
use App\Livewire\Reception\Entry as ReceptieEntry;
use App\Livewire\Reception\Home as ReceptieHome;
use App\Livewire\Reception\Login as ReceptieLogin;
use App\Livewire\Reception\PartyPicker as ReceptiePartyPicker;
use App\Livewire\Reception\Recent as ReceptieRecent;
use App\Livewire\Reception\Report as ReceptieReport;
use App\Livewire\Reception\TokenSale as ReceptieTokenSale;
use App\Models\Participant;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', Login::class)->name('login');
        Route::get('/forgot-password', ForgotPassword::class)->name('forgot-password');
    });

    // Rute accesibile doar printr-un link semnat, trimis prin SMS.
    Route::middleware('signed')->group(function () {
        Route::get('/invite/{admin}', InviteComplete::class)->name('invite.complete');
        Route::get('/reset-password/{admin}', ResetPassword::class)->name('reset-password');
    });

    Route::middleware(['auth:admin', 'admin.active', 'admin.access:admin'])->group(function () {

        // Accesibilă chiar dacă adminul trebuie să-și seteze telefonul.
        Route::get('/setup-phone', SetupPhone::class)->name('setup-phone');

        Route::middleware('admin.phone_setup')->group(function () {
            Route::get('/', Dashboard::class)->name('dashboard');

            Route::get('/users', UsersIndex::class)->name('users.index');
            Route::get('/users/create', UsersCreate::class)->name('users.create');

            Route::get('/announcements', AnnouncementsIndex::class)->name('announcements.index');
            Route::get('/announcements/create', AnnouncementForm::class)->name('announcements.create');
            Route::get('/announcements/{announcement}/edit', AnnouncementForm::class)->name('announcements.edit');

            // DXA: adaugat (Petreceri)
            Route::get('/parties', PartiesIndex::class)->name('parties.index');
            Route::get('/parties/create', PartyForm::class)->name('parties.create');
            // 'overview' inainte de '{party}' (ca la /participants/stats), altfel ruta cu wildcard il inghite.
            Route::get('/parties/overview', PartiesOverviewPage::class)->name('parties.overview'); // DXA: adaugat (statistici agregate)
            Route::get('/parties/{party}/edit', PartyForm::class)->name('parties.edit');
            Route::get('/parties/{party}', PartiesShow::class)->name('parties.show'); // DXA: adaugat
            Route::get('/parties/{party}/stats', PartiesStats::class)->name('parties.stats'); // DXA: adaugat (statistici)
            Route::get('/promoters', PromotersIndex::class)->name('promoters.index'); // DXA: adaugat (Coduri de reducere - promotori)

            // DXA: adaugat (Meniu bar - produse)
            Route::get('/menu/items', MenuItemsIndex::class)->name('menu-items.index');
            Route::get('/menu/items/create', MenuItemForm::class)->name('menu-items.create');
            Route::get('/menu/items/{menuItem}/edit', MenuItemForm::class)->name('menu-items.edit');

            Route::get('/logs', LogsIndex::class)->name('logs.index');

            // DXA: adaugat (Bar - stocuri)
            Route::get('/stocks/items', StockItemsIndex::class)->name('stock-items.index');

            // DXA: adaugat (Bar - necesare)
            Route::get('/stocks/requisitions', StockRequisitionsIndex::class)->name('stock-requisitions.index');
            Route::get('/stocks/requisitions/create', StockRequisitionForm::class)->name('stock-requisitions.create');
            Route::get('/stocks/requisitions/{requisition}/edit', StockRequisitionForm::class)->name('stock-requisitions.edit');

            // DXA: adaugat (Bar - vanzari)
            // DXA: adaugat (Participanți)
            Route::get('/participants', ParticipantsIndex::class)->name('participants.index');
            Route::get('/participants/stats', ParticipantsStats::class)->name('participants.stats'); // DXA: adaugat (Participanți - statistici)
            Route::get('/participants/{participant}', ParticipantsShow::class)->name('participants.show');
            // DXA: adaugat (Aplicația participanților - runda 12). Poza de profil a unui participant, pentru lista din admin (discul e privat).
            Route::get('/participants/{participant}/poza', function (Participant $participant) {
                abort_unless($participant->hasAvatar() && Storage::disk('local')->exists($participant->avatar_path), 404);

                return response(Storage::disk('local')->get($participant->avatar_path), 200, [
                    'Content-Type' => 'image/jpeg',
                    'Cache-Control' => 'private, max-age=86400',
                ]);
            })->name('participants.avatar');

            // DXA: adaugat (Credite - pagina Credite, doar afișare, pe modelul paginii Carduri)
            Route::get('/credits', CreditsIndex::class)->name('credits.index');

            // DXA: adaugat (Card de fidelitate - pagina Carduri, pe modelul paginii Tokeni)
            Route::get('/loyalty/cards', LoyaltyCardsPage::class)->name('loyalty.cards');

            // DXA: adaugat (Recepție - intrări): /reception = lista, /reception/create = adăugarea (ca la Vânzări)
            Route::get('/reception', ReceptionIndex::class)->name('reception.index');
            Route::get('/reception/create', ReceptionForm::class)->name('reception.create');
            Route::get('/reception/tokens', ReceptionTokens::class)->name('reception.tokens'); // DXA: adaugat (tokeni)
            Route::get('/settings/reception-app', ReceptionAppSettings::class)->name('settings.reception-app'); // DXA: adaugat (PWA Recepție - setări aplicație)
            Route::get('/settings/bar-app', BarAppSettings::class)->name('settings.bar-app'); // DXA: adaugat (PWA Bar - setări aplicație)
            // DXA: adaugat (Recepție - raportări = închiderea casei)
            Route::get('/reception/reports', ReceptionReportsIndex::class)->name('reception.reports.index');
            Route::get('/reception/reports/{report}', ReceptionReportForm::class)->name('reception.reports.show');

            Route::get('/sales', SalesIndex::class)->name('sales.index');
            Route::get('/sales/create', SaleForm::class)->name('sales.create');

            // DXA: adaugat (Bar - raportari). 'edit' serveste si vizualizarea unui
            // raport finalizat (read-only e doar o stare a Form-ului, nu ruta separata).
            Route::get('/stocks/reports', StockReportsIndex::class)->name('stock-reports.index');
            Route::get('/stocks/reports/create', StockReportForm::class)->name('stock-reports.create');
            Route::get('/stocks/reports/{report}/edit', StockReportForm::class)->name('stock-reports.edit');
            Route::get('/bar/reports', BarReportsIndex::class)->name('bar.reports.index'); // DXA: adaugat (Bar - raportări casă)
            Route::get('/bar/reports/{report}', BarReportShow::class)->name('bar.reports.show');
            Route::get('/bar/reports/{report}/pdf', BarReportPdfController::class)->name('bar.reports.pdf');
            Route::get('/evening-balance', ReconciliationIndex::class)->name('reconciliation'); // DXA: adaugat (Bilanțul serii = reconcilierea recepție + bar)
            Route::get('/bar/stats', BarStatsPage::class)->name('bar.stats'); // DXA: adaugat (Bar - statistici agregate)

            // DXA: adaugat (Receptie - statistici agregate)
            Route::get('/reception/stats', ReceptionStatsPage::class)->name('reception.stats');

            // DXA: adaugat (Setari)
            Route::get('/settings', SettingsIndex::class)->name('settings.index');

            Route::get('/account', AccountEdit::class)->name('account.edit');
        });

        Route::post('/logout', function () {
            ActivityLogger::log('admin.logout', 'S-a delogat.');

            Auth::guard('admin')->logout();

            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('admin.login');
        })->name('logout');
    });

});

// DXA: adaugat (PWA Recepție). Aplicația de recepție, pe subcale (/receptie), cu manifest + service worker propriu,
// login propriu (același cont ca în admin) și acces doar cu bifa „Aplicație recepție" (admin.access:reception).
Route::prefix('receptie')->name('receptie.')->group(function () {
    Route::get('/manifest.webmanifest', [PwaController::class, 'receptionManifest'])->name('manifest');
    Route::get('/sw.js', [PwaController::class, 'receptionServiceWorker'])->name('sw');
    Route::get('/icon/{size}.png', [PwaController::class, 'receptionIcon'])->whereNumber('size')->name('icon');

    Route::middleware('guest:admin')->get('/login', ReceptieLogin::class)->name('login');

    Route::middleware(['auth:admin', 'admin.active', 'admin.access:reception'])->group(function () {
        Route::get('/', ReceptieHome::class)->name('home');
        Route::get('/petrecere', ReceptiePartyPicker::class)->name('party');
        Route::get('/intrare', ReceptieEntry::class)->name('entry');
        Route::get('/tokeni', ReceptieTokenSale::class)->name('tokens');
        Route::get('/credite', ReceptieCreditSale::class)->name('credits');
        Route::get('/tranzactii', ReceptieRecent::class)->name('recent');
        Route::get('/raportare', ReceptieReport::class)->name('report');
    });
});

// DXA: adaugat (PWA Bar). Aplicația de bar, pe subcale (/bar), cu manifest + service worker propriu, login propriu
// (același cont ca în admin) și acces doar cu bifa „Aplicație bar" (admin.access:bar).
Route::prefix('bar')->name('bar.')->group(function () {
    Route::get('/manifest.webmanifest', [PwaController::class, 'barManifest'])->name('manifest');
    Route::get('/sw.js', [PwaController::class, 'barServiceWorker'])->name('sw');
    Route::get('/icon/{size}.png', [PwaController::class, 'barIcon'])->whereNumber('size')->name('icon');

    Route::middleware('guest:admin')->get('/login', BarLogin::class)->name('login');

    Route::middleware(['auth:admin', 'admin.active', 'admin.access:bar'])->group(function () {
        Route::get('/', BarHome::class)->name('home');
        Route::get('/petrecere', BarPartyPicker::class)->name('party');
        Route::get('/vanzare', BarSale::class)->name('sale');
        Route::get('/vanzari', BarRecent::class)->name('recent');
        Route::get('/raportare', BarReportPage::class)->name('report');
    });
});

// DXA: adaugat (Utilizatori - acces pe aplicație). Deconectare comună (folosită de PWA și de pagina „Fără acces”):
// nu cere acces la nicio aplicație, doar un cont autentificat. `to` alege login-ul unde ajunge după.
Route::post('/sesiune/logout', function (Request $request) {
    ActivityLogger::log('admin.logout', 'S-a delogat.');

    Auth::guard('admin')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route(match ($request->input('to')) {
        'receptie' => 'receptie.login',
        'bar' => 'bar.login',
        default => 'admin.login',
    });
})->middleware('auth:admin')->name('session.logout');

// DXA: adaugat (Aplicația participanților - runda 1). PWA în rădăcina domeniului: partea publică (Acasă, Petreceri) fără cont,
// login / înregistrare cu SMS / resetare parolă și contul (guard `participant`, separat de admin). Rutele cu cuvinte fixe
// (/petreceri, /intra ...) nu se ating cu /admin, /receptie, /bar; service worker-ul (scope „/”) ignoră explicit acele zone.
Route::name('app.')->group(function () {
    Route::get('/manifest.webmanifest', [PwaController::class, 'participantManifest'])->name('manifest');
    Route::get('/sw.js', [PwaController::class, 'participantServiceWorker'])->name('sw');
    Route::get('/icon/{size}.png', [PwaController::class, 'participantIcon'])->whereNumber('size')->name('icon');

    Route::get('/', ParticipantHome::class)->name('home');
    Route::get('/petreceri', ParticipantParties::class)->name('parties');
    Route::get('/anunturi', ParticipantAnnouncements::class)->name('announcements');
    Route::get('/petreceri/{party}', ParticipantPartyShow::class)->name('party');

    Route::middleware('guest:participant')->group(function () {
        Route::get('/intra', ParticipantLogin::class)->name('login');
        Route::get('/inregistrare', ParticipantRegister::class)->name('register');
        Route::get('/verificare', ParticipantVerify::class)->name('verify');
        Route::get('/parola-uitata', ParticipantForgotPassword::class)->name('forgot');
    });

    // Linkul din SMS: semnat + temporar; funcționează și cu un cont deja logat (nu cere guest).
    Route::get('/resetare-parola/{participant}', ParticipantResetPassword::class)->middleware('signed')->name('reset');

    Route::middleware('auth:participant')->group(function () {
        Route::get('/cont', ParticipantAccount::class)->name('account');
        Route::get('/bilete', ParticipantTickets::class)->name('tickets');
        Route::get('/bilete/toate', ParticipantHistory::class)->defaults('kind', 'tickets')->name('tickets.all');
        Route::get('/portofel', ParticipantWallet::class)->name('wallet');
        Route::get('/portofel/incarcari', ParticipantHistory::class)->defaults('kind', 'credits')->name('wallet.all');
        Route::get('/cont/intrari', ParticipantHistory::class)->defaults('kind', 'entries')->name('entries.all');
        Route::get('/cont/consumatii', ParticipantHistory::class)->defaults('kind', 'bar')->name('bar.all');
        Route::get('/cont/poza', function () {
            $me = Auth::guard('participant')->user();
            abort_unless($me?->hasAvatar() && Storage::disk('local')->exists($me->avatar_path), 404);

            return response(Storage::disk('local')->get($me->avatar_path), 200, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'private, max-age=86400',
            ]);
        })->name('avatar');
        Route::post('/iesire', function (Request $request) {
            Auth::guard('participant')->logout();
            $request->session()->regenerate();
            $request->session()->regenerateToken();

            return redirect()->route('app.home');
        })->name('logout');
    });
});
