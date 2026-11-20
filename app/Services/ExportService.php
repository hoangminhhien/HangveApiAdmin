<?php

namespace App\Services;

class ExportService extends BaseService
{
    public function pushNotification($group, $name, $userId, $pathFile = null)
    {
        var_dump('ExportExcelJob');
        $notificationService = \App::make('App\Services\NotificationService');
        $data_info = [
            'path_file'         => $pathFile
        ];
        $data = [
            'group'           => $group,
            'name'            => $name,
            'data'            => json_encode($data_info),
            'time_create'     => time(),
            'status'          => 1
        ];
        $notificationService->storeNoti($userId, $data);
    }
}
