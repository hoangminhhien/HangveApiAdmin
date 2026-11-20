<?php

namespace App\Models;

use App\Scopes\Traits\Filterable;
use App\Scopes\Traits\HasSortDescByCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupKpi extends BaseModel
{
    use HasFactory, HasSortDescByCreated, Filterable;

    protected string $_tableNameFriendly = 'Kpi nhóm';
    protected $table = 'group_kpi_months';
    protected $fillable = ['group_id', 'month', 'note', 'kpi', 'kpi_sales', 'kpi_services', 'kpi_transports'];

    protected $casts = [
        'kpi_sales' => 'float',
        'kpi_services' => 'float',
        'kpi_transports' => 'float',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
