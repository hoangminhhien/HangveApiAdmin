<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserExternalLoginRequest;
use App\Services\UserExternalService;
use Illuminate\Http\Request;

class UserExternalController extends Controller
{
    public function __construct()
    {
        $this->_service = new UserExternalService();
    }

    public function login(UserExternalLoginRequest $request)
    {
        try {
            $this->_service->login($request->all());

            return resSuccess();
        } catch (\Exception $e) {
            //dd($e->getMessage());
            return resError($e->getMessage());
        }
    }
}
