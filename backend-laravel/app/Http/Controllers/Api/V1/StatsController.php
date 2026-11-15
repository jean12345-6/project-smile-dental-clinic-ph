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
        $ap = fn () => Scope::patients(DB::table('appointments'), 'appointments.patient_id', $u);
        $today = now()->toDateString();

        $byStatus = $ap()->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

        $todayRows = Scope::patients(DB::table('appointments'), 'appointments.patient_id', $u)
            ->join('patients', 'patients.patient_id', '=', 'appointments.patient_id')
            ->join('services', 'services.service_id', '=', 'appointments.service_id')
            ->whereDate('appointments.start_time', $today)
            ->orderBy('appointments.start_time')->limit(12)
            ->get(['appointments.appointment_id', 'appointments.start_time', 'appointments.status', 'appointments.dentist_id', 'patients.full_name as patient', 'services.service_name as service']);

        $low = Schema::hasTable('inventory')
            ? Scope::table(DB::table('inventory'), 'inventory', $u)->whereColumn('quantity', '<=', 'reorder_level')->orderBy('quantity')->get()
            : collect();

        $recent = Scope::patients(DB::table('treatments'), 'treatments.patient_id', $u)
            ->join('patients', 'patients.patient_id', '=', 'treatments.patient_id')
            ->orderByDesc('treatments.treatment_id')->limit(5)
            ->get(['treatments.procedure_name', 'treatments.cost', 'patients.full_name as patient']);

        $inv = Scope::table(DB::table('invoices'), 'invoices', $u);
        $paid = (float) (clone $inv)->sum('total_amount') - (float) (clone $inv)->sum('balance');

        return response()->json([
            'appointments' => [
                'total' => (int) $byStatus->sum(),
                'today' => $ap()->whereDate('appointments.start_time', $today)->count(),
                'byStatus' => $byStatus,
                'highRisk' => $ap()->where('risk_flag', 1)->whereIn('status', ['pending', 'confirmed'])->count(),
            ],
            'patients' => Scope::table(DB::table('patients'), 'patients', $u)->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'archived'))->count(),
            'waitlist' => Scope::table(DB::table('waitlist'), 'waitlist', $u)->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status'),
            'revenue' => round($paid, 2),
            'unpaid' => (float) (clone $inv)->sum('balance'),
            'lowStock' => $low,
            'escalated' => \App\Http\Controllers\Api\V1\StaffInboxController::openCount($u), // chats still waiting for a staff reply
            'today' => $todayRows,
            'recentTreatments' => $recent,
        ]);
    }
}
