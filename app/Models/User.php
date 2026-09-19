<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password', 'profile_photo_path', 'role', 'phone_verified_at', 'is_guest', 'is_member', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public function isAdmin(): bool
    {
        return in_array($this->role, ['Admin', 'Super Admin']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'Super Admin';
    }

    public function isGuest(): bool
    {
        return (bool) $this->is_guest;
    }

    public function isMember(): bool
    {
        return (bool) $this->is_member || $this->isAdmin();
    }

    public function isRegistered(): bool
    {
        return !$this->is_guest && !empty($this->password);
    }

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function membershipRequests()
    {
        return $this->hasMany(MembershipRequest::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_guest' => 'boolean',
            'is_member' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
