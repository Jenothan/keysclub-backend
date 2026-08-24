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
        
        $todaysBookings = Booking::where('booking_date', $today)->count();
        $pendingBookings = Booking::where('status', 'Pending')->count();
        $confirmedBookings = Booking::where('status', 'Confirmed')->count();
        $newInquiries = Inquiry::where('status', 'Pending')->count();

        return response()->json([
            'todays_bookings' => $todaysBookings,
            'pending_requests' => $pendingBookings,
            'confirmed_bookings' => $confirmedBookings,
            'new_inquiries' => $newInquiries,
        ]);
    }
}
