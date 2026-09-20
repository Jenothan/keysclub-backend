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
            ->whereIn('status', ['Confirmed', 'Blocked'])
            ->get();

        $isBlockedDate = \App\Models\BlockedDate::whereDate('date', $date)->exists();
        $recurringBlockedSlots = \App\Models\RecurringBlockedSlot::where('is_active', true)
            ->where(function ($q) use ($courtId) {
                $q->whereNull('court_id')->orWhere('court_id', $courtId);
            })->get();

        $slotOverrides = \App\Models\SlotOverride::whereDate('date', $date)
            ->where(function ($q) use ($courtId) {
                $q->whereNull('court_id')->orWhere('court_id', $courtId);
            })->get();

        $now = \Carbon\Carbon::now('Asia/Colombo');
        $todayStr = $now->format('Y-m-d');
        $isToday = ($date === $todayStr);
        $isPastDate = ($date < $todayStr);

        $availability = collect($slots)->map(function ($slot) use ($bookings, $isBlockedDate, $recurringBlockedSlots, $slotOverrides, $courtId, $date, $now, $isToday, $isPastDate) {
            $booking = $bookings->first(function ($b) use ($slot) {
                return $slot['start_time'] >= $b->start_time && $slot['end_time'] <= $b->end_time;
            });

            $override = $slotOverrides->first(function ($o) use ($slot) {
                return $slot['start_time'] >= $o->start_time && $slot['end_time'] <= $o->end_time;
            });

            $recurringBlock = $recurringBlockedSlots->first(function ($r) use ($slot) {
                return $slot['start_time'] >= $r->start_time && $slot['end_time'] <= $r->end_time;
            });

            $slotStartDateTime = \Carbon\Carbon::parse($date . ' ' . $slot['start_time'], 'Asia/Colombo');
            $slotCutoffTime = $slotStartDateTime->copy()->subHours(2);
            $isPastSlot = $now->greaterThanOrEqualTo($slotCutoffTime);

            if ($isBlockedDate || ($booking && $booking->status === 'Blocked')) {
                $status = 'Blocked';
            } elseif ($booking) {
                $status = ($booking->status === 'Confirmed' || $booking->status === 'Booked') ? 'Booked' : $booking->status;
            } elseif ($override) {
                $status = $override->status;
            } elseif ($recurringBlock) {
                $status = 'Blocked';
            } elseif ($isPastSlot) {
                $status = 'Past';
            } else {
                $status = 'Available';
            }

            return [
                'court_id' => (int) $courtId,
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'status' => $status,
                'is_past' => $isPastSlot,
                'is_recurring_blocked' => !!$recurringBlock,
                'is_overridden' => !!$override,
                'user' => $booking && $booking->user ? $booking->user->name : ($booking && $booking->customer_name ? $booking->customer_name : null),
            ];
        });

        return response()->json($availability);
    }
}
