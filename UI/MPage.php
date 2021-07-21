<?php

namespace Orkester\UI;

use Orkester\Manager;
use Orkester\UI\Components\MBase;
use Ramsey\Uuid\Uuid;

class MPage
{

    private MTemplate $template;
    private string $templateName;
    private string|object $content;
    private string $id;
    private MScripts $scripts;
    private string $styleSheetCode;

    public function __construct()
    {
        $id = 'page-' . Uuid::uuid4();
        $this->id = $id;
        $this->scripts = new MScripts($id);
        $templateName = mrequest('__TEMPLATE');
        if ($templateName == '') {
            if (Manager::isAjaxCall()) {
                $templateName = 'content';
            } else {
                $templateName = (Manager::getConf('theme.template') ?: 'index');
            }
        }
        $this->setTemplateName($templateName);
        $this->setTemplate();
        $this->styleSheetCode = '';
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setTemplate(): void
    {
        $paths = [];
        $this->template = new MTemplate($paths);
        $this->template->context('page', $this);
        $this->template->context('charset', Manager::getOptions('charset'));
        //$this->template->context('layout', $this->layout);
        $this->template->context('template', $this->template);
    }

    public function getTemplate(): MTemplate
    {
        return $this->template;
    }

    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    public function setTemplateName(string $name): void
    {
        $this->templateName = $name;
    }

    public function getStyleSheetCode(): string
    {
        return $this->styleSheetCode;
    }

    public function getScripts(): MScripts
    {
        return $this->scripts;
    }

    public function onSubmit(string $idForm, string $jsCode): void
    {
        $this->scripts->addOnSubmit($idForm, $jsCode);
    }

    public function onLoad(string $jsCode): void
    {
        $this->scripts->addOnLoad($jsCode);
    }

    public function addJsCode(string $jsCode): void
    {
        $this->scripts->addJsCode($jsCode);
    }

    public function addJsFile(string $fileName): void
    {
        $jsCode = file_get_contents($fileName);
        $this->scripts->addJsCode($jsCode);
    }

    /**
     * Generate methods
     */
    public function fetch(string $template = '', array $params = []): string
    {
        $template = $template ?: $this->getTemplateName();
        mdump('mpage::fetch - template =  ' . $template);
        $html = ($template != '') ? $this->template->fetch($template, $params) : '';
        return $html;
    }

    public function generate(string $element = 'content'): string
    {
        mtrace('mpage::generate');
        return $this->fetch($element);
    }

    public function render(string $template = ''): string
    {
        $html = $this->fetch($template);
        return $html;
    }

    /**
     * Content
     */

    public function getControl(): MBase
    {
        $this->content = Manager::getContainer()->get('MPageControl');
        return $this->content;
    }

    public function getContent(): string|object
    {
        return $this->content;
    }

    public function setContent(string|object $content): void
    {
        $this->content = $content;
    }

    public function clearContent(): void
    {
        $this->content = '';
    }

    public function addContent(string $content): void
    {
        $this->content .= $content;
    }

    public function generateContent(): string
    {
        return is_string($this->content) ? $this->content : $this->content->generate();
    }

}