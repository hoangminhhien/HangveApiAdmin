<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $group;
    protected $name;
    protected $userId;
    protected $pathFile;
    public function __construct($group, $name, $userId, $pathFile = null)
    {
        $this->group = $group;
        $this->name = $name;
        $this->userId = $userId;
        $this->pathFile = $pathFile;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $notificationService = \App::make('App\Services\NotificationService');
        $data_info = [
            'path_file'         => $this->pathFile
        ];
        $data = [
            'group'           => $this->group,
            'name'            => $this->name,
            'data'            => json_encode($data_info),
            'time_create'     => time(),
            'status'          => 1
        ];
        $notificationService->storeNoti($this->userId, $data);
    }
}
