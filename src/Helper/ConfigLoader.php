<?php

declare(strict_types=1);

namespace App\Helper;

use Symfony\Component\Yaml\Yaml;
use RuntimeException;


class ConfigLoader
{
    private const fileNames = [
        '.ftpdeployrc',
        '.ftp-deploy.yml',
        '.ftp-deploy.yaml',
        'ftp-deploy.yml',
        'ftp-deploy.yaml',
    ];

    private const envMap = [
        'DEPLOY_HOST' => 'host',
        'DEPLOY_PORT' => 'port',
        'DEPLOY_USER' => 'user',
        'DEPLOY_PASSWORD' => 'password',
        'DEPLOY_LOCAL_ROOT' => 'localRoot',
        'DEPLOY_REMOTE_ROOT' => 'remoteRoot',
        'DEPLOY_PUBLIC_DIR' => 'publicDir',
        'DEPLOY_REMOTE_ROOT_RELATIVE_TO_PUBLIC_DIR' => 'remoteRootRelativeToPublicDir',
        'DEPLOY_BASE_URL' => 'baseUrl',
    ];

    private const configMap = [
        'host' => 'string',
        'port' => 'int?',
        'user' => 'string',
        'password' => 'string',
        'localRoot' => 'string',
        'remoteRoot' => 'string?',
        'baseUrl' => 'string',
        'publicDir' => 'string?',
        'remoteRootRelativeToPublicDir' => 'string?',
        'files' => 'string[]?',
    ];

    public function load(string | null $file = null): Config
    {
        $config = $this->loadFromEnvironment();

        if ($file = $this->resolveConfigFile($file)) {
            $config += $this->loadFromFile($file);
        }

        return new Config(...$this->validate($config));
    }

    private function loadFromEnvironment(): array
    {
        $config = [];

        foreach (self::envMap as $name => $key) {
            if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
                $config[$key] = $_ENV[$name];
            }
        }

        if (isset($config['localRoot'])) {
            $config['localRoot'] = Path::resolve($config['localRoot']);
        }

        return $config;
    }

    private function resolveConfigFile(string | null $file = null) : string | null
    {
        if ($file) {
            return Path::resolve($file);
        }

        $dir = Path::resolve();

        do {
            foreach (self::fileNames as $candidate) {
                $file = sprintf('%s/%s', $dir, $candidate);

                if (is_file($file)) {
                    return $file;
                }
            }
        } while (!is_file(sprintf('%s/composer.json', $dir)) && ($dir = dirname($dir)) !== '/');

        return null;
    }

    private function loadFromFile($path): array
    {
        $config = Yaml::parseFile($path);

        if (isset($config['localRoot'])) {
            $config['localRoot'] = Path::resolve(dirname($path), $config['localRoot']);
        }

        return $config;
    }

    private function validate(array $config): array
    {
        foreach (self::configMap as $key => $type) {
            if (preg_match('~^([a-z]+)(\[])?(\?)?$~', $type, $m)) {
                $type = $m[1];
                $array = !empty($m[2]);
                $optional = !empty($m[3]);
            } else {
                $array = false;
                $optional = false;
            }

            if (!isset($config[$key]) || $config[$key] === '') {
                if ($optional) {
                    $config[$key] = $array ? [] : null;
                    continue;
                } else {
                    throw new ConfigException(sprintf('Missing required option "%s"', $key));
                }
            }

            if ($array) {
                if (!is_array($config[$key])) {
                    $config[$key] = (array) $config[$key];
                }

                $config[$key] = array_map(fn($v) => $this->validateType($key, $v, $type), $config[$key]);
            } else {
                $config[$key] = $this->validateType($key, $config[$key], $type);
            }
        }

        $config['remoteRoot'] = sprintf('/%s', trim($config['remoteRoot'] ?? '', '/'));
        $config['publicDir'] = Path::resolve($config['remoteRoot'], $config['publicDir'] ?? '.');
        $config['remoteRootRelativeToPublicDir'] = isset($config['remoteRootRelativeToPublicDir'])
            ? trim($config['remoteRootRelativeToPublicDir'], '/')
            : Path::relative($config['publicDir'], $config['remoteRoot']);
        $config['baseUrl'] = rtrim($config['baseUrl'], '/');
        return $config;
    }

    private function validateType(string $key, mixed $value, string $type): mixed
    {
        switch ($type) {
            case 'string': return (string) $value;
            case 'int':
                if (is_numeric($value)) {
                    return (int) $value;
                } else {
                    throw new ConfigException(sprintf('Config option "%s" must be an int', $key));
                }
            default:
                throw new RuntimeException(sprintf('Unknown type: "%s"', $type));
        }
    }
}
