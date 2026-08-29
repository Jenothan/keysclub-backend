<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use Carbon\Carbon;

class AvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $date = \Carbon\Carbon::parse($request->date)->format('Y-m-d');
        $courtId = $request->court_id;
        if (!$courtId || !\App\Models\Court::where('id', $courtId)->exists()) {
            $firstCourt = \App\Models\Court::first();
            if (!$firstCourt) {
                return response()->json([], 404); // Or any error indicating no courts
            }
            $courtId = $firstCourt->id;
        }

        // Operating hours: 6:00 AM to 10:00 PM (22:00)
        $startHour = 6;
        $endHour = 22;

        $slots = [];
        for ($i = $startHour; $i < $endHour; $i++) {
            $slots[] = [
                'start_time' => sprintf('%02d:00:00', $i),
                'end_time' => sprintf('%02d:00:00', $i + 1),
            ];
        }

        $bookings = Booking::with('user')->where('court_id', $courtId)
            ->where('booking_date', $date)
            ->whereIn('status', ['Pending', 'Confirmed'])
            ->get();

        $isBlockedDate = \App\Models\BlockedDate::whereDate('date', $date)->exists();

        $availability = collect($slots)->map(function ($slot) use ($bookings, $isBlockedDate) {
            $booking = $bookings->firstWhere('start_time', $slot['start_time']);
            
            if ($isBlockedDate) {
                $status = 'Blocked';
            } elseif ($booking) {
                $status = $booking->status === 'Confirmed' ? 'Booked' : 'Pending';
            } else {
                $status = 'Available';
            }

            return [
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'status' => $status,
                'user' => $booking && $booking->user ? $booking->user->name : null,
            ];
        });

        return response()->json($availability);
    }
}
