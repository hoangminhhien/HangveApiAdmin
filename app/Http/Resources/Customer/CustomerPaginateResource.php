<?php

namespace App\Http\Resources\Customer;

use App\Http\Resources\Traits\HasPaginate;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use JsonSerializable;

class CustomerPaginateResource extends ResourceCollection
{
    use HasPaginate;

    private ?string $_resource;

    public function __construct($resource, ?string $class = Resource::class)
    {
        $this->_resource = $class;
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'items' => $this->collection->transform(
                function ($item) {
                    return new $this->_resource($item);
                }
            ),
            'pagination' => $this->getPaginateInfo()
        ];
    }
}
