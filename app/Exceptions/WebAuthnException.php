<?php
// app/Exceptions/WebAuthnException.php

namespace App\Exceptions;

use Exception;

class WebAuthnException extends Exception
{
    protected $statusCode;

    public function __construct(string $message = '', int $statusCode = 400, \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function registrationFailed(string $reason = ''): self
    {
        $message = 'WebAuthn registration failed';
        if ($reason) {
            $message .= ': ' . $reason;
        }

        return new self($message, 400);
    }

    public static function authenticationFailed(string $reason = ''): self
    {
        $message = 'WebAuthn authentication failed';
        if ($reason) {
            $message .= ': ' . $reason;
        }

        return new self($message, 401);
    }

    public static function unsupportedBrowser(): self
    {
        return new self('WebAuthn is not supported by your browser', 400);
    }

    public static function challengeExpired(): self
    {
        return new self('WebAuthn challenge has expired', 400);
    }

    public static function invalidChallenge(): self
    {
        return new self('Invalid WebAuthn challenge', 400);
    }

    public static function credentialNotFound(): self
    {
        return new self('WebAuthn credential not found', 404);
    }

    public static function invalidSignature(): self
    {
        return new self('Invalid WebAuthn signature', 401);
    }

    public static function counterMismatch(): self
    {
        return new self('WebAuthn signature counter mismatch - possible replay attack', 401);
    }
}

// app/Exceptions/TwoFactorException.php

namespace App\Exceptions;

use Exception;

class TwoFactorException extends Exception
{
    protected $statusCode;

    public function __construct(string $message = '', int $statusCode = 400, \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function sessionExpired(): self
    {
        return new self('Two-factor authentication session expired', 401);
    }

    public static function invalidCode(): self
    {
        return new self('Invalid two-factor authentication code', 400);
    }

    public static function alreadyEnabled(): self
    {
        return new self('Two-factor authentication is already enabled', 400);
    }

    public static function notEnabled(): self
    {
        return new self('Two-factor authentication is not enabled', 400);
    }

    public static function required(): self
    {
        return new self('Two-factor authentication is required', 403);
    }
}
