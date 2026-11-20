<?php

namespace App\Models;

use App\Models\Traits\ImageAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FineImage extends BaseModel
{
    use HasFactory, ImageAttribute;

    protected $table = 'fine_images';

}
