<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['phone', 'otp_code', 'expires_at', 'purpose'])]
class OtpVerification extends Model
{
    /** @use HasFactory<\Database\Factories\OtpVerificationFactory> */
    use HasFactory;
}
