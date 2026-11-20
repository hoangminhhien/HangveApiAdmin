<?php


namespace App\Helpers;


use App\Constants\ReportConstant;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderPackage;
use App\Models\ReportCustomer;
use App\Models\Transaction;
use App\Services\ReportService;
use App\Services\UserService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RandomHelper
{
    /**
     * @param  int  $length
     * @return string
     */
    public static function numberString(int $length): string
    {
        $number = '';
        for ($i = 0; $i < $length; $i++) {
            $number .= mt_rand(0, 9);
        }
        return $number;
    }

    /**
     * @return string
     */
    public static function phoneNumber(): string
    {
        $prefix = Arr::random(['090', '091', '092']);
        return $prefix.RandomHelper::numberString(7);
    }

    /**
     * @return string
     */
    public static function customerCode(): string
    {
        // return 'KH_'.date('ym').sprintf(
        //         "%'.05d",
        //         (new ReportService())->getQuantityByKey(ReportConstant::QUANTITY_CUSTOMER)
        //     );

        // Logic 6 số
        // $reportQuantityModel = \App::make('App\Models\ReportQuantity');
        // $reportQuantityCustomer = $reportQuantityModel->where('key', ReportConstant::QUANTITY_CUSTOMER)->first();

        // $countCustomer = $reportQuantityCustomer->quantity;
        // if ($countCustomer < 300000) {
        //     $reportQuantityCustomer->quantity = 300000;
        //     $reportQuantityCustomer->save();
        // }

        // Logic Chữ cái + 4 số tự tăng
        // $arrChar = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        // $reportQuantityModel = \App::make('App\Models\ReportQuantity');
        // $reportQuantityCustomer = $reportQuantityModel->where('key', ReportConstant::QUANTITY_CUSTOMER)->first();

        // $step = 10000; // Bước nhẩy
        // $count = $reportQuantityCustomer->quantity;

        // // Nếu nó đang ở đoạn 9999 thì nhẩy thêm 1 nấc nữa
        // $done = ($count + 1) % $step;
        // if ($done == 0) {
        //     $reportQuantityCustomer->quantity += 1;
        //     $reportQuantityCustomer->save();
        //     $count = $reportQuantityCustomer->quantity;
        // }
        // $positionChar = floor($count / $step);
        // $positionChar = (int) $positionChar;

        // $charCode = $arrChar[$positionChar];
        // $countReal = $count - $positionChar * $step + 1;

        // $countRealText = $countReal;
        // if ($countReal < 1000) {
        //     $countRealText = '0' . $countReal;
        // }
        // if ($countReal < 100) {
        //     $countRealText = '00' . $countReal;
        // }
        // if ($countReal < 10) {
        //     $countRealText = '000' . $countReal;
        // }

        // $countCustomerReturn = $charCode . $countRealText;
        // return $countCustomerReturn;

        // Logic 4 kí tự 0001 -> 9999 + 1 kí tự A->Z  => gộp lại rồi trộn
        return RandomHelper::loopCode();
    }

    public static function loopCode()
    {
        $codeCustomer = RandomHelper::makeCode();
        // Nếu mã code này trùng với trong DB thì tạo mã khác luôn
        $customerModel = \App::make('App\Models\Customer');
        $customerDB = $customerModel->where('code', $codeCustomer)->withoutGlobalScopes()->first();
        if (!is_null($customerDB)) {
            \Log::info('code lặp rồi');
            return RandomHelper::loopCode();
        }
        return $codeCustomer;
    }

    public static function makeCode()
    {
        // https://stackoverflow.com/questions/1643431/how-to-get-random-value-out-of-an-array
        // Lấy số
        $number = rand(0, 9999);
        $numberText = strval($number);
        if ($number < 1000) {
            $numberText = '0' . $number;
        }
        if ($number < 100) {
            $numberText = '00' . $number;
        }
        if ($number < 10) {
            $numberText = '000' . $number;
        }

        // Lấy chữ
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = $characters[rand(0, strlen($characters) - 1)];

        // Mã code
        $codeCustomer = $numberText . $randomString;
        $array = str_split($codeCustomer);
        shuffle($array);
        $codeCustomer = implode("",$array);

        // Trả về
        return $codeCustomer;
    }

    /**
     * @return string
     */
    public static function userCode(): string
    {
        $totalUser = (new ReportService())->getQuantityByKey(ReportConstant::QUANTITY_USER);
        return str_pad($totalUser + 1, 4, "0", STR_PAD_LEFT);
    }

    /**
     * @return string
     */
    public static function roleName(string $name): string
    {
        return Str::slug($name).'-'.time();
    }

    public static function checkDuplicateOrderCode($customerId): string
    {
        do {
            $orderCode = self::orderCode($customerId);
        } while (Order::query()->where('code', $orderCode)->exists());

        return $orderCode;
    }

    public static function orderCode($customerId): string
    {
        $customer = Customer::query()->find($customerId);
        $customerCode4 = $customer->code;
        $modelDB = new ReportCustomer();
        $reportDB = $modelDB->query()->where('customer_id', $customer->id)->first();
        if (is_null($reportDB)) {
            $reportDB = ReportCustomer::query()->create(['customer_id' => $customer->id]);
        }
        $numberOrder = $reportDB->orders_number_increment + 1;
        if ($numberOrder >= 10000) {
            $numberRenew = $numberOrder;
        } else if ($numberOrder >= 1000) {
            $numberRenew = '0' . $numberOrder;
        }
        else if ($numberOrder >= 100) {
            $numberRenew = '00' . $numberOrder;
        }
        else if ($numberOrder >= 10) {
            $numberRenew = '000' . $numberOrder;
        } else {
            $numberRenew = '0000' . $numberOrder;
        }
        return $customerCode4 . $numberRenew;
    }

    /**
     * @return string
     */
    public static function billCode(): string
    {
        return 'SPXVN-'. self::getNumberByLength(12);
    }

    /**
     * @param  int  $length
     * @return string
     */
    private static function getNumberByLength(int $length): string
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= mt_rand(0, 9);
        }
        return $result;
    }

    /**
     * @param  int|null  $optional
     * @return string
     */
    public static function getTransactionCode(?int $optional = 1): string
    {
        return RandomHelper::getTransactionCodeMax();
        $count = Transaction::query()->count();
        return "GD_".date('ymd').sprintf(
                "%'.04d",
                $count + $optional
            );
    }

    public static function getTransactionCodeMax(?int $optional = 1): string
    {
        return "GD_".date('ymdHis');
    }

    /**
     * @param  int|null  $optional
     * @return string
     */
    public static function getPackageCode(?int $optional = 1): string
    {
        $count = OrderPackage::query()->count();
        return "KI_".date('ymd').sprintf(
                "%'.04d",
                $count + $optional
            );
    }

    /**
     * @param  int|null  $optional
     * @return string
     */
    public static function getDeliveryCode(?int $optional = 1): string
    {
        $count = Delivery::query()->count();
        return sprintf(
            "GH_%'.04d",
            $count + $optional
        );
    }
}
