<?php


namespace App\Services;


use App\Constants\UserExternalConstant;
use App\Models\UserExternal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use function PHPUnit\Framework\throwException;

class UserExternalService extends BaseService
{
    public function login($data)
    {
        $source = $data['source'];
        $url    = $this->getUrlLoginBySource($source);
        if (!$url) {
            throw new \Exception('Đường dẫn đăng nhập không đúng!');
        }

        $params = [
            'email'    => $data['user_name'],
            'password' => $data['user_password']
        ];
        $response = $this->curl($params, $url, "POST");

        $responseCode = $response->code ?? '';
        if (!$responseCode || $responseCode != 200) {
            $msg = $response->message;
            if ($responseCode == 422) {
                $dataRes = $response->data;
                $msg .= ": ";
                if (!empty($dataRes->email)) {
                    $msg .= $dataRes->email;
                }
                if (!empty($dataRes->password)) {
                    $msg .= $dataRes->password;
                }
            }

            throw new \Exception($msg);
        }
        // save info login
        $userId       = Auth::user()->id;
        $userExternal = UserExternal::where(['user_id' => $userId, 'source' => $data['source']])->first();
        if ($userExternal) {
            $userExternal->user_name     = $data['user_name'];
            $userExternal->user_password = $this->encrypt($data['user_password']);
            $userExternal->is_active     = true;
            $userExternal->updated_at    = date('Y-m-d H:i:s');
            $userExternal->save();

            return $userExternal;
        }

        $dataLogin = [
            'user_id'       => Auth::user()->id,
            'source'        => $data['source'],
            'user_name'     => $data['user_name'],
            'user_password' => $this->encrypt($data['user_password']),
            'is_active'     => true
        ];
        $userExternal = UserExternal::create($dataLogin);
        return $userExternal;
    }

    public function getUrlLoginBySource($source)
    {
        $url = '';

        switch ($source) {
            case UserExternalConstant::SOURCE_XADAL:
                $url =  env('URL_XADAL_LOGIN');
                break;
        }

        return $url;
    }
}
