<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\V1\SlotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Slots
 *
 * Find the free time slots of a dentist.
 */
class SlotController extends APIController
{
    /**
     * GET /api/v1/slots
     * Lists the free slots of one dentist on one date. The slot length comes from the service.
     *
     * @queryParam dentistId integer required The dentist. Example: 1
     * @queryParam serviceId integer required The service (sets the duration). Example: 1
     * @queryParam date string required YYYY-MM-DD, today or later. Example: 2026-11-09
     * @queryParam branchId integer The branch (optional). Example: 1
     */
    public function index(Request $request, SlotService $slots)
    {
        $data = $request->validate([
            'dentistId' => 'required|integer|exists:dentists,dentist_id',
            'serviceId' => 'required|integer|exists:services,service_id',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'branchId' => 'sometimes|integer|exists:branches,branch_id',
        ]);

        $duration = DB::table('services')
            ->where('service_id', $data['serviceId'])
            ->where('is_active', 1)
            ->value('duration_min');

        if (! $duration) {
            return $this->error([[
                'status' => 422,
                'message' => 'That service is not available.',
                'source' => 'serviceId',
            ]]);
        }

        $free = $slots->forDay(
            (int) $data['dentistId'],
            $data['date'],
            (int) $duration,
            isset($data['branchId']) ? (int) $data['branchId'] : null,
            $request->user()
        );

        return response()->json([
            'data' => $free,
            'meta' => [
                'date' => $data['date'],
                'durationMin' => (int) $duration,
                'count' => count($free),
            ],
        ]);
    }
}