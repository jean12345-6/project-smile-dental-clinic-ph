<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Clinic settings that the administrator can change without touching code (NFR-23). */
class Settings
{
    public const DEFAULTS = ['reminder_hours' => 24, 'no_show_threshold' => 3, 'offer_expiry_hours' => 2];

    public static function get(?int $clinicId, string $key, $default = null)
    {
        $default ??= self::DEFAULTS[$key] ?? null;
        if (! Schema::hasTable('clinic_settings')) {
            return $default;
        }
        $v = DB::table('clinic_settings')->where('key', $key)
            ->where(fn ($q) => $q->where('clinic_id', $clinicId)->orWhereNull('clinic_id'))
            ->orderByRaw('clinic_id IS NULL')->value('value');

        return $v === null ? $default : $v;
    }

    public static function all(?int $clinicId): array
    {
        $out = [];
        foreach (self::DEFAULTS as $k => $d) {
            $out[$k] = (int) self::get($clinicId, $k, $d);
        }

        return $out;
    }

    public static function set(?int $clinicId, string $key, $value): void
    {
        DB::table('clinic_settings')->updateOrInsert(['clinic_id' => $clinicId, 'key' => $key], ['value' => (string) $value, 'updated_at' => now(), 'created_at' => now()]);
    }
}
