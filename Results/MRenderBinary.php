<?php
namespace Orkester\Results;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * MRenderBinary.
 * Retorna dados para download de arquivo. Tem tratamento específico na classe MResponse.
 */
class MRenderBinary extends MResult
{

    protected $stream;
    protected string $inline;
    protected string $fileName;
    protected string $filePath;

    public function __construct($stream, bool $inline = true, string $fileName = '', string $filePath = '')
    {
        parent::__construct();
        $this->stream = $stream;
        $this->inline = $inline;
        $this->fileName = $fileName ?: basename($filePath);
        $this->filePath = $filePath;
    }

    public function getStream()
    {
        return $this->stream;
    }

    public function getInline(): bool
    {
        return $this->inline;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function apply(Request $request, Response $response): Response
    {
        $filePath = $this->getFilePath();
        if ($filePath != '') {
            if (file_exists($filePath)) {
                $fileName = $this->getFileName() ?: 'download';
                header('Expires: 0');
                header('Pragma: public');
                header("Content-Type: application/save");
                header("Content-Length: " . filesize($filePath));
                if ($this->getInline()) {
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
            $fileName = $this->getFileName() ?: 'download';
            $stream = $this->getStream();
            if ($fileName != 'raw') {
                header('Expires: 0');
                header('Pragma: public');
                header("Content-Type: application/save");
                header("Content-Length: " . strlen($stream));
                if ($this->getInline()) {
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


}

