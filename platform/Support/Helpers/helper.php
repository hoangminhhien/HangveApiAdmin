<?php

use Illuminate\Database\Eloquent\Collection as EloquentCollection;

if (!function_exists('collectEloquent')) {
    /**
     * Tạo một Eloquent Collection
     *
     * @param mixed $value
     * @return EloquentCollection
     */
    function collectEloquent($value = null): EloquentCollection
    {
        // nếu $value là instance của Traversable thì tạo Eloquent Collection luôn
        // nếu $value là single model thì wrap trong một array
        return is_array($value) || $value instanceof Traversable
            ? new EloquentCollection($value)
            : new EloquentCollection([$value]);
    }
}

if (!function_exists('mb_ucfirst')) {
    /**
     * Viết hoa chữ cái đầu
     *
     * @param string $value
     * @param string $encoding
     * @return string
     */
    function mb_ucfirst(string $value = '', string $encoding = 'UTF-8'): string
    {
        return $value
            ? mb_strtoupper(mb_substr($value, 0, 1, $encoding), $encoding) . mb_substr($value, 1, null, $encoding)
            : '';
    }
}
