<?php


namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

class SortByCreatedAscScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        //$column = 'created_at';
        //$column = $model->getCreatedAtColumn() ? $model->getCreatedAtColumn() : 'id';
        $column = 'id';
//        $isColExist = Schema::hasColumn($model->getTable(), $column);
//        if (!$isColExist) {
//            return;
//        }
        $builder->orderBy($column);
    }
}