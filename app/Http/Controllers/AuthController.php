<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

use App\Models\OtpVerification;
use App\Services\SmsService;

class AuthController extends Controller
{
    public function requestRegisterOtp(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if (!SmsService::checkDailyOtpLimit($request->phone)) {
            return response()->json([
                'message' => 'Maximum limit of 3 OTP requests per day reached for this phone number. Please try again tomorrow.'
            ], 422);
        }

        $otpCode = (string) rand(1000, 9999);

        OtpVerification::updateOrCreate(
            ['phone' => $request->phone, 'purpose' => 'registration'],
            ['otp_code' => $otpCode, 'expires_at' => now()->addMinutes(10), 'purpose' => 'registration']
        );

        SmsService::incrementDailyOtpCount($request->phone);

        // Send OTP via SMSlenz.lk API
        SmsService::sendSms($request->phone, "Your KEYS Club signup OTP is: {$otpCode}. Valid for 10 minutes.");

        return response()->json([
            'message' => 'OTP sent successfully.'
        ], 200);
    }

    public function verifyAndRegister(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|unique:users',
            'password' => 'required|string|min:8',
            'otp_code' => 'required|string',
        ]);

        $verification = OtpVerification::where('phone', $request->phone)
            ->where('purpose', 'registration')
            ->where('otp_code', $request->otp_code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        OtpVerification::where('phone', $request->phone)->delete();

        // Send Welcome SMS via SMSlenz.lk API
        SmsService::sendSms($user->phone, "Welcome to KEYS Club, {$user->name}! Your account has been registered successfully. Play • Grow • Win!");

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    public function requestForgotPasswordOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|exists:users,phone',
        ]);

        if (!SmsService::checkDailyOtpLimit($request->phone)) {
            return response()->json([
                'message' => 'Maximum limit of 3 OTP requests per day reached for this phone number. Please try again tomorrow.'
            ], 422);
        }

        $otpCode = (string) rand(1000, 9999);

        OtpVerification::updateOrCreate(
            ['phone' => $request->phone, 'purpose' => 'password_reset'],
            ['otp_code' => $otpCode, 'expires_at' => now()->addMinutes(10), 'purpose' => 'password_reset']
        );

        SmsService::incrementDailyOtpCount($request->phone);

        // Send Password Reset OTP via SMSlenz.lk API
        SmsService::sendSms($request->phone, "Your KEYS Club password reset OTP is: {$otpCode}. Valid for 10 minutes.");

        return response()->json([
            'message' => 'OTP sent successfully.'
        ], 200);
    }

    public function resetPasswordWithOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|exists:users,phone',
            'otp_code' => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        $verification = OtpVerification::where('phone', $request->phone)
            ->where('purpose', 'password_reset')
            ->where('otp_code', $request->otp_code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        $user = User::where('phone', $request->phone)->first();
        if ($user) {
            $user->password = Hash::make($request->password);
            $user->save();
        }

        OtpVerification::where('phone', $request->phone)->where('purpose', 'password_reset')->delete();

        return response()->json([
            'message' => 'Password reset successfully. You can now login.'
        ]);
    }
}
