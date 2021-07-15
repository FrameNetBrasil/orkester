<?php

namespace Orkester\UI;

use Atk4\Ui\App;
use Atk4\Ui\JsExpression;
use Orkester\Manager;

class MApp extends App
{
    protected $url_building_ext = '';
    public $db = null;

    public function __construct($defaults = [])
    {
        parent::__construct([
            'call_exit' => (bool)($_GET['APP_CALL_EXIT'] ?? true),
            'catch_exceptions' => (bool)($_GET['APP_CATCH_EXCEPTIONS'] ?? true),
            'always_run' => false,//(bool) ($_GET['APP_ALWAYS_RUN'] ?? true),
        ]);
        $this->db = Manager::getPersistence('lutma');

        $this->title = Manager::getOptions('pageTitle');


        if ($this->call_exit !== true) {
            $this->stickyGet('APP_CALL_EXIT');
        }

        if ($this->catch_exceptions !== true) {
            $this->stickyGet('APP_CATCH_EXCEPTIONS');
        }
    }

    public function layout(string $className, string $templateFile = '') {
        $this->initLayout([$className]);
        if ($templateFile != '') {
            $fileTemplate = Manager::getHome() . "/app/UI/templates/{$templateFile}.html";
            $this->layout->template->loadFromFile($fileTemplate);
        }
    }

    public function getOutput(): string
    {
        ob_start();
        $this->run();
        return ob_get_clean();
    }

}