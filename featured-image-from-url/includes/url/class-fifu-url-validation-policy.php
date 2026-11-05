<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Defines when a FIFU URL may be checked and when it is considered broken.
 */
final class Fifu_Url_Validation_Policy
{
    public const RETRY_AFTER_HOURS = 24;
    public const BROKEN_AFTER_FAILURES = 3;

    public static function is_due(?string $lastAttempt, \DateTimeImmutable $now): bool
    {
        if ($lastAttempt === null || trim($lastAttempt) === '') {
            return true;
        }

        try {
            $lastAttemptAt = new \DateTimeImmutable($lastAttempt, $now->getTimezone());
        } catch (\Exception $exception) {
            return true;
        }

        return $lastAttemptAt->modify('+' . self::RETRY_AFTER_HOURS . ' hours') <= $now;
    }

    public static function next_failure_count(int $currentFailures, bool $succeeded): int
    {
        if ($succeeded) {
            return 0;
        }

        return max(0, $currentFailures) + 1;
    }

    public static function is_broken(int $failureCount): bool
    {
        return $failureCount >= self::BROKEN_AFTER_FAILURES;
    }
}
