<?php

use App\Constants\BuyderServiceConstant;
use App\Constants\LocateConstant;
use App\Exceptions\CustomValidationException;
use App\Models\ConversationConfig;
use App\Models\Ecommerce;
use App\Models\Order;
use App\Models\User;
use App\Models\OrderPackage;
use App\Services\ConfigService;
use App\Services\OrganizationService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Ramsey\Uuid\Uuid;
use Carbon\Carbon;
use App\Models\Config;
use App\Constants\ConfigConstant;
use App\Models\Organization;
use App\Constants\OrganizationConstant;

if (!function_exists('getSourceFromProductLink')) {
    function getSourceFromProductLink(string $link): string
    {
        switch ($link) {
            case (str_contains($link, BuyderServiceConstant::DOMAIN_1688)):
                return BuyderServiceConstant::SOURCE_1688;
            default:
                return BuyderServiceConstant::SOURCE_TAOBAO;
        }
    }
}

if (!function_exists('checkTimeOrderSlowQuote')) {
    function checkTimeOrderSlowQuote($order, $lists): bool
    {
        $isSentNotification = false;
        if (empty($lists)) {
            return false;
        }
        if (is_null($order->orderStatusTime->status_0)) {
            return false;
        }
        $orderTime = date('Y-m-d H:i:s', strtotime($order->orderStatusTime->status_0));
        $now = date('Y-m-d H:i:s');
        foreach ($lists as $list) {
            $convertedTime = '';
            if (
                ($order->ecommerce == $list['key'] || (is_null($order->ecommerce) && $list['key'] == 'other'))
                && $list['time'] > 0) {
                $time = '';
                $time_type = '';
                if ($list['time_type'] == Ecommerce::TIME_TYPE_MINUTE) {
                    $time = $list['time'];
                    $time_type = $list['time_type'];
                } else if ($list['time_type'] == Ecommerce::TIME_TYPE_HOUR) {
                    $time = $list['time'];
                    $time_type = $list['time_type'];
                } else if ($list['time_type'] == Ecommerce::TIME_TYPE_DAY) {
                    $time = $list['time'];
                    $time_type = $list['time_type'];
                }
                if ($time && $time_type) {
                    $convertedTime = date('Y-m-d H:i:s', strtotime("+$time $time_type", strtotime($orderTime)));
                }
                if ($convertedTime && $convertedTime < $now) {
                    $isSentNotification = true;
                }
            }
        }
        return $isSentNotification;
    }
}

if (!function_exists('pluckByKeyValue')) {
    function pluckByKeyValue(array $array): array
    {
        $data = [];
        foreach ($array as $key => $item) {
            $data[] = ['value' => $key, 'name' => $item];
        }
        return $data;
    }
}

if (!function_exists('getConversationConfig')) {
    function getConversationConfig($time)
    {
        return ConversationConfig::query()->where('time', '>', $time)
            ->where('id', '>', 2)
            ->orderBy('time', 'asc')
            ->first();
    }
}

if (!function_exists('generateRandomString')) {
    function generateRandomString($length): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

if (!function_exists('generateOtpCode')) {
    function generateOtpCode(): int
    {
        $min = 100000;
        $max = 999999;
        return rand($min,$max);
    }
}


if (!function_exists('convertPhoneNumber')) {
    function convertPhoneNumber($phone_number)
    {
        return '84'.ltrim($phone_number, "0");
    }
}

if (!function_exists('getPlatformIdFromUrl')) {
    function getPlatformIdFromUrl(string $url): ?string
    {
        preg_match('/(?:\?|&)id=(\d+)/', $url, $matches);
        return $matches[1] ?? null;
    }
}

if (!function_exists('get1688IdFromRouteParam')) {
    function get1688IdFromRouteParam(string $url): ?string
    {
        $rs1 = explode('.html', $url);
        $rs2 = explode('offer/', data_get($rs1, '0'));

        return data_get($rs2, '1');
    }
}

if (!function_exists('getTaoBaoSkuIdFromRouteParam')) {
    function getTaoBaoSkuIdFromRouteParam(string $url)
    {
        $parsedUrl = parse_url($url);
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            return $queryParams['skuId'] ?? null;
        } else {
            return null;
        }
    }
}

