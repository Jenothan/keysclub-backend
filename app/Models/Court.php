<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'type', 'status'])]
class Court extends Model
{
    /** @use HasFactory<\Database\Factories\CourtFactory> */
    use HasFactory;

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
