<?php

declare(strict_types=1);

namespace App\Helper;

use Generator;


class Comparator
{
    private LocalScanner $localScanner;

    public function __construct(LocalScanner $localScanner)
    {
        $this->localScanner = $localScanner;
    }

    /** @return Generator<string, array> */
    public function compareWithLocal(array $remoteFiles, string $remoteRoot) : Generator
    {
        $localRoot = $this->localScanner->getRootDir();

        foreach ($this->localScanner->scan() as $path => $localInfo) {
            $remoteInfo = $remoteFiles[$path] ?? null;

            if ($remoteInfo) {
                if ($remoteInfo['type'] !== $localInfo['type']) {
                    yield $path => $this->error('local file is a %s, but remote file is a %s', $localInfo['type'], $remoteInfo['type']);
                } else if ($localInfo['type'] === 'dir') {
                    if ($remoteInfo['mode'] !== $localInfo['mode']) {
                        yield $path => $this->chmod($localInfo['mode']);
                    }
                } else if ($localInfo['type'] === 'link') {
                    if ($symlink = $this->symlink($localInfo['target'], $remoteInfo['target'], $localRoot, $remoteRoot)) {
                        yield $path => $symlink;
                    }
                } else if ($localInfo['type'] === 'file') {
                    if ($remoteInfo['hash'] !== $localInfo['hash']) {
                        yield $path => $this->upload($localInfo);
                    } else if ($remoteInfo['mode'] !== $localInfo['mode']) {
                        yield $path => $this->chmod($localInfo['mode']);
                    }
                } else {
                    yield $path => $this->error('unknown file type, please add to ignore list');
                }

                unset($remoteFiles[$path]);
            } else {
                if ($localInfo['type'] === 'dir') {
                    yield $path => ['action' => 'mkdir', 'mode' => $localInfo['mode']];
                } else if ($localInfo['type'] === 'link') {
                    yield $path => $this->symlink($localInfo['target'], null, $localRoot, $remoteRoot);
                } else if ($localInfo['type'] === 'file') {
                    yield $path => $this->upload($localInfo);
                } else {
                    yield $path => $this->error('unknown local file type, please add to ignore list');
                }
            }
        }

        foreach ($remoteFiles as $path => $info) {
            if ($info['type'] === 'dir') {
                yield $path => ['action' => 'rmdir'];
            } else if ($info['type'] === 'link' || $info['type'] === 'file') {
                yield $path => ['action' => 'unlink'];
            } else {
                yield $path => $this->error('unknown remote file type, please add to ignore list');
            }
        }
    }

    private function error(string $message, ...$args) : array
    {
        return [
            'action' => 'error',
            'message' => $args ? vsprintf($message, $args) : $message,
        ];
    }

    private function chmod(int $mode) : array
    {
        return [
            'action' => 'chmod',
            'mode' => $mode,
        ];
    }

    private function symlink(string $localTarget, string | null $remoteTarget, string $localRoot, string $remoteRoot) : array | null
    {
        if (Path::isAbsolute($localTarget)) {
            $localTarget = Path::normalize($localTarget);

            if (Path::isSubpathOf($localTarget, $localRoot)) {
                $target = sprintf('%s%s', $remoteRoot, substr($localTarget, strlen($localRoot)));

                return $target === $remoteTarget ? null : [
                    'action' => 'symlink',
                    'target' => $target,
                ];
            } else {
                return $this->error('symlink target outside root directory');
            }
        } else {
            return $localTarget === $remoteTarget ? null : [
                'action' => 'symlink',
                'target' => $localTarget,
            ];
        }
    }

    private function upload(array $info) : array
    {
        return [
            'action' => 'upload',
            'mode' => $info['mode'],
            'hash' => $info['hash'],
            'path' => $info['path'],
        ];
    }
}
