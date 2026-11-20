<?php


namespace App\Services;


use App\Http\Resources\ListResource;
use App\Http\Resources\PaginateJsonResource;
use App\Http\Resources\ReportStatusResource;
use App\Http\Resources\Resource;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BaseService implements Service
{
    public ?Model $_model;
    protected string $_paginateResource = PaginateJsonResource::class;
    protected string $_listResource = ListResource::class;
    protected string $_resource = Resource::class;
    protected array $_relations = [];


    public function __construct()
    {
        $this->setModel();
    }

    /**
     * Danh sách bản ghi giới hạn 1000 bản ghi khi trả về
     * @param int|null $limit
     * @param mixed ...$params
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        [$limit] = func_get_args();
        return resSuccessWithinData(
            new $this->_listResource(
                $this->_model->newQuery()->with($this->_relations)->limit($limit)->get(),
                $this->_resource
            )
        );
    }

    /**
     * Phân trang
     * @param int $perPage
     * @return JsonResponse
     * @throws Exception
     */
    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();
        $data = $this->_model->newQuery()->paginate($perPage);
        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    /**
     * Tạo mới bản ghi
     * @param array $data
     * @return Builder|Model|JsonResponse
     * @throws Exception
     */
    public function store(array $data)
    {
        $this->throwModel();
        return $this->_model->newQuery()->create($data);
    }

    /**
     * Cập nhật bản ghi theo ID
     * @param  string  $id
     * @param  array  $data
     * @return Builder|Builder[]|\Illuminate\Database\Eloquent\Collection|Model|null
     * @throws Exception
     */
    public function update(string $id, array $data)
    {
        $this->throwModel();
        $record = $this->_model->newQuery()->findOrFail($id);
        $record->update($data);
        return $record;
    }

    /**
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $this->_model->newQuery()->findOrFail($id)->delete();
        return resSuccess(trans('system.deleted'));
    }

    /**
     * @param string $id
     * @return JsonResponse
     */
    public function detail(string $id): JsonResponse
    {
        return resSuccessWithinData(new $this->_resource($this->_model->newQuery()->findOrFail($id)));
    }

    /**
     * Khởi tạo model
     */
    private function setModel()
    {
        $namespaceModel = 'App\Models\\' . str_replace('Service', '', class_basename($this));
        $this->_model = class_exists($namespaceModel) ? new $namespaceModel() : null;
    }

    /**
     * Kiểm tra lại model
     * @throws Exception
     */
    protected function throwModel()
    {
        if (!$this->_model) {
            throw new Exception(trans('system.model_not_init'));
        }
    }

    /**
     * @param  string  $table
     * @param  string  $class
     * @return array
     */
    public function getReportsHasQuantity(string $model, string $class): array
    {
        $reports = (new $model)->select(
            array_keys($class::STATUSES)
        )->first();
        $reports = $reports ? $reports->toArray(): array_reduce(array_keys($class::STATUSES), function ($result, $key) {
            $result[$key] = 0;
            return $result;
        }, array());
        $data = [];
        foreach ($reports as $key => $report) {
            $data[] = new ReportStatusResource($key, $class::STATUSES, $class::STATUSES_COLOR, $report);
        }
        return $data;
    }

    /**
     * @param  string  $table
     * @param  string  $class
     * @param  string  $status
     * @param  string|null  $customerId
     * @return ReportStatusResource|array
     */
    public function getReportHasQuantity(string $table, string $class, string $status, ?string $customerId)
    {
        $reports = DB::table($table);
        if($customerId) $reports = $reports->where('customer_id', $customerId);
        $reports = (array)$reports->select($status)->first();
        $data = [];
        foreach ($reports as $key => $report) {
            $data = new ReportStatusResource($key, $class::STATUSES, $class::STATUSES_COLOR, $report);
        }
        return $data;
    }
    public function dateRange($time)
    {
        if(!$time) {
            throw new Exception('Vui lòng nhập định dạng ngày');
        }
        if(!in_array($time, ['d', 'w', 'm', 'q', 'y'])) {

            throw new Exception('Định dạng ngày tháng không hợp lệ');
        }
        $endDate = \Carbon\Carbon::now();
        $startDate = '';
        $startPrevious = '';
        $endPrevious = '';

        if ($time == 'd') {
            $startDate =  Carbon::today()->startOfDay();
            $startPrevious = Carbon::today()->subDay()->startOfDay();
            $endPrevious = Carbon::today()->subDay()->endOfDay();
        }
        if ($time == 'w'){
            $startDate = Carbon::now()->startOfWeek();
            $startPrevious = Carbon::today()->subWeek()->startOfWeek();
            $endPrevious = Carbon::today()->subWeek()->endOfWeek();
        }
        if ($time == 'm'){
            $startDate = Carbon::now()->firstOfMonth();
            $startPrevious = Carbon::today()->subMonth()->startOfMonth();
            $endPrevious = Carbon::today()->subMonth()->endOfMonth();
        }
        if ($time == 'q'){
            $startDate = Carbon::now()->startOfQuarter();
            $startPrevious = Carbon::today()->subMonth(3)->startOfQuarter();
            $endPrevious = Carbon::today()->subMonth(3)->endOfQuarter();
        }
        if ($time == 'y'){
            $startDate = Carbon::now()->startOfYear();
            $startPrevious = Carbon::today()->subMonth(12)->startOfYear();
            $endPrevious = Carbon::today()->subMonth(12)->endOfYear();
        }

        return array(
            'startDate'     => $startDate->format('Y-m-d H:i:s'),
            'endDate'       => $endDate->format('Y-m-d H:i:s'),
            'startPrevious' => $startPrevious->format('Y-m-d H:i:s'),
            'endPrevious'   => $endPrevious->format('Y-m-d H:i:s'),
        );
    }

    public function generateRandomCode() {
        $min = 100000;
        $max = 999999;
        $rand = rand($min,$max);
        return 'FD_' . date('Ymd') . '_' . $rand;
    }

    public function curl($data, $url, $method)
    {
        try {
            $data_string = json_encode($data);
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER,
                $this->setHeaderCurl($data_string)
            );
            $result = curl_exec($curl);
            curl_close($curl);
            return json_decode($result, false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function setHeaderCurl($data_string): array
    {
        return [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string),
        ];
    }

    public function encrypt($string)
    {
        $cipher_method = 'aes-128-ctr';
        $enc_key = openssl_digest(php_uname(), 'SHA256', TRUE);
        $enc_iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher_method));
        $crypted_token = openssl_encrypt($string, $cipher_method, $enc_key, 0, $enc_iv) . "::" . bin2hex($enc_iv);
        return $crypted_token;
    }

    public function decrypt($string)
    {
        list($crypted_token, $enc_iv) = explode("::", $string);
        $cipher_method = 'aes-128-ctr';
        $enc_key = openssl_digest(php_uname(), 'SHA256', TRUE);
        $token = openssl_decrypt($crypted_token, $cipher_method, $enc_key, 0, hex2bin($enc_iv));
        return $token;
    }
}
