<?php

class Ip
{
    private static $cached = array();
    private static $countryNames = NULL;

    public static function find($ip)
    {
        $nip = self::normalizeIp($ip);
        if ($nip === '')
        {
            return self::emptyLocation();
        }

        if (isset(self::$cached[$nip]) === TRUE)
        {
            return self::$cached[$nip];
        }

        if (filter_var($nip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== FALSE)
        {
            $location = self::findCountryByIpv6($nip);
            self::$cached[$nip] = $location ? array($location) : array('IPv6');
            return self::$cached[$nip];
        }

        if (filter_var($nip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== FALSE)
        {
            $location = self::findCountryByIpv4($nip);
            self::$cached[$nip] = $location ? array($location) : self::emptyLocation();
            return self::$cached[$nip];
        }

        return self::emptyLocation();
    }

    private static function normalizeIp($ip)
    {
        $ip = trim((string)$ip);
        if ($ip === '')
        {
            return '';
        }

        if (strpos($ip, ',') !== FALSE)
        {
            $parts = explode(',', $ip);
            $ip = trim($parts[0]);
        }

        if (isset($ip[0]) && $ip[0] === '[')
        {
            $end = strpos($ip, ']');
            if ($end !== FALSE)
            {
                $ip = substr($ip, 1, $end - 1);
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== FALSE)
        {
            return $ip;
        }

        if (preg_match('/^(.+):\d+$/', $ip, $matches) && filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== FALSE)
        {
            return $matches[1];
        }

        if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $matches) && filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== FALSE)
        {
            return $matches[1];
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== FALSE)
        {
            return $ip;
        }

        return '';
    }

    private static function findCountryByIpv4($ip)
    {
        $file = __DIR__ . '/ip/dbip_country_lite_v4.dat';
        $ipNum = (int)sprintf('%u', ip2long($ip));
        return self::binarySearch($file, 'DBIP4EN1', 10, function ($record) use ($ipNum) {
            $range = unpack('Nstart/Nend', substr($record, 0, 8));
            if ($ipNum < $range['start'])
            {
                return -1;
            }
            if ($ipNum > $range['end'])
            {
                return 1;
            }
            return substr($record, 8, 2);
        });
    }

    private static function findCountryByIpv6($ip)
    {
        $file = __DIR__ . '/ip/dbip_country_lite_v6.dat';
        $ipBin = inet_pton($ip);
        if ($ipBin === FALSE)
        {
            return '';
        }

        return self::binarySearch($file, 'DBIP6EN1', 34, function ($record) use ($ipBin) {
            $start = substr($record, 0, 16);
            $end = substr($record, 16, 16);
            if (strcmp($ipBin, $start) < 0)
            {
                return -1;
            }
            if (strcmp($ipBin, $end) > 0)
            {
                return 1;
            }
            return substr($record, 32, 2);
        });
    }

    private static function binarySearch($file, $magic, $recordSize, $matcher)
    {
        if (is_file($file) === FALSE || is_readable($file) === FALSE)
        {
            return '';
        }

        $fp = fopen($file, 'rb');
        if ($fp === FALSE)
        {
            return '';
        }

        $header = fread($fp, 12);
        if (strlen($header) !== 12 || substr($header, 0, 8) !== $magic)
        {
            fclose($fp);
            return '';
        }

        $count = unpack('Ncount', substr($header, 8, 4));
        $left = 0;
        $right = (int)$count['count'] - 1;
        while ($left <= $right)
        {
            $mid = (int)floor(($left + $right) / 2);
            fseek($fp, 12 + $mid * $recordSize);
            $record = fread($fp, $recordSize);
            if (strlen($record) !== $recordSize)
            {
                break;
            }

            $result = $matcher($record);
            if ($result === -1)
            {
                $right = $mid - 1;
            }
            elseif ($result === 1)
            {
                $left = $mid + 1;
            }
            else
            {
                fclose($fp);
                return self::countryName($result);
            }
        }

        fclose($fp);
        return '';
    }

    private static function countryName($code)
    {
        $code = strtoupper(trim((string)$code));
        if ($code === '' || $code === 'ZZ')
        {
            return '';
        }

        $countryNames = self::countryNames();
        return $countryNames[$code] ?? $code;
    }

    private static function countryNames()
    {
        if (self::$countryNames !== NULL)
        {
            return self::$countryNames;
        }

        $file = __DIR__ . '/ip/country_names_en.php';
        self::$countryNames = is_file($file) ? (include $file) : array();
        if (!is_array(self::$countryNames))
        {
            self::$countryNames = array();
        }
        return self::$countryNames;
    }

    private static function emptyLocation()
    {
        return array('N/A');
    }
}

?>
