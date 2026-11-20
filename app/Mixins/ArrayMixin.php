<?php

namespace App\Mixins;

class ArrayMixin
{
    /**
     * Macro cho Illuminate\Support\Arr, tương tự như 'each' của Collection
     *
     * @return \Closure
     */
    public function each(): \Closure
    {
        return function ($arr, $callback) {
            foreach ($arr as $key => $value) {
                $callback($value, $key);
            }

            return $arr;
        };
    }

    /**
     * Macro cho Illuminate\Support\Arr, tương tự như 'map' của Collection
     *
     * @return \Closure
     */
    public function map(): \Closure
    {
        return function ($arr, $callback) {
            return array_map(function ($value, $key) use ($callback) {
                return $callback($value, $key);
            }, $arr, array_keys($arr));
        };
    }

    /**
     * Macro cho Illuminate\Support\Arr, tương tự như 'find' của Collection
     *
     * @return \Closure
     */
    public function find(): \Closure
    {
        return function ($arr, $callback) {
            foreach ($arr as $key => $value) {
                if ($callback($value, $key)) {
                    return $value;
                }
            }
        };
    }

    /**
     * Macro cho Illuminate\Support\Arr, tương tự như 'filter' of Collection
     *
     * @return \Closure
     */
    public function filter(): \Closure
    {
        return function ($arr, $callback) {
            return array_filter($arr, function ($value, $key) use ($callback) {
                return $callback($value, $key);
            }, ARRAY_FILTER_USE_BOTH);
        };
    }
}
