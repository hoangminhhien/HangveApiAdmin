<?php

namespace App\Pivots;

use App\Scopes\Traits\HasOrganization;
use App\Scopes\Traits\HasSortDescByCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class BasePivot extends Pivot
{
    use HasFactory, HasOrganization, HasSortDescByCreated;
}
