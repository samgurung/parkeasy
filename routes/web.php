<?php

use App\Livewire\Admin\FloorManager;
use App\Livewire\Admin\LotManager;
use App\Livewire\LotOverview;
use App\Livewire\Home;
use App\Livewire\SlotDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');

// Live parking occupancy dashboard (driven by ESP32 IR slot sensors).
Route::get('/lots', LotOverview::class)->name('lots.overview');
Route::get('/slots', SlotDashboard::class)->name('slots.dashboard');

// Admin: configure the floors and slot counts of the parking lot.
Route::get('/admin/floors', FloorManager::class)->name('admin.floors');
Route::get('/admin/lots', LotManager::class)->name('admin.lots');
