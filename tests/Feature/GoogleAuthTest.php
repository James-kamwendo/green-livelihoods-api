<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure required roles exist
        Role::firstOrCreate(['name' => 'unverified']);
        Role::firstOrCreate(['name' => 'buyer']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_redirect_to_google()
    {
        // Mock the Socialite factory and provider
        $mockProvider = $this->createMock(GoogleProvider::class);
        $mockProvider->method('redirect')
            ->willReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
            
        $mockSocialite = $this->createMock(SocialiteFactory::class);
        $mockSocialite->method('driver')
            ->with('google')
            ->willReturn($mockProvider);
            
        $this->app->instance(SocialiteFactory::class, $mockSocialite);

        // Make the request and follow the redirect
        $response = $this->get('/api/auth/google/redirect');
        
        // Assert we got a redirect response
        $response->assertStatus(302);
        $response->assertRedirect();
    }

    public function test_handle_google_callback_new_user()
    {
        // Create a mock Socialite user
        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = '123456789';
        $socialiteUser->name = 'Test User';
        $socialiteUser->email = 'test@example.com';
        $socialiteUser->token = 'test-token';
        $socialiteUser->refreshToken = 'test-refresh-token';
        $socialiteUser->avatar = 'https://example.com/avatar.jpg';
        
        // Mock the Socialite provider
        $mockProvider = $this->createMock(GoogleProvider::class);
        $mockProvider->method('stateless')
            ->willReturnSelf();
        $mockProvider->method('user')
            ->willReturn($socialiteUser);
            
        // Mock the Socialite factory
        $mockSocialite = $this->createMock(SocialiteFactory::class);
        $mockSocialite->method('driver')
            ->with('google')
            ->willReturn($mockProvider);
            
        $this->app->instance(SocialiteFactory::class, $mockSocialite);

        // Mock the request state to match the expected state
        $state = 'test_state';
        $this->withSession(['state' => $state]);

        try {
            // Make the request with the required state parameter
            $response = $this->get("/api/auth/google/callback?state={$state}");
            
            // Check if the response is a redirect
            if ($response->status() === 302) {
                $response->assertStatus(302);
                $response->assertRedirect();
            } else {
                // If not a redirect, check for JSON response
                $response->assertStatus(200);
                $response->assertJsonStructure([
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'email_verified_at',
                        'provider',
                        'provider_id',
                        'created_at',
                        'updated_at'
                    ],
                    'access_token',
                    'token_type',
                    'requires_profile_update'
                ]);
            }
            
            // Assert the user was created in the database
            $this->assertDatabaseHas('users', [
                'email' => 'test@example.com',
                'provider' => 'google',
                'provider_id' => '123456789'
            ]);
            
        } catch (\Exception $e) {
            $this->fail('Test failed with exception: ' . $e->getMessage());
        }
    }

    public function test_complete_profile_flow()
    {
        // Create a new user via Google OAuth
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'provider' => 'google',
            'provider_id' => '123456789',
            'name' => 'Test User',
            'email_verified_at' => now(),
        ]);
        
        $user->assignRole('unverified');
        
        // Simulate login
        $token = $user->createToken('test-token')->plainTextToken;
        
        // Complete profile
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/auth/complete-profile', [
            'role' => 'buyer',
            'age' => 25,
            'gender' => 'male',
            'location' => 'Test Location',
            'phone_number' => '1234567890',
        ]);
        
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Profile completed successfully',
        ]);
        
        // Verify user was updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'age' => 25,
            'gender' => 'male',
            'location' => 'Test Location',
            'phone_number' => '1234567890',
        ]);
        
        // Verify role was updated
        $this->assertTrue($user->fresh()->hasRole('buyer'));
        $this->assertFalse($user->fresh()->hasRole('unverified'));
    }
    
    public function test_handle_google_callback_existing_user()
    {
        // Create an existing user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'provider' => 'google',
            'provider_id' => '123456789',
            'name' => 'Existing User'
        ]);
        
        // Create a mock Socialite user
        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = '123456789';
        $socialiteUser->name = 'Test User';
        $socialiteUser->email = 'test@example.com';
        $socialiteUser->token = 'test-token';
        $socialiteUser->refreshToken = 'test-refresh-token';
        
        // Mock the Socialite provider
        $mockProvider = $this->createMock(GoogleProvider::class);
        $mockProvider->method('stateless')
            ->willReturnSelf();
        $mockProvider->method('user')
            ->willReturn($socialiteUser);
            
        // Mock the Socialite factory
        $mockSocialite = $this->createMock(SocialiteFactory::class);
        $mockSocialite->method('driver')
            ->with('google')
            ->willReturn($mockProvider);
            
        $this->app->instance(SocialiteFactory::class, $mockSocialite);
        
        // Make the request
        $response = $this->getJson('/api/auth/google/callback');
        
        // Assert the response
        $response->assertStatus(200);
        
        // Assert no new user was created
        $this->assertDatabaseCount('users', 1);
        
        // Assert the existing user was updated with the new tokens
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Existing User', // Name should not be updated
            'provider_token' => 'test-token',
            'provider_refresh_token' => 'test-refresh-token'
        ]);
    }
}
