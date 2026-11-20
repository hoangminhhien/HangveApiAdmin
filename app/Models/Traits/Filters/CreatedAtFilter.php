<?php


namespace App\Models\Traits\Filters;


use App\Services\FilterService;

trait CreatedAtFilter
{
    public function scopeCreatedAt($query)
    {
        $table = static::getTable();
        return (new FilterService())->rangeDateFilter($query, request()->query('created_at'), "$table.created_at");
    }
}
