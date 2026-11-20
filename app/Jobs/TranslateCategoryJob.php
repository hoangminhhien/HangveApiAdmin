<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Category;
use GuzzleHttp\Client as Guzzle;

class TranslateCategoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $categoryId;
    public function __construct($categoryId)
    {
        $this->categoryId = $categoryId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $categoryId = $this->categoryId;
        $category = Category::find($categoryId);
        if($category) {
            $categoryName = [$category->name];
            $translateService = \App::make('App\Services\GoogleTranslateService');
            $translateZh = $translateService->translateSearchText($categoryName, 'zh-CN');
            $translateEn = $translateService->translateSearchText($categoryName, 'en');
            $categoryNameChina = data_get($translateZh, '0.translatedText');
            $categoryNameEn = data_get($translateEn, '0.translatedText');
            $category->update([
                'thuongdo_name_china' => $categoryNameChina,
                'thuongdo_name_en' => $categoryNameEn
            ]);
            $guzzle = new Guzzle;
            $url = env('URL_DONGDONG') . 'webhook/hangve-x329kxm2k2ixk39x92x1911947165';
            $options = [
                'json' => [
                    'event_name' => 'sync_category',
                    'data'       => $category
                ],
                'verify' => false,
                'http_errors' => false,
            ];
            $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
            $result = json_decode($result, true);
        }
    }
}
