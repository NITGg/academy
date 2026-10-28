<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_appleauth;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

/**
 * Verifies the identity token the iOS / Flutter app gets from Sign in with Apple.
 *
 * Checks, against Apple's public keys (JWKS, cached): RS256 signature, iss,
 * aud ∈ the configured client ids (iOS bundle id / Services ID), exp / iat, sub,
 * and the nonce when the app sent one.
 *
 * @package    local_appleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apple_token {

    /** Token issuer. */
    const ISSUER = 'https://appleid.apple.com';

    /** Apple's public signing keys. */
    const JWKS_URL = 'https://appleid.apple.com/auth/keys';

    /** Refetch the key set after this long. */
    const JWKS_MAXAGE = DAYSECS;

    /** An unknown key id refetches at most this often. */
    const JWKS_COOLDOWN = 5 * MINSECS;

    /** Clock skew allowed on exp / iat. */
    const LEEWAY = 60;

    /**
     * Verify an identity token and return its profile.
     *
     * @param string $idtoken the JWT from Apple
     * @param string[] $audiences accepted `aud` values
     * @param string|null $nonce the nonce the app sent, if any
     * @param array|null $jwks a key set to use instead of Apple's (tests)
     * @return \stdClass {sub, email|null, email_verified, is_private_email}
     * @throws auth_error
     */
    public static function verify(string $idtoken, array $audiences, ?string $nonce = null, ?array $jwks = null): \stdClass {
        if (!$audiences) {
            throw new auth_error('no_clientids_configured', 503);
        }

        $parts = explode('.', $idtoken);
        $header = count($parts) === 3 ? json_decode(JWT::urlsafeB64Decode($parts[0])) : null;
        if (!is_object($header) || ($header->alg ?? '') !== 'RS256' || empty($header->kid)) {
            throw new auth_error('invalid_idtoken', 401);
        }

        $keys = $jwks !== null ? JWK::parseKeySet($jwks, 'RS256') : self::keys((string) $header->kid);
        if (!isset($keys[$header->kid])) {
            throw new auth_error('invalid_idtoken', 401);
        }

        JWT::$leeway = self::LEEWAY;
        try {
            $claims = JWT::decode($idtoken, $keys);
        } catch (ExpiredException $e) {
            throw new auth_error('token_expired', 401);
        } catch (\Throwable $e) {
            throw new auth_error('invalid_idtoken', 401);
        }

        if (($claims->iss ?? '') !== self::ISSUER) {
            throw new auth_error('invalid_issuer', 401);
        }
        $aud = (array) ($claims->aud ?? []);
        if (!array_intersect($aud, $audiences)) {
            // Include the received aud so the exact client id to whitelist is visible.
            throw new auth_error('invalid_audience', 401, ['received_aud' => $claims->aud ?? null]);
        }
        if (empty($claims->exp) || empty($claims->iat)) {
            throw new auth_error('invalid_idtoken', 401);
        }
        if (empty($claims->sub) || !is_string($claims->sub)) {
            throw new auth_error('invalid_idtoken', 401);
        }
        if ($nonce !== null && $nonce !== '' && !self::nonce_matches($claims->nonce ?? null, $nonce)) {
            throw new auth_error('nonce_mismatch', 401);
        }

        $email = is_string($claims->email ?? null) ? \core_text::strtolower(trim($claims->email)) : '';
        return (object) [
            'sub' => $claims->sub,
            'email' => $email !== '' ? $email : null,
            'email_verified' => $email !== '' && self::truthy($claims->email_verified ?? false),
            'is_private_email' => self::truthy($claims->is_private_email ?? false),
        ];
    }

    /**
     * The app may send the raw nonce (Apple then carries its SHA-256 — the
     * sign_in_with_apple / Firebase convention) or the exact value it gave Apple.
     *
     * @param mixed $claim the token's nonce claim
     * @param string $nonce what the app sent
     * @return bool
     */
    public static function nonce_matches($claim, string $nonce): bool {
        if (!is_string($claim) || $claim === '') {
            return false;
        }
        return hash_equals($claim, $nonce) || hash_equals($claim, hash('sha256', $nonce));
    }

    /**
     * Apple sends booleans as `true` or as the string "true".
     *
     * @param mixed $value
     * @return bool
     */
    private static function truthy($value): bool {
        return $value === true || $value === 'true';
    }

    /**
     * Apple's signing keys by kid, from cache unless stale or missing $kid.
     *
     * @param string $kid key id the token was signed with
     * @return \Firebase\JWT\Key[]
     * @throws auth_error apple_unavailable when the key set cannot be fetched
     */
    private static function keys(string $kid): array {
        $cache = \cache::make('local_appleauth', 'jwks');
        $entry = $cache->get('apple');
        $age = is_array($entry) ? time() - $entry['fetched'] : PHP_INT_MAX;
        $known = is_array($entry) && in_array($kid, array_column($entry['jwks']['keys'], 'kid'), true);

        if ($age >= self::JWKS_MAXAGE || (!$known && $age >= self::JWKS_COOLDOWN)) {
            $curl = new \curl();
            $curl->setopt(['CURLOPT_TIMEOUT' => 10, 'CURLOPT_CONNECTTIMEOUT' => 5]);
            $jwks = json_decode((string) $curl->get(self::JWKS_URL), true);
            if (!$curl->get_errno() && is_array($jwks) && !empty($jwks['keys'])) {
                $entry = ['fetched' => time(), 'jwks' => $jwks];
                $cache->set('apple', $entry);
            } else if (!is_array($entry)) {
                // Apple's key endpoint unreachable and nothing cached: our problem, not a bad token.
                throw new auth_error('apple_unavailable', 503);
            }
        }

        try {
            return JWK::parseKeySet($entry['jwks'], 'RS256');
        } catch (\Throwable $e) {
            throw new auth_error('apple_unavailable', 503);
        }
    }
}
