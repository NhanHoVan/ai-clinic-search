<?php

namespace AICLINIC\Core\Includes\Helpers;

/**
 * A helper object for Cookie matters.
 */
class Cookie_Helper
{

    const TOKEN_TTL = 30 * DAY_IN_SECONDS;

    public function set_cookie_token(string $name, string $token): void
    {
        // PHP >= 7.3
        $params = [
            'expires'  => time() + self::TOKEN_TTL,
            'path'     => COOKIEPATH ?: '/',
            'domain'   => COOKIE_DOMAIN ?: $_SERVER['HTTP_HOST'],
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie($name, $token, $params);
    }

    public function is_token_expired(int $verified_at): bool
    {
        return (time() - (int)$verified_at) > self::TOKEN_TTL;
    }
}
