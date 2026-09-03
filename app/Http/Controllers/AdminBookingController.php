<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Services\SmsService;

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
        $targetBooking = Booking::with('user')->findOrFail($id);
        
        if ($targetBooking->status !== 'Pending') {
            return response()->json(['message' => 'Only pending bookings can be confirmed.'], 422);
        }

        $reference = $targetBooking->booking_reference;
        $groupBookings = Booking::with('user')
            ->where('booking_reference', $reference)
            ->where('status', 'Pending')
            ->get();

        if ($groupBookings->isEmpty()) {
            $groupBookings = collect([$targetBooking]);
        }

        $timeRanges = [];
        foreach ($groupBookings as $b) {
            $b->status = 'Confirmed';
            $b->confirmed_by = request()->user()->id;
            $b->save();

            $startFmt = \Carbon\Carbon::parse($b->start_time)->format('h:i A');
            $endFmt = \Carbon\Carbon::parse($b->end_time)->format('h:i A');
            $timeRanges[] = "{$startFmt} - {$endFmt}";
        }

        $timeDisplay = implode(', ', array_unique($timeRanges));
        $dateDisplay = \Carbon\Carbon::parse($targetBooking->booking_date)->format('d/m/Y');

        // Send 1 single SMS notification for booking confirmation
        $recipientPhone = $targetBooking->customer_phone ?: ($targetBooking->user ? $targetBooking->user->phone : null);
        $recipientName = $targetBooking->customer_name ?: ($targetBooking->user ? $targetBooking->user->name : 'Valued Member');

        if ($recipientPhone) {
            SmsService::sendSms($recipientPhone, "Dear {$recipientName}, your booking {$reference} for {$dateDisplay} ({$timeDisplay}) at KEYS Club has been CONFIRMED! Play • Grow • Win.");
        }
        
        return response()->json([
            'message' => 'Booking confirmed successfully.',
            'booking' => $targetBooking
        ]);
    }

    public function reject($id)
    {
        $targetBooking = Booking::findOrFail($id);
        
        if ($targetBooking->status !== 'Pending') {
            return response()->json(['message' => 'Only pending bookings can be rejected.'], 422);
        }

        $reference = $targetBooking->booking_reference;
        Booking::where('booking_reference', $reference)
            ->where('status', 'Pending')
            ->update([
                'status' => 'Rejected',
                'rejected_by' => request()->user()->id,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Booking rejected successfully.',
            'booking' => $targetBooking
        ]);
    }

    public function blockSlots(Request $request)
    {
        $request->validate([
            'court_id' => 'nullable|exists:courts,id',
            'date' => 'required|date|after_or_equal:today',
            'slots' => 'required|array|min:1',
            'slots.*.start_time' => 'required|date_format:H:i:s',
            'slots.*.end_time' => 'required|date_format:H:i:s',
            'notes' => 'nullable|string',
        ]);

        $courtId = $request->court_id;
        if (!$courtId || !\App\Models\Court::where('id', $courtId)->exists()) {
            $courtId = \App\Models\Court::first()?->id ?? 1;
        }

        $date = \Carbon\Carbon::parse($request->date)->format('Y-m-d');
        $user = $request->user();

        // Sort slots by start_time
        $rawSlots = $request->slots;
        usort($rawSlots, function ($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });

        // Merge contiguous slots for block action
        $mergedChunks = [];
        foreach ($rawSlots as $s) {
            if (empty($mergedChunks)) {
                $mergedChunks[] = $s;
            } else {
                $lastIdx = count($mergedChunks) - 1;
                if ($mergedChunks[$lastIdx]['end_time'] === $s['start_time']) {
                    $mergedChunks[$lastIdx]['end_time'] = $s['end_time'];
                } else {
                    $mergedChunks[] = $s;
                }
            }
        }

        $blockedRecords = [];
        $blockRef = '#BLOCK-' . strtoupper(\Illuminate\Support\Str::random(5));

        foreach ($mergedChunks as $chunk) {
            $blockedRecords[] = Booking::create([
                'booking_reference' => $blockRef,
                'court_id' => $courtId,
                'booking_date' => $date,
                'start_time' => $chunk['start_time'],
                'end_time' => $chunk['end_time'],
                'status' => 'Blocked',
                'customer_name' => 'Blocked by Admin',
                'booked_by_id' => $user->id,
                'notes' => $request->notes ?? 'Blocked by Admin',
            ]);
        }

        return response()->json(['message' => 'Slots blocked successfully', 'blocked' => $blockedRecords], 201);
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
