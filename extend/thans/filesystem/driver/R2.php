<?php

declare(strict_types=1);

namespace thans\filesystem\driver;

use Aws\S3\S3Client;
use League\Flysystem\AdapterInterface;
use thans\filesystem\traits\Storage;
use think\filesystem\Driver;

class R2 extends Driver
{
    use Storage;

    protected function createAdapter(): AdapterInterface
    {
        $usePathStyle = $this->config['usePathStyle'] ?? true;
        if (is_string($usePathStyle)) {
            $usePathStyle = !in_array(strtolower($usePathStyle), ['0', 'false', 'off', 'no'], true);
        }

        $client = new S3Client([
            'version'                          => 'latest',
            'region'                           => $this->config['region'] ?: 'auto',
            'endpoint'                         => rtrim($this->config['endpoint'], '/'),
            'suppress_php_deprecation_warning' => true,
            'credentials'                      => [
                'key'    => $this->config['accessKey'],
                'secret' => $this->config['secretKey'],
            ],
            'use_path_style_endpoint'          => $usePathStyle,
        ]);

        return new R2Adapter($client, $this->config['bucket'], $this->config['prefix'] ?? '');
    }
}
