<?php

declare(strict_types=1);

namespace App\DI;

use App\Commands\DeployCommand;
use App\Helper\ArchiveBuilder;
use App\Helper\Comparator;
use App\Helper\Config;
use App\Helper\ConfigLoader;
use App\Helper\DeployRunner;
use App\Helper\FileFilter;
use App\Helper\FtpClient;
use App\Helper\HelperRunner;
use App\Helper\LocalScanner;
use App\Helper\RemoteScannerBuilder;
use App\Helper\RemoteScanRunner;
use GuzzleHttp\Client;
use Symfony\Component\Console\Application;


class Container
{
    private ?string $configPath = null;
    private ?Application $application;
    private ?ArchiveBuilder $archiveBuilder;
    private ?Comparator $comparator;
    private ?ConfigLoader $configLoader;
    private ?Config $config;
    private ?DeployRunner $deployRunner;
    private ?FileFilter $fileFilter;
    private ?FtpClient $ftpClient;
    private ?Client $httpClient;
    private ?HelperRunner $helperRunner;
    private ?LocalScanner $localScanner;
    private ?RemoteScannerBuilder $remoteScannerBuilder;
    private ?RemoteScanRunner $remoteScanRunner;

    public function setConfigPath(string $configPath) : void
    {
        $this->configPath = $configPath;
    }

    public function getApplication() : Application
    {
        if (!isset($this->application)) {
            $this->application = new Application('ftp-deploy', '1.0');
            $this->application->add(new DeployCommand($this->getDeployRunner()));
            $this->application->setDefaultCommand('ftp-deploy', true);
            $this->application->setCatchExceptions(true);
        }

        return $this->application;
    }

    public function getArchiveBuilder() : ArchiveBuilder
    {
        return $this->archiveBuilder ??= new ArchiveBuilder($this->getConfig()->remoteRootRelativeToPublicDir);
    }

    public function getComparator() : Comparator
    {
        return $this->comparator ??= new Comparator($this->getLocalScanner());
    }

    public function getConfigLoader() : ConfigLoader
    {
        return $this->configLoader ??= new ConfigLoader();
    }

    public function getConfig() : Config
    {
        return $this->config ??= $this->getConfigLoader()->load($this->configPath);
    }

    public function getDeployRunner() : DeployRunner
    {
        return $this->deployRunner ??= new DeployRunner(
            $this->getRemoteScanRunner(),
            $this->getComparator(),
            $this->getArchiveBuilder(),
            $this->getHelperRunner(),
        );
    }

    public function getFileFilter() : FileFilter
    {
        return $this->fileFilter ??= new FileFilter($this->getConfig()->files);
    }

    public function getFtpClient() : FtpClient
    {
        if (!isset($this->ftpClient)) {
            $config = $this->getConfig();

            $this->ftpClient = new FtpClient(
                $config->host,
                $config->port,
                $config->user,
                $config->password,
            );
        }

        return $this->ftpClient;
    }

    public function getHelperRunner() : HelperRunner
    {
        return $this->helperRunner ??= new HelperRunner(
            $this->getHttpClient(),
            $this->getFtpClient(),
            $this->getConfig(),
        );
    }

    public function getHttpClient() : Client
    {
        return $this->httpClient ??= new Client();
    }

    public function getLocalScanner(): LocalScanner
    {
        return $this->localScanner ??= new LocalScanner($this->getConfig()->localRoot, $this->getFileFilter());
    }

    public function getRemoteScannerBuilder() : RemoteScannerBuilder
    {
        return $this->remoteScannerBuilder ??= new RemoteScannerBuilder(
            $this->getConfig()->remoteRootRelativeToPublicDir,
            $this->getFileFilter(),
        );
    }

    public function getRemoteScanRunner() : RemoteScanRunner
    {
        return $this->remoteScanRunner ??= new RemoteScanRunner(
            $this->getRemoteScannerBuilder(),
            $this->getHelperRunner(),
        );
    }
}
