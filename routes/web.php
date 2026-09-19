<?php

use App\Livewire\Admin\Account\Edit as AccountEdit;
use App\Livewire\Admin\Announcements\Form as AnnouncementForm;
use App\Livewire\Admin\Announcements\Index as AnnouncementsIndex;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\ForgotPassword;
use App\Livewire\Admin\Invite\Complete as InviteComplete;
use App\Livewire\Admin\Login;
use App\Livewire\Admin\Logs\Index as LogsIndex;
use App\Livewire\Admin\MenuCategories\Index as MenuCategoriesIndex; // DXA: adaugat (Meniu bar - nomenclator categorii)
use App\Livewire\Admin\MenuItems\Form as MenuItemForm;             // DXA: adaugat (Meniu bar - produse)
use App\Livewire\Admin\MenuItems\Index as MenuItemsIndex;          // DXA: adaugat (Meniu bar - produse)
use App\Livewire\Admin\Parties\Form as PartyForm;         // DXA: adaugat (Petreceri)
use App\Livewire\Admin\Parties\Index as PartiesIndex;     // DXA: adaugat (Petreceri)
use App\Livewire\Admin\Parties\Show as PartiesShow;       // DXA: adaugat (Petreceri - view single)
use App\Livewire\Admin\ResetPassword;
use App\Livewire\Admin\Settings\Index as SettingsIndex; // DXA: adaugat (Setari)
use App\Livewire\Admin\SetupPhone;
use App\Livewire\Admin\Stocks\Index as StockItemsIndex; // DXA: adaugat (Bar - stocuri)
use App\Livewire\Admin\Users\Create as UsersCreate;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(
        Auth::guard('admin')->check() ? 'admin.dashboard' : 'admin.login'
    );
});

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

    Route::middleware(['auth:admin', 'admin.active'])->group(function () {

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
            Route::get('/parties/{party}/edit', PartyForm::class)->name('parties.edit');
            Route::get('/parties/{party}', PartiesShow::class)->name('parties.show'); // DXA: adaugat

            // DXA: adaugat (Meniu bar - nomenclator categorii)
            Route::get('/menu/categories', MenuCategoriesIndex::class)->name('menu-categories.index');

            // DXA: adaugat (Meniu bar - produse)
            Route::get('/menu/items', MenuItemsIndex::class)->name('menu-items.index');
            Route::get('/menu/items/create', MenuItemForm::class)->name('menu-items.create');
            Route::get('/menu/items/{menuItem}/edit', MenuItemForm::class)->name('menu-items.edit');

            Route::get('/logs', LogsIndex::class)->name('logs.index');

            // DXA: adaugat (Bar - stocuri)
            Route::get('/stocks/items', StockItemsIndex::class)->name('stock-items.index');

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
