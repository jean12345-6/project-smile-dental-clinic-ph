<?php

namespace App\Console\Commands;

use App\Services\V1\ReminderService;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'smilesync:reminders';
    protected $description = 'Send appointment reminders, update risk flags, expire old waitlist offers';

    public function handle(ReminderService $svc): int
    {
        $this->table(['What', 'Count'], collect($svc->run())->map(fn ($v, $k) => [$k, $v])->values()->all());

        return self::SUCCESS;
    }
}
