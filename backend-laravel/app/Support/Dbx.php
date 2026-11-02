<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Small helpers so controllers only touch columns that really exist. */
class Dbx
{
    public static function cols(string $table): array
    {
        static $cache = [];

        return $cache[$table] ??= Schema::getColumnListing($table);
    }

    public static function has(string $table, string $col): bool
    {
        return in_array($col, self::cols($table), true);
    }

    public static function only(string $table, array $data): array
    {
        return array_intersect_key($data, array_flip(self::cols($table)));
    }

    public static function insert(string $table, array $data)
    {
        $now = now()->toDateTimeString();
        foreach (['created_at', 'updated_at'] as $c) {
            if (self::has($table, $c) && ! isset($data[$c])) {
                $data[$c] = $now;
            }
        }

        return DB::table($table)->insertGetId(self::only($table, $data));
    }

    public static function audit($user, string $action, string $table, $recordId, $old = null, $new = null): void
    {
        try {
            if (! Schema::hasTable('audit_logs')) {
                return;
            }
            self::insert('audit_logs', [
                'user_id' => $user?->id,
                'action' => $action,
                'table_name' => $table,
                'record_id' => $recordId,
                'old_value' => $old ? json_encode($old) : null,
                'new_value' => $new ? json_encode($new) : null,
                'timestamp' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            // logging must never break the real action
        }
    }

    public static function err(string $message, int $status = 422)
    {
        return response()->json(['errors' => [['status' => $status, 'message' => $message]]], $status);
    }
}
