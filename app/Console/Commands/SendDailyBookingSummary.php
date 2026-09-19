<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\WebsiteData;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendDailyBookingSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:daily-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Send daily morning 5 AM SMS summary of today's court bookings to primary website contact phone.";

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::now('Asia/Colombo')->toDateString();
        $dateFormatted = Carbon::now('Asia/Colombo')->format('d M Y');

        // Fetch primary phone number from website data
        $websiteData = WebsiteData::first();
        $targetPhone = $websiteData?->primary_phone;

        if (!$targetPhone) {
            $this->error("No primary phone number found in website data settings.");
            Log::warning("Daily booking summary SMS failed: No primary_phone in website_data.");
            return 1;
        }

        // Fetch today's active bookings (Confirmed and Pending)
        $bookings = Booking::with(['user', 'court'])
            ->whereDate('booking_date', $today)
            ->whereIn('status', ['Confirmed', 'Pending'])
            ->orderBy('start_time', 'asc')
            ->get();

        if ($bookings->count() > 0) {
            $lines = [];
            $lines[] = "Badminton Court - Today's Bookings ({$dateFormatted}):";
            
            $grouped = $bookings->groupBy('booking_reference');
            $num = 1;

            foreach ($grouped as $ref => $group) {
                $first = $group->first();
                $customerName = $first->customer_name ?: ($first->user?->name ?: 'Guest');
                $customerPhone = $first->customer_phone ?: ($first->user?->phone ?: '');
                $phoneStr = $customerPhone ? "{$customerPhone}" : "";

                $slotTimes = [];
                foreach ($group as $b) {
                    $startStr = $b->start_time;
                    $endStr = $b->end_time;
                    try {
                        $startStr = Carbon::parse($today . ' ' . $b->start_time)->format('h:i A');
                        $endStr = Carbon::parse($today . ' ' . $b->end_time)->format('h:i A');
                    } catch (\Exception $e) {
                        // keep raw
                    }
                    $slotTimes[] = "{$startStr} - {$endStr}";
                }

                $timeStr = implode(', ', $slotTimes);
                $lines[] = "{$num}. {$customerName} - {$phoneStr} - ({$timeStr})";
                $num++;
            }
            
            $lines[] = "Total: {$bookings->count()} session(s).";
            $message = implode("\n", $lines);
        } else {
            $message = "Badminton Court: No court bookings scheduled for today ({$dateFormatted}).";
        }

        $this->info("Sending daily summary SMS to {$targetPhone}...");
        $this->info($message);

        $sent = SmsService::sendSms($targetPhone, $message);

        if ($sent) {
            $this->info("Daily booking summary SMS sent successfully!");
            Log::info("Daily booking summary SMS sent to {$targetPhone}");
            return 0;
        } else {
            $this->error("Failed to send daily booking summary SMS.");
            return 1;
        }
    }
}
