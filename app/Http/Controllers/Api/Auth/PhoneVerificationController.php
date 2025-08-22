<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PhoneVerificationController extends Controller
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send OTP to the user's phone
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => ['required', 'string', 'exists:users,phone_number'],
        ]);

        $user = User::where('phone_number', $request->phone_number)->firstOrFail();
        
        // Generate and send OTP
        $otp = $this->smsService->generateAndSendOtp($user->phone_number);

        return response()->json([
            'message' => 'OTP sent successfully',
            'expires_in' => config('auth.otp.expires'),
        ]);
    }

    /**
     * Verify the OTP
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => ['required', 'string', 'exists:users,phone_number'],
            'otp' => ['required', 'string', 'digits:6'],
        ]);

        $user = User::where('phone_number', $request->phone_number)->firstOrFail();
        
        if ($this->smsService->verifyOtp($user->phone_number, $request->otp)) {
            // Mark phone as verified
            $user->markPhoneAsVerified();
            
            // Generate auth token
            $token = $user->createToken('auth-token')->plainTextToken;
            
            return response()->json([
                'message' => 'Phone number verified successfully',
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user->only(['id', 'name', 'phone_number', 'email']),
                'verification_required' => false,
            ]);
        }

        throw ValidationException::withMessages([
            'otp' => ['The provided OTP is invalid or has expired.'],
        ]);
    }
    
    /**
     * Resend OTP
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => ['required', 'string', 'exists:users,phone_number'],
        ]);

        $user = User::where('phone_number', $request->phone_number)->firstOrFail();
        
        if ($otp = $this->smsService->resendOtp($user->phone_number)) {
            return response()->json([
                'message' => 'OTP resent successfully',
                'expires_in' => config('auth.otp.expires'),
            ]);
        }

        return response()->json([
            'message' => 'Please wait before requesting a new OTP',
        ], 429);
    }
}
