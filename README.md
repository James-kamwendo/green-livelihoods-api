# Green Livelihoods API

[![Build Status](https://github.com/laravel/framework/workflows/tests/badge.svg)](https://github.com/laravel/framework/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/laravel/framework)](https://packagist.org/packages/laravel/framework)
[![Latest Stable Version](https://img.shields.io/packagist/v/laravel/framework)](https://packagist.org/packages/laravel/framework)
[![License](https://img.shields.io/packagist/l/laravel/framework)](https://packagist.org/packages/laravel/framework)

## Authentication API

This API provides user authentication with email/phone verification and role-based access control.

## Table of Contents

- [Authentication](#authentication)
  - [Google OAuth](#google-oauth)
  - [Register](#register)
  - [Login](#login)
  - [Email Verification](#email-verification)
  - [Resend Verification Email](#resend-verification-email)
  - [Update User Role](#update-user-role)
  - [Get Authenticated User](#get-authenticated-user)
  - [Logout](#logout)
  - [Error Responses](#error-responses)

## Authentication

### Google OAuth

The API supports Google OAuth for authentication with profile completion. Here's how it works:

1. **Redirect to Google OAuth**

   ```http
   GET /api/auth/google/redirect
   ```

   This will redirect the user to Google's OAuth consent screen.

2. **Handle OAuth Callback**

   After successful authentication, Google will redirect to:

   ```http
   GET /api/auth/google/callback
   ```

   **Response for New User (requires profile completion):**
   ```json
   {
     "user": {
       "id": 1,
       "name": "John Doe",
       "email": "john@example.com",
       "email_verified_at": "2023-01-01T00:00:00.000000Z",
       "provider": "google",
       "provider_id": "123456789",
       "created_at": "2023-01-01T00:00:00.000000Z",
       "updated_at": "2023-01-01T00:00:00.000000Z"
     },
     "access_token": "1|abcdefghijklmnopqrstuvwxyz",
     "token_type": "Bearer",
     "requires_profile_update": true,
     "available_roles": [
       {
         "id": 1,
         "name": "buyer",
         "guard_name": "web"
       },
       {
         "id": 2,
         "name": "artisan",
         "guard_name": "web"
       },
       {
         "id": 3,
         "name": "marketer",
         "guard_name": "web"
       }
     ]
   }
   ```

   **Response for Existing User (profile complete):**
   ```json
   {
     "user": {
       "id": 1,
       "name": "John Doe",
       "email": "john@example.com",
       "email_verified_at": "2023-01-01T00:00:00.000000Z",
       "provider": "google",
       "provider_id": "123456789",
       "age": 25,
       "gender": "male",
       "location": "Nairobi, Kenya",
       "phone_number": "+254712345678",
       "created_at": "2023-01-01T00:00:00.000000Z",
       "updated_at": "2023-01-01T00:00:00.000000Z",
       "roles": [
         {
           "id": 1,
           "name": "buyer",
           "guard_name": "web"
         }
       ]
     },
     "access_token": "1|abcdefghijklmnopqrstuvwxyz",
     "token_type": "Bearer",
     "requires_profile_update": false
   }
   ```

3. **Complete Profile (if required)**

   If `requires_profile_update` is `true`, the frontend should collect additional user information:

   ```http
   POST /api/auth/complete-profile
   ```

   **Headers:**
   ```
   Authorization: Bearer your-access-token
   Accept: application/json
   Content-Type: application/json
   ```

   **Request Body:**
   ```json
   {
     "role": "buyer",
     "age": 25,
     "gender": "male",
     "location": "Nairobi, Kenya",
     "phone_number": "+254712345678"
   }
   ```

   **Successful Response (200 OK):**
   ```json
   {
     "message": "Profile completed successfully",
     "user": {
       "id": 1,
       "name": "John Doe",
       "email": "john@example.com",
       "age": 25,
       "gender": "male",
       "location": "Nairobi, Kenya",
       "phone_number": "+254712345678",
       "roles": [
         {
           "id": 1,
           "name": "buyer",
           "guard_name": "web"
         }
       ]
     }
   }
   ```

   **Error Response (422 Unprocessable Entity) - Validation Error:**
   ```json
   {
     "message": "The given data was invalid.",
     "errors": {
       "role": ["The selected role is invalid."],
       "age": ["The age field is required."],
       "gender": ["The selected gender is invalid."],
       "location": ["The location field is required."]
     }
   }
   ```

### Register

Register a new user account.

**Endpoint:** `POST /api/auth/register`

**Headers:**

```http
Content-Type: application/json
Accept: application/json
```

**Request Body (Email Registration):**

```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "your-password",
    "password_confirmation": "your-password"
}
```

**Request Body (Phone Registration):**

```json
{
    "name": "John Doe",
    "phone_number": "+1234567890",
    "password": "your-password",
    "password_confirmation": "your-password"
}
```

**Response (201 Created):**

```json
{
    "message": "Registration successful. Please verify your email/phone.",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": null,
        "created_at": "2023-01-01T00:00:00.000000Z",
        "updated_at": "2023-01-01T00:00:00.000000Z"
    }
}
```

## Login

Authenticate a user and retrieve an access token.

**Endpoint:** `POST /api/auth/login`

**Headers:**

```http
Content-Type: application/json
Accept: application/json
```

**Request Body (Email Login):**

```json
{
    "email": "john@example.com",
    "password": "your-password"
}
```

**Request Body (Phone Login):**

```json
{
    "phone_number": "+1234567890",
    "password": "your-password"
}
```

**Response (200 OK):**

```json
{
    "token": "your-access-token",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": "2023-01-01T00:00:00.000000Z",
        "created_at": "2023-01-01T00:00:00.000000Z",
        "updated_at": "2023-01-01T00:00:00.000000Z"
    }
}
```

## Email Verification

Verify a user's email address using the verification token sent to their email.

**Endpoint:** `POST /api/email/verify`

**Headers:**

```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer your-access-token
```

**Request Body:**

```json
{
    "email": "john@example.com",
    "token": "verification-token-from-email"
}
```

**Response (200 OK):**

```json
{
    "message": "Email verified successfully.",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": "2023-01-01T00:00:00.000000Z",
        "created_at": "2023-01-01T00:00:00.000000Z",
        "updated_at": "2023-01-01T00:00:00.000000Z"
    }
}
```

## Resend Verification Email

Resend the email verification notification.

**Endpoint:** `POST /api/email/resend`

**Headers:**

```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer your-access-token
```

**Response (200 OK):**

```json
{
    "message": "Verification email has been resent."
}
```

## Update User Role

Update the authenticated user's role. Users can only update their role once.

**Endpoint:** `POST /api/update-role`

**Headers:**

```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer your-access-token
```

**Request Body:**

```json
{
    "role": "buyer"
}
```

**Available Roles:** `buyer`, `artisan`, `marketer`, `admin`

**Response (200 OK):**

```json
{
    "message": "Role updated successfully.",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": "2023-01-01T00:00:00.000000Z",
        "created_at": "2023-01-01T00:00:00.000000Z",
        "updated_at": "2023-01-01T00:00:00.000000Z",
        "roles": ["buyer"]
    }
}
```

## Get Authenticated User

Get the currently authenticated user's details.

**Endpoint:** `GET /api/user`

**Headers:**

```http
Accept: application/json
Authorization: Bearer your-access-token
```

**Response (200 OK):**

```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": "2023-01-01T00:00:00.000000Z",
    "created_at": "2023-01-01T00:00:00.000000Z",
    "updated_at": "2023-01-01T00:00:00.000000Z",
    "roles": ["buyer"]
}
```

## Logout

Revoke the current access token (log out).

**Endpoint:** `POST /api/auth/logout`

**Headers:**

```http
Accept: application/json
Authorization: Bearer your-access-token
```

**Response (200 OK):**

```json
{
    "message": "Successfully logged out"
}
```

## Error Responses

### 401 Unauthorized

```json
{
    "message": "Unauthenticated."
}
```

### 403 Forbidden

```json
{
    "message": "This action is unauthorized."
}
```

### 404 Not Found

```json
{
    "message": "The requested resource was not found."
}
```

### 422 Unprocessable Entity (Validation Errors)

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email has already been taken."],
        "password": ["The password must be at least 8 characters."]
    }
}
```

## Rate Limiting

- Authentication endpoints are rate limited to 5 attempts per minute.
- After 5 failed attempts, the user will be locked out for 1 minute.

## Security

- All requests must include the `Accept: application/json` header.
- All authenticated requests must include a valid `Authorization: Bearer <token>` header.
- Passwords are hashed using bcrypt.
- Tokens are invalidated on logout.
- Email verification is required for full access to protected resources.
- `location` is optional
- User will be assigned the 'unverified' role by default

**Response (201 Created):**

```json
{
    "message": "Registration successful. Please verify your email/phone.",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "phone_number": "+1234567890",
        "email_verified_at": null,
        "phone_verified_at": null,
        "gender": "male",
        "age": 25,
        "location": "New York, USA"
    },
    "verification_required": true,
    "verification_method": "email" // or "phone"
}
```

### Login

Authenticate a user and retrieve an access token.

**Endpoint:** `POST /api/auth/login`

**Request Headers:**

```
Content-Type: application/json
Accept: application/json
```

**Request Body (Email Login):**

```json
{
    "email": "john@example.com",
    "password": "your-password"
}
```

**Request Body (Phone Login):**

```json
{
    "phone_number": "+1234567890",
    "password": "your-password"
}
```

**Response (200 OK):**

```json
{
    "access_token": "your-access-token",
    "token_type": "Bearer",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "phone_number": "+1234567890",
        "roles": ["buyer"]
    }
}
```

### Email Verification

Verify user's email address using the verification token.

**Endpoint:** `POST /api/auth/email/verify`

**Request Headers:**

```
Content-Type: application/json
Accept: application/json
Authorization: Bearer your-access-token
```

**Request Body:**

```json
{
    "email": "john@example.com",
    "token": "verification-token"
}
```

**Response (200 OK):**

```json
{
    "message": "Email verified successfully.",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": "2023-01-01T12:00:00.000000Z"
    },
    "access_token": "new-access-token",
    "token_type": "Bearer"
}
```

### Phone Verification

Verify user's phone number using OTP.

**Endpoint:** `POST /api/auth/phone/verify-otp`

**Request Headers:**

```
Content-Type: application/json
Accept: application/json
Authorization: Bearer your-access-token
```

**Request Body:**

```json
{
    "phone_number": "+1234567890",
    "otp": "123456"
}
```

**Response (200 OK):**

```json
{
    "message": "Phone number verified successfully.",
    "user": {
        "id": 1,
        "name": "John Doe",
        "phone_number": "+1234567890",
        "phone_verified_at": "2023-01-01T12:00:00.000000Z"
    },
    "access_token": "new-access-token",
    "token_type": "Bearer"
}
```

### Resend Verification

Resend verification email or OTP.

**Endpoint (Email):** `POST /api/auth/email/resend`
**Endpoint (Phone):** `POST /api/auth/phone/resend-otp`

**Request Headers:**

```
Content-Type: application/json
Accept: application/json
```

**Request Body (Email):**

```json
{
    "email": "john@example.com"
}
```

**Request Body (Phone):**

```json
{
    "phone_number": "+1234567890"
}
```

**Response (200 OK):**

```json
{
    "message": "Verification email/OTP has been resent."
}
```

### Update User Role

Update the authenticated user's role (only for unverified users).

**Endpoint:** `POST /api/auth/update-role`

**Request Headers:**

```
Content-Type: application/json
Accept: application/json
Authorization: Bearer your-access-token
```

**Request Body:**

```json
{
    "role": "buyer"
}
```

**Available Roles:** `buyer`, `artisan`, `marketer`, `admin`

**Response (200 OK):**

```json
{
    "message": "Role updated successfully.",
    "user": {
        "id": 1,
        "name": "John Doe",
        "roles": ["buyer"]
    }
}
```

### Get Authenticated User

Get the authenticated user's profile.

**Endpoint:** `GET /api/auth/me`

**Request Headers:**

```
Accept: application/json
Authorization: Bearer your-access-token
```

**Response (200 OK):**

```json
{
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "phone_number": "+1234567890",
        "email_verified_at": "2023-01-01T12:00:00.000000Z",
        "phone_verified_at": "2023-01-01T12:00:00.000000Z",
        "gender": "male",
        "age": 25,
        "location": "New York, USA",
        "created_at": "2023-01-01T10:00:00.000000Z",
        "updated_at": "2023-01-01T12:00:00.000000Z",
        "roles": ["buyer"]
    }
}
```

### Logout

Revoke the current access token.

**Endpoint:** `POST /api/auth/logout`

**Request Headers:**

```
Accept: application/json
Authorization: Bearer your-access-token
```

**Response (200 OK):**

```json
{
    "message": "Successfully logged out"
}
```

## Error Responses

### 401 Unauthorized

```json
{
    "message": "Unauthenticated."
}
```

### 403 Forbidden

```json
{
    "message": "This action is unauthorized."
}
```

### 404 Not Found

```json
{
    "message": "User not found."
}
```

### 422 Unprocessable Entity (Validation Errors)

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required when phone number is not present."],
        "password": ["The password field is required."]
    }
}
```

## Rate Limiting

- Email verification: 6 attempts per minute
- Phone OTP: 3 attempts per minute
- Login attempts: 5 attempts per minute

## Security

- All endpoints except `/api/auth/register`, `/api/auth/login`, and verification endpoints require authentication
- Passwords are hashed using bcrypt
- Tokens are invalidated on logout
- Email and phone verification required for full access: "Registration successful. Please verify your email.",
    "user": {
        "name": "John Doe",
        "email": "<john@example.com>",
        "updated_at": "2023-01-01T12:00:00.000000Z",
        "created_at": "2023-01-01T12:00:00.000000Z",
        "id": 1,
        "email_verified_at": null,
        "verification_token": "hashed-token",
        "verification_token_expires_at": "2023-01-01T13:00:00.000000Z"
    }
}

```

### Login

Authenticate a user and retrieve an access token.

**Endpoint:** `POST /api/auth/login`

**Request Body:**
```json
{
    "email": "john@example.com",
    "password": "your-password"
}
```

**Response (200 OK):**

```json
{
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": "2023-01-01T12:30:00.000000Z",
        "roles": ["buyer"]
    },
    "access_token": "your-access-token",
    "token_type": "Bearer"
}
```

**Note:** The `access_token` should be included in the `Authorization` header for authenticated requests.

### Email Verification

Verify a user's email address using the verification token.

**Endpoint:** `POST /api/auth/email/verify`

**Request Body:**

```json
{
    "email": "john@example.com",
    "token": "verification-token"
}
```

**Response (200 OK):**

```json
{
    "message": "Email verified successfully.",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": "2023-01-01T12:30:00.000000Z",
        "roles": ["buyer"]
    },
    "access_token": "new-access-token",
    "token_type": "Bearer"
}
```

### Resend Verification Email

Resend the verification email to the user.

**Endpoint:** `POST /api/auth/email/resend`

**Request Body:**

```json
{
    "email": "john@example.com"
}
```

**Response (200 OK):**

```json
{
    "message": "Verification email resent."
}
```

### Get Authenticated User

Get the currently authenticated user's information.

**Endpoint:** `GET /api/auth/me`

**Headers:**

```
Authorization: Bearer your-access-token
```

**Response (200 OK):**

```json
{
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": "2023-01-01T12:30:00.000000Z",
        "roles": ["buyer"]
    }
}
```

### Logout

Invalidate the current access token.

**Endpoint:** `POST /api/auth/logout`

**Headers:**

```
Authorization: Bearer your-access-token
```

**Response (200 OK):**

```json
{
    "message": "Successfully logged out"
}
```

## Testing

To run the tests and see if something is failing do:

```json
php artisan test
