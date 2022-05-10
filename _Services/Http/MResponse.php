<?php
namespace Orkester\Services\Http;

use Orkester\Manager;
use Orkester\Results\MResult;
use Orkester\Results\MRenderBinary;
use Ds\Map;

class MResponse
{

    private array $mimeType = [
        'ai' => 'application/postscript', 'aif' => 'audio/x-aiff',
        'aifc' => 'audio/x-aiff', 'aiff' => 'audio/x-aiff',
        'asf' => 'video/x-ms-asf', 'asr' => 'video/x-ms-asf',
        'asx' => 'video/x-ms-asf', 'au' => 'audio/basic',
        'avi' => 'video/x-msvideo', 'bin' => 'application/octet-stream',
        'bmp' => 'image/bmp', 'css' => 'text/css',
        'doc' => 'application/msword', 'gif' => 'image/gif',
        'gz' => 'application/x-gzip', 'hlp' => ' application/winhlp',
        'htm' => 'text/html', 'html' => 'text/html',
        'ico' => 'image/x-icon', 'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'jpg' => 'image/jpeg',
        'js' => 'application/x-javascript', 'lzh' => 'application/octet-stream',
        'mid' => 'audio/mid', 'mov' => 'video/quicktime',
        'mp3' => 'audio/mpeg', 'mpa' => 'video/mpeg',
        'mpe' => 'video/mpeg', 'mpeg' => 'video/mpeg',
        'mpg' => 'video/mpeg', 'pdf' => 'application/pdf',
        'png' => 'image/png', 'pps' => 'application/vnd.ms-powerpoint',
        'ppt' => 'application/vnd.ms-powerpoint', 'ps' => 'application/postscript',
        'qt' => 'video/quicktime', 'ra' => 'audio/x-pn-realaudio',
        'ram' => 'audio/x-pn-realaudio', 'rtf' => 'application/rtf',
        'snd' => 'audio/basic', 'tgz' => 'application/x-compressed',
        'tif' => 'image/tiff', 'tiff' => 'image/tiff',
        'txt' => 'text/plain', 'wav' => 'audio/x-wav',
        'xbm' => 'image/x-xbitmap', 'xpm' => 'image/x-xpixmap',
        'z' => 'application/x-compress', 'zip' => 'application/zip',
        'json' => 'application/json'
    ];
    private string $contentLength;
    private string $contentDisposition;
    private string $contentTransferEncoding;
    private string $fileName;
    private string $fileNameDownload;
    private string $baseName;
    private bool $alreadyFlushed = false;

    /**
     * Response status code
     */
    private int $status = 200;

    /**
     * Response content type
     */
    private string $contentType;

    /**
     * Response headers
     */
    private Map $headers;

    /**
     * Response cookies
     */
    private Map $cookies;

    /**
     * Response body stream
     */
    private $out;

    /**
     * Send this file directly
     */
    private bool $direct;


    public function __construct()
    {
        $this->contentType = "";
        $this->contentLength = "";
        $this->contentDisposition = "";
        $this->contentTransferEncoding = "";
        $this->fileName = "";
        $this->fileNameDown = "";
        $this->headers = new Map();
        $this->cookies = new Map();
    }

    /**
     * Get a response header
     * @param name Header name case-insensitive
     * @return the header value as a String
     */
    public function getHeader($name): string
    {
        return $this->headers->get($name);
    }

    /**
     * Set a response header
     * @param name Header name
     * @param value Header value
     */
    public function setHeader(string $name, string $value)
    {
        $this->headers->put($name, $value);
    }

    public function setContentTypeIfNotSet(string $contentType)
    {
        if ($this->contentType == '') {
            $this->contentType = $contentType;
        }
    }

    public function setOut(string $content)
    {
        $this->out = $content;
    }

    public function getOut(): string
    {
        return $this->out;
    }

    public function setStatus(string $value)
    {
        $this->status = $value;
    }

    public function getStatus(): string
    {
        return $this->value;
    }

