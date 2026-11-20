<?php


namespace App\Services;


use App\Http\Resources\BankResource;
use App\Models\ApproveUnitPrice;
use App\Constants\ApproveUnitPriceConstant;
use App\Constants\PackageConstant;
use App\Services\ApproveUnitPriceService;
use Illuminate\Http\JsonResponse;

class ApproveUnitPriceService extends BaseService
{
    public function approve(array $listId)
    {
        $approveUnitPrices = ApproveUnitPrice::whereIn('id', $listId)->with(['orderPackage'])->get();
        $packageController    = \App::make('App\Http\Controllers\PackageController');
        foreach($approveUnitPrices as $approveUnitPrice) {
            $package = $approveUnitPrice->orderPackage;
            if($package) {
                $isKg = $package->type_quantitative == PackageConstant::WEIGHT;
                $item['unit_price'] = $approveUnitPrice->unit_price;
                if($isKg) {
                    $international_shipping_cost = $item['unit_price'] * $package->weight;
                } else {
                    $international_shipping_cost = $item['unit_price'] * $package->volume;
                }
                $packageController->updateOrderAndPackage($package, $item, $international_shipping_cost);
            }
            $approveUnitPrice->update([
                'status' => ApproveUnitPriceConstant::APPROVED,
                'approve_user_id' => getCurrentUserId(),
                'approve_date' => now(),
            ]);
            $this->notiChangeStatus($approveUnitPrice);
        }
        return null;
    }

    public function cancel(array $listId, string $reason)
    {
        $approveUnitPrices = ApproveUnitPrice::whereIn('id', $listId)->with(['orderPackage'])->get();
        foreach($approveUnitPrices as $approveUnitPrice) {
            $approveUnitPrice->update([
                'status' => ApproveUnitPriceConstant::CANCEL,
                'approve_user_id' => getCurrentUserId(),
                'approve_date' => now(),
                'cancel_reason' => $reason,
            ]);
            $this->notiChangeStatus($approveUnitPrice);
        }
        return null;
    }

    private function notiChangeStatus($approveUnitPrice)
    {
        $notificationService = \App::make('App\Services\NotificationService');
        if($approveUnitPrice->status == ApproveUnitPriceConstant::APPROVED) {
            $name = 3009;
        }else {
            $name = 3010;
        }
        $orderPackage = $approveUnitPrice->orderPackage;
        $dataJson = [
            'bill_code' => data_get($orderPackage, 'bill_code'),
            'cancel_reason' => $approveUnitPrice->cancel_reason
        ];
        $data = [
            'group'           => 3,
            'name'            => $name,
            'data'            => json_encode($dataJson),
            'time_create'     => time(),
            'status'          => 1
        ];

        if ($approveUnitPrice->user_id) {
            $notificationService->storeNoti($approveUnitPrice->user_id, $data);
        }
    }
}
