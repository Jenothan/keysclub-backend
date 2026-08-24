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