if (!function_exists('detectPlatform')) {
    function detectPlatform(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (!$host) {
            return 'unknown';
        }

        $host = strtolower($host);

        switch (true) {
            case Str::contains($host, 'taobao.com'):
                return 'taobao';

            case Str::contains($host, '1688.com'):
                return '1688';

            case Str::contains($host, 'tmall.com'):
                return 'tmall';

            default:
                return 'unknown';
        }
    }
}

if (!function_exists('getPlatformItemId')) {
    function getPlatformItemId(
        string $searchKey,
               $platformSource = BuyderServiceConstant::SOURCE_TAOBAO
    )
    {
        $platformItemId = null;

        if (preg_match('/^[0-9]+$/', $searchKey)) {
            $platformItemId = $searchKey;
        } else if (filter_var($searchKey, FILTER_VALIDATE_URL) !== false) {
            if ($platformSource == BuyderServiceConstant::SOURCE_TAOBAO) {
                $pattern = '/[?&]id=(\d+)/';
                preg_match($pattern, $searchKey, $matches);
                $platformItemId = $matches[1] ?? 0;
            } else if ($platformSource == BuyderServiceConstant::SOURCE_1688) {
                $parts = explode('offer/', $searchKey)[1];
                $platformItemId = explode('.', $parts)[0];
            }
        }

        return $platformItemId;
    }
}

if (!function_exists('getCurrentUser')) {
    function getCurrentUser(): ?Authenticatable
    {
        return Auth::user() ?? User::find(1);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin(): bool
    {
        $userLogin = auth()->user();
        $roles = $userLogin->roles ?? [];
        $checkIsAdmin = false;
        foreach ($roles as $role) {
            if($role->type == 1) {
                $checkIsAdmin = true;
            }
        }
        return $checkIsAdmin;
    }
}

if (!function_exists('isAcceptViewLogFastCreateOrder')) {
    function isAcceptViewLogFastCreateOrder(): bool
    {
        $userLogin = auth()->user();
        $roles = $userLogin->roles ?? [];
        $check = false;
        foreach ($roles as $role) {
            if(in_array($role->name, [
                'admin.1', 'pho-phong-bao-gia.1',
                'nhan-vien-bao-gia.1', 'nhan-vien-dat-hang.1',
                'truong-phong-giao-dich-quoc-te.1'
            ])) {
                $check = true;
            }
        }
        return $check;
    }
}
/* Nhân viên kinh doanh */
if (!function_exists('isBusiness')) {
    function isBusiness(): bool
    {
        $userLogin = auth()->user();

        if ($userLogin->department_id == 1) {
            return true;
        }
        return false;
    }
}
/* Trưởng phòng kinh doanh */
if (!function_exists('isSalesManager')) {
    function isSalesManager(): bool
    {
        $userLogin = auth()->user();
        $roles = $userLogin->roles ?? [];
        if ($roles) {
            $roles = $roles->toArray();
        }
        if ($userLogin->department_id == 1 && !empty($roles) && $roles[0]['id'] == 6) {
            return true;
        }
        return false;
    }
}
/* Phó phòng kinh doanh */
if (!function_exists('isDeputySalesManager')) {
    function isDeputySalesManager(): bool
    {
        $userLogin = auth()->user();
        $roles = $userLogin->roles ?? [];
        if ($roles) {
            $roles = $roles->toArray();
        }
        if ($userLogin->department_id == 1 && !empty($roles) && $roles[0]['id'] == 7) {
            return true;
        }
        return false;
    }
}

if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId(): string
    {
        return getCurrentUser()->id;
    }
}

