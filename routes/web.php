<?php

use App\Http\Controllers\ForgetKioskController;
use App\Livewire\Admin\FloorManager;
use App\Livewire\Admin\KioskManager;
use App\Livewire\Admin\LotManager;
use App\Livewire\Admin\VehicleManager;
use App\Livewire\Auth\Login;
use App\Livewire\Home;
use App\Livewire\LotOverview;
use App\Livewire\SlotDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');

// Release this browser from a kiosk it is bound to, so a shared machine can go back to
// being a plain kiosk viewer. Re-binding is just opening another gate's ?kiosk= URL.
Route::get('/kiosk/forget', ForgetKioskController::class)->name('kiosk.forget');

// Live parking occupancy dashboard (driven by ESP32 IR slot sensors).
Route::get('/lots', LotOverview::class)->name('lots.overview');
Route::get('/slots', SlotDashboard::class)->name('slots.dashboard');

// ── Staff authentication ─────────────────────────────────────────────────────
// The kiosk terminal and the two live dashboards above stay public on purpose: a gate
// tablet and a wall-mounted monitor have no account, and the occupancy figures they show
// are not sensitive. Everything that *changes* data sits behind this.

Route::get('/login', Login::class)->middleware('guest')->name('login');

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return to_route('login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    // Admin: configure the floors and slot counts of the parking lot.
    Route::get('/admin/floors', FloorManager::class)->name('admin.floors');
    Route::get('/admin/lots', LotManager::class)->name('admin.lots');
    Route::get('/admin/kiosks', KioskManager::class)->name('admin.kiosks');
    Route::get('/admin/vehicles', VehicleManager::class)->name('admin.vehicles');
});
