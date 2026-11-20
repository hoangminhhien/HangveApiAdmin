<?php

namespace App\Services;

use App\Abstracts\AbstractBaseService;
use App\Http\Resources\Customer\CustomerOfferEndowResource;
use App\Models\CustomerLogEndow;

class CustomerLogEndowService extends AbstractBaseService
{
    protected string $_resource = CustomerOfferEndowResource::class;
    protected array $relations = ['user'];
    protected function setModel()
    {
        $this->_model = new CustomerLogEndow();
    }

    public function setParams($request, $query)
    {
        if (!empty($request['customer_id'])) {
            $query->where('customer_id', $request['customer_id']);
        }
        return $query->orderBy('id', 'DESC');
    }
}
