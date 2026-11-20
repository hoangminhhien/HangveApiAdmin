<?php


namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

class SortByCreatedDescScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        //$column = 'created_at';
        //$column = $model->getCreatedAtColumn() ? $model->getCreatedAtColumn() : 'id';
        $column = 'id';
        $table  = $model->getTable();
        //$isColExist = Schema::hasColumn($table, $column);
//        if (!$model->__isset($column)) {
//            return;
//        }
        $builder->orderByDesc($table . '.' . $column);
    }
}