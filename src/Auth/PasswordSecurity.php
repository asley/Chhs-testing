<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
*/

namespace Gibbon\Auth;

/**
 * PasswordSecurity - Secure password hashing and verification
 *
 * Provides secure password handling using bcrypt/argon2id while maintaining
 * backward compatibility with legacy SHA-256 hashes during migration.
 *
 * MIGRATION PLAN:
 * - New passwords are hashed with bcrypt/argon2id
 * - Legacy SHA-256 hashes are verified for backward compatibility
 * - Passwords are re-hashed on successful login (automatic upgrade)
 * - All passwords should be migrated within 90 days
 *
 * @since v31
 */
class PasswordSecurity
{
    // Hash algorithm constants
    const ALGO_BCRYPT = 'bcrypt';
    const ALGO_ARGON2ID = 'argon2id';
    const DEFAULT_ALGO = PASSWORD_ARGON2ID;

    // Legacy algorithm (being phased out)
    const LEGACY_ALGO_SHA256 = 'sha256';

    /**
     * Hash a password using secure bcrypt/argon2id algorithm
     *
     * @param string $password The plain-text password to hash
     * @param string $algo Algorithm to use (PASSWORD_ARGON2ID or PASSWORD_BCRYPT)
     * @return string The hashed password
     */
    public static function hashPassword($password, $algo = null)
    {
        if ($algo === null) {
            $algo = self::DEFAULT_ALGO;
        }

        $options = [];

        if ($algo === PASSWORD_ARGON2ID) {
            $options = [
                'memory_cost' => 65536,  // 64MB
                'time_cost' => 4,         // 4 iterations
                'threads' => 2            // 2 parallel threads
            ];
        } elseif ($algo === PASSWORD_BCRYPT) {
            $options = ['cost' => 12];    // Work factor (higher = slower, more secure)
        }

        return password_hash($password, $algo, $options);
    }

    /**
     * Verify a password against its hash
     *
     * Supports both new bcrypt/argon2id hashes and legacy SHA-256 hashes
     * Legacy hashes require the salt to be passed separately
     *
     * @param string $password The plain-text password to verify
     * @param string $hash The hash to verify against
     * @param string $legacySalt Optional salt for legacy SHA-256 verification
     * @return bool True if password matches, false otherwise
     */
    public static function verifyPassword($password, $hash, $legacySalt = null)
    {
        // Try new algorithm first
        if (password_verify($password, $hash)) {
            return true;
        }

        // Fall back to legacy SHA-256 verification during migration period
        if ($legacySalt !== null && self::verifyLegacySha256($password, $hash, $legacySalt)) {
            return true;
        }

        return false;
    }

    /**
     * Verify a password using legacy SHA-256 hashing
     *
     * DEPRECATED: This is for backward compatibility only during migration
     * This algorithm is NOT secure and should not be used for new systems
     *
     * @param string $password The plain-text password
     * @param string $hash The SHA-256 hash (format: salt+password)
     * @param string $salt The salt used in hashing
     * @return bool True if password matches legacy hash
     */
    protected static function verifyLegacySha256($password, $hash, $salt)
    {
        if (empty($salt) || empty($password)) {
            return false;
        }

        $computedHash = hash('sha256', $salt . $password);

        // Use hash_equals() to prevent timing attacks
        return hash_equals($computedHash, $hash);
    }

    /**
     * Check if a password needs re-hashing
     *
     * This is used to upgrade legacy SHA-256 hashes to bcrypt/argon2id
     * when a user successfully authenticates
     *
     * @param string $hash The current password hash
     * @param string $algo The algorithm to check against (default: current default algo)
     * @return bool True if password should be re-hashed
     */
    public static function needsRehash($hash, $algo = null)
    {
        if ($algo === null) {
            $algo = self::DEFAULT_ALGO;
        }

        // Check if hash is using modern algorithm and parameters
        return password_needs_rehash($hash, $algo, self::getAlgoOptions($algo));
    }

    /**
     * Get the appropriate options for a password algorithm
     *
     * @param string $algo The algorithm to get options for
     * @return array Options for the algorithm
     */
    protected static function getAlgoOptions($algo)
    {
        if ($algo === PASSWORD_ARGON2ID) {
            return [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 2
            ];
        } elseif ($algo === PASSWORD_BCRYPT) {
            return ['cost' => 12];
        }

        return [];
    }

    /**
     * Detect if a hash is using legacy SHA-256 algorithm
     *
     * Hashes from the new algorithms start with specific prefixes:
     * - bcrypt: $2y$
     * - argon2id: $argon2id$
     *
     * If it's neither of these, it's likely a legacy SHA-256 hex hash
     *
     * @param string $hash The hash to check
     * @return bool True if hash appears to be legacy SHA-256
     */
    public static function isLegacyHash($hash)
    {
        if (empty($hash)) {
            return false;
        }

        // Check for bcrypt prefix
        if (strpos($hash, '$2y$') === 0) {
            return false;
        }

        // Check for argon2id prefix
        if (strpos($hash, '$argon2id$') === 0) {
            return false;
        }

        // Check for argon2i prefix (older variant)
        if (strpos($hash, '$argon2i$') === 0) {
            return false;
        }

        // If none of the modern prefixes, assume legacy SHA-256
        return true;
    }

    /**
     * Generate a secure random salt
     *
     * Note: Modern algorithms (bcrypt, argon2) generate their own salt
     * This is for compatibility with legacy code only
     *
     * @return string A random salt
     */
    public static function generateLegacySalt()
    {
        // Use a reasonably strong salt for legacy SHA-256
        return bin2hex(random_bytes(16));
    }
}
