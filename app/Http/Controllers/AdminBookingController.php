<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;

class AdminBookingController extends Controller
{
    public function index()
    {
        // Get all bookings with user and court info, ordered by newest first
        $bookings = Booking::with(['user', 'court'])->latest()->get();
        return response()->json($bookings);
    }

    public function confirm($id)
    {
        $booking = Booking::findOrFail($id);
        
        if ($booking->status !== 'Pending') {
            return response()->json(['message' => 'Only pending bookings can be confirmed.'], 422);
        }

        $booking->status = 'Confirmed';
        $booking->save();

        // Optional: send SMS/Email notification to the user here
        
        return response()->json([
            'message' => 'Booking confirmed successfully.',
            'booking' => $booking
        ]);
    }

    public function reject($id)
    {
        $booking = Booking::findOrFail($id);
        
        if ($booking->status !== 'Pending') {
            return response()->json(['message' => 'Only pending bookings can be rejected.'], 422);
        }

        $booking->status = 'Rejected';
        $booking->save();

        return response()->json([
            'message' => 'Booking rejected successfully.',
            'booking' => $booking
        ]);
    }
}
