<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Caches report figures (WST criterion 18: performance).
 *
 * Reports run several GROUP BY / SUM queries over every appointment and payment. The result
 * is kept for up to 10 minutes, but it is never stale: every successful POST, PUT, PATCH or
 * DELETE to the API bumps a "data version" number (BumpReportCache middleware), and that
 * number is part of the cache key. So the first report after any change is computed fresh, and repeat views
 * (opening the page again, switching tabs, printing) come straight from the cache.
 */
class ReportCache
{
    private const VERSION_KEY = 'report-data-version';
    private const TTL_SECONDS = 600;

    /** Called after every successful write (BumpReportCache middleware). */
    public static function bump(): void
    {
        try {
            Cache::add(self::VERSION_KEY, 1);
            Cache::increment(self::VERSION_KEY);
        } catch (\Throwable $e) {
            // a cache problem must never block a save
        }
    }

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    /**
     * @param  string  $name    which report, e.g. 'reports' or 'finance'
     * @param  array   $params  everything that changes the answer: user's clinic, role, date range, branch...
     */
    public static function remember(string $name, array $params, \Closure $compute): mixed
    {
        $key = 'report:' . $name . ':v' . self::version() . ':' . md5(json_encode($params));

        try {
            return Cache::remember($key, self::TTL_SECONDS, $compute);
        } catch (\Throwable $e) {
            return $compute(); // the cache is an optimisation, never a requirement
        }
    }
}
