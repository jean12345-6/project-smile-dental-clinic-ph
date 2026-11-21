<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\User;
use App\Services\V1\SlotService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Validation\ValidationException;

/**
 * Registered once from routes/api_v1_extra.php (after the user is authenticated). It adds three rules
 * to the existing models without changing their files:
 *   1. every query is limited to the user's clinic (FR-02, NFR-14)
 *   2. a booking is locked per dentist, so two patients can never take the same slot (NFR-18)
 *   3. every create, update and delete is written to the audit log (FR-03)
 */
class Boot
{
    private static bool $done = false;
    private static array $locks = [];

    private static function me()
    {
        $g = Auth::guard('api');

        return (method_exists($g, "hasUser") && $g->hasUser()) ? $g->user() : null;
    }

    public static function register(): void
    {
        if (self::$done) {
            return;
        }
        self::$done = true;

        // 1. clinic isolation
        $clinic = fn () => Scope::clinic(self::me());
        $lenient = fn (Builder $b, string $col, int $c) => $b->where(fn ($w) => $w->where($col, $c)->orWhereNull($col));

        Appointment::addGlobalScope('clinic', function (Builder $b) use ($clinic) {
            if ($c = $clinic()) {
                $b->where(fn ($w) => $w->whereNull('appointments.branch_id')->orWhereIn('appointments.branch_id', fn ($s) => $s->select('branch_id')->from('branches')->where(fn ($x) => $x->where('clinic_id', $c)->orWhereNull('clinic_id'))));
            }
            // A dentist sees their own appointments only, the same rule the daily queue
            // already applied. Without it the appointment list showed every dentist's
            // bookings, with a Cancel button on each.
            Scope::ownDentist($b, 'appointments.dentist_id', self::me());
        });
        User::addGlobalScope('clinic', function (Builder $b) use ($clinic, $lenient) {
            if ($c = $clinic()) {
                $lenient($b, 'users.clinic_id', $c);
            }
        });
        if (class_exists(\App\Models\Patient::class)) {
            \App\Models\Patient::addGlobalScope('clinic', function (Builder $b) use ($clinic, $lenient) {
                if ($c = $clinic()) {
                    $lenient($b, 'patients.clinic_id', $c);
                }
            });
        }

        // 2. booking lock + final overlap check
        Appointment::creating(function (Appointment $a) {
            if (in_array($a->status, ['cancelled', 'no_show'], true) || $a->booked_via === 'walk-in') {
                return;
            }
            $lock = Cache::lock('book-dentist-' . $a->dentist_id, 15);
            try {
                $lock->block(5);
            } catch (LockTimeoutException $e) {
                throw ValidationException::withMessages(['data.attributes.startTime' => ['Another booking is being saved for this dentist. Please try again.']]);
            }
            self::$locks[spl_object_id($a)] = $lock;
            $fmt = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : (string) $v;
            if (! app(SlotService::class)->isFree((int) $a->dentist_id, $fmt($a->start_time), $fmt($a->end_time))) {
                $lock->release();
                unset(self::$locks[spl_object_id($a)]);
                throw ValidationException::withMessages(['data.attributes.startTime' => ['That time is already taken. Please pick another slot.']]);
            }
        });
        Appointment::created(function (Appointment $a) {
            if ($l = self::$locks[spl_object_id($a)] ?? null) {
                $l->release();
                unset(self::$locks[spl_object_id($a)]);
            }
        });

        // 3. audit trail for the original endpoints
        foreach ([Appointment::class => 'appointments', User::class => 'users'] as $model => $table) {
            $skip = ['updated_at', 'created_at', 'remember_token', 'password'];
            $model::created(fn ($m) => Dbx::audit(self::me(), 'create', $table, $m->getKey(), null, array_diff_key($m->getAttributes(), array_flip($skip))));
            $model::updated(function ($m) use ($table, $skip) {
                $new = array_diff_key($m->getChanges(), array_flip($skip));
                $old = array_intersect_key($m->getOriginal(), $new);
                if ($new) {
                    Dbx::audit(self::me(), 'update', $table, $m->getKey(), $old, $new);
                }
            });
            $model::deleted(fn ($m) => Dbx::audit(self::me(), 'delete', $table, $m->getKey(), array_diff_key($m->getAttributes(), array_flip($skip)), null));
        }
    }
}
