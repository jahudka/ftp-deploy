<?php

declare(strict_types=1);

namespace App\Helper;

use GuzzleHttp\Client;
use Symfony\Component\String\ByteString;


class HelperRunner
{
    private Client $http;
    private FtpClient $ftp;
    private Config $config;

    public function __construct(Client $http, FtpClient $ftp, Config $config)
    {
        $this->http = $http;
        $this->ftp = $ftp;
        $this->config = $config;
    }

    public function run(callable $build) : string
    {
        $helperName = sprintf('%s.php', ByteString::fromRandom(72)->toString());
        $helperPath = sprintf('%s/%s', $this->config->publicDir, $helperName);
        $helperUrl = sprintf('%s/%s', $this->config->baseUrl, $helperName);
        $secret = ByteString::fromRandom(128)->toString();

        $tmp = tempnam(sys_get_temp_dir(), 'ftpdeploy');
        $build($tmp, $secret);

        $this->ftp->upload($tmp, $helperPath);
        unlink($tmp);

        $response = $this->http->post($helperUrl, [
            'form_params' => ['key' => $secret],
        ]);

        return $response->getBody()->getContents();
    }
}
