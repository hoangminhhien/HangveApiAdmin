<?php

namespace App\Actions;

use App\Http\Resources\PaginateJsonResource;

/**
 * Mọi actions đều phải extends abstract BaseAction này
 */
abstract class BaseAction
{
    /**
     * Paginate Json Resource
     *
     * @var string
     */
    protected string $paginateResource = PaginateJsonResource::class;

    /**
     * Chứa logic thực thi chính của action
     *
     * @param ?array $params
     * @return mixed
     */
    abstract public function handle(?array $params);
}
