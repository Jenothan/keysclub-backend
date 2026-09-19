<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\User;
use App\Models\Inquiry;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function stats()
    {
        $today = Carbon::today()->format('Y-m-d');
        
        $todaysBookings = Booking::where('booking_date', $today)->whereIn('status', ['Confirmed', 'Pending'])->count();
        $pendingMemberships = \App\Models\MembershipRequest::where('status', 'Pending')->count();
        $confirmedBookings = Booking::where('status', 'Confirmed')->count();
        $newInquiries = Inquiry::where('status', 'Pending')->count();

        return response()->json([
            'todays_bookings' => $todaysBookings,
            'pending_memberships' => $pendingMemberships,
            'confirmed_bookings' => $confirmedBookings,
            'new_inquiries' => $newInquiries,
        ]);
    }

    public function sendDailySummarySms()
    {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('bookings:daily-summary');
        $output = \Illuminate\Support\Facades\Artisan::output();

        if ($exitCode === 0) {
            return response()->json([
                'message' => 'Daily booking summary SMS sent successfully!',
                'output' => trim($output)
            ]);
        }

        return response()->json([
            'message' => 'Failed to send daily booking summary SMS.',
            'output' => trim($output)
        ], 500);
    }
}
