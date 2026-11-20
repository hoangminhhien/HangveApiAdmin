<?php

namespace App\Scopes\Traits;


trait FillterOrganization
{
    public function scopeOrganizationId($query)
    {
        return $query->where("organization_id", request()->query('organization_id'));
    }
}