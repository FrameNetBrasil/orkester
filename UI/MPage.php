<?php

namespace Orkester\UI;

use Orkester\Manager;

class MPage
{
    private string $templatePath;
    private string $templateName;
    private string $content;
    private MBlade $template;

    public function __construct()
    {
        $this->templatePath = Manager::getConf('template.path');
        $this->templateName = Manager::getConf('template.index');
        $path = Manager::getBasePath("public/{$this->templatePath}");
        $this->template = new MBlade([$path]);
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function fetch($templateName = '')
    {
        $args['page'] = $this;
        return ($templateName == '') ? $this->template->fetch($this->templateName, $args) : $this->template->fetch($templateName, $args);
    }

    public function renderContent()
    {
        return $this->content;
    }

    public function renderView(string $view, bool $fragment = false)
    {
        $viewPath = Manager::getAppPath("UI/Views/{$view}");
        $path = dirname($viewPath);
        $view = basename($viewPath);
        $template = new MBlade([$path]);
        $this->content = $template->fetch($view);
        return $this->content;
    }

    public function renderInertia(object $inertia)
    {
        $page = htmlspecialchars(
            json_encode($inertia),
            ENT_QUOTES,
            'UTF-8',
            true
        );
        $this->content = "<div id=\"app\" class=\"fit\" data-page=\"{$page}\"></div>";
        $args = [
            'component' => $inertia->component
        ];
        return $this->fetch('', $args);
    }

}
