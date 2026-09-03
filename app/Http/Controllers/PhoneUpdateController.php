<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OtpVerification;
use Illuminate\Validation\ValidationException;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PhoneUpdateController extends Controller
{
    public function requestOtp(Request $request)
    {
        $request->validate([
            'purpose' => 'required|in:old_phone_verify,new_phone_verify',
            'new_phone' => 'required_if:purpose,new_phone_verify|nullable|string|unique:users,phone',
        ]);

        $phone = $request->purpose === 'old_phone_verify' 
            ? $request->user()->phone 
            : $request->new_phone;

        if (!SmsService::checkDailyOtpLimit($phone)) {
            return response()->json([
                'message' => 'Maximum limit of 3 OTP requests per day reached for this phone number. Please try again tomorrow.'
            ], 422);
        }

        // Generate a 4 digit OTP
        $otp = rand(1000, 9999);

        OtpVerification::create([
            'phone' => $phone,
            'otp_code' => $otp,
            'purpose' => $request->purpose,
            'expires_at' => Carbon::now()->addMinutes(10)
        ]);

        SmsService::incrementDailyOtpCount($phone);

        // Send OTP via SMSlenz.lk API
        SmsService::sendSms($phone, "Your KEYS Club phone update OTP is: {$otp}. Valid for 10 minutes.");

        return response()->json(['message' => 'OTP sent successfully']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|string',
            // frontend could send purpose to make sure we verify the right one
            'purpose' => 'nullable|in:old_phone_verify,new_phone_verify',
            'new_phone' => 'nullable|string'
        ]);

        // We find the latest unexpired OTP for this user's old phone OR the new phone they provided
        $phoneToVerify = $request->new_phone ?? $request->user()->phone;

        $verification = OtpVerification::where('phone', $phoneToVerify)
            ->where('otp_code', $request->otp)
            ->where('expires_at', '>', Carbon::now())
            ->when($request->purpose, function ($query, $purpose) {
                return $query->where('purpose', $purpose);
            })
            ->latest()
            ->first();

        if (!$verification) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired OTP.'],
            ]);
        }

        // If it was for new phone verify, we update the user's phone number
        if ($verification->purpose === 'new_phone_verify') {
            $request->user()->update([
                'phone' => $verification->phone,
                'phone_verified_at' => Carbon::now(),
            ]);
            
            // Delete old verifications
            OtpVerification::where('phone', $verification->phone)->delete();
            
            return response()->json(['message' => 'Phone number updated successfully']);
        }

        // If it was old_phone_verify, just return success so frontend moves to next step
        return response()->json(['message' => 'OTP verified successfully']);
    }
}
