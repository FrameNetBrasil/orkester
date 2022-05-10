<?php
namespace Orkester\Services\Http;

use Orkester\Manager;

/**
 * MAjax.
 * Tratamento das respostas às requisições Ajax. Define um objeto base (que pode
 * ser composto por outros objetos) e gera a resposta (Texto, XML ou JSON) a partir
 * deste objeto.
 */
class MAjax
{

    /**
     * Versão do XML.
     * @var string
     */
    private string $version = '1.0';

    /**
     * Tipo da resposta (TXT, HTML, JSON, OBJECT, E4X, XML).
     * @var string
     */
    private string $responseType;

    /**
     * Array com objetos internos.
     * @var array
     */
    private array $composites = [];

    private $data;

    /**
     * Define a codificação de caracteres usada na geração da resposta.
     * @var string
     */
    private string $inputEncoding;

    public function __construct($inputEncoding = 'UTF-8')
    {
        $this->data = '';
        $this->setEncoding($inputEncoding);
        $this->setResponseType(Manager::getRequest()->getResultFormat() ?: 'TXT');
    }

    public function initialize($inputEncoding = 'UTF-8')
    {
        $this->setEncoding($inputEncoding);
    }

    public function setData($data) {
        $this->data = $data;
    }

    public function getData() {
        return $this->data;
    }

    /**
     * Retorna a resposta formatada de acordo com o tipo definido em $responseType.
     * @return mixed
     */
    public function returnData()
    {
        $charset = MAjaxTransformer::findOutputCharset($this->getEncoding());
        switch ($this->responseType) {
            case 'TXT':
            case 'HTML':
                header('Content-type: text/plain; charset=' . $charset);
                $data = MAjaxTransformer::toString($this);
                return $data;
                break;

            case 'JSON':
            case 'OBJECT':
                $data = MAjaxTransformer::toJSON($this);
                $header = 'Content-type: application/json; ';
                if (Manager::getContext()->isFileUpload()) {
                    $newdata = "{\"base64\":\"" . base64_encode($data) . "\"}";
                    $data = "<html><body><textarea>$newdata</textarea></body></html>";
                    $header = 'Content-type: text/html; ';
                }
                header($header . 'charset=' . $charset);
                return $data;
                break;

            case 'E4X':
            case 'XML':
                header('Content-type:  text/xml; charset=' . $charset);
                $data = '<?xml version="1.0" encoding="' . $charset . '"?>'
                    . MAjaxTransformer::toXML($this);
                return $data;
                break;

            default:
                return 'ERROR: invalid response type \'' . $this->responseType . '\'';
        }
    }

    /**
     * Retorna a resposta JSON, quando os dados em $this->base->data já foram definidos neste formato.
     * @return string
     */
    public function returnJSON()
    {
        $data = $this->getData();
        $header = 'Content-type: application/json; ';
        header($header . 'charset=UTF-8');
        return $data;
    }

    public function setEncoding($encoding)
    {
        $this->inputEncoding = strtoupper((string)$encoding);
    }

    public function getEncoding()
    {
        return $this->inputEncoding;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function addNode($nodeName, $id = '')
    {
        $composites = count($this->composites);
        $this->composites[$composites] = new MAjax($this->inputEncoding);
        $this->composites[$composites]->setName($nodename);
        $this->composites[$composites]->setAttribute('id', $id);
    }

    public function getResponseType()
    {
        return $this->responseType;
    }

    public function setResponseType($value)
    {
        if (isset($value)) {
            $this->responseType = htmlentities(strip_tags(strtoupper((string)$value)));
        }
    }

    public function isEmpty()
    {
        return (count($this->composites) == 0) && ($this->data == '');
    }

}

