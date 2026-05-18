<?php

/*
    全球 IPv4 地址归属地数据库(17MON.CN 版)
    高春辉(pAUL gAO) <gaochunhui@gmail.com>
    Build 20141009 版权所有 17MON.CN
    (C) 2006 - 2014 保留所有权利
    请注意及时更新 IP 数据库版本
    数据问题请加 QQ 群: 346280296
    Code for PHP 5.3+ only
*/

class Ip
{
    private static $ip     = NULL;

    private static $fp     = NULL;
    private static $offset = NULL;
    private static $index  = NULL;

    private static $cached = array();

    public static function find($ip)
    {
        $nip = self::normalizeIp($ip);
        if ($nip === '')
        {
            return self::emptyLocation();
        }

        if (filter_var($nip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== FALSE)
        {
            return self::ipv6Location($nip);
        }

        $ipdot = explode('.', $nip);
        if ($ipdot[0] < 0 || $ipdot[0] > 255 || count($ipdot) !== 4)
        {
            return self::emptyLocation();
        }

        if (isset(self::$cached[$nip]) === TRUE)
        {
            return self::$cached[$nip];
        }

        if (self::$fp === NULL)
        {
            self::init();
        }

        $nip2 = pack('N', ip2long($nip));

        $tmp_offset = (int)$ipdot[0] * 4;
        $start      = unpack('Vlen', self::$index[$tmp_offset] . self::$index[$tmp_offset + 1] . self::$index[$tmp_offset + 2] . self::$index[$tmp_offset + 3]);

        $index_offset = $index_length = NULL;
        $max_comp_len = self::$offset['len'] - 1024 - 4;
        for ($start = $start['len'] * 8 + 1024; $start < $max_comp_len; $start += 8)
        {
            if (self::$index[$start] . self::$index[$start + 1] . self::$index[$start + 2] . self::$index[$start + 3] >= $nip2)
            {
                $index_offset = unpack('Vlen', self::$index[$start + 4] . self::$index[$start + 5] . self::$index[$start + 6] . "\x0");
                $index_length = unpack('Clen', self::$index[$start + 7]);

                break;
            }
        }

        if ($index_offset === NULL)
        {
            return self::emptyLocation();
        }

        fseek(self::$fp, self::$offset['len'] + $index_offset['len'] - 1024);

        self::$cached[$nip] = explode("\t", fread(self::$fp, $index_length['len']));

        return self::$cached[$nip];
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

    private static function emptyLocation()
    {
        return array('N/A');
    }

    private static function ipv6Location($ip)
    {
        return array('IPv6');
    }

    private static function init()
    {
        if (self::$fp === NULL)
        {
            self::$ip = new self();

            self::$fp = fopen(__DIR__ . '/ip/17monipdb.dat', 'rb');
            if (self::$fp === FALSE)
            {
                throw new Exception('Invalid 17monipdb.dat file!');
            }

            self::$offset = unpack('Nlen', fread(self::$fp, 4));
            if (self::$offset['len'] < 4)
            {
                throw new Exception('Invalid 17monipdb.dat file!');
            }

            self::$index = fread(self::$fp, self::$offset['len'] - 4);
        }
    }

    public function __destruct()
    {
        if (self::$fp !== NULL)
        {
            fclose(self::$fp);
        }
    }
}

?>
