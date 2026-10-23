<?php

namespace App\Http\Filters\V1;

/**
 * The filters allowed on the appointments endpoints.
 */
class AppointmentFilter extends QueryFilter
{
    // The columns clients may sort appointments by
    protected array $sortable = [
        'status',
        'startTime' => 'start_time',
        'endTime' => 'end_time',
        'bookedVia' => 'booked_via',
        'riskFlag' => 'risk_flag',
        'createdAt' => 'created_at',
        'updatedAt' => 'updated_at',
    ];

    /**
     * ?startTime=2026-10-20                 one day
     * ?startTime=2026-10-01,2026-10-31      a date range (from,to), both days included
     */
    public function startTime($value)
    {
        $dates = explode(',', $value);

        if (count($dates) > 1) {
            return $this->builder->whereBetween('start_time', [
                $dates[0] . ' 00:00:00',
                $dates[1] . ' 23:59:59',
            ]);
        }

        return $this->builder->whereDate('start_time', $value);
    }

    /**
     * ?createdAt=... works like startTime, on the created_at column.
     */
    public function createdAt($value)
    {
        $dates = explode(',', $value);

        if (count($dates) > 1) {
            return $this->builder->whereBetween('created_at', [
                $dates[0] . ' 00:00:00',
                $dates[1] . ' 23:59:59',
            ]);
        }

        return $this->builder->whereDate('created_at', $value);
    }

    /**
     * ?updatedAt=... works exactly like createdAt, on the updated_at column.
     */
    public function updatedAt($value)
    {
        $dates = explode(',', $value);

        if (count($dates) > 1) {
            return $this->builder->whereBetween('updated_at', [
                $dates[0] . ' 00:00:00',
                $dates[1] . ' 23:59:59',
            ]);
        }

        return $this->builder->whereDate('updated_at', $value);
    }

    /**
     * ?include=patient loads the patient. Only relationship names on the
     * allowed list are accepted, so clients cannot request anything else.
     */
    public function include($value)
    {
        $allowed = array_intersect(explode(',', $value), ['patient']);

        return $this->builder->with($allowed);
    }

    /**
     * ?status=confirmed   or   ?status=confirmed,pending   (one or several statuses)
     */
    public function status($value)
    {
        return $this->builder->whereIn('status', explode(',', $value));
    }

    /**
     * ?bookedVia=chatbot   or   ?bookedVia=chatbot,portal
     */
    public function bookedVia($value)
    {
        return $this->builder->whereIn('booked_via', explode(',', $value));
    }

    /**
     * ?riskFlag=1 shows only appointments flagged as a no-show risk, ?riskFlag=0 the rest.
     */
    public function riskFlag($value)
    {
        return $this->builder->where('risk_flag', (bool) $value);
    }

    /**
     * ?dentistId=1   or   ?dentistId=1,2
     */
    public function dentistId($value)
    {
        return $this->builder->whereIn('dentist_id', explode(',', $value));
    }

    /**
     * ?patientId=14
     */
    public function patientId($value)
    {
        return $this->builder->whereIn('patient_id', explode(',', $value));
    }

    /**
     * ?branchId=1
     */
    public function branchId($value)
    {
        return $this->builder->whereIn('branch_id', explode(',', $value));
    }
}