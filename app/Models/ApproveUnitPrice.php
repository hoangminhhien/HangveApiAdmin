<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApproveUnitPrice extends BaseModel
{
    use HasFactory;

    public function orderPackage()
    {
        return $this->belongsTo(OrderPackage::class, 'order_package_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function approveUser()
    {
        return $this->belongsTo(User::class, 'approve_user_id', 'id');
    }
}
