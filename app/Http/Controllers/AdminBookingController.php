<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;

class AdminBookingController extends Controller
{
    public function index()
    {
        // Get all bookings with user, court and bookedBy info, ordered by newest first
        $bookings = Booking::with(['user', 'court', 'bookedBy'])->latest()->get();
        return response()->json($bookings);
    }

    public function confirm($id)
    {
        $booking = Booking::findOrFail($id);
        
        if ($booking->status !== 'Pending') {
            return response()->json(['message' => 'Only pending bookings can be confirmed.'], 422);
        }

        $booking->status = 'Confirmed';
        $booking->confirmed_by = request()->user()->id;
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
        $booking->rejected_by = request()->user()->id;
        $booking->save();

        return response()->json([
            'message' => 'Booking rejected successfully.',
            'booking' => $booking
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        if ($booking->status === 'Cancelled') {
            return response()->json(['message' => 'Booking is already cancelled'], 400);
        }

        $booking->update([
            'status' => 'Cancelled',
            'cancelled_by' => $request->user()->id
        ]);

        return response()->json(['message' => 'Booking cancelled successfully', 'booking' => $booking]);
    }

    public function reschedule(Request $request, $id)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s|after:start_time',
        ]);

        $booking = Booking::findOrFail($id);

        if ($booking->status === 'Cancelled' || $booking->status === 'Completed') {
            return response()->json(['message' => 'Cannot reschedule a cancelled or completed booking'], 400);
        }

        $date = \Carbon\Carbon::parse($request->date)->format('Y-m-d');
        
        $existing = Booking::where('court_id', $booking->court_id)
            ->where('booking_date', $date)
            ->where('start_time', $request->start_time)
            ->where('id', '!=', $booking->id)
            ->whereIn('status', ['Pending', 'Confirmed'])
            ->lockForUpdate()
            ->first();

        if ($existing) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'slot' => ['This slot is already booked. Please choose another.'],
            ]);
        }

        $booking->update([
            'booking_date' => $date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'rescheduled_by' => $request->user()->id,
            // Keep status as it was or set to Confirmed since admin is doing it. We'll set to Confirmed.
            'status' => 'Confirmed' 
        ]);

        return response()->json(['message' => 'Booking rescheduled successfully', 'booking' => $booking]);
    }
}
