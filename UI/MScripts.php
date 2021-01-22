<?php
namespace Orkester\UI;
/**
 * MScripts Class.
 * An auxiliary class to MPage to handle Javascript scripts.
 */

use Ds\Map;
use Orkester\Manager;

class MScripts
{
/*
    public $form;
    public $scripts;
    public $customScripts;
    public $onload;
    public $onsubmit;
    public $onunload;
    public $onfocus;
    public $onerror;
    public $jsCode;
    public $dojoRequire;
    public $events;
*/

    private string $id;
    private array $onsubmit;
    /**
     * @var Map
     */
    private Map $onload;
    /**
     * @var Map
     */
    private Map $onerror;
    /**
     * @var Map
     */
    private Map $onunload;
    /**
     * @var Map
     */
    private Map $onfocus;
    /**
     * @var Map
     */
    private Map $jsCode;
    /**
     * @var Map
     */
    private Map $scripts;
    /**
     * @var Map
     */
    private Map $customScripts;
    /**
     * @var Map
     */
    private Map $events;

    public function __construct($id)
    {
        //parent::__construct();
        $this->id = $id;
        $this->onsubmit = [];
        $this->onload = new Map();
        $this->onerror = new Map();
        $this->onunload = new Map();
        $this->onfocus = new Map();
        $this->jsCode = new Map();
        $this->scripts = new Map();
        $this->customScripts = new Map();
        $this->events = new Map();
    }

    public function addScript(string $url)
    {
        $url = Manager::getAppURL("public/scripts/{$url}");
        $key = md5($url);
        if (!$this->scripts->hasKey($key)) {
            $this->scripts->put($key, $url);
        }
    }

    public function addScriptURL(string $url)
    {
        $key = md5($url);
        if (!$this->scripts->hasKey($key)) {
            $this->scripts->put($key, $url);
        }
    }

    public function insertScript($url)
    {
        $url = Manager::getAbsoluteURL('html/scripts/' . $url);
        $this->scripts->insert($url);
    }

    public function addOnSubmit($jsCode, $formId)
    {
        if (!$this->onsubmit[$formId]) {
            $this->onsubmit[$formId] = new MStringList();
        }
        $this->onsubmit[$formId]->add($jsCode);
    }

    public static function tag($content)
    {
        return "<script type=\"text/javascript\">{$content}</script>\n";
    }

    public function getArray()
    {
        $scripts = [];
        /*
        $events = implode(",\n", $this->events->toArray());
        if ($events != '') {
            $this->onload->add("manager.registerEvents([\n " . $events . "\n]);");
        }

        $scripts[0] = $this->scripts->getTextByTemplate("<script type=\"text/javascript\" src=\"/:v/\"></script>\n");
        if (count($this->dojoRequire)) {
            $i = 0;
            foreach ($this->dojoRequire as $module) {
                $moduleList .= ($i++ ? ',' : '') . "\"{$module}\"";
            }
            $scripts[1] = "require([" . $moduleList . "]);\n";
        }
        $scripts[1] .= $this->jsCode->getValueText('', "\n");
        $scripts[2] = ($onload = $this->onload->getValueText('', "\n ")) ? "    {$onload}" : '';
        $onsubmit = '';
        if (count($this->onsubmit)) {
            foreach ($this->onsubmit as $formId => $list) {
                $onsubmit .= "manager.onSubmit[\"{$formId}\"] = function() { \n" .
                    "    form = manager.byId(\"{$formId}\");\n " . $list->getValueText('', " \n    ") .
                    "    return result;\n};\n";
            }
        }
        $scripts[3] = $onsubmit;
        $scripts[4] = ($onerror = $this->onerror->getValueText('', "\n    ")) ? "{$onerror}" : '';
*/
        return $scripts;

    }

    public function generate($id)
    {
        $isAjax = Manager::isAjaxCall();
        $scripts = $this->getArray();
        $hasCode = $scripts[0] . $scripts[1] . $scripts[2] . $scripts[3] . $scripts[4];
        if ($hasCode != '') {
            $code = "";

            if ($scripts[0] != '') {
                $code .= <<< HERE
$scripts[0]
                    
HERE;
            }
            $code .= "\n<script type=\"text/javascript\">\n";

            if ($scripts[1] != '') {
                $code .= <<< HERE
$scripts[1]

HERE;
            }

            if ($isAjax) {
                if (Manager::isAjaxEvent()) {
                    $code .= <<< HERE
{$scripts[2]}

HERE;
                } else {
                    $code .= <<< HERE
manager.onLoad["{$id}"] = function() {
    console.log("inside onload {$id}");
{$scripts[2]}
};
HERE;
                }
            } else {
                $code .= <<< HERE
require(["dojo/parser", "dojo/ready"], function(parser, ready){
  ready(function(){
    console.log("inside onload {$id}");
{$scripts[2]}
  });
});   

HERE;
            }
            $code .= <<< HERE

{$scripts[3]}
{$scripts[4]}
HERE;
            $code .= <<< HERE
//-->
</script>

HERE;
            return "<div id=\"{$id}\" class=\"mScripts\">{$code}</div>";
//            return $code;
        } else {
            return '';
        }
    }

}
