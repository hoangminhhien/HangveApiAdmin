<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationBank extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'bank_id',
        'name',
        'account_number',
        'branch',
        'type',
        'type_payment',
        'status'
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
    public function websites()
    {
        return $this->belongsToMany(
            Website::class,
            'organization_bank_websites'
        );
    }
}
