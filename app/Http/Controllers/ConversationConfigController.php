<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Services\ConversationConfigService;
use App\Constants\PackageConstant;
use App\Constants\ConversationConstant;
use App\Models\Order;

class ConversationConfigController extends Controller
{
    public function __construct(ConversationConfigService $service)
    {
        $this->_service = $service;
    }

    public function storeMessage(): ?array
    {
       return [
       ];
    }

    public function storeRequest(): array
    {
        return [
            'name'  => 'required|string|max:255|unique:conversation_configs',
            'color' => 'required|string|min:1|max:255',
            'time'  => 'required|numeric|min:1|max:10000000000',
        ];
    }

    public function store(): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__);
        try {
            \DB::beginTransaction();
            $params = request()->all();
            $result = $this->_service->store($params);
            \DB::commit();
            return resSuccessWithinData($result);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }
}
