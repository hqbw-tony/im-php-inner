<?php

if ($argc < 2) {
    fwrite(STDERR, "Usage: php build_dbip_country.php <dbip-country-lite.csv.gz>\n");
    exit(1);
}

$source = $argv[1];
if (!is_file($source)) {
    fwrite(STDERR, "Source file not found: {$source}\n");
    exit(1);
}

$dir = __DIR__;
$ipv4File = $dir . '/dbip_country_lite_v4.dat';
$ipv6File = $dir . '/dbip_country_lite_v6.dat';
$countryFile = $dir . '/country_names_en.php';
$countryCodes = [];

$input = gzopen($source, 'rb');
if (!$input) {
    fwrite(STDERR, "Failed to open source gzip: {$source}\n");
    exit(1);
}

$out4 = fopen($ipv4File, 'wb');
$out6 = fopen($ipv6File, 'wb');
if (!$out4 || !$out6) {
    fwrite(STDERR, "Failed to create output files\n");
    exit(1);
}

fwrite($out4, "DBIP4EN1" . pack('N', 0));
fwrite($out6, "DBIP6EN1" . pack('N', 0));

$count4 = 0;
$count6 = 0;
while (($row = fgetcsv($input)) !== false) {
    if (count($row) < 3) {
        continue;
    }

    $start = trim($row[0]);
    $end = trim($row[1]);
    $code = strtoupper(trim($row[2]));
    if ($code === '' || $code === 'ZZ') {
        continue;
    }
    $code = substr($code . '  ', 0, 2);
    $countryCodes[$code] = true;

    if (filter_var($start, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($end, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        fwrite($out4, pack('N', sprintf('%u', ip2long($start))));
        fwrite($out4, pack('N', sprintf('%u', ip2long($end))));
        fwrite($out4, $code);
        $count4++;
        continue;
    }

    if (filter_var($start, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($end, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        fwrite($out6, inet_pton($start));
        fwrite($out6, inet_pton($end));
        fwrite($out6, $code);
        $count6++;
    }
}

fseek($out4, 8);
fwrite($out4, pack('N', $count4));
fseek($out6, 8);
fwrite($out6, pack('N', $count6));

gzclose($input);
fclose($out4);
fclose($out6);

ksort($countryCodes);
$countryNames = [];
foreach (array_keys($countryCodes) as $code) {
    $countryNames[$code] = dbip_country_name($code);
}
file_put_contents($countryFile, "<?php\nreturn " . var_export($countryNames, true) . ";\n");

echo "Built {$ipv4File} ({$count4} records)\n";
echo "Built {$ipv6File} ({$count6} records)\n";
echo "Built {$countryFile} (" . count($countryNames) . " countries)\n";

function dbip_country_name($code)
{
    $special = [
        'HK' => 'Hong Kong',
        'MO' => 'Macau',
        'TW' => 'Taiwan',
    ];
    if (isset($special[$code])) {
        return $special[$code];
    }
    if (class_exists('Locale')) {
        $name = \Locale::getDisplayRegion('-' . $code, 'en');
        if ($name && $name !== 'Unknown Region') {
            return $name;
        }
    }
    return $code;
}
