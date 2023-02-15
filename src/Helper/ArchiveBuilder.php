<?php

declare(strict_types=1);

namespace App\Helper;


class ArchiveBuilder
{
    private string $rootDir;
    private array $files = [];

    public function __construct(string $rootDir)
    {
        $this->rootDir = $rootDir;
    }


    public function uploadFile(string $localPath, string $path, string $hash, int $mode = 0640) : void
    {
        $this->add($path, 'upload', ['file' => $localPath, 'hash' => $hash, 'mode' => $mode]);
    }

    public function deleteFile(string $path) : void
    {
        $this->add($path, 'unlink');
    }

    public function changePermissions(string $path, int $mode) : void
    {
        $this->add($path, 'chmod', ['mode' => $mode]);
    }

    public function createSymlink(string $path, string $target) : void
    {
        $this->add($path, 'symlink', ['target' => $target]);
    }

    public function createDirectory(string $path, int $mode = 0750) : void
    {
        $this->add($path, 'mkdir', ['mode' => $mode]);
    }

    public function deleteDirectory(string $path) : void
    {
        $this->add($path, 'rmdir');
    }

    public function isEmpty() : bool
    {
        return empty($this->files);
    }

    private function add(string $path, string $action, array $params = []) : void
    {
        $this->files['/' . trim($path, '/')] = ['action' => $action] + $params;
    }

    public function build(string $archivePath, string $key) : void
    {
        $this->preprocess();

        $archive = new HelperWriter($archivePath);
        $archive->writeFile(__DIR__ . '/Archive.php');
        $archive->writeln();
        $archive->writeln('$archive = new Archive(%s, %s);', $this->rootDir, $key);

        foreach ($this->buildCommands() as $command) {
            $archive->writeln(...$command);
        }

        $archive->writeln('$archive->cleanup();');
        $archive->writeln();
        $archive->writeln('__halt_compiler();');

        foreach ($this->files as $info) {
            if ($info['action'] === 'upload') {
                $archive->writeFile($info['file']);
            }
        }

        $archive->close();
    }

    private function preprocess() : void
    {
        uksort($this->files, static fn($a, $b) => substr_count($a, '/') - substr_count($b, '/'));
    }

    /** @return array[] */
    private function buildCommands() : array
    {
        $extract = [];
        $commit = [];
        $cleanup = [];

        foreach ($this->files as $path => $info) {
            if (in_array($info['action'], ['unlink', 'rmdir'], true)) {
                $cleanup[] = [sprintf('$archive->%s(%%s);', $info['action']), $path];
            } else if ($info['action'] === 'upload') {
                $size = filesize($info['file']);
                $extract[] = ['$archive->extract(%s, %d, %d, %s);', $path, $info['mode'], $size, $info['hash']];
                $commit[] = ['$archive->commit(%s);', $path];
            } else if (in_array($info['action'], ['chmod', 'mkdir'], true)) {
                $extract[] = [sprintf('$archive->%s(%%s, %%d);', $info['action']), $path, $info['mode']];
            } else if ($info['action'] === 'symlink') {
                $extract[] = ['$archive->symlink(%s, %s);', $path, $info['target']];
                $commit[] = ['$archive->commit(%s);', $path];
            }
        }

        return [
            ...$extract,
            ...$commit,
            ...array_reverse($cleanup), // needs to be depth-first
        ];
    }
}
