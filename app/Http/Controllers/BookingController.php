<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'upcoming');
        $query = $request->user()->bookings()->with('court');

        if ($tab === 'past') {
            $query->where('booking_date', '<', Carbon::today())
                  ->orWhere(function ($q) {
                      $q->where('booking_date', Carbon::today())
                        ->where('start_time', '<', Carbon::now()->format('H:i:s'));
                  });
        } else {
            $query->where('booking_date', '>', Carbon::today())
                  ->orWhere(function ($q) {
                      $q->where('booking_date', Carbon::today())
                        ->where('start_time', '>=', Carbon::now()->format('H:i:s'));
                  });
        }

        return response()->json($query->orderBy('booking_date')->orderBy('start_time')->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $isAdmin = in_array($user->role, ['Admin', 'Super Admin']);

        $rules = [
            'court_id' => 'required|exists:courts,id',
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s|after:start_time',
            'notes' => 'nullable|string',
        ];

        if ($isAdmin) {
            $rules['customer_name'] = 'nullable|string|max:255';
            $rules['customer_phone'] = 'nullable|string|max:255';
        }

        $request->validate($rules);

        $date = Carbon::parse($request->date)->format('Y-m-d');
        
        // Race condition check: make sure slot is still available
        $existing = Booking::where('court_id', $request->court_id)
            ->where('booking_date', $date)
            ->where('start_time', $request->start_time)
            ->whereIn('status', ['Pending', 'Confirmed'])
            ->lockForUpdate()
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'slot' => ['This slot has just been booked. Please choose another.'],
            ]);
        }

        $bookingData = [
            'booking_reference' => '#KC-' . strtoupper(Str::random(6)),
            'court_id' => $request->court_id,
            'booking_date' => $date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => 'Pending',
        ];

        if ($isAdmin && ($request->filled('customer_name') || $request->filled('customer_phone'))) {
            $bookingData['user_id'] = null;
            $bookingData['customer_name'] = $request->customer_name;
            $bookingData['customer_phone'] = $request->customer_phone;
            $bookingData['booked_by_id'] = $user->id;
            
            $booking = Booking::create($bookingData);
        } else {
            $bookingData['booked_by_id'] = $user->id;
            $booking = $user->bookings()->create($bookingData);
        }

        return response()->json(['message' => 'Booking created successfully', 'booking' => $booking], 201);
    }

    public function cancel(Request $request, $id)
    {
        $booking = $request->user()->bookings()->findOrFail($id);

        if ($booking->status === 'Cancelled') {
            return response()->json(['message' => 'Booking is already cancelled'], 400);
        }
        
        if ($booking->status === 'Completed') {
            return response()->json(['message' => 'Cannot cancel a completed booking'], 400);
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

        $booking = $request->user()->bookings()->findOrFail($id);

        if ($booking->status === 'Cancelled' || $booking->status === 'Completed') {
            return response()->json(['message' => 'Cannot reschedule a cancelled or completed booking'], 400);
        }

        $date = Carbon::parse($request->date)->format('Y-m-d');
        
        $existing = Booking::where('court_id', $booking->court_id)
            ->where('booking_date', $date)
            ->where('start_time', $request->start_time)
            ->where('id', '!=', $booking->id)
            ->whereIn('status', ['Pending', 'Confirmed'])
            ->lockForUpdate()
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'slot' => ['This slot is already booked. Please choose another.'],
            ]);
        }

        $booking->update([
            'booking_date' => $date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'rescheduled_by' => $request->user()->id,
            // Assuming rescheduling makes it pending again or keeps it confirmed? Usually pending.
            'status' => 'Pending' 
        ]);

        return response()->json(['message' => 'Booking rescheduled successfully', 'booking' => $booking]);
    }
}
