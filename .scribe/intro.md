# Introduction

A comprehensive authentication API with Two-Factor Authentication (2FA), Role-Based Access Control (RBAC), and user management features. Built with Laravel 12 and designed for modern applications requiring secure user authentication.

<aside>
    <strong>Base URL</strong>: <code>http://10.10.114.22:8000</code>
</aside>

    This API provides comprehensive authentication and user management features including:

    ## Authentication Features
    - **User Registration & Login** - Support for both email and username authentication
    - **Two-Factor Authentication (2FA)** - TOTP (Google Authenticator) and Email OTP support
    - **Role-Based Access Control (RBAC)** - Manage user roles and permissions
    - **Secure Token Management** - Laravel Sanctum for API authentication

    ## User Management
    - **User Profile Management** - Update profiles, upload avatars, manage preferences
    - **Admin Controls** - User management, role assignment, permission control
    - **Security Features** - Rate limiting, login attempt tracking, and audit logs

    ## Getting Started

    1. **Register a new user** via `POST /api/auth/register`
    2. **Login** via `POST /api/auth/login` to get your access token
    3. **Include the token** in the `Authorization` header: `Bearer {your_token}`
    4. **Access protected endpoints** with your authenticated token

    ## 2FA Setup

    1. **Enable 2FA** via `POST /api/2fa/enable` to get QR code
    2. **Scan QR code** with Google Authenticator or similar app
    3. **Confirm setup** via `POST /api/2fa/confirm` with verification code
    4. **Future logins** will require the 2FA code

    <aside>The API uses standard HTTP status codes and returns JSON responses. All timestamps are in UTC format.</aside>

