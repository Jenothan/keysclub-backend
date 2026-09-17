<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'primary_phone', 
    'support_email', 
    'club_address', 
    'facebook_url', 
    'instagram_url', 
    'court_pricing', 
    'membership_pricing', 
    'registration_fee', 
    'full_day_pricing',
    'peak_start_time',
    'peak_end_time',
    'peak_off_days'
])]
class WebsiteData extends Model
{
    protected $casts = [
        'peak_off_days' => 'array',
    ];
}
