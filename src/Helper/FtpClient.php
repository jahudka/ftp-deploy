<?php

declare(strict_types=1);

namespace App\Helper;


class FtpClient
{
    private string $host;
    private int $port;
    private string $user;
    private string $password;

    public function __construct(string $host, ?int $port, string $user, string $password)
    {
        $this->host = $host;
        $this->port = $port ?? 21;
        $this->user = $user;
        $this->password = $password;
    }

    public function upload(string $localPath, string $remotePath): void
    {
        $curl = curl_init();
        $fp = fopen($localPath, 'rb');

        curl_setopt_array($curl, [
            CURLOPT_URL => sprintf('ftp://%s:%s/%s', $this->host, $this->port, $remotePath),
            CURLOPT_USE_SSL => true,
            CURLOPT_USERNAME => $this->user,
            CURLOPT_PASSWORD => $this->password,
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $fp,
            CURLOPT_INFILESIZE => filesize($localPath),
            CURLOPT_FTP_CREATE_MISSING_DIRS => true,
            // todo:
            //CURLOPT_NOPROGRESS => false,
            //CURLOPT_PROGRESSFUNCTION => ...
        ]);

        curl_exec($curl);
        fclose($fp);

        try {
            if (($code = curl_errno($curl)) !== CURLE_OK) {
                $message = curl_error($curl);
                throw new UploadException($message, $code);
            }
        } finally {
            curl_close($curl);
        }
    }
}
