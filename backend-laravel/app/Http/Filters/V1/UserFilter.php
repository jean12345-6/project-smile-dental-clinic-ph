<?php

namespace App\Http\Filters\V1;

/**
 * The filters and sort columns allowed on the users endpoint.
 */
class UserFilter extends QueryFilter
{
    // The columns clients may sort users by
    protected array $sortable = [
        'name',
        'email',
        'role',
        'status',
        'createdAt' => 'created_at',
        'updatedAt' => 'updated_at',
    ];

    // ?id=1,2,3
    public function id($value)
    {
        return $this->builder->whereIn('id', explode(',', $value));
    }

    // ?name=*maria*
    public function name($value)
    {
        $likeStr = str_replace('*', '%', $value);

        return $this->builder->where('name', 'like', $likeStr);
    }

    // ?email=*@smilesync.test
    public function email($value)
    {
        $likeStr = str_replace('*', '%', $value);

        return $this->builder->where('email', 'like', $likeStr);
    }

    // ?role=dentist,receptionist
    public function role($value)
    {
        return $this->builder->whereIn('role', explode(',', $value));
    }

    // ?status=active
    public function status($value)
    {
        return $this->builder->whereIn('status', explode(',', $value));
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
}
