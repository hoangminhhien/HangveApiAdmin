<?php

namespace App\Http\Middleware;

use Closure;

class CheckCBHV
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $ip = $request->ip();

        // Check Token
        $params    = $request->all();
        $token2000a = isset($params['token2000a']) ? $params['token2000a'] : null;
        $passToken = false;

        if ($token2000a == env('TOKEN_SYNC_CB_HV')) {
            $passToken = true;
        }

        $text = $passToken ? 'a7891' : 'a123mb1';

        // \Log::info('IPApp : ' . $ip);
        // \Log::info('passTokenApp : ' . $text);

        // Check điều kiện
        if ($passToken) {
            return $next($request);
        }

        \Log::info('IP App loi rui:');

        return response()->json('ErrorCB', 500);
    }
}
