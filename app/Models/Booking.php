<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['booking_reference', 'user_id', 'customer_name', 'customer_phone', 'booked_by_id', 'court_id', 'booking_date', 'start_time', 'end_time', 'status', 'confirmed_by', 'rejected_by', 'cancelled_by', 'rescheduled_by'])]
class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;

    public function getStatusAttribute($value)
    {
        if ($value !== 'Confirmed') {
            return $value;
        }

        try {
            $now = \Carbon\Carbon::now('Asia/Colombo');
            $bookingDateStr = $this->booking_date;
            if (is_string($bookingDateStr) && str_contains($bookingDateStr, 'T')) {
                $bookingDateStr = explode('T', $bookingDateStr)[0];
            }

            $startDateTime = \Carbon\Carbon::parse($bookingDateStr . ' ' . $this->start_time);
            $endDateTime = \Carbon\Carbon::parse($bookingDateStr . ' ' . $this->end_time);

            if ($now->greaterThanOrEqualTo($startDateTime) && $now->lessThanOrEqualTo($endDateTime)) {
                return 'Ongoing';
            } elseif ($now->greaterThan($endDateTime)) {
                return 'Completed';
            }
        } catch (\Exception $e) {
            // fallback
        }

        return $value;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function bookedBy()
    {
        return $this->belongsTo(User::class, 'booked_by_id');
    }
}
