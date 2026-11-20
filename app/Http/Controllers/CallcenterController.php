<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use GuzzleHttp\Client as Guzzle;

class CallcenterController extends Controller
{
    public function __construct()
    {

    }

    public function makeCallOut(Request $request)
    {
        // Validate
        $validationRules = [
            'destination_id' => 'required',
        ];
        $validationMessages = [

        ];
        $validated = $request->validate($validationRules, $validationMessages);

        // Xử lý gọi ra
        $params = $request->all();
        $userOnline = \Auth::user();

        $url = env('URL_CALL_ONLINE_CB');

        // Bắn data
        $guzzle = new Guzzle;
        $options = [
            'json' => [
                'agent_email'    => $userOnline->email,
                'destination_id' => $params['destination_id'],
                'token19a'       => env('TOKEN_CALL_ONLINE_CB')
            ],
            'verify' => false,
            'http_errors' => false,
        ];
        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $results = json_decode($result, true);

        if ($results && $results['status_code'] == 200) {
            return resSuccessWithinData('Thành công');
        }

        if ($results && $results['status_code'] == 400) {
            $result = [ 'error' => $results['message']];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        return resErrorWithinData([], 'Có lỗi xảy ra', 422);
    }
}

