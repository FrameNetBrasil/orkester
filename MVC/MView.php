<?php
namespace Orkester\MVC;

use Orkester\Manager;
use Orkester\UI\Components\MBaseComponent;
use Orkester\UI\MLatte;
use Orkester\UI\MPage;
use Orkester\UI\MTemplate;
use Orkester\Results\{MRenderJavascript, MResult, MResultNull, MRenderPage, MRenderJSON, MRenderJSONText};
use Phpfastcache\Helper\Psr16Adapter;

class MView
{
    private string $viewFile;
    private string $baseName;
    private string $resultFormat;
    private Psr16Adapter $vueCache;

    public function __construct($viewFile = '')
    {
        $this->viewFile = $viewFile;
        $this->baseName = '';
        $this->vueCache = Manager::getCache();
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
            $resultFormat = $this->resultFormat ?? $resultFormat;
            mdump('== httpMethod = ' . $httpMethod . '   resultFormat = ' . $resultFormat);
            if ($content != '') {
                if ($httpMethod == 'GET') {
                    if ($resultFormat == 'html') {
                        $result = new MRenderPage($content);
                    }
                    if ($resultFormat == 'json') {
                        $result = new MRenderJSONText($content);
                    }
                    if ($resultFormat == 'javascript') {
                        $result = new MRenderJavascript($content);
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

    public function component(string $component)
    {
        $viewFile = Manager::getAppPath() . '/UI/Components/' . $component;
        mtrace('component view file = ' . $viewFile);
        $template = new MTemplate(dirname($viewFile));
        $template->context('view', $this);
        $template->context('data', Manager::getData());
        $template->context('template', $template);
        return $template->fetch(basename($component, '.blade.php'));
    }

    public function fragment(string $fragment): string
    {
        $dirname = dirname($this->viewFile);
        $filename = "$this->baseName-$fragment";
        $path = $dirname . DIRECTORY_SEPARATOR . $this->baseName . '-' . $fragment;
        mtrace('fragment view file = ' . $path);
        $template = new MTemplate($dirname);
        $template->context('view', $this);
        $template->context('data', Manager::getData());
        $template->context('template', $template);
        return $template->fetch(basename($filename, ".blade.php"));
    }

    protected function processPHP()
    {
        $this->baseName = basename($this->viewFile, '.blade.php');
        return $this->processTemplate();
    }

    protected function processXML()
    {
        $this->baseName = basename($this->viewFile, '.xml');
        $page = Manager::getObject(MPage::class);
        $paths = Manager::getOptions('templatePath');
        $paths[] = dirname($this->viewFile);
        $template = new MTemplate($paths);
        $xml = $template->fetch($this->baseName);
        //mdump($xml);
        $container = $page->getControl();
        $container->setView($this);
        $container->getControlsFromXMLString($xml);
        return (Manager::isAjaxCall() ? $page->generate() : $page->render());
    }

    protected function processTemplate()
    {
        $page = Manager::getObject(MPage::class);
        $template = new MTemplate(dirname($this->viewFile));
        $template->context('view', $this);
        $content = $template->fetch($this->baseName);
        $page->setContent($content);
        return (Manager::isAjaxCall() ? $page->generate() : $page->render());
    }

    protected function processLatte()
    {
        $this->baseName = basename($this->viewFile, '.latte');
        $page = Manager::getObject(MPage::class);
        $template = new MLatte(dirname($this->viewFile));
        $content = $template->fetch($this->baseName);
        $page->setContent($content);
        return (Manager::isAjaxCall() ? $page->generate() : $page->render());
    }

    protected function processHTML()
    {
        $this->baseName = basename($this->viewFile, '.html');
        return $this->processLatte();
    }

    protected function processJS()
    {
        $this->baseName = basename($this->viewFile, '.js');
        $template = new MTemplate(dirname($this->viewFile));
        $template->context('view', $this);
        $content = $template->fetch($this->baseName);
        $this->resultFormat = 'javascript';
        return $content;
    }

    protected function processVue()
    {
        $this->baseName = basename($this->viewFile, '.vue');
        $template = new MTemplate(dirname($this->viewFile));
        $template->context('view', $this);
        $template->context('data', Manager::getData());
        $template->context('template', $template);
        $content = $template->fetch($this->baseName);

        $key = md5($content);
        if ($this->vueCache->has($key)) {
            $newContent = $this->vueCache->get($key);
        } else {
            $outputArray = [];
            //$input = str_replace(["\n","\r"], '', $content);
            $input = $content;
            preg_match_all('/<template>(.*)<\/template>(.*)<script type="module">(.*)<\/script>/s', $input, $outputArray);
            $javascript = $outputArray[3][0];
            $newContent = str_replace("`#`", "`{$outputArray[1][0]}`", $javascript);
            $this->vueCache->set($key, $newContent);
        }
        $this->resultFormat = 'javascript';
        return $newContent;
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
    }

}
