<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Services\SmsService;

class AdminManagementController extends Controller
{
    public function index()
    {
        $admins = User::where('role', 'Admin')->get();
        return response()->json($admins);
    }

    public function destroy($id)
    {
        $admin = User::findOrFail($id);
        
        if ($admin->role !== 'Admin') {
            return response()->json(['message' => 'Can only delete Admin users.'], 403);
        }

        $admin->delete();
        
        return response()->json(['message' => 'Admin removed successfully.']);
    }

    public function requestOtp(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:users,phone',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        // Max limit of 5 Admins check
        $adminCount = User::where('role', 'Admin')->count();
        if ($adminCount >= 5) {
            return response()->json(['message' => 'Maximum limit of 5 administrators reached.'], 422);
        }

        if (!SmsService::checkDailyOtpLimit($validated['phone'])) {
            return response()->json([
                'message' => 'Maximum limit of 3 OTP requests per day reached for this phone number. Please try again tomorrow.'
            ], 422);
        }

        // Generate OTP
        $otpCode = (string) rand(1000, 9999);
        
        // Save OTP
        OtpVerification::updateOrCreate(
            ['phone' => $validated['phone'], 'purpose' => 'admin_creation'],
            ['otp_code' => $otpCode, 'expires_at' => now()->addMinutes(10), 'purpose' => 'admin_creation']
        );

        // Save admin details temporarily in cache
        Cache::put('admin_creation_' . $validated['phone'], $validated, now()->addMinutes(10));

        SmsService::incrementDailyOtpCount($validated['phone']);

        // Send OTP via SMS
        SmsService::sendSms($validated['phone'], "Your KEYS Club Admin creation OTP is: {$otpCode}. Valid for 10 minutes.");

        return response()->json([
            'message' => 'OTP sent successfully.'
        ]);
    }

    public function verifyAndAdd(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
            'otp_code' => 'required|string',
        ]);

        $phone = $request->phone;
        $otpCode = $request->otp_code;

        // Verify OTP
        $verification = OtpVerification::where('phone', $phone)
            ->where('purpose', 'admin_creation')
            ->where('otp_code', $otpCode)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        // Retrieve temp data
        $adminData = Cache::get('admin_creation_' . $phone);

        if (!$adminData) {
            return response()->json(['message' => 'Registration session expired. Please request OTP again.'], 422);
        }

        // Final max limit check (in case concurrent requests)
        $adminCount = User::where('role', 'Admin')->count();
        if ($adminCount >= 5) {
            return response()->json(['message' => 'Maximum limit of 5 administrators reached.'], 422);
        }

        // Create Admin
        $admin = User::create([
            'name' => $adminData['name'],
            'phone' => $adminData['phone'],
            'email' => $adminData['email'],
            'password' => Hash::make($adminData['password']),
            'role' => 'Admin',
            'phone_verified_at' => now(),
        ]);

        // Cleanup
        $verification->delete();
        Cache::forget('admin_creation_' . $phone);

        return response()->json([
            'message' => 'Admin created successfully.',
            'admin' => $admin
        ], 201);
    }
}
