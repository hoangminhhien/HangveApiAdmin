<?php


namespace App\Models\Traits\Filters;


trait WarehouseFilter
{
    public function scopeWarehouseId($query)
    {
        $table = static::getTable();
        return $query->where($table.'.warehouse_id', request()->query('warehouse_id'));
    }
}
