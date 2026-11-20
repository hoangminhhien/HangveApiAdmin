<?php

namespace App\Models;

use App\Scopes\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Scopes\Traits\Filterable;

class ContactMethod extends BaseModel
{
    use HasFactory, HasOrganization, Filterable;

    protected $fillable = [
        'name',
        'supplier_type_id',
        'position',
        'details',
    ];
}