if (!function_exists('getOrganization')) {
    function getOrganization()
    {
        return data_get(getCurrentUser(), 'organization_id');
    }
}

if (!function_exists('getModelNameByClass')) {
    function getModelNameByClass($class): string
    {
        $strArr = explode('\\', $class);
        return array_pop($strArr);
    }
}

if (!function_exists('getExchangeRate')) {
    function getExchangeRate($orderId = null): float
    {
        return (float)(optional(Order::query()->find($orderId))->exchange_rate ?? (new ConfigService(
            ))->getExchangeRate());
    }
}

if (!function_exists('getExchangeRateByPackage')) {
    function getExchangeRateByPackage($id = null): float
    {
        return (float)(optional(OrderPackage::query()->find($id))->exchange_rate ?? (new ConfigService(
            ))->getExchangeRate());
    }
}

if (!function_exists('getProvinceX')) {
    function getProvinceX(?string $provinceId = null): string
    {
        return $provinceId ?? LocateConstant::PROVINCE_ID_X;
    }
}

if (!function_exists('roundXPrecision')) {
    function roundXPrecision(float $number, $precision = 2): float
    {
        return round($number, $precision);
    }
}

if (!function_exists('roundHalfUp')) {
    function roundHalfUp(float $number): float
    {
        return $number <= 0.5 && $number > 0 ? 0.5 : $number;
    }
}

if (!function_exists('enoughMoneyToPay')) {
    /**
     * @param  bool  $isCheck
     * @throws Throwable
     */
    function enoughMoneyToPay(bool $isCheck)
    {
        throw_if(
            $isCheck,
            CustomValidationException::class,
            [trans('transaction.enough_money_to_pay')]
        );
    }
}

if (!function_exists('getUuid')) {
    /**
     * @return string
     */
    function getUuid(): string
    {
        return Uuid::uuid6()->toString();
    }
}

