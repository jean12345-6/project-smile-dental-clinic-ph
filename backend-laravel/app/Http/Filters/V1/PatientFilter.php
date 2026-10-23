<?php

namespace App\Http\Filters\V1;

/**
 * The filters and sort columns allowed on the patients endpoint.
 */
class PatientFilter extends QueryFilter
{
    // The columns clients may sort patients by
    protected array $sortable = [
        'fullName' => 'full_name',
        'email',
        'createdAt' => 'created_at',
        'updatedAt' => 'updated_at',
    ];

    // ?id=1,2,3  (one or several ids)
    public function id($value)
    {
        return $this->builder->whereIn('patient_id', explode(',', $value));
    }

    // ?fullName=*maria*  (wildcard search)
    public function fullName($value)
    {
        $likeStr = str_replace('*', '%', $value);

        return $this->builder->where('full_name', 'like', $likeStr);
    }

    // ?email=*@gmail.com  (wildcard search)
    public function email($value)
    {
        $likeStr = str_replace('*', '%', $value);

        return $this->builder->where('email', 'like', $likeStr);
    }

    // ?createdAt=2026-10-19  or  ?createdAt=from,to
    public function createdAt($value)
    {
        $dates = explode(',', $value);

        if (count($dates) > 1) {
            return $this->builder->whereBetween('created_at', [$dates[0] . ' 00:00:00', $dates[1] . ' 23:59:59']);
        }

        return $this->builder->whereDate('created_at', $value);
    }

    // ?updatedAt=2026-10-19  or  ?updatedAt=from,to
    public function updatedAt($value)
    {
        $dates = explode(',', $value);

        if (count($dates) > 1) {
            return $this->builder->whereBetween('updated_at', [$dates[0] . ' 00:00:00', $dates[1] . ' 23:59:59']);
        }

        return $this->builder->whereDate('updated_at', $value);
    }

    // ?include=appointments loads each patient's appointments (allowed relationships only)
    public function include($value)
    {
        $allowed = array_intersect(explode(',', $value), ['appointments']);

        return $this->builder->with($allowed);
    }
}