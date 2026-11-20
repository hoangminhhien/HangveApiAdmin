<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\NotificationService;
use App\Models\Notification;
use Illuminate\Support\Facades\Storage;

class NotificationController extends Controller
{
    public function __construct(NotificationService $service)
    {
        $this->_service = $service;
    }

    public function storeMessage(): ?array
    {
       return [
        'name.required' => 'Tên thông báo bắt buộc phải nhập',
       ];
    }

    public function storeRequest(): array
    {
        return [
            'name' => 'required',
        ];
    }


    public function clickNoti(Request $request, $id)
    {
        $data = request()->all();
        $notiModel = \App::make('App\Models\Notification');

        $userOnline = \Auth::user();
        $noti = $notiModel->where('id', $id)->where('time_seen', 0)->where('user_id', $userOnline->id)->first();
        if (is_null($noti)) {
            return resError('Bản ghi không tồn tại');
        }

        try {
            \DB::beginTransaction();

            // Cập nhật lại thời gian xem
            $noti->time_seen = time();
            $noti->save();

            // Cập nhật lại Noti của user_id
            // $total_noti = $notiModel->where('user_id', $userOnline->id)
            //                         ->where('time_seen', 0)
            //                         ->count();
            // $userOnline->total_noti = $total_noti;
            // $userOnline->save();

            \DB::commit();

            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function clickAllNoti(Request $request)
    {
        $data = request()->all();
        $notiModel = \App::make('App\Models\Notification');

        try {
            \DB::beginTransaction();

            $userOnline = \Auth::user();

            $notifications = $notiModel->where('time_seen', 0)
                                       ->where('user_id', $userOnline->id)
                                       ->update(['time_seen' => time()]);
            // Cập nhật lại Noti của user_id
            // $userOnline->total_noti = 0;
            // $userOnline->save();

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }
    public function downloadFile($id)
    {
        $notification = Notification::findOrFail($id);
        if($notification){
            $data = json_decode($notification->data);
            if(data_get($data, 'path_file')){
                $fullPath = config('app.url').'/'.$data->path_file;
                return resSuccessWithinData(['full_path' => $fullPath]);
            }else {
                return resSuccessWithinData(['full_path' => null]);
            }
        }else{
            return resError();
        }
    }

    public function testPushStatusToQuote(Request $request)
    {
        $packModel = \App::make('App\Models\OrderPackage');
        $orderPackage = $packModel->where('id', 985195)->first();

        // $notiService = \App::make('App\Services\NotificationService');
        // $notiService->pushStatusToQuote($orderPackage);

        // Gọi qua JOB
        $job = new \App\Jobs\Quote\SyncStatusPack($orderPackage);
        dispatch($job)->delay(now()->addSeconds(5))->onQueue('cron-data-2023');
    }

    public function clickAllNotiByGroup(Request $request)
    {
        $data = $request->all();
        $notiModel = \App::make('App\Models\Notification');

        try {
            \DB::beginTransaction();

            $userOnline = \Auth::user();

            if (empty($data['group_noti'])) {
                return resError('Thiếu group_noti');
            }

            $updated = $notiModel->where('time_seen', 0)
                ->where('user_id', $userOnline->id)
                ->where('group', $data['group_noti'])
                ->update(['time_seen' => time()]);

            if ($updated > 0) {
                // $userOnline->total_noti = max(0, $userOnline->total_noti - $updated);
                // $userOnline->save();
            }

            \DB::commit();

            return resSuccess();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }


}

