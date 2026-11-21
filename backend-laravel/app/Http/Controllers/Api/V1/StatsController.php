<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Dbx;
use App\Support\Scope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @group Dashboard
 */
class StatsController extends APIController
{
    /** GET /api/v1/stats — real counts for the staff dashboard, limited to the user's clinic. */
    public function index(Request $request)
    {
        $u = $request->user();
        if ($u->role === 'patient') {
            return Dbx::err('Not allowed.', 403);
        }

        // The superadmin belongs to no clinic, so every figure below covered all of them at
        // once while the header named a single clinic: 61 patients and PHP 525,550 collected
        // were both clinics added together. The clinic chosen in the switcher now filters
        // these figures too, not only where new records are written. Sending no clinic, or
        // "all", keeps the combined view, which is the one a superadmin sometimes wants.
        $picked = $request->query('clinicId', $request->header('X-Clinic-Id'));
        if ($u->role === 'superadmin' && $picked && strtolower((string) $picked) !== 'all') {
            // A stand-in user carrying that clinic, so every Scope call below behaves as a
            // clinic admin of it. The real user's role is unchanged.
            $u = (object) ['role' => 'clinic_admin', 'id' => $u->id, 'clinic_id' => (int) $picked];
        }
        $scopedTo = $u->role === 'superadmin' ? null : ($u->clinic_id ?? null);
        // A dentist's dashboard counts their own work, not the whole clinic's (see Scope::ownDentist).
        $ap = fn () => Scope::ownDentist(Scope::patients(DB::table('appointments'), 'appointments.patient_id', $u), 'appointments.dentist_id', $u);
        $today = now()->toDateString();

        $byStatus = $ap()->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

        $todayRows = Scope::ownDentist(Scope::patients(DB::table('appointments'), 'appointments.patient_id', $u), 'appointments.dentist_id', $u)
            ->join('patients', 'patients.patient_id', '=', 'appointments.patient_id')
            ->join('services', 'services.service_id', '=', 'appointments.service_id')
            ->whereDate('appointments.start_time', $today)
            ->orderBy('appointments.start_time')->limit(12)
            ->get(['appointments.appointment_id', 'appointments.start_time', 'appointments.status', 'appointments.dentist_id', 'patients.full_name as patient', 'services.service_name as service']);

        $low = Schema::hasTable('inventory')
            ? Scope::table(DB::table('inventory'), 'inventory', $u)->whereColumn('quantity', '<=', 'reorder_level')->orderBy('quantity')->get()
            : collect();

        $recent = Scope::ownDentist(Scope::patients(DB::table('treatments'), 'treatments.patient_id', $u), 'treatments.dentist_id', $u)
            ->join('patients', 'patients.patient_id', '=', 'treatments.patient_id')
            ->orderByDesc('treatments.treatment_id')->limit(5)
            ->get(['treatments.procedure_name', 'treatments.cost', 'patients.full_name as patient']);

        // Money is for the owner and the clinic admin only. The finance page already refuses
        // every other role, but the dashboard was handing the same totals to the receptionist
        // and the dentist, so the figures were hidden in one place and shown in another.
        $money = in_array($u->role, ['superadmin', 'clinic_admin'], true);
        if ($money) {
            $inv = Scope::table(DB::table('invoices'), 'invoices', $u);
            $paid = (float) (clone $inv)->sum('total_amount') - (float) (clone $inv)->sum('balance');
        }

        return response()->json([
            'appointments' => [
                'total' => (int) $byStatus->sum(),
                'today' => $ap()->whereDate('appointments.start_time', $today)->count(),
                'byStatus' => $byStatus,
                'highRisk' => $ap()->where('risk_flag', 1)->whereIn('status', ['pending', 'confirmed'])->count(),
            ],
            'patients' => Scope::table(DB::table('patients'), 'patients', $u)->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'archived'))->count(),
            'waitlist' => Scope::table(DB::table('waitlist'), 'waitlist', $u)->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status'),
            'revenue' => $money ? round($paid, 2) : null,
            'unpaid' => $money ? (float) (clone $inv)->sum('balance') : null,
            'lowStock' => $low,
            'escalated' => \App\Http\Controllers\Api\V1\StaffInboxController::openCount($u), // chats still waiting for a staff reply
            'clinicScope' => $scopedTo,   // null means the figures cover every clinic
            'today' => $todayRows,
            'recentTreatments' => $recent,
        ]);
    }
}
