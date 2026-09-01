<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PhoneUpdateController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminBookingController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\WebsiteDataController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\InquiryController;

Route::post('/register/request-otp', [AuthController::class, 'requestRegisterOtp']);
Route::post('/register/verify', [AuthController::class, 'verifyAndRegister']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/forgot/request-otp', [AuthController::class, 'requestForgotPasswordOtp']);
Route::post('/password/forgot/reset', [AuthController::class, 'resetPasswordWithOtp']);

Route::get('/courts', [CourtController::class, 'index']);
Route::get('/availability', [AvailabilityController::class, 'index']);

// Public Website Data
Route::get('/website-data', [WebsiteDataController::class, 'show']);

// Public Inquiries
Route::post('/inquiries', [InquiryController::class, 'store']);

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
    Route::post('/bookings/{id}/reschedule', [BookingController::class, 'reschedule']);
});

// --- GENERAL ADMIN ROUTES ---
Route::middleware(['auth:sanctum', 'role:Admin,Super Admin'])->prefix('admin')->group(function () {
    // Dashboard Stats
    Route::get('/stats', [AdminDashboardController::class, 'stats']);
    
    // Users Management
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users/{id}/toggle-status', [AdminUserController::class, 'toggleStatus']);
    
    // Bookings Management
    Route::get('/bookings', [AdminBookingController::class, 'index']);
    Route::post('/bookings/{id}/confirm', [AdminBookingController::class, 'confirm']);
    Route::post('/bookings/{id}/reject', [AdminBookingController::class, 'reject']);
    Route::post('/bookings/{id}/cancel', [AdminBookingController::class, 'cancel']);
    Route::post('/bookings/{id}/reschedule', [AdminBookingController::class, 'reschedule']);
    Route::post('/availability/block', [AdminBookingController::class, 'blockSlots']);

    // Blocked Dates & Recurring Slots
    Route::get('/blocked-dates', [App\Http\Controllers\AdminBlockedDateController::class, 'index']);
    Route::post('/blocked-dates', [App\Http\Controllers\AdminBlockedDateController::class, 'store']);
    Route::delete('/blocked-dates/{date}', [App\Http\Controllers\AdminBlockedDateController::class, 'destroy']);

    Route::get('/recurring-blocked-slots', [App\Http\Controllers\RecurringBlockedSlotController::class, 'index']);
    Route::post('/recurring-blocked-slots', [App\Http\Controllers\RecurringBlockedSlotController::class, 'store']);
    Route::delete('/recurring-blocked-slots/{id}', [App\Http\Controllers\RecurringBlockedSlotController::class, 'destroy']);
    Route::post('/slot-overrides', [App\Http\Controllers\RecurringBlockedSlotController::class, 'setOverride']);
    Route::delete('/slot-overrides', [App\Http\Controllers\RecurringBlockedSlotController::class, 'removeOverride']);

    // Inquiries Management
    Route::get('/inquiries', [InquiryController::class, 'index']);
    Route::post('/inquiries/{id}/resolve', [InquiryController::class, 'resolve']);
});

// --- SUPER ADMIN EXCLUSIVE ROUTES ---
Route::middleware(['auth:sanctum', 'role:Super Admin'])->prefix('super-admin')->group(function () {
    // Admin Management (Max 5 Limit)
    Route::get('/managers', [AdminManagementController::class, 'index']);
    Route::delete('/managers/{id}', [AdminManagementController::class, 'destroy']);
    
    // Admin Creation (OTP Flow)
    Route::post('/managers/request-otp', [AdminManagementController::class, 'requestOtp']);
    Route::post('/managers/verify-add', [AdminManagementController::class, 'verifyAndAdd']);
    
    // Website Data Config
    Route::post('/website-data', [WebsiteDataController::class, 'update']);
});
