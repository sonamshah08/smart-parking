<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ParkingLotController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/parking-lot', [ParkingLotController::class, 'getAvailability']);
Route::get('/parking-lot/{parking_master_id}', [ParkingLotController::class, 'getParkingLotStatusById']);
Route::post('/parking-spot/{id}/park', [ParkingLotController::class, 'park']);
Route::delete('/parking-spot/{id}/unpark', [ParkingLotController::class, 'unpark']);
