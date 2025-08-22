<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run the seeders
        $this->seed();
    }

    #[Test]
    public function user_can_register_with_email()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone_number' => '+1234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
            'gender' => 'male',
            'age' => 25,
            'location' => 'Test Location',
            'role' => 'buyer',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'phone_number',
                    'roles',
                ],
                'access_token',
                'token_type',
                'requires_email_verification',
                'verification_token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        // Assert user has the buyer role
        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue($user->hasRole('buyer'));
        $this->assertFalse($user->hasVerifiedEmail());
    }

    #[Test]
    public function registration_requires_valid_email()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'phone_number' => '+1234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function user_can_login_with_email()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('buyer');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'roles',
                ],
                'access_token',
                'token_type',
            ]);
            
        $responseData = $response->json();
        $this->assertContains('buyer', $responseData['user']['roles']);
    }

    #[Test]
    public function user_cannot_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function user_cannot_login_with_unverified_email()
    {
        $user = User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => null,
        ]);
        $user->assignRole('buyer');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'unverified@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Your email address is not verified.']);
    }

    #[Test]
    public function user_can_verify_email()
    {
        $verificationToken = 'test-token';
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => null,
            'verification_token' => Hash::make($verificationToken),
            'verification_token_expires_at' => now()->addHour(),
        ]);
        $user->assignRole('buyer');

        $response = $this->postJson('/api/auth/email/verify', [
            'email' => 'test@example.com',
            'token' => $verificationToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'roles',
                ],
                'access_token',
                'token_type',
            ]);

        $this->assertNotNull($response->json('user.email_verified_at'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function user_can_resend_verification_email()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/auth/email/resend', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Verification email resent.',
            ]);

        $this->assertNotNull($user->fresh()->verification_token);
        $this->assertNotNull($user->fresh()->verification_token_expires_at);
    }

    #[Test]
    public function user_cannot_verify_with_invalid_token()
    {
        $validToken = 'valid-token';
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'verification_token' => Hash::make($validToken),
            'verification_token_expires_at' => now()->addHour(),
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/auth/email/verify', [
            'email' => 'test@example.com',
            'token' => 'invalid-token',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid or expired verification token.',
            ]);
            
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function user_cannot_verify_with_expired_token()
    {
        $expiredToken = 'expired-token';
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'verification_token' => Hash::make($expiredToken),
            'verification_token_expires_at' => now()->subHour(),
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/auth/email/verify', [
            'email' => 'test@example.com',
            'token' => $expiredToken,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid or expired verification token.',
            ]);
            
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function user_can_register_without_role_and_gets_unverified_role()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'No Role User',
            'email' => 'nouser@example.com',
            'phone_number' => '+1234567891',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'nouser@example.com')->first();
        $this->assertTrue($user->hasRole('unverified'));
        $this->assertFalse($user->hasRole('buyer'));
    }

    #[Test]
    public function authenticated_user_can_get_their_profile()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ]
            ]);
    }

    #[Test]
    public function authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }
}
