<?php

namespace ThuongDo\Buyder\src\Traits;

use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

trait ActionTrait
{
    /**
     * Tách lấy id sản phẩm nếu user submit lên valid link hoặc id
     *
     * @param string $searchKey
     * @return string|null
     */
    public function getPlatformItemId(
        string $searchKey,
        $platformSource = BuyderServiceConstant::SOURCE_TAOBAO
    ): ?string {
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

    /**
     * Dịch từ khóa sang tiếng Trung
     *
     * @param string $searchKey Từ gốc cần dịch
     * @param string $target Dịch qua ngôn ngữ gì
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function translateSearchKey(string $searchKey, string $target = 'zh-CN'): string
    {
        $translateUrl = sprintf(
            "https://www.googleapis.com/language/translate/v2?key=%s&q=%s&target={$target}",
            config('services.gg-translate.key'),
            rawurlencode($searchKey)
        );
        $rawResponse = $this->guzzle->get(
            $translateUrl,
            ['timeout' => 10, 'connect_timeout' => 10]
        )->getBody()->getContents();
        $response = data_get(json_decode($rawResponse, true), 'data');

        return isset($response['translations'][0]['translatedText'])
            ? $response['translations'][0]['translatedText']
            : '';
    }
}
