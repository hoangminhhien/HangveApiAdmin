<?php


namespace App\Models\Traits\Filters;


trait CodeFilter
{
    public function scopeCode($query)
    {
        $table = static::getTable();
        return $query->where($table.'.code', request()->query('code'));
    }
}
