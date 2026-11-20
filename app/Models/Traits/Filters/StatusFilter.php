<?php


namespace App\Models\Traits\Filters;


trait StatusFilter
{
    public function scopeStatus($query)
    {
        $table = static::getTable();
        $status = is_array(request()->query('status')) ? request()->query('status') : [request()->query('status')];
        return $query->whereIn($table.'.status', $status);
    }
}
