<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderPackageImage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_package_id',
        'image',
        'source'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }
}