if (!function_exists('convert_number_to_words')) {
    function convert_number_to_words($number)
    {
        $hyphen = ' ';
        $conjunction = ' ';
        $separator = ' ';
        $negative = 'âm ';
        $decimal = ' phẩy ';
        $one = 'mốt';
        $ten = 'lẻ';
        $dictionary = array(
            0 => 'Không',
            1 => 'Một',
            2 => 'Hai',
            3 => 'Ba',
            4 => 'Bốn',
            5 => 'Năm',
            6 => 'Sáu',
            7 => 'Bảy',
            8 => 'Tám',
            9 => 'Chín',
            10 => 'Mười',
            11 => 'Mười một',
            12 => 'Mười hai',
            13 => 'Mười ba',
            14 => 'Mười bốn',
            15 => 'Mười lăm',
            16 => 'Mười sáu',
            17 => 'Mười bảy',
            18 => 'Mười tám',
            19 => 'Mười chín',
            20 => 'Hai mươi',
            30 => 'Ba mươi',
            40 => 'Bốn mươi',
            50 => 'Năm mươi',
            60 => 'Sáu mươi',
            70 => 'Bảy mươi',
            80 => 'Tám mươi',
            90 => 'Chín mươi',
            100 => 'trăm',
            1000 => 'ngàn',
            1000000 => 'triệu',
            1000000000 => 'tỷ',
            1000000000000 => 'nghìn tỷ',
            1000000000000000 => 'ngàn triệu triệu',
            1000000000000000000 => 'tỷ tỷ'
        );

        if (!is_numeric($number)) {
            return false;
        }

        if ($number < 0) {
            return $negative . convert_number_to_words(abs($number));
        }

        $fraction = null;

        if (strpos($number, '.') !== false) {
            [$number, $fraction] = explode('.', $number);
        }

        switch (true) {
            case $number < 21:
                $string = $dictionary[$number];
                break;
            case $number < 100:
                $tens = ((int)($number / 10)) * 10;
                $units = $number % 10;
                $string = $dictionary[$tens];
                if ($units) {
                    $string .= strtolower($hyphen . ($units == 1 ? $one : $dictionary[$units]));
                }
                break;
            case $number < 1000:
                $hundreds = $number / 100;
                $remainder = $number % 100;
                $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
                if ($remainder) {
                    $string .= strtolower(
                        $conjunction . ($remainder < 10 ? $ten . $hyphen : null) . convert_number_to_words($remainder)
                    );
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int)($number / $baseUnit);
                $remainder = $number - ($numBaseUnits * $baseUnit);
                $string = convert_number_to_words($numBaseUnits) . ' ' . $dictionary[$baseUnit];
                if ($remainder) {
                    $string .= strtolower($remainder < 100 ? $conjunction : $separator);
                    $string .= strtolower(convert_number_to_words($remainder));
                }
                break;
        }

        if (null !== $fraction && is_numeric($fraction)) {
            $string .= $decimal;
            $words = [];
            foreach (str_split((string)$fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }

    if (!function_exists('getStartDateEndDateByType')) {
        function getStartDateEndDateByType($type = null, $year = null)
        {
            $now = Carbon::createFromDate($year);

            if ($type == "year") {
                $startDate = $now->copy()->startOfYear();
                $endDate = $now->copy()->endOfYear();
            } elseif ($type == "month") {
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
            } elseif ($type == "week") {
                $startDate = $now->copy()->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
            } else {
                $startDate = $now->copy()->startOfDay();
                $endDate = $now->copy()->endOfDay();
            }

            return [$startDate, $endDate];
        }
    }

    if (!function_exists('getStartDateEndDateByTypeToDate')) {
        function getStartDateEndDateByTypeToDate($type = null, $format = "Y-m-d")
        {
            $now = Carbon::now();

            if ($type == "year") {
                $startDate = $now->copy()->startOfYear();
                $endDate = $now->copy()->endOfYear();
            } elseif ($type == "month") {
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
            } elseif ($type == "week") {
                $startDate = $now->copy()->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
            } else {
                $startDate = $now->copy()->startOfDay();
                $endDate = $now->copy()->endOfDay();
            }

            return [date($format, strtotime($startDate)), date($format, strtotime($endDate))];
        }
    }

    if (!function_exists('getTimeByDate')) {
        function getTimeByDate()
        {
            return Carbon::now()->format('Ymd');
        }
    }
    if (!function_exists('getPathFileExport')) {
        function getPathFileExport($module = 'customer')
        {
            $folder = getTimeByDate();
            $path = 'storage/exports/'.$module.'/'.$folder;
            return $path;
        }
    }

    if (!function_exists('getNameRole')) {
        function getNameRole($roles = null)
        {
            $nameRole = '';

            if (!$roles) {
                $user  = Auth::user();
                $roles = optional($user->roles)->all();
            }

            foreach ($roles as $key => $role) {
                $nameRole = \Str::slug($role->display_name);
                if ($nameRole == 'admin') {
                    break;
                }
            }

            return $nameRole;
        }
    }
}


// Hàm dùng để chuyển đổi tiền từ số sáng dạng chữ
if (!function_exists('numInWords')) {
    /**
     * @return integer
     */
    function numInWords($num)
    {
        $nwords = array(
            0                   => 'không',
            1                   => 'một',
            2                   => 'hai',
            3                   => 'ba',
            4                   => 'bốn',
            5                   => 'năm',
            6                   => 'sáu',
            7                   => 'bảy',
            8                   => 'tám',
            9                   => 'chín',
            10                  => 'mười',
            11                  => 'mười một',
            12                  => 'mười hai',
            13                  => 'mười ba',
            14                  => 'mười bốn',
            15                  => 'mười lăm',
            16                  => 'mười sáu',
            17                  => 'mười bảy',
            18                  => 'mười tám',
            19                  => 'mười chín',
            20                  => 'hai mươi',
            30                  => 'ba mươi',
            40                  => 'bốn mươi',
            50                  => 'năm mươi',
            60                  => 'sáu mươi',
            70                  => 'bảy mươi',
            80                  => 'tám mươi',
            90                  => 'chín mươi',
            100                 => 'trăm',
            1000                => 'nghìn',
            1000000             => 'triệu',
            1000000000          => 'tỷ',
            1000000000000       => 'nghìn tỷ',
            1000000000000000    => 'ngàn triệu triệu',
            1000000000000000000 => 'tỷ tỷ',
        );
        $separate = ' ';
        $negative = ' âm ';
        $rltTen   = ' linh ';
        $decimal  = ' phẩy ';
        if (!is_numeric($num)) {
            $w = '#';
        } else if ($num < 0) {
            $w = $negative . numInWords(abs($num));
        } else {
            if (fmod($num, 1) != 0) {
                $numInstr    = strval($num);
                $numInstrArr = explode(".", $numInstr);
                $w           = numInWords(intval($numInstrArr[0])) . $decimal . numInWords(intval($numInstrArr[1]));
            } else {
                $w = '';
                if ($num < 21) // 0 to 20
                {
                    $w .= $nwords[$num];
                } else if ($num < 100) {
                    // 21 to 99
                    $w .= $nwords[10 * floor($num / 10)];
                    $r = fmod($num, 10);
                    if ($r > 0) {
                        $w .= $separate . $nwords[$r];
                    }

                } else if ($num < 1000) {
                    // 100 to 999
                    $w .= $nwords[floor($num / 100)] . $separate . $nwords[100];
                    $r = fmod($num, 100);
                    if ($r > 0) {
                        if ($r < 10) {
                            $w .= $rltTen . $separate . numInWords($r);
                        } else {
                            $w .= $separate . numInWords($r);
                        }
                    }
                } else {
                    $baseUnit     = pow(1000, floor(log($num, 1000)));
                    $numBaseUnits = (int) ($num / $baseUnit);
                    $r            = fmod($num, $baseUnit);
                    if ($r == 0) {
                        $w = numInWords($numBaseUnits) . $separate . $nwords[$baseUnit];
                    } else {
                        if ($r < 100) {
                            if ($r >= 10) {
                                $w = numInWords($numBaseUnits) . $separate . $nwords[$baseUnit] . ' không trăm ' . numInWords($r);
                            }
                            else{
                                $w = numInWords($numBaseUnits) . $separate . $nwords[$baseUnit] . ' không trăm linh ' . numInWords($r);
                            }
                        } else {
                            $baseUnitInstr      = strval($baseUnit);
                            $rInstr             = strval($r);
                            $lenOfBaseUnitInstr = strlen($baseUnitInstr);
                            $lenOfRInstr        = strlen($rInstr);
                            if (($lenOfBaseUnitInstr - 1) != $lenOfRInstr) {
                                $numberOfZero = $lenOfBaseUnitInstr - $lenOfRInstr - 1;
                                if ($numberOfZero == 2) {
                                    $w = numInWords($numBaseUnits) . $separate . $nwords[$baseUnit] . ' không trăm linh ' . numInWords($r);
                                } else if ($numberOfZero == 1) {
                                    $w = numInWords($numBaseUnits) . $separate . $nwords[$baseUnit] . ' không trăm ' . numInWords($r);
                                } else {
                                    $w = numInWords($numBaseUnits) . $separate . $nwords[$baseUnit] . $separate . numInWords($r);
                                }
                            } else {
                                $w = numInWords($numBaseUnits) . $separate . $nwords[$baseUnit] . $separate . numInWords($r);
                            }
                        }
                    }
                }
            }
        }
        return $w;
    }
}
if (!function_exists('numberInVietnameseWords')) {
    function numberInVietnameseWords($num)
    {
        return str_replace("mươi năm", "mươi lăm", str_replace("mươi một", "mươi mốt", numInWords($num)));
    }
}

if (!function_exists('numberInVietnameseCurrency')) {
    function numberInVietnameseCurrency($num)
    {
        $rs    = numberInVietnameseWords($num);
        $rs[0] = strtoupper($rs[0]);
        return $rs . ' đồng';
    }
}
// Quy tắc làm tròn kg màn kiện
if (!function_exists('ruleForKg')) {
    function ruleForKg($number = 0)
    {
        if ($number == 0) {
            return $number;
        }elseif ($number <= 0.5) {
            return 0.5;
        }else {
            if($number > round($number, 1)){
                return round($number, 1) + 0.1;
                return $number;
            }else{
                return round($number, 1);
            }
        }
    }
}

// Số tiền cần thêm để làm tròn tổng tiền đơn hàng
if (!function_exists('extAmountNeed')) {
    function extAmountNeed($number)
    {
        return round($number);
        $numberSub = (int)substr($number, -3);
        if($numberSub <= 500){
            return (int) (500 - $numberSub);
        }else{
            return (int) (1000 - $numberSub);
        }
    }
}

if (!function_exists('roundMoney')) {
    function roundMoney($number)
    {
        return round($number); // round with number is decimal (eg: 10.11)
        $numberSub = (int)substr($number, -3);

        if ($numberSub == 0) {
            return $number;
        }

        if ($numberSub <= 500) {
            return $number + (int) (500 - $numberSub);
        }

        return $number + (int) (1000 - $numberSub);
    }
}


if (!function_exists('normalize')) {
    function normalize($str) {
        // bỏ khoảng trắng dư
        $str = preg_replace('/\s+/', '', $str);

        // lowercase (nếu có tiếng Anh)
        $str = mb_strtolower($str);

        return $str;
    }
}


if (!function_exists('roundMoneyReverse')) {
    function roundMoneyReverse($number)
    {
        $numberSub = (int)substr($number, -3);

        if ($numberSub == 0) {
            return $number;
        }

        if ($numberSub >= 500) {
            return $number + (int) (1000 - $numberSub);
        }

        return $number - (int) ($numberSub);
    }
}

if (!function_exists('resultDevision')) {
    function resultDevision($number1 = 0, $number2)
    {
        if($number2 == 0){
            return 0;
        }
        return $number1/$number2;
    }
}

if (!function_exists('getTimeDiff')) {
    function getTimeDiff($value)
    {
        $now = Carbon::now();
        $time = $now->diffInMinutes($value);
        $timeText = '';
        if($time < 1) {
            $time = 1;
            $timeText = $time . ' phút trước';
        }
        if($time > 1 && $time < 60){
            $timeText = $time . ' phút trước';
        }
        if($time >= 60){
            $time = $now->diffInHours($value);
            $timeText = $time . ' giờ trước';
        }
        return $timeText;
    }
}

if (!function_exists('getFeeInternationalOfficialM3')) {
    function getFeeInternationalOfficialM3()
    {
        $config = Config::where('key', ConfigConstant::FEE_INTERNATIONAL_OFFICIAL_M3)->first();
        return data_get($config, 'value') ?? 0;
    }
}
if (!function_exists('getPrintReceiptCode')) {
    function getPrintReceiptCode($warehouseCode)
    {
       return $warehouseCode . ' ' . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
if (!function_exists('isValidBillCode')) {
    function isValidBillCode($str)
    {
        // Validate kí tự đăc biệt
        return preg_match('/^[a-zA-Z0-9_-]+$/', $str) == 1;
    }
}
if (!function_exists('getOrganizationTD')) {
    function getOrganizationTD()
    {
        $organization = Organization::query()->where('short_name', OrganizationConstant::SHORT_NAME_TD)->first();
        return $organization ? $organization->id : 1;
    }
}
if (!function_exists('getOrganizationBuyder')) {
    function getOrganizationBuyder()
    {
        $organization = Organization::query()->where('short_name', OrganizationConstant::SHORT_NAME_BUYDER)->first();
        return $organization ? $organization->id : 1;
    }
}



