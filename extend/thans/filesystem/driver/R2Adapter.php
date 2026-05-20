<?php

declare(strict_types=1);

namespace thans\filesystem\driver;

use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Config;
use League\Flysystem\Util;

class R2Adapter extends AwsS3Adapter
{
    public function write($path, $contents, Config $config)
    {
        return $this->putObject($path, $contents, $config);
    }

    public function update($path, $contents, Config $config)
    {
        return $this->putObject($path, $contents, $config);
    }

    public function writeStream($path, $resource, Config $config)
    {
        return $this->putObject($path, $resource, $config);
    }

    public function updateStream($path, $resource, Config $config)
    {
        return $this->putObject($path, $resource, $config);
    }

    protected function putObject($path, $body, Config $config)
    {
        $key = $this->applyPathPrefix($path);
        $options = $this->getOptionsFromConfig($config);
        unset($options['ACL']);

        if (!$this->isDirectoryObject($path)) {
            if (!isset($options['ContentType'])) {
                $options['ContentType'] = Util::guessMimeType($path, $body);
            }

            if (!isset($options['ContentLength'])) {
                $options['ContentLength'] = is_resource($body) ? Util::getStreamSize($body) : Util::contentSize($body);
            }

            if ($options['ContentLength'] === null) {
                unset($options['ContentLength']);
            }
        }

        $this->s3Client->putObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
            'Body'   => $body,
        ] + $options);

        return $this->normalizeResponse($options, $path);
    }

    protected function isDirectoryObject($path): bool
    {
        return substr($path, -1) === '/';
    }
}
