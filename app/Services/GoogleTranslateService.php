<?php

namespace App\Services;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;

class GoogleTranslateService
{
    /**
     * @throws GuzzleException
     */
    /* 'zh-CN => China, 'vi' => Viet Nam*/
    public static function translateWithCache(string $encodedData, string $target = 'zh-CN'): string
    {
        $searchKey = rawurlencode($encodedData);
        $searchKeys = Cache::get('keys_search'.$target, []);
        if (count($searchKeys) && !empty($searchKeys[$searchKey])) {
            return str_replace(['&#39;s'], ' ', $searchKeys[$searchKey]);
        }
        $guzzle = new Guzzle();
        $translateUrl = sprintf(
            "https://www.googleapis.com/language/translate/v2?key=%s&q=%s&target=%s",
            env('GOOGLE_TRANSLATE_KEY', 'AIzaSyCPOq7e79Gg0Xs_Fx7rqiJI0Yr6ugTvH-U'),
            $searchKey,
            $target
        );
        $rawResponse = $guzzle->get(
            $translateUrl,
            ['timeout' => 15, 'connect_timeout' => 15]
        )->getBody()->getContents();
        $response = data_get(json_decode($rawResponse, true), 'data');

        $result = data_get($response, 'translations.0.translatedText') ?? '';
        $result = str_replace(['&#39;s'], ' ', $result);
        $searchKeys[$searchKey] = $result;
        Cache::put('keys_search'.$target, $searchKeys, now()->addMinutes(10));

        return $result;
    }

    /**
     * @throws GuzzleException
     */
    public static function translateWithOutCache(string $encodedData, string $target = 'zh-CN'): string
    {
        $searchKey = rawurlencode($encodedData);
        $guzzle = new Guzzle();
        $translateUrl = sprintf(
            "https://www.googleapis.com/language/translate/v2?key=%s&q=%s&target=%s",
            env('GOOGLE_TRANSLATE_KEY', 'AIzaSyCPOq7e79Gg0Xs_Fx7rqiJI0Yr6ugTvH-U'),
            $searchKey,
            $target
        );
        $rawResponse = $guzzle->get(
            $translateUrl,
            ['timeout' => 15, 'connect_timeout' => 15]
        )->getBody()->getContents();
        $response = data_get(json_decode($rawResponse, true), 'data');
        $result = data_get($response, 'translations.0.translatedText') ?? '';
        return str_replace(['&#39;s'], ' ', $result);
    }

    public static function translateSearchText(array $text, string $target = 'zh-CN'): array
    {
        $guzzle = new Guzzle();
        $translateUrl = sprintf(
            config('services.gg-translate.endpoint_text'),
            config('services.gg-translate.key')
        );

        $params = [
            'q' => $text,
            'target' => $target
        ];

        $rawResponse = $guzzle->post(
            $translateUrl,
            [ RequestOptions::JSON => $params ],
        )->getBody()->getContents();
        $response = data_get(json_decode($rawResponse, true), 'data');

        return data_get($response, 'translations') ?? [];
    }
}
