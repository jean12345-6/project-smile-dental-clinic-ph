<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Dbx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Clinic info and hours
 */
class ClinicInfoController extends APIController
{
    private const DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];

    private const INFO = ['director', 'address', 'phone2'];

    private function put(string $key, string $value): void
    {
        DB::table('clinic_settings')->updateOrInsert(['clinic_id' => null, 'key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
    }

    private function t(string $hhmm): string
    {
        return date('g:i A', strtotime($hhmm));
    }

    private function allowed(Request $r): bool
    {
        return in_array($r->user()->role, ['superadmin', 'clinic_admin'], true);
    }

    /** GET /api/v1/clinic-info — {info:{director,address,phone2}, hours:{mon:{closed,open,close},...}} */
    public function show(Request $r)
    {
        if (! $this->allowed($r)) {
            return Dbx::err('Not allowed.', 403);
        }
        $rows = DB::table('clinic_settings')->whereNull('clinic_id')
            ->where(function ($q) { $q->where('key', 'like', 'clinic.%')->orWhere('key', 'like', 'hours.%'); })
            ->pluck('value', 'key');
        $info = [];
        foreach (self::INFO as $k) {
            $info[$k] = (string) ($rows['clinic.' . $k] ?? '');
        }
        $hours = [];
        foreach (self::DAYS as $d => $name) {
            $v = (string) ($rows['hours.' . $d] ?? ($d === 'sun' ? 'closed' : ($d === 'sat' ? '08:00-15:00' : '08:00-19:00')));
            if ($v === 'closed' || ! preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $v, $m)) {
                $hours[$d] = ['closed' => true, 'open' => '08:00', 'close' => '17:00'];
            } else {
                $hours[$d] = ['closed' => false, 'open' => $m[1], 'close' => $m[2]];
            }
        }

        return response()->json(['info' => (object) $info, 'hours' => (object) $hours]);
    }

    /** PUT /api/v1/clinic-info {info:{...}, hours:{mon:{closed,open,close},...}, sync_landing:bool} */
    public function save(Request $r)
    {
        if (! $this->allowed($r)) {
            return Dbx::err('Not allowed.', 403);
        }
        $r->validate(['info' => 'array', 'hours' => 'array']);
        $info = (array) $r->input('info', []);
        $hours = (array) $r->input('hours', []);

        $clean = [];
        foreach (self::DAYS as $d => $name) {
            $h = (array) ($hours[$d] ?? []);
            if (! empty($h['closed'])) {
                $clean[$d] = 'closed';
                continue;
            }
            $o = (string) ($h['open'] ?? '');
            $c = (string) ($h['close'] ?? '');
            if (! preg_match('/^\d{2}:\d{2}$/', $o) || ! preg_match('/^\d{2}:\d{2}$/', $c)) {
                return Dbx::err("{$name}: please set the opening and closing time.");
            }
            if ($o >= $c) {
                return Dbx::err("{$name}: closing time must be later than opening time.");
            }
            $clean[$d] = $o . '-' . $c;
        }
        foreach (self::INFO as $k) {
            $v = trim((string) ($info[$k] ?? ''));
            if (mb_strlen($v) > 250) {
                return Dbx::err("The text for \"{$k}\" is too long (250 characters at most).");
            }
            $this->put('clinic.' . $k, $v);
        }
        foreach ($clean as $d => $v) {
            $this->put('hours.' . $d, $v);
        }

        $synced = false;
        if ($r->boolean('sync_landing')) {
            $label = function (string $d) use ($clean) {
                if ($clean[$d] === 'closed') {
                    return 'Closed';
                }
                [$o, $c] = explode('-', $clean[$d]);

                return $this->t($o) . ' – ' . $this->t($c);
            };
            $wk = array_map($label, array_combine(['mon', 'tue', 'wed', 'thu', 'fri'], ['mon', 'tue', 'wed', 'thu', 'fri']));
            if (count(array_unique($wk)) === 1) {
                $weekday = 'Monday – Friday: ' . $wk['mon'];
            } else {
                $parts = [];
                foreach ($wk as $d => $txt) {
                    $parts[] = ucfirst($d) . ' ' . $txt;
                }
                $weekday = mb_substr(implode(', ', $parts), 0, 250);
            }
            $this->put('landing.hours.weekday', $weekday);
            $this->put('landing.hours.sat', 'Saturday: ' . $label('sat'));
            $this->put('landing.hours.sun', 'Sunday: ' . $label('sun'));
            if (trim((string) ($info['address'] ?? '')) !== '') {
                $this->put('landing.contact.address', trim((string) $info['address']));
            }
            if (trim((string) ($info['phone2'] ?? '')) !== '') {
                $this->put('landing.contact.phone2', trim((string) $info['phone2']));
            }
            $synced = true;
        }
        Dbx::audit($r->user(), 'update', 'clinic_info', 0, null, ['synced_landing' => $synced]);

        return response()->json(['message' => $synced ? 'Saved. The landing page was updated too.' : 'Saved.', 'synced' => $synced]);
    }
}
