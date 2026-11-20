<?php

namespace App\Models;

use App\Models\Traits\CustomerRelation;
use App\Models\Traits\Filters\CreatedAtFilter;
use App\Models\Traits\Filters\StatusFilter;
use App\Models\Traits\OrderRelation;
use App\Models\Traits\StaffServicesRelation;
use App\Scopes\Traits\Filterable;
use App\Scopes\Traits\HasSortDescByCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogEdit extends BaseModel
{
    use HasFactory;

    protected static $logAttributes = [
        'log_name',
        'description',
        'log_id',
        'data',
    ];
}
