<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provides helpers for FIFU cron jobs, including semaphore checks.
 */
final class Fifu_Cron_Job_Service
{
    /**
     * Checks whether a job identified by a semaphore key is still active.
     *
     * @param string $semaphore
     * @param int    $minutes
     * @return bool
     */
    public static function is_active(string $semaphore, int $minutes): bool
    {
        $date = Fifu_Transient_Manager::get($semaphore);
        if (!$date) {
            return false;
        }

        if (!$date instanceof \DateTimeInterface) {
            Fifu_Transient_Manager::set($semaphore, new DateTime(), 0);
            return true;
        }

        $now = new \DateTimeImmutable();
        $startedAt = $date->getTimestamp();

        if ($startedAt > $now->getTimestamp()) {
            return true;
        }

        $windowSeconds = max(0, $minutes) * 60;
        if (($now->getTimestamp() - $startedAt) < $windowSeconds) {
            return true;
        }

        Fifu_Transient_Manager::delete($semaphore);
        return false;
    }

}
