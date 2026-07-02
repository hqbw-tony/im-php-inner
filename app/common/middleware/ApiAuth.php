<?php
namespace app\common\middleware;

use app\common\model\ThirdPlatform;

// 验证开放 API 权限
class ApiAuth
{
    public function handle($request, \Closure $next)
    {
        $apiStatus = config('app.api_status');
        if (!$apiStatus) {
            return shutdown(lang('system.apiClose'));
        }
        $header = $request->header();
        $appId = $header['x-im-appid'] ?? '';
        $timeStamp = $header['x-im-timestamp'] ?? 0;
        $sign = $header['x-im-sign'] ?? '';
        if (!$appId || !$timeStamp || !$sign) {
            return shutdown(lang('system.parameterError'));
        }
        if (time() - $timeStamp > 60) {
            return shutdown(lang('system.longTime'));
        }

        $platform = ThirdPlatform::findByAppId($appId);
        if ($platform) {
            if ((int)$platform['status'] !== 1) {
                return shutdown(lang('system.forbidden'));
            }
            $originResponse = $this->checkPlatformOrigin($request, $header, $platform);
            if ($originResponse) {
                return $originResponse;
            }
            $appSecret = $platform['app_secret'];
            $request->thirdPlatform = $platform->toArray();
        } else {
            $legacyAppId = config('app.app_id');
            $appSecret = config('app.app_secret');
            if ($legacyAppId != $appId) {
                return shutdown(lang('system.appIdError'));
            }
            $request->thirdPlatform = null;
        }

        $signStr = md5($appId . $timeStamp . $appSecret);
        if ($sign != $signStr) {
            return shutdown(lang('system.signError'));
        }
        return $next($request);
    }

    protected function checkPlatformOrigin($request, $header, $platform)
    {
        $allowedOrigins = ThirdPlatform::extractAllowedOrigins($platform['extra'] ?? []);
        if (!$allowedOrigins) {
            return null;
        }
        $requestOrigin = trim((string)($header['origin'] ?? ''));
        $origin = $requestOrigin ?: trim((string)($header['x-im-origin'] ?? ''));
        if ($origin === '') {
            $origin = trim((string)$request->param('origin', ''));
        }
        if ($origin !== '' && !ThirdPlatform::originAllowed($origin, $allowedOrigins)) {
            return shutdown(lang('system.forbidden'));
        }
        if ($requestOrigin !== '') {
            $this->sendCorsHeaders($requestOrigin);
        }
        return null;
    }

    protected function sendCorsHeaders($origin)
    {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, x-im-appid, x-im-timestamp, x-im-sign, x-im-origin');
        header('Vary: Origin');
    }
}
