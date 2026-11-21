<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Sends dates over the API as the clinic's own wall-clock time, with no zone attached.
 *
 * Every date-time in this database is a local wall-clock value: a 10:00 AM appointment is
 * stored as 10:00:00, and the React screens have always read it as it comes. Eloquent's
 * default serialisation converts a cast date to UTC and appends a Z. While the application
 * was configured as UTC that conversion changed nothing, so it went unnoticed. Once the
 * application was put on the clinic's clock, the same 15:00 was correctly understood as
 * 15:00 in Manila and correctly re-expressed as 07:00Z — and every screen then showed an
 * appointment eight hours early, which is the sort of mistake that makes a patient arrive
 * in the morning for an afternoon visit.
 *
 * The fix is to stop converting. The format below is exactly what the column holds, which
 * is what the whole front end already expects. Timestamps written by now() are now in
 * clinic time as well, so the two agree for the first time.
 */
trait LocalWallClockDates
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
