<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Dbx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Patient portal
 */
class PatientBillingController extends APIController
{
    /** GET /api/v1/my/transactions — every payment made on the patient's own invoices. */
    public function transactions(Request $r)
    {
        $p = $r->user()->role === 'patient' ? $r->user()->patient : null;
        if (! $p) {
            return Dbx::err('Only patients can see their own transactions.', 403);
        }
        $rows = DB::table('payments')
            ->join('invoices', 'invoices.invoice_id', '=', 'payments.invoice_id')
            ->where('invoices.patient_id', $p->patient_id)
            ->orderByDesc('payments.paid_at')->orderByDesc('payments.payment_id')
            ->get(['payments.payment_id', 'payments.invoice_id', 'payments.amount', 'payments.method', 'payments.paid_at']);

        return response()->json(['data' => $rows]);
    }
}
