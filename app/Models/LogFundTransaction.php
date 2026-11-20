<?php

namespace App\Models;


class LogFundTransaction extends BaseModel
{
    public $fillable = [
        'fund_transactions',
        'name',
        'user_id',
        'user_name',
        'user_email'
    ];

    protected $table = 'log_fund_transactions';
    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_create_id', 'id');
    }

}
