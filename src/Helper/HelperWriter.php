<?php

declare(strict_types=1);

namespace App\Helper;


class HelperWriter
{
    private $fp;

    public function __construct(string $path)
    {
        $this->fp = fopen($path, 'wb');
    }

    public function writeFile(string $path) : void
    {
        $fp = fopen($path, 'rb');
        stream_copy_to_stream($fp, $this->fp);
        fclose($fp);
    }

    public function writeln(string $src = '', ...$args) : void
    {
        if ($args) {
            $src = vsprintf($src, array_map(fn($v) => var_export($v, true), $args));
        }

        fwrite($this->fp, $src . "\n");
    }

    public function close() : void
    {
        fclose($this->fp);
    }
}
