<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use GuzzleHttp\Client as Guzzle;

class GuideController extends Controller
{
    public function __construct()
    {

    }

    public function checkEmailExist(Request $request)
    {
        $validationRules = [
            'email'     => 'required',
        ];
        $validationMessages = [

        ];
        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $userModel = \App::make('App\Models\User');
        $userDB = $userModel->where('email', $params['email'])->first();

        $data_result = true;
        if (is_null($userDB)) {
            $data_result = false;
        }

        return resSuccessWithinData($data_result);
    }

    public function checkEmailLogin(Request $request)
    {
        $validationRules = [
            'email'    => 'required',
            'password' => 'required',
        ];
        $validationMessages = [

        ];
        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $userModel = \App::make('App\Models\User');
        $userDB = $userModel->where('email', $params['email'])->first();

        if (is_null($userDB)) {
            return resSuccessWithinData(false);
        }

        if (\Hash::check($params['password'], $userDB->password)) {
            return resSuccessWithinData(true);
        }
        return resSuccessWithinData(false);
    }

    public function checkLoginAppDepositAuto(Request $request)
    {
        $validationRules = [
            'email'    => 'required',
            'password' => 'required',
        ];
        $validationMessages = [

        ];
        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $userModel = \App::make('App\Models\User');
        $userDB = $userModel->where('email', $params['email'])->first();

        if (is_null($userDB)) {
            return resSuccessWithinData([
                'name'   => null,
                'status' => false,
                'access' => false,
            ]);
        }

        if (\Hash::check($params['password'], $userDB->password)) {
            // Check xem có quyền không
            $permissionDBs = $userDB->getAllPermissions();
            $perOfRole = \Arr::pluck($permissionDBs, 'name');
            if (in_array('fund.view-app-deposit-auto', $perOfRole)) {
                return resSuccessWithinData([
                    'name'   => $userDB->name,
                    'status' => true,
                    'access' => true,
                ]);
            }
            return resSuccessWithinData([
                'name'   => $userDB->name,
                'status' => true,
                'access' => false,
            ]);
        }
        return resSuccessWithinData([
            'name'   => null,
            'status' => false,
            'access' => false,
        ]);
    }
}

