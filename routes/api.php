<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\VerificationController;
use App\Http\Controllers\Api\Auth\PhoneVerificationController;
use App\Http\Controllers\Api\Auth\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Auth Routes
Route::prefix('auth')->group(function () {
    // Public auth routes
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // Email verification routes
    Route::prefix('email')->group(function () {
        Route::get('/verify/{id}/{hash}', [VerificationController::class, 'verify'])
            ->name('verification.verify');
        Route::post('/verify', [VerificationController::class, 'verify']);
        Route::post('/resend', [VerificationController::class, 'resend'])
            ->middleware(['throttle:6,1']);
    });

    // Phone verification routes
    Route::prefix('phone')->group(function () {
        Route::post('/send-otp', [PhoneVerificationController::class, 'sendOtp']);
        Route::post('/verify-otp', [PhoneVerificationController::class, 'verifyOtp']);
        Route::post('/resend-otp', [PhoneVerificationController::class, 'resendOtp']);
    });
    
        // Social authentication routes
    Route::prefix('google')->group(function () {
        Route::get('/redirect', [\App\Http\Controllers\Auth\SocialAuthController::class, 'redirectToGoogle']);
        Route::get('/callback', [\App\Http\Controllers\Auth\SocialAuthController::class, 'handleGoogleCallback']);
    });
    
    // Profile completion route (protected)
    Route::middleware('auth:sanctum')->post('/complete-profile', 
        [\App\Http\Controllers\Auth\SocialAuthController::class, 'completeProfile']
    );
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        
        // Role management
        Route::post('/update-role', [AuthController::class, 'updateRole']);
    });
});

// Example protected route
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user()->load('roles');
});
