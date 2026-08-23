<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PhoneUpdateController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/courts', [CourtController::class, 'index']);
Route::get('/availability', [AvailabilityController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Profile
    Route::get('/user', [ProfileController::class, 'show']);
    Route::post('/user/photo', [ProfileController::class, 'updatePhoto']);
    Route::post('/user/password', [ProfileController::class, 'updatePassword']);
    
    // Phone OTP Flow
    Route::post('/user/phone/request-otp', [PhoneUpdateController::class, 'requestOtp']);
    Route::post('/user/phone/verify-otp', [PhoneUpdateController::class, 'verifyOtp']);

    // Bookings
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
});
