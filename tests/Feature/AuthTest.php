<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
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
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'phone_number',
                ],
                'verification_required',
                'verification_method',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'email_verified_at' => null,
        ]);
        
        // Verify the user has the unverified role by default
        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue($user->hasRole('unverified'));
    }

    #[Test]
    public function user_can_register_with_phone()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Phone User',
            'phone_number' => '+1234567890',
            'gender' => 'female',
            'age' => 30,
            'location' => 'Test Location',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'phone_number',
                ],
                'verification_required',
                'verification_method',
                'otp_expires_in',
            ]);

        $this->assertDatabaseHas('users', [
            'phone_number' => '+1234567890',
            'name' => 'Phone User',
            'phone_verified_at' => null,
        ]);
        
        // Verify the user has the unverified role by default
        $user = User::where('phone_number', '+1234567890')->first();
        $this->assertTrue($user->hasRole('unverified'));
    }

    #[Test]
    public function registration_requires_either_email_or_phone()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Invalid User',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'phone_number']);
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
    public function user_can_verify_phone_with_otp()
    {
        $user = User::factory()->create([
            'phone_number' => '+1234567890',
            'phone_verified_at' => null,
        ]);

        // Mock the OTP verification
        $this->mock(SmsService::class, function ($mock) {
            $mock->shouldReceive('verifyOtp')
                ->andReturn(true);
        });

        $response = $this->postJson('/api/auth/phone/verify-otp', [
            'phone_number' => '+1234567890',
            'otp' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => [
                    'id',
                    'name',
                    'phone_number',
                ],
                'verification_required',
            ]);

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    #[Test]
    public function user_can_verify_email()
    {
        $verificationToken = 'test-verification-token';
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => null,
            'verification_token' => Hash::make($verificationToken),
            'verification_token_expires_at' => now()->addHour(),
        ]);
        $user->assignRole('unverified');

        // Use the POST endpoint that the controller actually handles
        $response = $this->postJson('/api/auth/email/verify', [
            'email' => 'test@example.com',
            'token' => $verificationToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Email verified successfully.',
            ]);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('unverified'));
        $this->assertFalse($user->hasRole('buyer'));
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
    public function test_login_with_invalid_credentials()
    {
        // First, create a user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('buyer');

        // Test with wrong password
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The provided credentials are incorrect.',
                'errors' => [
                    'email' => ['The provided credentials are incorrect.']
                ]
            ]);
    }
    
    #[Test]
    public function test_login_requires_email_or_phone()
    {
        $response = $this->postJson('/api/auth/login', [
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'phone_number']);
    }

    #[Test]
    public function user_can_login_with_phone()
    {
        $user = User::factory()->create([
            'phone_number' => '+1234567890',
            'password' => Hash::make('password'),
            'phone_verified_at' => now(),
        ]);
        $user->assignRole('buyer');

        $response = $this->postJson('/api/auth/login', [
            'phone_number' => '+1234567890',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'phone_number',
                    'phone_verified_at',
                ],
                'access_token',
                'token_type',
            ]);
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
    public function user_cannot_verify_with_invalid_token()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => null,
            'verification_token' => Hash::make('valid-token'),
            'verification_token_expires_at' => now()->addHour(),
        ]);
        $user->assignRole('unverified');

        $response = $this->postJson('/api/auth/email/verify', [
            'email' => 'test@example.com',
            'token' => 'invalid-token',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid or expired verification token.',
            ]);
            
        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function user_cannot_verify_with_expired_token()
    {
        $expiredToken = 'expired-token';
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => null,
            'verification_token' => Hash::make($expiredToken),
            'verification_token_expires_at' => now()->subHour(),
        ]);
        $user->assignRole('unverified');

        $response = $this->postJson('/api/auth/email/verify', [
            'email' => 'test@example.com',
            'token' => $expiredToken,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid or expired verification token.',
            ]);
            
        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function user_gets_unverified_role_by_default()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'No Role User',
            'email' => 'nouser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(201);
        $user = User::where('email', 'nouser@example.com')->first();
        $this->assertTrue($user->hasRole('unverified'));
        $this->assertFalse($user->hasRole('buyer'));
        
        // Verify the response structure
        $response->assertJsonStructure([
            'message',
            'user' => [
                'id',
                'name',
                'email',
            ],
            'verification_required',
            'verification_method',
        ]);
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
