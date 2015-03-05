<?php

/**
 * Class Application_Common_FileIO contains helper functions for reading and writing files, and sending them over HTTP.
 */
class Application_Common_FileIO
{
    /**
     * Reads the requested portion of a file and sends its contents to the client with the appropriate headers.
     *
     * This HTTP_RANGE compatible read file function is necessary for allowing streaming media to be skipped around in.
     *
     * @param string $filePath - the full filepath pointing to the location of the file
     * @param string $mimeType - the file's mime type. Defaults to 'audio/mp3'
     * @param integer $size - the file size, in bytes
     * @return void
     *
     * @link https://groups.google.com/d/msg/jplayer/nSM2UmnSKKA/Hu76jDZS4xcJ
     * @link http://php.net/manual/en/function.readfile.php#86244
     */
    public static function smartReadFile($filePath, $size, $mimeType)
    {
        $fm = @fopen($filePath, 'rb');
        if (!$fm) {
            header ("HTTP/1.1 505 Internal server error");
            return;
        }

        //Note that $size is allowed to be zero. If that's the case, it means we don't
        //know the filesize, and we just won't send the Content-Length header.
        if ($size < 0) {
            throw new Exception("Invalid file size returned for file at $filePath");
        }


        $begin = 0;
        $end   = $size - 1;

        if (isset($_SERVER['HTTP_RANGE'])) {
            if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches)) {
                $begin = intval($matches[1]);
                if (!empty($matches[2])) {
                    $end = intval($matches[2]);
                }
            }
        }

        if (isset($_SERVER['HTTP_RANGE'])) {
            header('HTTP/1.1 206 Partial Content');
        } else {
            header('HTTP/1.1 200 OK');
        }
        header("Content-Type: $mimeType");
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Accept-Ranges: bytes');
        if ($size > 0) {
            header('Content-Length:' . (($end - $begin) + 1));
            if (isset($_SERVER['HTTP_RANGE'])) {
                header("Content-Range: bytes $begin-$end/$size");
            }
        }
        header("Content-Transfer-Encoding: binary");

        //We can have multiple levels of output buffering. Need to
        //keep looping until all have been disabled!!!
        //http://www.php.net/manual/en/function.ob-end-flush.php
        while (@ob_end_flush());

        // NOTE: We can't use fseek here because it does not work with streams
        // (a.k.a. Files stored in the cloud)
        while(!feof($fm) && (connection_status() == 0)) {
            echo fread($fm, 1024 * 8);
        }
        fclose($fm);
    }
}