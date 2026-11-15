<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Dbx;
use App\Support\Scope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Dashboard
 */
class FinanceController extends APIController
{
    /** GET /api/v1/stats/finance?branch_id= — sales, paid, pending and charts for the admin dashboard. */
    public function index(Request $r)
    {
        $u = $r->user();
        if (! in_array($u->role, ['superadmin', 'clinic_admin'], true)) {
            return Dbx::err('Not allowed.', 403);
        }
        $branch = (int) $r->query('branch_id', 0);

        // every invoice, with the branch of its appointment (invoices without an appointment have no branch)
        $inv = function () use ($u, $branch) {
            $q = Scope::table(DB::table('invoices'), 'invoices', $u)
                ->leftJoin('appointments', 'appointments.appointment_id', '=', 'invoices.appointment_id');
            if ($branch) {
                $q->where('appointments.branch_id', $branch);
            }

            return $q;
        };
        $paidSql = 'SUM(invoices.total_amount - invoices.balance)';

        $t = $inv()->selectRaw('COUNT(*) as n, COALESCE(SUM(invoices.total_amount),0) as sales, COALESCE(' . $paidSql . ',0) as paid')->first();
        $sales = (float) $t->sales;
        $paid = (float) $t->paid;

        $month = $inv()->where('invoices.date_issued', '>=', now()->subMonths(11)->startOfMonth()->toDateString())
            ->selectRaw("DATE_FORMAT(invoices.date_issued,'%Y-%m') as month, SUM(invoices.total_amount) as sales, " . $paidSql . ' as paid')
            ->groupBy('month')->orderBy('month')->get();

        $byBranch = $inv()->leftJoin('branches', 'branches.branch_id', '=', 'appointments.branch_id')
            ->selectRaw("COALESCE(branches.branch_name,'No branch') as name, COUNT(*) as invoices, SUM(invoices.total_amount) as sales, " . $paidSql . ' as paid')
            ->groupBy('name')->orderByDesc('sales')->get()
            ->map(fn ($b) => ['name' => $b->name, 'invoices' => (int) $b->invoices, 'sales' => (float) $b->sales, 'paid' => (float) $b->paid, 'pending' => round($b->sales - $b->paid, 2), 'pct' => $b->sales > 0 ? round($b->paid / $b->sales * 100, 1) : 0]);

        $byDentist = $inv()->leftJoin('dentists', 'dentists.dentist_id', '=', 'appointments.dentist_id')
            ->selectRaw("COALESCE(dentists.full_name,'Unassigned') as name, COUNT(*) as invoices, SUM(invoices.total_amount) as sales")
            ->groupBy('name')->orderByDesc('sales')->limit(8)->get();

        $procedures = Scope::patients(DB::table('treatments'), 'treatments.patient_id', $u)
            ->selectRaw('procedure_name as name, COUNT(*) as n')->groupBy('procedure_name')->orderByDesc('n')->limit(8)->get();

        $trend = Scope::patients(DB::table('appointments'), 'appointments.patient_id', $u)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch))
            ->where('start_time', '>=', now()->subMonths(11)->startOfMonth()->toDateString())
            ->selectRaw("DATE_FORMAT(start_time,'%Y-%m') as month, COUNT(*) as total, SUM(status='completed') as completed, SUM(status IN ('cancelled','no_show')) as lost")
            ->groupBy('month')->orderBy('month')->get();

        return response()->json([
            'totals' => ['invoices' => (int) $t->n, 'sales' => $sales, 'paid' => $paid, 'pending' => round($sales - $paid, 2), 'pct' => $sales > 0 ? round($paid / $sales * 100, 1) : 0],
            'byMonth' => $month, 'byBranch' => $byBranch, 'byDentist' => $byDentist, 'procedures' => $procedures, 'trend' => $trend,
        ]);
    }
}
