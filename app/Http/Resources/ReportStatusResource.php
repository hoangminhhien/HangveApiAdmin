<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportStatusResource extends JsonResource
{
    public ?int $_quantity;
    private array $_statuses;
    private array $_colors;

    public function __construct($resource, array $statuses, array $colors, ?int $quantity = null)
    {
        $this->_quantity = $quantity;
        $this->_statuses = $statuses;
        $this->_colors = $colors;
        parent::__construct($resource);
    }

    public function toArray($request)
    {
        $status = $this->resource;
        $data = [
            'value' => $status,
            'name' => !empty($this->_statuses[$status]) ? $this->_statuses[$status] : '',
            'color' => !empty($this->_colors[$status]) ? $this->_colors[$status] : '',
            'value_const' => $status
        ];
        if (!is_null($this->_quantity)) {
            $data['quantity'] = $this->_quantity;
        }
        return $data;
    }
}
