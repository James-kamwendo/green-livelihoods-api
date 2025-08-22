<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Generate a random password for phone registration if not provided
        if (isset($validated['phone_number']) && !isset($validated['password'])) {
            $validated['password'] = Hash::make(Str::random(12));
        }

        // Create the user
        $user = User::create($validated);

        // Handle email registration
        if (isset($validated['email'])) {
            $user->sendEmailVerificationNotification();
            
            return response()->json([
                'message' => 'Registration successful. Please verify your email.',
                'user' => $user->only(['id', 'name', 'email', 'phone_number']),
                'verification_required' => true,
                'verification_method' => 'email',
            ], 201);
        }

        // Handle phone registration
        if (isset($validated['phone_number'])) {
            // Generate and send OTP
            $otp = $this->smsService->generateAndSendOtp($user->phone_number);
            
            return response()->json([
                'message' => 'Registration successful. Please verify your phone number with the OTP sent.',
                'user' => $user->only(['id', 'name', 'phone_number']),
                'verification_required' => true,
                'verification_method' => 'phone',
                'otp_expires_in' => config('auth.otp.expires'), // in minutes
            ], 201);
        }

        return response()->json([
            'message' => 'Registration failed. Please provide either email or phone number.',
        ], 400);
    }
}
