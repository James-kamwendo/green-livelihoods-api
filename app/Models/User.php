<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\CustomVerifyEmail;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmail);
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            // Assign 'unverified' role if no role is provided
            if (!$user->hasAnyRole(['admin', 'artisan', 'buyer', 'marketer'])) {
                $user->assignRole('unverified');
            }
        });
    }
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'gender',
        'age',
        'location',
        'password',
        'provider',
        'provider_id',
        'provider_token',
        'provider_refresh_token',
        'email_verified_at',
        'phone_verified_at',
        'verification_token',
        'verification_token_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'provider_token',
        'provider_refresh_token',
        'verification_token',
        'verification_token_expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'verification_token_expires_at' => 'datetime',
            'password' => 'hashed',
            'age' => 'integer',
        ];
    }

    /**
     * Mark the user's phone as verified.
     */
    public function markPhoneAsVerified(): bool
    {
        return $this->forceFill([
            'phone_verified_at' => $this->freshTimestamp(),
            'verification_token' => null,
            'verification_token_expires_at' => null,
        ])->save();
    }

    /**
     * Check if the user has verified their phone.
     */
    public function hasVerifiedPhone(): bool
    {
        return ! is_null($this->phone_verified_at);
    }

    /**
     * Update the user's role.
     */
    public function updateRole(string $role): bool
    {
        try {
            // Remove all existing roles except 'unverified'
            $this->roles()->where('name', '!=', 'unverified')->delete();
            
            // Assign the new role
            $this->assignRole($role);
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to update user role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the verification status.
     */
    public function getVerificationStatusAttribute(): array
    {
        return [
            'email_verified' => ! is_null($this->email_verified_at),
            'phone_verified' => ! is_null($this->phone_verified_at),
            'verification_complete' => ! is_null($this->email_verified_at) || ! is_null($this->phone_verified_at),
        ];
    }

    /**
     * Get the user's profile photo URL.
     *
     * @return string
     */
    public function getProfilePhotoUrlAttribute()
    {
        if ($this->provider === 'google' || $this->provider === 'facebook') {
            return $this->profile_photo_path;
        }
        
        return $this->profile_photo_path
            ? asset('storage/'.$this->profile_photo_path)
            : $this->defaultProfilePhotoUrl();
    }

    /**
     * Get the default profile photo URL if no profile photo has been uploaded.
     *
     * @return string
     */
    protected function defaultProfilePhotoUrl()
    {
        $name = trim(collect(explode(' ', $this->name))->map(function ($segment) {
            return mb_substr($segment, 0, 1);
        })->join(' '));

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=7F9CF5&background=EBF4FF';
    }

    /**
     * Route notifications for the Twilio channel.
     *
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return string
     */
    public function routeNotificationForTwilio($notification)
    {
        return $this->phone_number;
    }
}
