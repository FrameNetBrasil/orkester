<?php

namespace Orkester\UI;

use Ds\Map;

class MStyle
{

    /**
     * CSS selector.
     */
    public string $cssClass;

    /**
     * A list with style attributes.
     */
    public Map $style;

    /**
     * Is the control absolutely positioned?
     */
    public bool $cssp;

    public function __construct()
    {
        $this->cssClass = '';
        $this->cssp = false;
        $this->style = new Map();
    }

    public static function selector($name)
    {
        return match($name) {
            'color',
            'font',
            'border',
            'overflow',
            'cursor',
            'padding',
            'margin',
            'width',
            'height',
            'float',
            'clear',
            'visibility',
            'display',
            'top',
            'left',
            'position' => $name,
            'fontSize' => 'font-size',
            'fontStyle'=> 'font-style',
            'fontFamily'=> 'font-family',
            'fontWeight'=> 'font-weight',
            'textAlign'=> 'text-align',
            'textIndent'=> 'text-indent',
            'lineHeight'=> 'line-height',
            'zIndex'=> 'z-index',
            'backgroundColor'=> 'background-color',
            'verticalAlign'=> 'vertical-align',
            'borderCollapse'=> 'border-collapse',
            'borderWidth'=> 'border-width',
            'borderSpacing'=> 'border-spacing',
            'borderTop'=> 'border-top',
            'borderRight'=> 'border-right',
            'borderBottom'=> 'border-bottom',
            'borderLeft'=> 'border-left',
            'emptyCells'=> 'empty-cells',
            default=> ''
        };
    }

    public function __set($name, $value)
    {
        if ($name == '')
            mtracestack();
        $selector = self::selector($name);
        if ($selector != '') {
            $this->addStyle('font-size', $value);
        }
    }

    public function __get($name)
    {
        return match ($name) {
            'top', 'left', 'width', 'height', 'padding', 'float', 'position' =>  $this->style->get($name),
            default => ''
        };
    }

    /**
     * The clone method.
     * It is used on clone of controls, avoiding references to same styles.
     */
    public function __clone()
    {
        $this->style = clone $this->style;
    }

    /**
     * The setter method.
     */
    public function set($name, $value)
    {
        if ($value != '') {
            $this->__set($name, $value);
        }
    }

    public function get($name)
    {
        return ( $name != '' ) ? $this->style->get($name) : '';
    }

    public function addStyle($name, $value)
    {
        if ($value != '') {
            $this->style->put($name, $value);
        }
    }

    public function addStyleFile($styleFile)
    {
        $this->page->addStyle($styleFile);
    }

    public function getClass()
    {
        return $this->cssClass;
    }

    /* TODO=> tokenizer */

    public function setStyle($style)
    {
        $this->style->items = $style;
    }

    public function getStyle()
    {
        return $this->style->reduce(function($carry, $key, $value) {
            return $carry .  $key . ":" . $value . ';' ;
        }, '');
    }

    public function setPosition($left, $top, $position = 'absolute')
    {
        $this->addStyle('position', $position);
        $this->addStyle('left', "{$left}px");
        $this->addStyle('top', "{$top}px");
    }

    public function setWidth($value)
    {
        if (!$value) {
            return;
        }
        $this->addStyle('width', $value);
    }

    public function setHeight($value)
    {
        if (!$value) {
            return;
        }
        $this->addStyle('height', $value);
    }

    public function setColor($value)
    {
        $this->addStyle('color', $value);
    }

    public function setVisibility($value)
    {
        $value = ($value ? 'visible' : 'hidden');
        $this->addStyle('visibility', $value);
    }

    public function setFont($value)
    {
        $this->addStyle('font', $value);
    }

}
