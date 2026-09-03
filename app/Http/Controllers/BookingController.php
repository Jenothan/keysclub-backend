<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Services\SmsService;
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
            $query->where(function ($q) {
                $q->where('booking_date', '<', Carbon::today())
                  ->orWhere(function ($sub) {
                      $sub->where('booking_date', Carbon::today())
                          ->where('start_time', '<', Carbon::now()->format('H:i:s'));
                  });
            });
        } else {
            $query->where(function ($q) {
                $q->where('booking_date', '>', Carbon::today())
                  ->orWhere(function ($sub) {
                      $sub->where('booking_date', Carbon::today())
                          ->where('start_time', '>=', Carbon::now()->format('H:i:s'));
                  });
            });
        }

        return response()->json($query->orderBy('booking_date')->orderBy('start_time')->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $isAdmin = in_array($user->role, ['Admin', 'Super Admin']);

        // Auto-resolve court_id if invalid or default 1 is passed
        $courtId = $request->court_id;
        if (!$courtId || !\App\Models\Court::where('id', $courtId)->exists()) {
            $firstCourt = \App\Models\Court::first();
            if ($firstCourt) {
                $courtId = $firstCourt->id;
                $request->merge(['court_id' => $courtId]);
            }
        }

        $request->validate([
            'court_id' => 'required|exists:courts,id',
            'date' => 'required|date|after_or_equal:today',
            'slots' => 'nullable|array',
            'slots.*.start_time' => 'required_with:slots|date_format:H:i:s',
            'slots.*.end_time' => 'required_with:slots|date_format:H:i:s',
            'start_time' => 'required_without:slots|date_format:H:i:s',
            'end_time' => 'required_without:slots|date_format:H:i:s|after:start_time',
            'notes' => 'nullable|string',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:255',
        ]);

        $date = Carbon::parse($request->date)->format('Y-m-d');
        $rawSlots = [];

        if ($request->has('slots') && is_array($request->slots) && count($request->slots) > 0) {
            $rawSlots = $request->slots;
        } else {
            $rawSlots = [
                ['start_time' => $request->start_time, 'end_time' => $request->end_time]
            ];
        }

        // Sort slots by start_time
        usort($rawSlots, function ($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });

        // Merge contiguous slots (e.g. 16:00:00-17:00:00 + 17:00:00-18:00:00 => 16:00:00-18:00:00)
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

        // Check availability for all merged chunks
        foreach ($mergedChunks as $chunk) {
            $existing = Booking::where('court_id', $courtId)
                ->where('booking_date', $date)
                ->where(function ($q) use ($chunk) {
                    $q->where('start_time', '<', $chunk['end_time'])
                      ->where('end_time', '>', $chunk['start_time']);
                })
                ->whereIn('status', ['Pending', 'Confirmed', 'Blocked'])
                ->lockForUpdate()
                ->first();

            $override = \App\Models\SlotOverride::whereDate('date', $date)
                ->where(function ($q) use ($courtId) {
                    $q->whereNull('court_id')->orWhere('court_id', $courtId);
                })
                ->where('start_time', '<', $chunk['end_time'])
                ->where('end_time', '>', $chunk['start_time'])
                ->first();

            $recurringBlock = \App\Models\RecurringBlockedSlot::where('is_active', true)
                ->where(function ($q) use ($courtId) {
                    $q->whereNull('court_id')->orWhere('court_id', $courtId);
                })
                ->where('start_time', '<', $chunk['end_time'])
                ->where('end_time', '>', $chunk['start_time'])
                ->first();

            $isUnavailable = $existing || ($override && $override->status === 'Blocked') || ($recurringBlock && (!$override || $override->status !== 'Available'));

            if ($isUnavailable) {
                throw ValidationException::withMessages([
                    'slot' => ['One or more of your selected time slots are currently unavailable. Please choose available slots.'],
                ]);
            }
        }

        // Generate ONE shared booking reference for this entire request
        $bookingReference = '#KC-' . strtoupper(Str::random(6));
        $createdBookings = [];
        $formattedTimeRanges = [];

        foreach ($mergedChunks as $chunk) {
            $bookingData = [
                'booking_reference' => $bookingReference,
                'court_id' => $courtId,
                'booking_date' => $date,
                'start_time' => $chunk['start_time'],
                'end_time' => $chunk['end_time'],
                'status' => 'Pending',
                'booked_by_id' => $user->id,
            ];

            if ($isAdmin && ($request->filled('customer_name') || $request->filled('customer_phone'))) {
                $bookingData['user_id'] = null;
                $bookingData['customer_name'] = $request->customer_name;
                $bookingData['customer_phone'] = $request->customer_phone;
                $bRecord = Booking::create($bookingData);
            } else {
                $bookingData['user_id'] = $user->id;
                $bRecord = Booking::create($bookingData);
            }

            $createdBookings[] = $bRecord;

            $startFmt = Carbon::parse($chunk['start_time'])->format('h:i A');
            $endFmt = Carbon::parse($chunk['end_time'])->format('h:i A');
            $formattedTimeRanges[] = "{$startFmt} - {$endFmt}";
        }

        $timeDisplay = implode(', ', $formattedTimeRanges);
        $dateDisplay = Carbon::parse($date)->format('d/m/Y');

        // Send 1 single SMS notification for the booking request
        $firstBooking = $createdBookings[0];
        $recipientPhone = $firstBooking->customer_phone ?: ($user ? $user->phone : null);
        $recipientName = $firstBooking->customer_name ?: ($user ? $user->name : 'Valued Member');

        if ($recipientPhone) {
            SmsService::sendSms($recipientPhone, "Dear {$recipientName}, your court booking request {$bookingReference} for {$dateDisplay} ({$timeDisplay}) has been received by KEYS Club.");
        }

        return response()->json([
            'message' => 'Booking request submitted successfully',
            'booking' => $createdBookings[0],
            'bookings' => $createdBookings,
            'booking_reference' => $bookingReference,
            'time_display' => $timeDisplay,
        ], 201);
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
