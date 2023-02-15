<?php

declare(strict_types=1);

namespace App\Helper;


class Archive
{
    private string $rootDir;
    private $fp;
    private int $ts;
    private array $revertJobs = [];
    private array $cleanupJobs = [];

    public function __construct(string $rootDir, string $key)
    {
        if (!isset($_POST['key']) || $_POST['key'] !== $key) {
            unlink(__FILE__);
            echo "[FAIL] invalid key\n";
            exit;
        }

        $this->rootDir = rtrim($rootDir, '/');
        $this->fp = fopen(__FILE__, 'rb');
        $this->ts = time();

        while (($line = fgets($this->fp)) !== false) {
            if (str_starts_with($line, '__halt_compiler();')) {
                break;
            }
        }

        ignore_user_abort(true);
        set_time_limit(0);
    }

    public function mkdir(string $path, int $mode) : void
    {
        if (@mkdir($this->rootDir . $path, $mode)) {
            $this->revertJobs[$path] = fn() => rmdir($this->rootDir . $path);
            $this->ack('mkdir', $path);
        } else if (!is_dir($this->rootDir . $path)) {
            $this->revert('mkdir', $path);
        }
    }

    public function chmod(string $path, int $mode) : void
    {
        $orig = fileperms($this->rootDir . $path) & 0777;

        if (@chmod($this->rootDir . $path, $mode)) {
            $this->revertJobs[$path] = fn() => chmod($this->rootDir . $path, $orig);
            $this->ack('chmod', $path);
        } else {
            $this->revert('chmod', $path, 'failed to set permissions');
        }
    }

    public function symlink(string $path, string $target) : void
    {
        $backup = sprintf('%s.%d.bak', $path, $this->ts);
        $new = sprintf('%s.%d.new', $path, $this->ts);

        if (file_exists($this->rootDir . $path)) {
            if (is_link($this->rootDir . $path)) {
                if (@symlink(readlink($this->rootDir . $path), $this->rootDir . $backup)) {
                    $this->revertJobs[$backup] = fn() => unlink($this->rootDir . $backup);
                } else {
                    $this->revert('symlink', $path, 'failed to create backup file');
                }
            } else {
                $this->revert('symlink', $path, 'file exists and not a link');
            }
        }

        if (!@symlink($target, $this->rootDir . $new)) {
            $this->revert('symlink', $path, 'failed to create new symlink');
        }

        $this->revertJobs[$path] = fn() => unlink($this->rootDir . $new);
        $this->ack('symlink', $path);
    }

    public function extract(string $path, int $mode, int $size, string $hash) : void
    {
        $backup = sprintf('%s.%d.bak', $path, $this->ts);
        $new = sprintf('%s.%d.new', $path, $this->ts);

        if (is_file($this->rootDir . $path)) {
            if (
                @copy($this->rootDir . $path, $this->rootDir . $backup)
                && @chmod($this->rootDir . $backup, fileperms($this->rootDir . $path))
            ) {
                $this->revertJobs[$backup] = fn() => unlink($this->rootDir . $backup);
            } else {
                $this->revert('extract', $path, 'failed to create backup file');
            }
        }

        $dst = @fopen($this->rootDir . $new, 'wb');

        if (!$dst) {
            $this->revert('extract', $path, 'failed to open file for writing');
        }

        $this->revertJobs[$path] = fn() => unlink($this->rootDir . $new);

        while ($size > 0) {
            $chunk = min($size, 1024);
            $size -= $chunk;

            if (fwrite($dst, fread($this->fp, $chunk)) === false) {
                fclose($dst);
                $this->revert('extract', $path, 'failed writing file contents');
            }
        }

        fclose($dst);

        if (sha1_file($this->rootDir . $new) !== $hash) {
            $this->revert('extract', $path, 'hash mismatch');
        }

        if (!@chmod($this->rootDir . $new, $mode)) {
            $this->revert('extract', $path, 'failed to set permissions');
        }

        $this->ack('extract', $path);
    }

    public function commit(string $path) : void
    {
        $backup = sprintf('%s.%d.bak', $path, $this->ts);
        $new = sprintf('%s.%d.new', $path, $this->ts);

        if (@rename($this->rootDir . $new, $this->rootDir . $path)) {
            if (isset($this->revertJobs[$backup])) {
                unset($this->revertJobs[$backup]);
                $this->revertJobs[$path] = fn() => rename($this->rootDir . $backup, $this->rootDir . $path);
                $this->cleanupJobs[$backup] = fn() => unlink($this->rootDir . $backup);
            }

            $this->ack('commit', $path);
        } else {
            $this->revert('commit', $path);
        }
    }

    public function unlink(string $path) : void
    {
        if (@unlink($this->rootDir . $path)) {
            $this->ack('unlink', $path);
        } else if (file_exists($this->rootDir . $path)) {
            $this->fail('unlink', $path);
        }
    }

    public function rmdir(string $path) : void
    {
        if (@rmdir($this->rootDir . $path)) {
            $this->ack('rmdir', $path);
        } else if (file_exists($this->rootDir . $path)) {
            $this->fail('rmdir', $path);
        }
    }

    private function ack(string $action, string $path) : void
    {
        printf("[OK] %s '%s'\n", $action, strtr($path, ["'" => "\\'"]));
    }

    private function fail(string $action, string $path, ?string $message = null) : void
    {
        $err = error_get_last();
        $message = $message ? sprintf(': %s', $message) : '';
        $message = $err ? sprintf('%s: %s', $message, $err['message']) : $message;
        printf("[FAIL] %s '%s'%s\n", $action, strtr($path, ["'" => "\\'"]), $message);
    }

    private function revert(string $action, string $path, ?string $message = null) : void
    {
        $this->fail($action, $path, $message);

        foreach (array_reverse($this->revertJobs) as $job) {
            $job();
        }

        printf("[OK] revert\n");
        $this->cleanup(false);
        exit();
    }

    public function cleanup(bool $runJobs = true) : void
    {
        if ($runJobs) {
            foreach ($this->cleanupJobs as $job) {
                $job();
            }
        }

        fclose($this->fp);
        unlink(__FILE__);
        printf("[OK] cleanup\n");
    }
}
