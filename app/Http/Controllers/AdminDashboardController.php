<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function stats()
    {
        // Calculate basic stats for the dashboard
        $totalBookings = Booking::count();
        $pendingBookings = Booking::where('status', 'Pending')->count();
        $confirmedBookings = Booking::where('status', 'Confirmed')->count();
        $totalUsers = User::where('role', 'user')->count();

        return response()->json([
            'total_bookings' => $totalBookings,
            'pending_bookings' => $pendingBookings,
            'confirmed_bookings' => $confirmedBookings,
            'total_users' => $totalUsers,
        ]);
    }
}
