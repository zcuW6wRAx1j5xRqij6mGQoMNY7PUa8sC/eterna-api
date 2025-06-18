<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Internal\Security\Services\CloudflareCaptcha;
use Symfony\Component\HttpFoundation\Response;

class ValidateTurnstile
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cfToken = $request->get('cf_token', '');
        if (!$cfToken || !(new CloudflareCaptcha())($request, $cfToken)) {
            return response()->json(['code'=>400, 'message' => __('Incorrect submitted data'), 'data'=>new \stdClass()]);
        }

        return $next($request);
    }
}
