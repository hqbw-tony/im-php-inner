<?php

namespace app\common\middleware;

use app\enterprise\model\User;
use think\facade\Lang;

class Locale
{
    public function handle($request, \Closure $next)
    {
        $lang=User::getRequestLanguage($request) ?: User::getClientDefaultLanguage();
        Lang::switchLangSet($lang);
        return $next($request);
    }
}
