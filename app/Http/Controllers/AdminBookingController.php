<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Services\SmsService;

class AdminBookingController extends Controller
{
    public function index()
    {
        // Get all bookings with user, court, bookedBy and action admin info, ordered by newest first
        $bookings = Booking::with([
            'user', 
            'court', 
            'bookedBy', 
            'confirmedBy', 
            'rejectedBy', 
            'cancelledBy', 
            'rescheduledBy'
        ])->latest()->get();
        return response()->json($bookings);
    }

    public function confirm($id)
    {
        $targetBooking = Booking::with('user')->findOrFail($id);
        
        if ($targetBooking->status !== 'Pending') {
            return response()->json(['message' => 'Only pending bookings can be confirmed.'], 422);
        }

        return \Illuminate\Support\Facades\DB::transaction(function() use ($targetBooking) {
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
                SmsService::sendSms($recipientPhone, "Dear {$recipientName}, your booking {$reference} for {$dateDisplay} ({$timeDisplay}) at Badminton Court, Karanavai East Youth Sports Club has been CONFIRMED! Play • Grow • Win!");
            }
            
            return response()->json([
                'message' => 'Booking confirmed successfully.',
                'booking' => $targetBooking
            ]);
        });
    }

    public function reject($id)
    {
        $targetBooking = Booking::findOrFail($id);
        
        if ($targetBooking->status !== 'Pending') {
            return response()->json(['message' => 'Only pending bookings can be rejected.'], 422);
        }

        return \Illuminate\Support\Facades\DB::transaction(function() use ($targetBooking) {
            $reference = $targetBooking->booking_reference;
            if ($reference) {
                Booking::where('booking_reference', $reference)
                    ->where('status', 'Pending')
                    ->update([
                        'status' => 'Rejected',
                        'rejected_by' => request()->user()->id,
                        'updated_at' => now(),
                    ]);
            } else {
                $targetBooking->update([
                    'status' => 'Rejected',
                    'rejected_by' => request()->user()->id,
                ]);
            }

            $recipientPhone = $targetBooking->customer_phone ?: ($targetBooking->user ? $targetBooking->user->phone : null);
            $recipientName = $targetBooking->customer_name ?: ($targetBooking->user ? $targetBooking->user->name : 'Valued Member');

            if ($recipientPhone) {
                SmsService::sendSms($recipientPhone, "Dear {$recipientName}, your booking request {$reference} for KEYS Club could not be confirmed at this time. Please check available slots or contact us.");
            }

            return response()->json([
                'message' => 'Booking rejected successfully.',
                'booking' => $targetBooking
            ]);
        });
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

        return \Illuminate\Support\Facades\DB::transaction(function() use ($mergedChunks, $courtId, $date, $user, $request) {
            foreach ($mergedChunks as $chunk) {
                $existing = Booking::where('court_id', $courtId)
                    ->where('booking_date', $date)
                    ->where(function ($q) use ($chunk) {
                        $q->where('start_time', '<', $chunk['end_time'])
                          ->where('end_time', '>', $chunk['start_time']);
                    })
                    ->whereIn('status', ['Confirmed', 'Blocked'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'slot' => ['This slot is currently booked. Please choose another slot.'],
                    ]);
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
        });
    }

    public function cancel(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        if ($booking->status === 'Cancelled') {
            return response()->json(['message' => 'Booking is already cancelled'], 400);
        }

        \Illuminate\Support\Facades\DB::transaction(function() use ($booking, $request) {
            if ($booking->booking_reference) {
                Booking::where('booking_reference', $booking->booking_reference)
                    ->where('status', 'Confirmed')
                    ->update([
                        'status' => 'Cancelled',
                        'cancelled_by' => $request->user()->id,
                        'updated_at' => now(),
                    ]);
            } else {
                $booking->update([
                    'status' => 'Cancelled',
                    'cancelled_by' => $request->user()->id
                ]);
            }
        });

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
        
        $bookingReference = $booking->booking_reference;
        $relatedBookings = ($bookingReference && $bookingReference !== '#KC-') 
            ? Booking::where('booking_reference', $bookingReference)->get()
            : collect([$booking]);

        $relatedIds = $relatedBookings->pluck('id')->toArray();

        // Check conflict against OTHER bookings (excluding related bookings in same reference)
        $conflict = Booking::where('court_id', $booking->court_id)
            ->where('booking_date', $date)
            ->whereNotIn('id', $relatedIds)
            ->where(function ($q) use ($request) {
                $q->where('start_time', '<', $request->end_time)
                  ->where('end_time', '>', $request->start_time);
            })
            ->whereIn('status', ['Confirmed', 'Blocked'])
            ->lockForUpdate()
            ->first();

        if ($conflict) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'slot' => ['This slot is currently booked. Please choose another slot.'],
            ]);
        }

        \Illuminate\Support\Facades\DB::transaction(function() use ($relatedBookings, $booking, $date, $request) {
            $primaryBooking = $relatedBookings->first() ?? $booking;

            if ($request->has('slots') && is_array($request->slots) && count($request->slots) > 0) {
                $requestSlots = $request->slots;
                $existingCount = count($relatedBookings);
                $newCount = count($requestSlots);

                for ($i = 0; $i < max($existingCount, $newCount); $i++) {
                    if ($i < $newCount) {
                        $s = $requestSlots[$i];
                        if ($i < $existingCount) {
                            $relatedBookings[$i]->update([
                                'booking_date' => $date,
                                'start_time' => $s['start_time'],
                                'end_time' => $s['end_time'],
                                'rescheduled_by' => $request->user()->id,
                                'status' => 'Confirmed',
                                'updated_at' => now(),
                            ]);
                        } else {
                            Booking::create([
                                'booking_reference' => $primaryBooking->booking_reference,
                                'court_id' => $primaryBooking->court_id,
                                'user_id' => $primaryBooking->user_id,
                                'customer_name' => $primaryBooking->customer_name,
                                'customer_phone' => $primaryBooking->customer_phone,
                                'booking_date' => $date,
                                'start_time' => $s['start_time'],
                                'end_time' => $s['end_time'],
                                'status' => 'Confirmed',
                                'booked_by_id' => $primaryBooking->booked_by_id,
                                'rescheduled_by' => $request->user()->id,
                            ]);
                        }
                    } else {
                        $relatedBookings[$i]->delete();
                    }
                }
            } else {
                foreach ($relatedBookings as $b) {
                    $b->update([
                        'booking_date' => $date,
                        'start_time' => $request->start_time,
                        'end_time' => $request->end_time,
                        'rescheduled_by' => $request->user()->id,
                        'status' => 'Confirmed',
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        // Send instant SMS notification to customer
        $recipientPhone = $booking->customer_phone ?: ($booking->user ? $booking->user->phone : null);
        $recipientName = $booking->customer_name ?: ($booking->user ? $booking->user->name : 'Valued Member');

        if ($recipientPhone) {
            $dateDisplay = \Carbon\Carbon::parse($date)->format('d/m/Y');
            $startFmt = \Carbon\Carbon::parse($request->start_time)->format('h:i A');
            $endFmt = \Carbon\Carbon::parse($request->end_time)->format('h:i A');
            $ref = $booking->booking_reference ?: "#KC-{$booking->id}";

            \App\Services\SmsService::sendSms(
                $recipientPhone,
                "Dear {$recipientName}, your court booking {$ref} has been RESCHEDULED to {$dateDisplay} ({$startFmt} - {$endFmt}) at Badminton Court, Karanavai East Youth Sports Club! Play • Grow • Win!"
            );
        }

        return response()->json(['message' => 'Booking rescheduled successfully', 'booking' => $booking]);
    }
}
