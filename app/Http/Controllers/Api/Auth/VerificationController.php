<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VerificationController extends Controller
{
    /**
     * Verify the user's email address.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        // Check if the token is valid and not expired
        if (!Hash::check($request->token, $user->verification_token) || 
            $user->verification_token_expires_at->isPast()) {
            return response()->json([
                'message' => 'Invalid or expired verification token.',
            ], 400);
        }

        // Mark email as verified
        $user->email_verified_at = now();
        $user->verification_token = null;
        $user->verification_token_expires_at = null;
        $user->save();

        // Generate a new token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully.',
            'user' => array_merge($user->toArray(), [
                'roles' => $user->getRoleNames(),
            ]),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ], 400);
        }

        // Generate new verification token (1 hour expiry)
        $verificationToken = Str::random(60);
        $user->verification_token = Hash::make($verificationToken);
        $user->verification_token_expires_at = now()->addHour();
        $user->save();

        // In production, send verification email here
        // Mail::to($user->email)->send(new VerifyEmail($user, $verificationToken));

        return response()->json([
            'message' => 'Verification email resent.',
            'verification_token' => $verificationToken, // Only for development
        ]);
    }
}
