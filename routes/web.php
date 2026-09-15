<?php

use App\Livewire\Admin\Account\Edit as AccountEdit;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\ForgotPassword;
use App\Livewire\Admin\Invite\Complete as InviteComplete;
use App\Livewire\Admin\Login;
use App\Livewire\Admin\Logs\Index as LogsIndex;
use App\Livewire\Admin\ResetPassword;
use App\Livewire\Admin\SetupPhone;
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

            Route::get('/logs', LogsIndex::class)->name('logs.index');

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
