<?php
namespace Orkester\UI;

use Orkester\Manager;
use Ramsey\Uuid\Uuid;

class MPage
{

    /*
    public static $instance = null;
    public $id;
    public $layout;
    public $scripts;
    public $state;
    public $action;
    public $actionChanged;
    public $redirectTo;
    public $fileUpload;
    public $window;
    public $prompt;
    public $binary;
    public $download;
    public $template;
    public $templateName;
    public $theme;
    public $content;
    public $styleSheetCode;
    public $title;
    public $uiPath;
    public $menu;
    public $menus = [];
*/

    /**
     * @var MTemplate
     */
    private MTemplate $template;
    private string $templateName;
    private string $content;

    public function __construct()
    {
        $id = 'page-' . Uuid::uuid4();
        $this->id = $id;
        $this->scripts = new MScripts($id);
        //$this->state = new MState($id);
        //$this->action = Manager::getRequest()->getURL();
        //$this->actionChanged = false;
        $this->layout = mrequest('__LAYOUT') ?: 'default';
        $this->fileUpload = mrequest('__ISFILEUPLOAD') == 'yes';
        //$this->menu = Manager::isLogged() ? 'siga' : 'home';
        //$this->container = new MPageContent();
        $templateName = mrequest('__TEMPLATE');
        if ($templateName == '') {
            if (Manager::isAjaxCall()) {
                $templateName = 'content';
            } else {
                $templateName = (Manager::getConf('theme.template') ?: 'index');
            }
        }
        //$this->uiPath = Manager::getHome() . '/maestro/ui';
        $this->setTemplateName($templateName);
        $this->setTemplate();
        //$this->theme = Manager::$conf['theme']['name'];
        //$this->title = Manager::getConf('name');
        $this->styleSheetCode = '';
        ob_start();
    }

    /*
    public function getLayout()
    {
        return $this->layout;
    }

    public function setLayout($layout)
    {
        $this->layout = $layout;
    }
    */

    /**
     * Template methods
     */

