<?php

namespace App\Actions\ReportProductsPipeline;

use App\Actions\BaseAction;
use App\Constants\Report\ProductsPipelineConstant;
use Illuminate\Http\JsonResponse;

class RequestSourceListAction extends BaseAction
{
    /**
     * Chứa logic thực thi chính của action
     *
     * @param array $data
     * @return JsonResponse
     */
    public function handle(?array $data): JsonResponse
    {
        $availableSources = ProductsPipelineConstant::AVAILABLE_SOURCES;
        return resSuccessWithinData($availableSources);
    }
}
