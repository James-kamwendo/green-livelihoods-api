<?php

namespace App\Services;

use App\Models\PhoneVerification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SmsService
{
    public function generateAndSendOtp(string $phoneNumber): string
    {
        // Generate a 6-digit OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // In a real app, you would send this OTP via an SMS gateway here
        // For example: $this->sendSms($phoneNumber, "Your verification code is: $otp");
        
        // Store the OTP in cache with expiration
        $expiresAt = now()->addMinutes((int) config('auth.otp.expires', 10));
        Cache::put("otp:{$phoneNumber}", [
            'otp' => $otp,
            'attempts' => 0,
        ], $expiresAt);

        // For development/testing purposes, log the OTP
        \Log::info("OTP for {$phoneNumber}: {$otp}");
        
        return $otp;
    }

    public function verifyOtp(string $phoneNumber, string $otp): bool
    {
        $cacheKey = "otp:{$phoneNumber}";
        $stored = Cache::get($cacheKey);

        if (!$stored || $stored['otp'] !== $otp) {
            return false;
        }

        // Clear the OTP after successful verification
        Cache::forget($cacheKey);
        
        return true;
    }

    public function resendOtp(string $phoneNumber): ?string
    {
        // Check if there's a recent OTP request to prevent abuse
        if (Cache::has("otp_resend:{$phoneNumber}")) {
            return null;
        }

        // Set a cooldown period for resending OTP (e.g., 1 minute)
        $cooldown = now()->addSeconds((int) config('auth.otp.resend_after', 60));
        Cache::put("otp_resend:{$phoneNumber}", true, $cooldown);

        return $this->generateAndSendOtp($phoneNumber);
    }
}
