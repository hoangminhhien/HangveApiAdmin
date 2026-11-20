<?php

namespace App\Traits;

use App\Models\OrderActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait OrderActivityLogs
{
    public static function bootOrderActivityLogs()
    {
        static::created(function ($model) {
            $model->logActivity('created');
        });

        static::updated(function ($model) {
            $model->logActivity('updated');
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted');
        });
    }

    public function logActivity($action)
    {
        OrderActivityLog::query()->create([
            'user_id' => Auth::id() ?? 1,
            'action' => $action,
            'model_type' => get_class($this),
            'model_id' => $this->id ?? 0,
            'properties' => $this->getActivityChanges($action),
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
        ]);
    }

    protected function getActivityChanges($action): array
    {
        if ($action === 'updated') {
            return [
                'old' => $this->getOriginal(),
                'new' => $this->getChanges(),
            ];
        }

        return $this->toArray();
    }
}
