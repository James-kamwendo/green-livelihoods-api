<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'password' => Hash::make($validated['password']),
            'gender' => $validated['gender'] ?? null,
            'age' => $validated['age'] ?? null,
            'location' => $validated['location'] ?? null,
            'email_verified_at' => null, // Will be set after email verification
        ]);

        // Assign the specified role or default to 'unverified'
        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }
        // Note: The 'unverified' role is assigned automatically via the User model's boot method

        // Generate verification token
        $verificationToken = Str::random(60);
        $user->verification_token = hash('sha256', $verificationToken);
        $user->save();

        // In production, send verification email here
        // Mail::to($user->email)->send(new VerifyEmail($user, $verificationToken));

        // Generate auth token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => array_merge($user->toArray(), [
                'roles' => $user->getRoleNames(),
            ]),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'requires_email_verification' => true,
            'verification_token' => $verificationToken, // Only for development
        ], 201);
    }

    /**
     * Authenticate the user and issue a token.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Check if user exists and password is correct
        if (!Auth::attempt($validated)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = $request->user();
        
        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Your email address is not verified.',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => array_merge($user->toArray(), [
                'roles' => $user->getRoleNames(),
            ]),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Log the user out (Invalidate the token).
     */
    public function logout(): JsonResponse
    {
        Auth::user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Update the authenticated user's role.
     */
    public function updateRole(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'required|string|in:buyer,seller,admin',
        ]);

        $user = $request->user();
        
        // Only allow unverified users to update their role once
        if (!$user->hasRole('unverified')) {
            return response()->json([
                'message' => 'You have already selected a role.',
            ], 400);
        }

        // Remove unverified role and assign the new role
        $user->removeRole('unverified');
        $user->assignRole($request->role);

        return response()->json([
            'message' => 'Role updated successfully.',
            'user' => array_merge($user->toArray(), [
                'roles' => $user->getRoleNames(),
            ]),
        ]);
    }

    /**
     * Get the authenticated user.
     */
    public function me(): JsonResponse
    {
        $user = Auth::user();
        return response()->json([
            'user' => array_merge($user->toArray(), [
                'roles' => $user->getRoleNames(),
            ]),
        ]);
    }


}
