<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;
use App\Services\FilterService;
use App\Constants\CenterApiConstant;

class LogPing extends BaseModel
{
    use Filterable;

    protected string $_tableNameFriendly = 'Log Ping';
    protected $_prefixRoute = 'log-ping';

    public $fillable = [
        'source',
        'type',
        'time',
        'user_id',
    ];

    protected $table = 'log_pings';
    public $timestamps = true;
    protected $dates = [];


    public const SOURCE_9PAY = 1;

    public const TYPE_LOG   = 1;
    public const TYPE_RESET = 2;
}
