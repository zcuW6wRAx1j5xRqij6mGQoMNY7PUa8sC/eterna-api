<?php

namespace App\Http\Middleware;

use App\Enums\CommonEnums;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (strstr($request->path(), 'api/app/') !== false) {
            $locale = $request->header('Accept-Language');
            if (in_array($locale, CommonEnums::LanguageAll)) {
                $locale = $locale;
            } else {
                $locale = CommonEnums::LanguageEn;
            }
            App::setLocale($locale);
        }
        return $next($request);
    }
}
