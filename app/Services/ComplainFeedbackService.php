<?php


namespace App\Services;


use App\Constants\ComplainConstant;
use App\Http\Resources\ComplainResource;
use App\Models\ComplainFeedback;

class ComplainFeedbackService extends BaseService
{
    protected string $_resource = ComplainResource::class;
}