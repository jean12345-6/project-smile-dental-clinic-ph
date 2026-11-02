<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Waitlist
 *
 * Join the waiting list when a slot is full. A freed slot is offered to the first person waiting.
 */
class WaitlistController extends APIController
{
    /**
     * GET /api/v1/waitlist
     * Staff see everyone. A patient sees only their own entries.
     *
     * @queryParam status string Filter: waiting, offered. Example: waiting
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = DB::table('waitlist')->orderBy('created_at');

        if ($user->role === 'patient') {
            $query->where('patient_id', $user->patient?->patient_id ?? 0);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json([
            'data' => $query->get()->map(fn ($w) => $this->format($w))->values(),
        ]);
    }

    /**
     * POST /api/v1/waitlist
     * Join the waiting list. A patient joins for themselves. Staff must send patientId.
     *
     * @bodyParam patientId integer Required for staff only. Example: 14
     * @bodyParam serviceId integer required Example: 1
     * @bodyParam branchId integer required Example: 1
     * @bodyParam dentistId integer Any dentist if empty. Example: 1
     * @bodyParam preferredDate string Any date if empty. YYYY-MM-DD. Example: 2026-11-09
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $isPatient = $user->role === 'patient';

        $data = $request->validate([
            'patientId' => [$isPatient ? 'nullable' : 'required', 'integer', 'exists:patients,patient_id'],
            'serviceId' => 'required|integer|exists:services,service_id',
            'branchId' => 'required|integer|exists:branches,branch_id',
            'dentistId' => 'nullable|integer|exists:dentists,dentist_id',
            'preferredDate' => 'nullable|date_format:Y-m-d|after_or_equal:today',
        ]);

        $patientId = $isPatient ? $user->patient?->patient_id : $data['patientId'];

        if (! $patientId) {
            return $this->notAuthorized('Your account has no patient record.');
        }

        if ($isPatient && isset($data['patientId']) && (int) $data['patientId'] !== (int) $patientId) {
            return $this->notAuthorized('You may only join the waitlist for yourself.');
        }

        $already = DB::table('waitlist')
            ->where('patient_id', $patientId)
            ->where('service_id', $data['serviceId'])
            ->where('branch_id', $data['branchId'])
            ->whereIn('status', ['waiting', 'offered'])
            ->exists();

        if ($already) {
            return $this->error([[
                'status' => 422,
                'message' => 'You are already on the waitlist for this service.',
                'source' => 'serviceId',
            ]]);
        }

        $id = DB::table('waitlist')->insertGetId([
            'patient_id' => $patientId,
            'service_id' => $data['serviceId'],
            'branch_id' => $data['branchId'],
            'dentist_id' => $data['dentistId'] ?? null,
            'preferred_date' => $data['preferredDate'] ?? null,
            'status' => 'waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'waitlist_id');

        return response()->json([
            'data' => $this->format(DB::table('waitlist')->where('waitlist_id', $id)->first()),
        ], 201);
    }

    private function format(object $w): array
    {
        return [
            'id' => $w->waitlist_id,
            'status' => $w->status,
            'patientId' => $w->patient_id,
            'serviceId' => $w->service_id,
            'branchId' => $w->branch_id,
            'dentistId' => $w->dentist_id,
            'preferredDate' => $w->preferred_date,
            'offeredAt' => $w->offered_at,
            'createdAt' => $w->created_at,
        ];
    }
}
