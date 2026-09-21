<?php

use App\Http\Controllers\RfidScannedController;
use App\Http\Controllers\SlotStatusController;
use Illuminate\Support\Facades\Route;

Route::post('/rfid-scan', [RfidScannedController::class, 'rfidScanned']);
Route::post('/rfid-scan/details', [RfidScannedController::class, 'saveDetails']);
Route::post('/rfid-scan/register', [RfidScannedController::class, 'registerVehicle']);
Route::post('/kiosk-mode', [RfidScannedController::class, 'setMode']);

// ESP32 IR sensor nodes toggle per-slot occupancy. Each hit flips the slot:
// free → occupied (vehicle entered), occupied → free (vehicle left). Body: { "floor": 0, "slot": 3 }
Route::post('/slot-status', [SlotStatusController::class, 'update']);
