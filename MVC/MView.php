<?php
namespace Orkester\MVC;

use Orkester\Manager;
use Orkester\UI\MPage;
use Orkester\UI\MTemplate;
use Orkester\Results\{MResult, MResultNull, MRenderPage, MRenderJSON, MRenderJSONText};

class MView
{
    private string $viewFile;
    private string $baseName;

    public function __construct($viewFile = '')
    {
        $this->viewFile = $viewFile;
        $this->baseName = '';
    }

    public function getPath()
    {
        return pathinfo($this->viewFile, PATHINFO_DIRNAME);
    }

    public function getResult(string $httpMethod, string $resultFormat): MResult
    {
        $result = new MResultNull;
        if ($this->viewFile != '') {
            $content = $this->process();
            //mdump('== httpMethod = ' . $httpMethod . '   resultForm = ' . $resultFormat);
            if ($content != '') {
                if ($httpMethod == 'GET') {
                    if ($resultFormat == 'html') {
                        $result = new MRenderPage($content);
                    }
                    if ($resultFormat == 'json') {
                        $result = new MRenderJSONText($content);
                    }
                } else { // post
                    if ($resultFormat == 'html') {
                        $result = new MRenderJSONText($content);
                    }
                    if ($resultFormat == 'json') {
                        $json = json_encode($content);
                        $result = new MRenderJSON($json);
                    }
                }
            }
        }
        return $result;
    }

    public function process()
    {
        mtrace('view file = ' . $this->viewFile);
        $extension = pathinfo($this->viewFile, PATHINFO_EXTENSION);
        $process = 'process' . $extension;
        return $this->$process();;
    }

    protected function processPHP()
    {
        /*
        $page = Manager::getObject(MPage::class);
        $viewName = basename($this->viewFile, '.php');
        include_once $this->viewFile;
        $control = new $viewName();
        $control->setView($this);
        $control->load();
        $container = $page->getControl();
        $container->setView($this);
        $container->addControl($control);
        return (Manager::isAjaxCall() ? $page->generate() : $page->render());
        */
        return $this->processTemplate();
    }

    protected function processXML()
    {
        $page = Manager::getObject(MPage::class);
        $container = $page->getControl();
        $container->setView($this);
        $container->addControlsFromXML($this->viewFile);
        $controls = $container->getControls();
        if (is_array($controls)) {
            foreach ($controls as $control) {
                if ($control instanceof MControl) {
                    $control->load();
                }
            }
        }
        return (Manager::isAjaxCall() ? $page->generate() : $page->render());
    }

    protected function processTemplate()
    {
        $page = Manager::getObject(MPage::class);
        $template = new MTemplate(dirname($this->viewFile));
        //$template->context('manager', Manager::getInstance());
        $template->context('page', $page);
        $template->context('view', $this);
        $template->context('data', Manager::getData());
        $template->context('components', Manager::getAppPath() . "/Components");
        $template->context('appURL', Manager::getAppURL());
        $template->context('template', $template);
        $content = $template->fetch($this->baseName);
        $page->setContent($content);
        return (Manager::isAjaxCall() ? $page->generate() : $page->render());
    }

    protected function processLatte()
    {
        $this->baseName = basename($this->viewFile, '.latte');
        return $this->processTemplate();
    }

    protected function processHTML()
    {
        $this->baseName = basename($this->viewFile, '.html');
        return $this->processTemplate();
    }

    protected function processJS()
    {
        $this->baseName = basename($this->viewFile, '.js');
        return $this->processTemplate();
    }

    protected function processVue()
    {
        $this->baseName = basename($this->viewFile, '.vue');
        /*
        $page = Manager::getObject(MPage::class);
        $template = new MTemplate(dirname($this->viewFile));
        //$template->context('manager', Manager::getInstance());
        $template->context('page', $page);
        $template->context('view', $this);
        $template->context('data', Manager::getData());
        $template->context('components', Manager::getAppPath() . "/Components");
        $template->context('appURL', Manager::getAppURL());
        $template->context('template', $template);
        $content = $template->fetch($this->baseName);
        //$content = str_replace("<template","<script", $content);
        //$content = str_replace("</template","</script", $content);
        //$content = str_replace("script id","script type=\"text/x-template\" id", $content);
        $page->setContent($content);
        return (Manager::isAjaxCall() ? $page->generate() : $page->render());
        */
        return $this->processTemplate();
    }

    public function processPrompt(MPromptData $prompt)//$type, $message = '', $action1 = '', $action2 = '')
    {

        $oPrompt = new MPrompt();
        $oPrompt->setId('prompt' . uniqid());
        $oPrompt->setType($prompt->type);
        $oPrompt->setMessage($prompt->message);
        $oPrompt->setAction1($prompt->action1);
        $oPrompt->setAction2($prompt->action2);

        $page = MPage::getInstance();
        $container = $page->getControl();
        $container->addControl($oPrompt);
        if (Manager::isAjaxCall()) {
            $prompt->setContent($page->generate());
        } else {
            $prompt->setContent($page->render());
        }
        $prompt->setId($oPrompt->getId());

        /*
        if (is_string($type)) {
            $oPrompt = new MPrompt(["type" => $type, "msg" => $message, "action1" => $action1, "action2" => $action2, "event1" => '', "event2" => '']);
        } elseif (is_object($type)) {
            $oPrompt = $type;
        } else {
            throw new ERuntimeException("Invalid parameter for MController::renderPrompt.");
        }
        $page = MPage::getInstance();
        $container = $page->getControl();
        $container->addControl($oPrompt);
        //$container->setInner($content);
        return (Manager::isAjaxCall() ? $page->generate() : $page->render());
        */
    }

}
