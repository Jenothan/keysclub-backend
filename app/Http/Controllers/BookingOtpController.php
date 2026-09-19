<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\OtpVerification;
use App\Services\SmsService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class BookingOtpController extends Controller
{
    public function requestOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        if (!SmsService::checkDailyOtpLimit($request->phone)) {
            return response()->json([
                'message' => 'Maximum limit of 3 OTP requests per day reached for this phone number. Please try again tomorrow.'
            ], 422);
        }

        $otpCode = (string) rand(1000, 9999);

        OtpVerification::updateOrCreate(
            ['phone' => $request->phone, 'purpose' => 'booking'],
            ['otp_code' => $otpCode, 'expires_at' => now()->addMinutes(10), 'purpose' => 'booking']
        );

        SmsService::incrementDailyOtpCount($request->phone);

        SmsService::sendSms(
            $request->phone,
            "Your OTP to book a court slot at Karanavai East Youth Sports Club is: {$otpCode}. Valid for 10 minutes."
        );

        return response()->json([
            'message' => 'OTP sent successfully.'
        ], 200);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp_code' => 'required|string',
            'name' => 'nullable|string|max:255',
        ]);

        $verification = OtpVerification::where('phone', $request->phone)
            ->where('purpose', 'booking')
            ->where('otp_code', $request->otp_code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        if ($user) {
            $isRegistered = !$user->is_guest && !empty($user->password);
            $isGuest = !$isRegistered;

            if ($request->filled('name') && ($user->name === 'Guest User' || empty($user->name))) {
                $user->name = $request->name;
                $user->save();
            }
        } else {
            $user = User::create([
                'name' => $request->name ?: 'Guest User',
                'phone' => $request->phone,
                'password' => null,
                'is_guest' => true,
                'phone_verified_at' => now(),
            ]);
            $isRegistered = false;
            $isGuest = true;
        }

        OtpVerification::where('phone', $request->phone)
            ->where('purpose', 'booking')
            ->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'is_registered' => $isRegistered,
            'is_guest' => $isGuest,
        ]);
    }

    public function setPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8',
            'name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user->password = Hash::make($request->password);
        $user->is_guest = false;

        if ($request->filled('name')) {
            $user->name = $request->name;
        }

        $user->save();

        SmsService::sendSms(
            $user->phone,
            "Welcome to Karanavai East Youth Sports Club, {$user->name}! Your account has been registered successfully. Play • Grow • Win!"
        );

        return response()->json([
            'message' => 'Account created successfully.',
            'user' => $user,
        ]);
    }
}
