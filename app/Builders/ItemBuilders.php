<?php

namespace App\Builders;

use App\Traits\Builders\PlatformSourceTrait;
use Illuminate\Database\Eloquent\Builder;

class ItemBuilders extends Builder
{
    use PlatformSourceTrait;
}