    /**
     * Set a new cookie that will expire in (current) + duration
     * @param name
     * @param value
     * @param duration Ex: 3d
     */
    public function setCookie(string $name, string $value, int $expire = 0, string $path = '', string $domain = '', bool $secure = false, bool $httpOnly = false)
    {
        setcookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
    }

    private function down()
    {
        $this->contentType = "application/save";
        $this->contentLength = "";
        $this->contentDisposition = "";
        $this->contentTransferEncoding = "";
        $this->fileName = "";
        $this->fileNameDown = "";
    }

    public function setContentType(string $value)
    {
        $this->contentType = $value;
    }

    public function setContentLength(int $value = 0)
    {
        $this->contentLength = $value;
    }

    public function setContentDisposition(string $value)
    {
        $this->contentDisposition = $value;
    }

    public function setContentTransferEncoding(string $value)
    {
        $this->contentTransferEncoding = $value;
    }

    public function getMimeType($fileName): string
    {
        $path_parts = pathinfo($fileName);
        $mime = $this->mimeType[$path_parts['extension']];
        $type = $mime ? $mime : "application/octet-stream";
        return $type;
    }

    /*
      Send methods.
     */

    /**
     * Send response to browser.
     * Analyse $result object and decide the method of response.
     * $return indicates if response is sent to browser ou returned to caller.
     *
     * @param object $result
     * @param boolean $return
     * @return string
     */
    public function sendResponse(MResult $result): string|null
    {
        if ($this->alreadyFlushed) {
            return '';
        }
        if ($result == null) {
            return '';
        }
        $request = Manager::getRequest();
        $response = $this;
        if ($result instanceof MRenderBinary) {
            $this->sendStream($result);
        } else {
            //mdump('%%% ' . get_class($result));
            $result->apply($request, $response);
            foreach ($this->headers->values() as $header) {
                header($header);
            }
            $this->setResponseCode();
            echo $this->out;
            return $this->out;
        }
    }

    private function setResponseCode()
    {
        /* Em algumas situações, como falha de autenticação e erro interno ,
         * o código 200 não representa a situação real.  */
        if (http_response_code() == MStatusCode::OK) {
            http_response_code($this->status);
        }
    }

    public function sendStream(MRenderBinary $result)
    {
        $filePath = $result->getFilePath();
        if ($filePath != '') {
            if (file_exists($filePath)) {
                $fileName = $result->getFileName() ?: $this->baseName;
                $this->setContentLength();
                header('Expires: 0');
                header('Pragma: public');
                header("Content-Type: " . $this->contentType);
                header("Content-Length: " . filesize($filePath));
                if ($result->getInline()) {
                    header("Content-Disposition: inline; filename=" . $fileName);
                } else {
                    header("Content-Disposition: attachment; filename=" . $fileName);
                }
                header("Cache-Control: cache"); // HTTP/1.1
                header("Content-Transfer-Encoding: binary");

                $fp = fopen($filePath, "r");
                fpassthru($fp);
                fclose($fp);
            }
        } else {
            $fileName = $result->getFileName() ?: 'download';
            $stream = $result->getStream();
            if ($fileName != 'raw') {
                $this->contentLength = strlen($stream);
                header('Expires: 0');
                header('Pragma: public');
                header("Content-Type: " . $this->contentType);
                header("Content-Length: " . $this->contentLength);
                if ($result->getInline()) {
                    header("Content-Disposition: inline; filename=" . $fileName);
                } else {
                    header("Content-Disposition: attachment; filename=" . $fileName);
                }
                header("Cache-Control: cache"); // HTTP/1.1
                header("Content-Transfer-Encoding: binary");
            }
            echo $stream;
        }
        exit;
    }

    public function prepareFlush()
    {
        $this->alreadyFlushed = true;
        header("Cache-Control: no-cache");
        for ($i = 0; $i < ob_get_level(); $i++) {
            ob_end_flush();
        }
        ob_implicit_flush(1);
        ob_start();
        echo str_repeat(" ", 1024), "\n";
    }

    public function sendFlush($output)
    {
        echo $output;
        ob_end_flush();
        ob_flush();
        flush();
    }

}
