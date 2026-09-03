<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send SMS via SMSlenz.lk API
     */
    public static function sendSms(string $contact, string $message): bool
    {
        try {
            $formattedContact = self::formatContact($contact);

            $userId = config('services.sms.user_id') ?: env('SMS_USER_ID');
            $apiKey = config('services.sms.api_key') ?: env('SMS_API_KEY');
            $senderId = config('services.sms.sender_id') ?: env('SMS_SENDER_ID');
            $apiUrl = config('services.sms.api_url') ?: env('SMS_API_URL', 'https://smslenz.lk/api/send-sms');

            if (!$userId || !$apiKey || !$senderId) {
                Log::error("SMS service error: Credentials missing in .env configuration.");
                return false;
            }

            $response = Http::withoutVerifying()->timeout(15)->post($apiUrl, [
                'user_id' => $userId,
                'api_key' => $apiKey,
                'sender_id' => $senderId,
                'contact' => $formattedContact,
                'message' => $message,
            ]);

            if ($response->successful()) {
                Log::info("SMS sent successfully to {$formattedContact}", ['response' => $response->json()]);
                return true;
            }

            Log::error("Failed to send SMS to {$formattedContact}", ['response' => $response->body()]);
            return false;
        } catch (\Exception $e) {
            Log::error("SMS service exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Format phone number to Sri Lankan format (+947XXXXXXXX)
     */
    public static function formatContact(string $contact): string
    {
        $cleaned = preg_replace('/[^\d]/', '', $contact);
        
        if (str_starts_with($cleaned, '0')) {
            return '+94' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '94')) {
            return '+' . $cleaned;
        } elseif (strlen($cleaned) === 9) {
            return '+94' . $cleaned;
        }

        return '+' . $cleaned;
    }

    /**
     * Check if phone number has reached daily limit of 3 OTP SMS requests
     */
    public static function checkDailyOtpLimit(string $contact): bool
    {
        $formattedPhone = self::formatContact($contact);
        $cacheKey = 'otp_daily_count_' . preg_replace('/[^\d]/', '', $formattedPhone) . '_' . date('Y-m-d');
        $count = (int) \Illuminate\Support\Facades\Cache::get($cacheKey, 0);
        return $count < 3;
    }

    /**
     * Increment and record daily OTP request count for a phone number
     */
    public static function incrementDailyOtpCount(string $contact): int
    {
        $formattedPhone = self::formatContact($contact);
        $cacheKey = 'otp_daily_count_' . preg_replace('/[^\d]/', '', $formattedPhone) . '_' . date('Y-m-d');
        $count = (int) \Illuminate\Support\Facades\Cache::get($cacheKey, 0) + 1;
        \Illuminate\Support\Facades\Cache::put($cacheKey, $count, \Carbon\Carbon::now()->endOfDay());
        return $count;
    }
}
