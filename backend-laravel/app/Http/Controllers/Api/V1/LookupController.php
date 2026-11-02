<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Scope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @group Lookups
 *
 * Lists the services, dentists and branches used by the booking forms.
 */
class LookupController extends APIController
{
    /**
     * GET /api/v1/lookups
     * Everything the front end needs to fill its dropdowns. Staff also get the patient names.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isPatient = $user->role === 'patient';

        $services = Scope::table(DB::table('services'), 'services', $user)->where('is_active', 1)->orderBy('service_name')->get();
        $branches = Scope::table(DB::table('branches'), 'branches', $user)->get();

        // Dentists: add a display name from the users table when the link exists
        $dentists = Scope::table(DB::table('dentists'), 'dentists', $user)->get();
        if (Schema::hasColumn('dentists', 'user_id')) {
            $names = DB::table('users')->whereIn('id', $dentists->pluck('user_id')->filter())->pluck('name', 'id');
            $dentists = $dentists->map(function ($d) use ($names) {
                $d->display_name = $names[$d->user_id] ?? null;

                return $d;
            });
        }

        // Patients: staff only
        $patients = [];
        if (! $isPatient) {
            $col = collect(['full_name', 'name'])->first(fn ($c) => Schema::hasColumn('patients', $c));
            if ($col) {
                $pq = Scope::table(DB::table('patients'), 'patients', $user);
                if (Schema::hasColumn('patients', 'status')) {
                    $pq->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'archived'));
                }
                $patients = $pq->orderBy($col)->get(['patient_id', $col . ' as name'])->all();
            }
        }

        return response()->json([
            'patientId' => $user->patient?->patient_id,
            'services' => $services,
            'clinicId' => $user->clinic_id,
            'branches' => $branches,
            'dentists' => $dentists->values(),
            'patients' => $patients,
        ]);
    }
}
