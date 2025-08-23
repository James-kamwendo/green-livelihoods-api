<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Validator;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        try {
            return Socialite::driver('google')->redirect();
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to initialize Google authentication',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function handleGoogleCallback()
    {
        try {
            $socialUser = Socialite::driver('google')->user();
            
            if (!$socialUser->getEmail()) {
                throw new \Exception('No email provided by Google');
            }
            
            // Check if user already exists
            $user = User::where('email', $socialUser->getEmail())->first();
            $isNewUser = false;

            if (!$user) {
                // Create new user with minimum required fields
                $user = User::create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                    'email' => $socialUser->getEmail(),
                    'email_verified_at' => now(),
                    'provider' => 'google',
                    'provider_id' => $socialUser->getId(),
                    'provider_token' => $socialUser->token ?? null,
                    'provider_refresh_token' => $socialUser->refreshToken ?? null,
                ]);
                
                // Assign 'unverified' role by default
                $user->assignRole('unverified');
                $isNewUser = true;
            } else {
                // Update existing user's provider info
                $user->update([
                    'provider' => 'google',
                    'provider_id' => $socialUser->getId(),
                    'provider_token' => $socialUser->token ?? $user->provider_token,
                    'provider_refresh_token' => $socialUser->refreshToken ?? $user->provider_refresh_token,
                ]);
            }

            // Generate token
            $token = $user->createToken('auth-token')->plainTextToken;

            // Check if user has completed profile
            $requiresProfileUpdate = $user->hasRole('unverified') || 
                                   is_null($user->age) || 
                                   is_null($user->gender) || 
                                   is_null($user->location);

            // Load user with roles relationship
            $user->load('roles');
            
            return response()->json([
                'user' => array_merge(
                    $user->makeHidden(['provider_token', 'provider_refresh_token'])->toArray(),
                    ['roles' => $user->roles->map->only(['id', 'name', 'guard_name'])]
                ),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'requires_profile_update' => $requiresProfileUpdate,
                'available_roles' => $isNewUser ? $this->getAvailableRoles() : null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Unable to login using Google',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function completeProfile(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'role' => 'required|string|in:buyer,artisan,marketer',
            'age' => 'required|integer|min:13|max:120',
            'gender' => 'required|in:male,female,other',
            'location' => 'required|string|max:255',
            'phone_number' => 'sometimes|string|max:20|unique:users,phone_number,' . $user->id,
        ]);

        // Update user profile
        $user->update([
            'age' => $validated['age'],
            'gender' => $validated['gender'],
            'location' => $validated['location'],
            'phone_number' => $validated['phone_number'] ?? $user->phone_number,
        ]);

        // Sync roles (remove 'unverified' and add selected role)
        $user->syncRoles([$validated['role']]);

        return response()->json([
            'message' => 'Profile completed successfully',
            'user' => $user->fresh()->load('roles')
        ]);
    }

    protected function getAvailableRoles()
    {
        return Role::whereIn('name', ['buyer', 'artisan', 'marketer'])
            ->get(['id', 'name', 'guard_name']);
    }
}
