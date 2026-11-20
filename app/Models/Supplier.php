<?php

namespace App\Models;

use App\Scopes\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Scopes\Traits\Filterable;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends BaseModel
{
    use HasFactory, Filterable;
    use LogsActivity;
    use SoftDeletes;

    protected static $logAttributes = ['*'];
    protected static $logOnlyDirty = true;
    protected static $logName = 'suppliers';
    protected static $recordEvents = ['deleted'];

    public function scopeType($query)
    {
        return $query->where('type', request()->input('type'));
    }

    public function scopeIndustry($query)
    {
        return $query->where('industry', request()->input('industry'));
    }

    public function scopeName($query)
    {
        return $query->where("name", 'like', '%' . request()->query('name') . '%');
    }

    public function orderSupplier(): hasMany
    {
        return $this->hasMany(OrderSupplier::class, 'supplier_id', 'id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ContactMethod::class);
    }

    public function banks(): HasMany
    {
        return $this->hasMany(SupplierBank::class);
    }
}
