<?php
namespace app\common\model;

use app\BaseModel;

class ThirdPlatform extends BaseModel
{
    protected $pk = 'id';

    protected $json = ['extra'];
    protected $jsonAssoc = true;

    public static function newSecret()
    {
        return bin2hex(random_bytes(16));
    }

    public static function newAppId()
    {
        return 'tp_' . bin2hex(random_bytes(8));
    }

    public static function findByAppId($appId)
    {
        if (!$appId) {
            return null;
        }
        return self::where(['app_id' => $appId, 'delete_time' => 0])->find();
    }

    public static function extractAllowedOrigins($extra)
    {
        if (is_string($extra)) {
            $extra = json_decode($extra, true) ?: [];
        }
        if (!is_array($extra)) {
            return [];
        }
        return self::normalizeOrigins($extra['allowed_origins'] ?? []);
    }

    public static function normalizeOrigins($origins)
    {
        if (is_string($origins)) {
            $json = json_decode($origins, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $origins = $json;
            } else {
                $origins = preg_split('/[\r\n,]+/', $origins);
            }
        }
        if (!is_array($origins)) {
            return [];
        }
        $list = [];
        foreach ($origins as $origin) {
            $origin = trim((string)$origin);
            if ($origin === '') {
                continue;
            }
            if ($origin === '*') {
                $list[] = '*';
                continue;
            }
            $origin = self::normalizeOrigin($origin);
            if ($origin) {
                $list[] = $origin;
            }
        }
        return array_values(array_unique($list));
    }

    public static function originAllowed($origin, $allowedOrigins)
    {
        $allowedOrigins = self::normalizeOrigins($allowedOrigins);
        if (!$allowedOrigins) {
            return true;
        }
        $origin = self::normalizeOrigin($origin);
        if (!$origin) {
            return false;
        }
        if (in_array('*', $allowedOrigins, true) || in_array($origin, $allowedOrigins, true)) {
            return true;
        }
        $originParts = parse_url($origin);
        foreach ($allowedOrigins as $allowedOrigin) {
            if (strpos($allowedOrigin, '*.') === false) {
                continue;
            }
            $allowedParts = parse_url($allowedOrigin);
            if (!$allowedParts || ($allowedParts['scheme'] ?? '') !== ($originParts['scheme'] ?? '')) {
                continue;
            }
            if ((int)($allowedParts['port'] ?? 0) !== (int)($originParts['port'] ?? 0)) {
                continue;
            }
            $suffix = substr($allowedParts['host'] ?? '', 1);
            $host = $originParts['host'] ?? '';
            if ($suffix && substr($host, -strlen($suffix)) === $suffix) {
                return true;
            }
        }
        return false;
    }

    protected static function normalizeOrigin($origin)
    {
        $origin = rtrim(trim((string)$origin), '/');
        $parts = parse_url($origin);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        return $scheme . '://' . $host . $port;
    }
}
