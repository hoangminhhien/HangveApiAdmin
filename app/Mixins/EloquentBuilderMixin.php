<?php

namespace App\Mixins;

use Illuminate\Support\Str;

class EloquentBuilderMixin
{
    /**
     * Thêm 'withWhereHas' cho Eloquent Builder do Laravel 8 chưa support
     *
     * Add a relationship count / exists condition to the query with where clauses.
     * Also load the relationship with same condition.
     *
     * @param  string  $relation
     * @param  \Closure|null  $callback
     * @param  string  $operator
     * @param  int  $count
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function withWhereHas(): \Closure
    {
        return function ($relation, \Closure $callback = null, $operator = '>=', $count = 1)
        {
            return $this->whereHas(Str::before($relation, ':'), $callback, $operator, $count)
                ->with($callback ? [$relation => fn ($query) => $callback($query)] : $relation);
        };
    }
}
