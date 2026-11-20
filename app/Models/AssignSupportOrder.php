<?php

namespace App\Models;

use App\Traits\Relationships\AssignSupportOrderRelationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssignSupportOrder extends BaseModel
{
    use HasFactory, AssignSupportOrderRelationships;

    protected $table = 'assign_support_orders';

    protected $fillable = [
        'organization_id',
        'order_no',
        'order_id',
        'order_detail_id',
        'assigner_id',
        'assignee_id',
        'quantity',
        'created_at',
        'updated_at'
    ];
}
