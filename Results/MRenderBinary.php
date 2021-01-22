<?php
namespace Orkester\Results;

use Orkester\Services\Http\MRequest;
use Orkester\Services\Http\MResponse;

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

    public function apply(MRequest $request, MResponse $response)
    {

    }

}

