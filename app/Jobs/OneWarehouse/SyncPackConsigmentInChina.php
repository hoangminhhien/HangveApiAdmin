<?php

namespace App\Jobs\OneWarehouse;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;
use App\Constants\PackageConstant;

class SyncPackConsigmentInChina implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $packDB;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($packDB)
    {
        $this->packDB = $packDB;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $packDB = $this->packDB;

        $this->pushToOneWarehouse($packDB);
    }

    public function pushToOneWarehouse($packDB)
    {
        // Nếu không có mã vận đơn thì bỏ qua
        if ($packDB->bill_code == null) {
            return;
        }

        sleep(1);

        \Log::info('SyncPackConsigmentInChina-V1 19092024 - 15: ' . $packDB->id);

        $packType = 'consignment';
        $packDetails = [];
        foreach($packDB->orderDetailsPackage as $key=>$orderDetailPack){
            $orderDetail = $orderDetailPack->orderDetail;
            array_push($packDetails, [
                'name' => data_get($orderDetail, 'name'),
                'image' => data_get($orderDetail, 'image'),
                'classification' => data_get($orderDetail, 'classification'),
                'quantity' => $orderDetailPack->quantity,
                'category_id' => data_get($orderDetail, 'category_id'),
                'category_name' => $orderDetail ? data_get($orderDetail->category, 'name') : "",
            ]);
        }
        $customerDB = $packDB->customer;
        $warehouse_id = $packDB->warehouse_id;
        if (
            $packDB->warehouse_id == 2 ||
            $packDB->warehouse_id == 6 ||
            $packDB->warehouse_id == null
        ) {
            \Log::info('set lại kho 1');
            $warehouse_id = 1;
        }
        $temp = [
            'pack_id'        => $packDB->id, // ID bên HV
            'pack_code'      => $packDB->bill_code, // Mã vận đơn
            'pack_type'      => $packType, // Loại hàng,
            'warehouse_id'   => $warehouse_id, // Kho trả hàng
            'order_code'     => $packDB->order_code, // Mã đơn hàng
            'customer_id'    => $packDB->customer_id,
            'customer_code'  => $customerDB ? $customerDB->code : '',
            'customer_name'  => $customerDB ? $customerDB->name : '',
            'delivery_type'  => $packDB->delivery_type, // Hình thức vận chuyển
            'is_inspection'  => (int) $packDB->is_inspection,
            'is_woodworking' => (int) $packDB->is_woodworking,
            'is_shock_proof' => (int) $packDB->is_shock_proof,
            'is_insurance'   => 0,
            'staff_order_id' => $packDB->staff_order_id, // Nhân viên đặt hàng
            'package_number' => $packDB->package_number, // Số kiện
            'pack_detail'    => $packDetails, // Danh sách sản phẩm của kiện
            'company_id'     => 1,
            'category_id'    => $packDB->category_id,
            'pack_code_po'   => $packDB->code_po,

            'height'      => $packDB->height,
            'length'      => $packDB->length,
            'width'       => $packDB->width,
            'weight'      => $packDB->weight,
            'weight_real' => $packDB->weight_real,
            'volumn'      => $packDB->thuongdo_volumn,

            'to_china'   => true
        ];

        $guzzle = new Guzzle;
        $options = [
            'headers' => [
                'X-Token-Auth' => env('TOKEN_ONE_WAREHOUSE')
            ],
            'json' => $temp
        ];
        $url = env('BASE_URL_DONGDONG') . 'open/sync/pack-consignment-hot-fix';
        $request = $guzzle->request('POST', $url, $options);
        $reponse = json_decode($request->getBody());

        if($reponse->code == 200) {
            $packDB->code_warehouse = data_get($reponse->data, 'code_warehouse');
            $packDB->save();
        }
        }
}
