<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Category;
use App\Exports\CategoryPrice;
use Maatwebsite\Excel\Facades\Excel;

use App\Imports\CategoryPriceImport;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::select('id', 'name', 'hn_kg', 'hn_m3', 'hcm_kg', 'hcm_m3')->get();
        return resSuccessWithinData($categories);
    }

    public function downloadFileTemplatePrice(\Request $request)
    {
        Excel::store(new CategoryPrice, 'excel/category_price.xlsx', 'local');

        // download
        $file = storage_path('app/excel/category_price.xlsx');

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        return response()->download($file, 'category_price.xlsx', $headers);

        // Cách 2
        $path1 = env('APP_URL') . '/storage/excel/category_price.xlsx';
        $data_result = [
            'path' => $path1,
        ];
        return resSuccessWithinData($data_result);
    }

    public function submitFileTemplatePrice(Request $request)
    {
        $params = $request->all();
        $data_category_prices = (new CategoryPriceImport)->toArray(request()->file('file'));

        $validate = $this->validateDataExcel($data_category_prices[0]);

        if ($validate) {
            return resError($validate);
        }

        try {
            \DB::beginTransaction();
            $this->updateCategoryPriceExcel($data_category_prices[0]);
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function validateDataExcel($data_category_prices)
    {
        foreach ($data_category_prices as $key => $category) {

            if ($key == 0) {
                continue;
            }

            if ($key == 1) {
                continue;
            }

            $category_name = $category[1];
            $hn_kg         = $category[2];
            $hn_m3         = $category[3];
            $hcm_kg        = $category[4];
            $hcm_m3        = $category[5];

            if (
                is_null($category_name) &&
                is_null($hn_kg) &&
                is_null($hn_m3) &&
                is_null($hcm_kg) &&
                is_null($hcm_m3)
            ) {
                continue;
            }

            if (is_null($hn_kg)) $hn_kg   = 0;
            if (is_null($hn_m3)) $hn_m3   = 0;
            if (is_null($hcm_kg)) $hcm_kg = 0;
            if (is_null($hcm_m3)) $hcm_m3 = 0;

            if (is_null($category_name)) {
                return 'Danh mục dòng số ' . ($key + 1) . ' tên không được để trống';
            }

            if (!is_numeric($hn_kg)) {
                return 'Danh mục dòng số ' . ($key + 1) . ' số tiền HN-KG không không đúng định dạng';
            }
            if ($hn_kg < 0) {
                return 'Danh mục dòng số ' . ($key + 1) . ' số tiền số tiền HN-KG không được nhỏ hơn 0';
            }

            if (!is_numeric($hn_m3)) {
                return 'Danh mục dòng số ' . ($key + 1) . ' số tiền HN-M3 không không đúng định dạng';
            }
            if ($hn_m3 < 0) {
                return 'Danh mục dòng số ' . ($key + 1) . ' số tiền số tiền HN-M3 không được nhỏ hơn 0';
            }

            if (!is_numeric($hcm_kg)) {
                return 'Danh mục dòng số ' . ($key + 1) . ' số tiền HCM-KG không không đúng định dạng';
            }
            if ($hcm_kg < 0) {
                return 'Danh mục dòng số ' . ($key + 1) . ' số tiền số tiền HCM-KG không được nhỏ hơn 0';
            }

            if (!is_numeric($hcm_m3)) {
                return 'Danh mục dòng số ' . ($key + 1) . ' số tiền HCM-M3 không không đúng định dạng';
            }
            if ($hcm_m3 < 0) {
                return 'Danh mục dòng số ' . ($key + 1) . ' số tiền số tiền HCM-M3 không được nhỏ hơn 0';
            }
        }

        return null;
    }

    public function updateCategoryPriceExcel($data_category_prices)
    {
        $categoryModel    = \App::make('App\Models\Category');
        foreach ($data_category_prices as $key => $category) {

            if ($key == 0) {
                continue;
            }

            if ($key == 1) {
                continue;
            }

            $category_name = $category[1];
            $hn_kg         = $category[2];
            $hn_m3         = $category[3];
            $hcm_kg        = $category[4];
            $hcm_m3        = $category[5];

            if (
                is_null($category_name) &&
                is_null($hn_kg) &&
                is_null($hn_m3) &&
                is_null($hcm_kg) &&
                is_null($hcm_m3)
            ) {
                continue;
            }

            if (is_null($hn_kg)) $hn_kg   = 0;
            if (is_null($hn_m3)) $hn_m3   = 0;
            if (is_null($hcm_kg)) $hcm_kg = 0;
            if (is_null($hcm_m3)) $hcm_m3 = 0;

            $categoryDB = $categoryModel->where('name', $category_name)->first();
            if (!is_null($categoryDB)) {
                $categoryDB->hn_kg  = $hn_kg;
                $categoryDB->hn_m3  = $hn_m3;
                $categoryDB->hcm_kg = $hcm_kg;
                $categoryDB->hcm_m3 = $hcm_m3;
                $categoryDB->save();
            }
        }
    }

    public function translate()
    {
        Category::query()
            ->whereNull('thuongdo_name_china')
            ->chunk(100, function($categories)
            {
                foreach($categories as $category) {
                    $job = new \App\Jobs\TranslateCategoryJob($category->id);
                    dispatch($job)->onQueue('sync-data-report');
                }
                
            });
        return resSuccess();
    }
}
