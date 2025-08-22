# Green Livelihoods API

[![Build Status](https://github.com/laravel/framework/workflows/tests/badge.svg)](https://github.com/laravel/framework/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/laravel/framework)](https://packagist.org/packages/laravel/framework)
[![Latest Stable Version](https://img.shields.io/packagist/v/laravel/framework)](https://packagist.org/packages/laravel/framework)
[![License](https://img.shields.io/packagist/l/laravel/framework)](https://packagist.org/packages/laravel/framework)

## Authentication API

This API provides user authentication with email verification and role-based access control.

## Table of Contents
- [Authentication](#authentication)
  - [Register](#register)
  - [Login](#login)
  - [Email Verification](#email-verification)
  - [Resend Verification Email](#resend-verification-email)
  - [Get Authenticated User](#get-authenticated-user)
  - [Logout](#logout)

## Authentication

### Register

Register a new user account.

**Endpoint:** `POST /api/auth/register`

**Request Body:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "phone_number": "+1234567890",
    "password": "your-password",
    "password_confirmation": "your-password",
    "gender": "male",
    "age": 25,
    "role": "buyer"
}
```

**Note:** The `role` field is optional. If not provided, the user will be assigned the 'unverified' role.

**Response (201 Created):**
```json
{
    "message": "Registration successful. Please verify your email.",
    "user": {
        "name": "John Doe",
        "email": "john@example.com",
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
