<?php

use App\Http\Controllers\ForgetKioskController;
use App\Http\Middleware\EnsureCanUseAdminPanel;
use App\Livewire\Admin\FloorManager;
use App\Livewire\Admin\KioskManager;
use App\Livewire\Admin\LotManager;
use App\Livewire\Admin\UserManager;
use App\Livewire\Admin\VehicleManager;
use App\Livewire\Auth\Login;
use App\Livewire\Home;
use App\Livewire\LotOverview;
use App\Livewire\SlotDashboard;
use Illuminate\Support\Facades\Route;

// The front door. Two pages share this one URL, decided in Home::render() rather than by
// `auth`: a guest gets the landing page (what the app is, and a way in), and signing in
// replaces it with the kiosk terminal. That is the entry and exit screen — an open browser
// at a gate can park, charge and release vehicles — so it must mean being somebody.
//
// A bare redirect to /login would have been the smaller change and the worse one: a first
// visitor learns nothing about what they are signing in to. Branching also keeps the kiosk
// URL as plain `/?kiosk=<key>`, which matters because that URL gets printed on labels and QR
// codes. Putting the terminal behind a path prefix would have invalidated every one.
Route::get('/', Home::class)->name('home');

// Release this browser from a kiosk it is bound to, so a shared machine can go back to
// being a plain kiosk viewer. Re-binding is just opening another gate's ?kiosk= URL.
// Left public: it discards a binding rather than revealing anything, and it has to keep
// working for a browser that is signed out.
Route::get('/kiosk/forget', ForgetKioskController::class)->name('kiosk.forget');

// Live parking occupancy dashboard (driven by ESP32 IR slot sensors). Still public: these are
// wall-mounted read-only monitors with no account and no keyboard, and the occupancy figures
// they show are not sensitive. Nothing here changes data.
Route::get('/lots', LotOverview::class)->name('lots.overview');
Route::get('/slots', SlotDashboard::class)->name('slots.dashboard');

// ── Staff authentication ─────────────────────────────────────────────────────
// Everything that *changes* data sits behind a login: the kiosk terminal above, and every
// `/admin/*` page.
//
// Three roles, and the shape of the panel follows from which one you are:
//   - super admin  every lot, plus the corrections that touch other people's records
//   - lot admin    one lot: floors, slots, kiosks, and the card registry
//   - operator     one lot: run a gate terminal, and nothing else
// An operator is redirected out of the admin panel to their kiosk page by the middleware
// below, which is the second half of "can only open the kiosk".

Route::get('/login', Login::class)->middleware('guest')->name('login');

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return to_route('login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', EnsureCanUseAdminPanel::class])->group(function () {
    // Admin: configure the floors and slot counts of the parking lot.
    Route::get('/admin/floors', FloorManager::class)->name('admin.floors');
    Route::get('/admin/lots', LotManager::class)->name('admin.lots');
    Route::get('/admin/kiosks', KioskManager::class)->name('admin.kiosks');
    Route::get('/admin/users', UserManager::class)->name('admin.users');
    Route::get('/admin/vehicles', VehicleManager::class)->name('admin.vehicles');
});
