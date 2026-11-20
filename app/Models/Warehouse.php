<?php

namespace App\Models;

use App\Models\Traits\DistrictRelation;
use App\Models\Traits\ProvinceRelation;
use App\Scopes\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends BaseModel
{
    use HasFactory, HasOrganization, ProvinceRelation, DistrictRelation, SoftDeletes;

    protected $appends = [
        'custom_name',
        'custom_name_second',
    ];

    /**
     * @return string
     */
    public function getCustomNameAttribute(): string
    {
        return optional($this->province)->name . ' - ' . $this->address;
    }

    public function getCustomNameV2Attribute(): string
    {
        return optional($this->province)->name . ' - ' . $this->address_other;
    }

    /**
     * @return string
     */
    public function getCustomNameSecondAttribute(): string
    {
        return $this->address . ', ' . optional($this->province)->name;
    }

    public function deliveryStores(): HasMany
    {
        return $this->hasMany(DeliveryStore::class);
    }

}