    /**
     * Define template and template variables
     */
    public function setTemplate()
    {
        $path = Manager::getHome() . '/app/UI/templates';
        $this->template = new MTemplate($path);
        //$this->template->context('manager', \Maestro\Manager);
        $this->template->context('page', $this);
        $this->template->context('charset', Manager::getOptions('charset'));
        $this->template->context('layout', $this->layout);
        //$this->template->context('menu', $this->menu);
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

    public function setTemplateName(string $name)
    {
        $this->templateName = $name;
    }

    /**
     * is* methods
     */
    public function isPostBack()
    {
        return Manager::getRequest()->isPostBack();
    }

    public function isWindow()
    {
        return ($this->layout == 'window');
    }

    /**
     * Action methods
     */
    /*
    function setAction($action)
    {
        $this->action = $action;
        $this->actionChanged = true;
    }

    public function getAction()
    {
        return $this->action;
    }
    */

    /*
      CSS Styles
     */

    /*
    public function addStyleSheet(string $fileName)
    {
        $file = Manager::getVarPath(). '/files/' . basename($fileName));
        copy($fileName, $file);
        $url = Manager::getDownloadURL('cache', basename($fileName), true);
        $this->onLoad("dojo.create(\"link\", {href:'{$url}', type:'text/css', rel:'stylesheet'}, document.getElementsByTagName('head')[0]);");
    }

    public function addStyleSheetCode($code)
    {
        if (Manager::isAjaxCall()) {
            $fileName = md5($code) . '.css';
            $file = Manager::getFrameworkPath('var/files/' . $fileName);
            file_put_contents($file, $code);
            $url = Manager::getDownloadURL('cache', $fileName, true);
            $this->onLoad("dojo.create(\"link\", {href:'{$url}', type:'text/css', rel:'stylesheet'}, document.getElementsByTagName('head')[0]);");
        } else {
            $this->styleSheetCode .= "\n" . $code;
        }
    }
    */

    /*
      Scripts
     */

    /*

    public function addScript(string $url)
    {
        $this->scripts->addScript($url);
    }

    public function addScriptURL(string $url)
    {
        $this->scripts->addScriptURL($url);
    }

    public function insertScript(string $url)
    {
        $this->scripts->insertScript($url);
    }

    public function getScripts(): array
    {
        return $this->scripts->scripts;
    }

    public function getCustomScripts()
    {
        return $this->scripts->customScripts;
    }

    public function getOnLoad()
    {
        return $this->scripts->onload;
    }

    public function getOnError()
    {
        return $this->scripts->onerror;
    }

    public function getOnSubmit()
    {
        return $this->scripts->onsubmit;
    }

    public function getOnUnLoad()
    {
        return $this->scripts->onunload;
    }

    public function getOnFocus()
    {
        return $this->scripts->onfocus;
    }

    public function getJsCode()
    {
        return $this->scripts->jsCode;
    }

    public function onSubmit($jsCode, $formId)
    {
        $this->scripts->addOnSubmit($jsCode, $formId);
    }

    public function onLoad($jsCode)
    {
        $this->scripts->onload->add($jsCode);
    }

    public function onUnLoad($jsCode)
    {
        $this->scripts->onunload->add($jsCode);
    }

    public function onError($jsCode)
    {
        $this->scripts->onerror->add($jsCode);
    }

    public function onFocus($jsCode)
    {
        $this->scripts->onfocus->add($jsCode);
    }

    public function addJsCode($jsCode)
    {
        $this->scripts->jsCode->add($jsCode);
    }

    public function addJsFile($fileName)
    {
        $jsCode = file_get_contents($fileName);
        $this->scripts->jsCode->add($jsCode);
    }

    public function registerEvent($event)
    {
        $this->scripts->events->add($event);
    }
*/
    /*
      State
     */

    /*
      Response related methods
     */
/*
    public function redirect($url)
    {
        $this->redirectTo = $url;
    }

    public function window($url)
    {
        $this->window = $url;
    }

    public function binary($stream)
    {
        $this->binary = $stream;
    }

    public function download($fileName)
    {
        $this->download = $fileName;
    }

    public function prompt($prompt)
    {
        $this->prompt = $prompt;
    }

    public function menu($menu)
    {
        $this->menu = $menu;
    }

    public function addMenu($menu) {
        $this->menus[] = $menu;
    }

    public function getMenus() {
        return $this->menus;
    }
*/
    /*
      Token
     */
/*
    public function getTokenId()
    {
        Manager::getSession()->set('__MAESTROTOKENID', md5(uniqid()));
        $tokenId = Manager::useToken ? Manager::getSession()->get('__MAESTROTOKENID') : '';
        //mdump('getting token id = ' . $tokenId);
        return "manager.page.tokenId = '{$tokenId}';";
    }

    public function sendTokenId()
    {
        $this->onload($this->getTokenId());
    }
*/
    /**
     * Generate methods
     */
    public function generate(string $element = 'content'): string
    {
        mtrace('mpage::generate');
        if ($element == 'content') {
            $html = $this->generateContent();// . $this->generateStyleSheetCode() . $this->generateScripts();
        } else {
            $component = new $element;
            $html = $component->generate();
        }
        return $html;
    }

    public function generateStyleSheetCode(): string
    {
        $code = ($this->styleSheetCode != '') ? "<style type=\"text/css\">" . $this->styleSheetCode . "\n</style>\n" : '';
        return $code;
    }

    public function generateScripts(): string
    {
        return $this->scripts->generate($this->id);
    }

    public function fetch(string $template = '', array $params = []): string
    {
        $template = $template ?: $this->getTemplateName();
        mdump('mpage::fetch - template =  ' . $template);
        $html = ($template != '') ? $this->template->fetch($template, $params) : '';
        return $html;
    }

    public function render(string $template = ''): string
    {
        $html = $this->fetch($template);
        if ($ob = ob_get_clean()) {
            $html = $ob . $html;
        }
        return $html;
    }

    /**
     * Content
     */

    // get the array of controls from content
    public function getControl($key = NULL)
    {
        //return ($key !== NULL ? $this->container->getControl($key) : $this->container);
    }

    // get the array of controls from content
    public function getContent($key = NULL)
    {
        //return ($key !== NULL ? $this->content->getControl($key) : $this->content->getControls());
        return $this->content;
    }

    // set the content
    public function setContent($content)
    {
        /*
        if (is_string($content)) {
            $this->container->setInner($content);
        } else {
            $this->clearContent();
            $this->container->setControls($content);
        }
        */
        $this->content = $content;
    }

    public function clearContent()
    {
        //$this->container->clearControls();
        $this->content = '';
    }

    public function addContent($content, $key = NULL)
    {
        /*
        if ($key !== NULL) {
            $this->container->insertControl($content, $key);
        } else {
            $this->container->addControl($content);
        }
        */
        $this->content .= $content;
    }

    public function appendContent($content)
    {
        $this->addContent($content);
    }

    public function insertContent($content)
    {
        //$this->container->insertControl($content, 0);
        $this->addContent($content);
    }

    public function generateContent()
    {
        //$this->container->generateInner();
        //$html = MBasePainter::generateToString($this->container->getInner());
        //return $html;
        //return $this->container->generate();
        return $this->content;
    }

}