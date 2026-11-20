<?php

namespace ThuongDo\Buyder\src\Constants;

use Illuminate\Support\Arr;

/**
 * Các constant nên extend BaseConstant này để tối ưu code
 */
class BaseConstant
{
    /**
     * Tránh lỗi với các trường hợp không truyền trực tiếp tên hằng mà sử dụng biến
     * do lấy từ DB, nhận từ bên khác, hoặc key không tồn tại trong array constant...
     *
     * Hiện chỉ hoạt động với 2 case
     *  + Lấy constant: SampleConstant::getConstantValue('constantName')
     *  + Lấy các item trong constant là mảng: SampleConstant::getConstantValue('constantName', 'key')
     *
     * Method này không dùng để lấy giá trị nếu constant là mảng, thay vào đó dùng 'getArrayConstantValue'
     *
     * Ví dụ: BuyderStatusConstant::STATUSES[data_get($orderBuyder, 'supplier_status')]
     *
     * @param string $constantName
     * @param string|null $key
     * @return mixed
     */
    public static function getConstantValue(string $constantName, ?string $key = null, string $source = '')
    {
        // lấy class
        $className = static::class;

        // kiểm tra xem constant đó có tồn tại không
        // return luôn nếu
        //  + không tồn tại
        //  + có tồn tại nhưng không phải array
        //  + là array nhưng không truyền key
        $constant = defined("$className::$constantName")
            ? constant("$className::$constantName")
            : null;

        if (is_null($constant) || !is_array($constant)) {
            return null;
        }

        if (is_array($constant) && is_null($key)) {
            return $source == 'format' ? $constant : null;
        }

        // nếu là array thì check xem key có tồn tại không
        //  + nếu key tồn tại thì return giá trị
        //  + không thì return null
        return array_key_exists($key, $constant)
            ? $constant[$key]
            : null;
    }

    /**
     * Lấy giá trị của array constant
     *   + Nếu constant không tồn tại hoặc không phải array thì return null
     *   + Nếu constant tôồn tại và là array thì return array đó
     *
     * @param string $constantName Tên constant
     * @return array|null
     */
    public static function getArrayConstantValue(string $constantName): ?array
    {
        // lấy class
        $className = static::class;

        // kiểm tra constant đó
        //   + nếu không tồn tại hoặc không phải mảng thì return null
        //   + nếu tồn tại và là mảng thì return mảng đó
        $constant = defined("$className::$constantName")
            ? constant("$className::$constantName")
            : null;

        if (is_null($constant) || !is_array($constant)) {
            return null;
        }

        return $constant;
    }

    /**
     * Lấy dữ liệu đã được format của các constant dạng mảng
     *
     * @param string $constantName
     * @return null|array<array{'code': string, 'name': mixed}>
     */
    public static function getFormattedArrayConstant(string $constantName): ?array
    {
        // lấy constant, return null luôn nếu
        //   + constant không tồn tại
        //   + constant không phải array
        $constant = self::getConstantValue($constantName, null, 'format');

        if (is_null($constant)) {
            return null;
        }

        return Arr::map($constant, function ($value, $key) {
            return [
                'code' => $key,
                'name' => $value,
            ];
        });
    }

    /**
     * Lấy mảng chứa keys của constant dạng mảng
     *
     * @param string $constantName
     * @return array|null
     */
    public function getArrayConstantKeys(string $constantName): ?array
    {
        $constant = self::getConstantValue($constantName);

        if (is_null($constant) || !is_array($constant)) {
            return null;
        }

        return array_keys($constant);
    }
}
