<?php

use App\Http\Controllers\RfidScannedController;
use Illuminate\Support\Facades\Route;

Route::post('/rfid-scan', [RfidScannedController::class, 'rfidScanned']);
Route::post('/rfid-scan/details', [RfidScannedController::class, 'saveDetails']);
Route::post('/rfid-scan/register', [RfidScannedController::class, 'registerVehicle']);
Route::post('/kiosk-mode', [RfidScannedController::class, 'setMode']);
