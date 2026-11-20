<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PCDataController extends Controller
{
    function __construct(){
    }

    public function updateData(Request $request)
    {
        $validationRules = [
            'model'       => 'required|string',
            'conditions'  => 'required|array',
            'update_data' => 'required|array',
            'time_enter'  => 'required|date_format:Y-m-d H:i',
            'run_test'    => 'required',
        ];
        $validationMessages = [
        ];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();

        \Log::info(json_encode($params, JSON_UNESCAPED_UNICODE));

        $model      = $validated['model'];
        $conditions = $validated['conditions'];
        $updateData = $validated['update_data'];
        $run_test   = $validated['run_test'];
        $time_enter = $validated['time_enter'];

        $result_check = $this->validateUpdateData($conditions, $time_enter);
        if(!is_null($result_check)) {
            return res422($result_check);
        }

        try {
            DB::beginTransaction();
            $result = $this->updateDataAction($model, $conditions, $updateData, $run_test);
            \Log::info(json_encode($result, JSON_UNESCAPED_UNICODE));
            DB::commit();
            return resSuccessWithinData($result);
        } catch (\Exception $e) {
            DB::rollback();
            return resError($e->getMessage());
        }
    }

    public function validateUpdateData($conditions, $time_enter)
    {
        // Check tham số
        foreach ($conditions as $condition) {
            if (!is_array($condition) || count($condition) !== 3) {
                return 'Phải có đủ 3 phần tử';
            }
        }

        // Check ai login
        $userOnline = \Auth::user();
        if ($userOnline->email != 'admin@thuongdo.com') {
            return 'Tài khoản xử lý không hợp lệ';
        }

        // Check thời gian
        $intTime = strtotime($time_enter);
        $now     = time();
        $diff    = $now - $intTime;
        if ($diff > 300 || $diff < 0) {
            return 'time_enter không còn hợp lệ';
        }
        return null;
    }

    public function updateDataAction($model, $conditions, $updateData, $run_test)
    {
        // Bắt đầu truy vấn
        $query = \App::make("App\Models\\{$model}");

        foreach ($conditions as $condition) {
            [$column, $operator, $value] = $condition;
            switch (strtolower($operator)) {
                case 'in':
                    $query = $query->whereIn($column, $value);
                    break;
                case 'not in':
                    $query = $query->whereNotIn($column, $value);
                    break;
                case 'like':
                    $query =$query->where($column, 'like', $value);
                    break;
                default:
                    $query = $query->where($column, $operator, $value);
                    break;
            }
        }

        // Lấy tất cả bản ghi thỏa điều kiện
        $records = $query->get();

        // Cập nhật
        $updatedCount = 0;
        $query = \App::make("App\Models\\{$model}");
        foreach ($records as $record) {
            if ($run_test == false) {
                $recordDB = $query->where('id', $record->id)->first();
                if (!is_null($recordDB)) {
                    $recordDB->update($updateData);
                }
            }
            $updatedCount++;
        }

        return [
            'message'      => $run_test == false ? 'Đã cập nhật thành công' : 'Đã test ok, Hãy chuyển sang run_test = true để thực hiện thật',
            'rows_found'   => $records->count(),
            'rows_updated' => $updatedCount,
            'updated_ids'  => \Arr::pluck($records, 'id'),
        ];
    }
}
