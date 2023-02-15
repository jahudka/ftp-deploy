<?php

declare(strict_types=1);

namespace App\Helper;


class Config
{
    public string $host;
    public int | null $port;
    public string $user;
    public string $password;
    public string $localRoot;
    public string $remoteRoot;
    public string $publicDir;
    public string $baseUrl;
    public string $remoteRootRelativeToPublicDir;
    public array $files;

    public function __construct(
        string $host,
        int | null $port,
        string $user,
        string $password,
        string $localRoot,
        string $remoteRoot,
        string $publicDir,
        string $baseUrl,
        string $remoteRootRelativeToPublicDir,
        array $files = [],
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
        $this->localRoot = $localRoot;
        $this->remoteRoot = $remoteRoot;
        $this->publicDir = $publicDir;
        $this->baseUrl = $baseUrl;
        $this->remoteRootRelativeToPublicDir = $remoteRootRelativeToPublicDir;
        $this->files = $files;
    }
}
