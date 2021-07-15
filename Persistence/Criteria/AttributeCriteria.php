<?php

namespace Orkester\Persistence\Criteria;

use Orkester\Persistence\EPersistenceException;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;

class AttributeCriteria
{

    private string $alias = '';
    private PersistentCriteria $criteria;
    private ?AttributeMap $attributeMap;
    private string $attribute;

    public function __construct(
        private string $name
    )
    {
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setCriteria(PersistentCriteria $criteria)
    {
        $this->criteria = $criteria;
    }

    public function getCriteria(): PersistentCriteria
    {
        return $this->criteria;
    }

    public function setAlias($alias): void
    {
        $this->alias = $alias;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    public function getAttributeMap(): AttributeMap|null
    {
        try {
            $this->attribute = $this->name;
            $this->attributeMap = null;
            if (!$this->checkAttributesToSkip($this->attribute)) {
                $this->processAttribute();
            }
            return $this->attributeMap;
        } catch (EPersistenceException $e) {
            return null;
        }
    }

    private function implodeReference(ClassMap $classMapBase, string $chain): string
    {
        $classMap = clone $classMapBase;
        $tokens = preg_split('/[.]+/', $chain);
        if (count($tokens) > 1) { // has associations
            $a = [];
            for ($i = 0; $i < count($tokens); $i++) {
                $name = $tokens[$i];
                if ($this->criteria->isAssociationAlias($name)) {
                    $a[] = $name;
                } else {
                    $associationMap = $classMap->getAssociationMap($name);
                    if ($associationMap != null) {
                        $classMap = $associationMap->getToClassMap();
                        $a[] = $name;
                    } else {
                        $attributeMap = $classMap->getAttributeMap($name);
                        if ($attributeMap != null) {
                            $reference = $attributeMap->getReference();
                            if ($reference != '') {
                                $a[] = $this->implodeReference($classMap, $reference);
                            } else {
                                $a[] = $name;
                            }
                        } else {
                            $a[] = $name;
                        }
                    }
                }
            }
            return implode('.', $a);
        } else {
            $attributeMap = $classMap->getAttributeMap($chain);
            if ($attributeMap != null) {
                $reference = $attributeMap->getReference();
                if ($reference != '') {
                    $chain = $this->implodeReference($classMap, $reference);
                }
            }
            return $chain;
        }
    }

    private function processAttribute(): void
    {
        $classMap = $this->criteria->getClassMap();
        $attribute = $this->attribute;
        //mdump('== ' . $attribute . ' ' . $classMap->getName());
        $tokens = explode('.', $attribute);
        $alias = '';

        if ($this->criteria->isAssociationAlias($tokens[0])) {
            $alias = $tokens[0];
        }

        if ($this->criteria->isClassAlias($tokens[0])) {
            $alias = $tokens[0];
        }

        $attribute = $this->implodeReference($classMap, $attribute);
        //mdump('$$ ' . $attribute);
        $tokens = preg_split('/[.]+/', $attribute);
        $n = count($tokens);
        $attributeName = $tokens[$n - 1];
        if ($n > 1) { // has associations
            $associationAlias = '';
            if ($alias == '') {
                $start = 0;
                $fromAlias = $this->criteria->getAlias();
            } else {
                $start = 1;
                $fromAlias = $alias;
            }
            for ($i = $start; $i < $n - 1; $i++) {
                $name = $tokens[$i];
                $currentClassMap = $classMap;
                $composedName = $fromAlias . '.' . $name;
                $associationCriteria = $this->criteria->getAssociationCriteria($composedName, $currentClassMap);
                if ($associationCriteria == null) {
                    $associationCriteria = $this->criteria->addAssociationCriteria($name, 'INNER', $currentClassMap, $fromAlias);
                }
                $associationCriteria->setFromAlias($fromAlias);
                //$associationAlias = $this->criteria->getActualAssociationAlias($name);
                //$associationCriteria->setAlias($associationAlias);
                $associationAlias = $associationCriteria->getAlias();
                if ($associationCriteria == NULL) {
                    throw new EPersistenceException($currentClassMap->getName() . ' Invalid association/alias name [' . $name . '] in attribute [' . $attribute . ']');
                }
                /*
                $associationCriteria = $this->criteria->getAssociationCriteria($name, $currentClassMap)
                    ?: $this->criteria->addAssociationCriteria($name, 'INNER', $currentClassMap);
                if ($associationCriteria == NULL) {
                    throw new EPersistenceException($currentClassMap->getName() . ' Invalid association/alias name [' . $name . '] in attribute [' . $attribute . ']');
                }
                $associationCriteria->setAlias($name);
                $associationCriteria->setFromAlias($fromAlias);
                */
                $associationMap = $associationCriteria->getAssociationMap();
                // If association map is NULL something wrong with names
                if (isset($associationMap)) {
                    $classMap = $associationMap->getToClassMap();
                } else {
                    throw new EPersistenceException($currentClassMap->getName() . ' Invalid association/alias name [' . $name . '] in attribute [' . $attribute . ']');
                }
                $fromAlias = $associationAlias;
            }

            if ($classMap != NULL) {
                $this->attributeMap = $classMap->getAttributeMap($attributeName);
                //if ($alias != '') {
                //    $this->attribute = $alias . '.' . $attributeName;
                //} else {
                $this->attribute = $attributeName;
                if ($associationAlias != '') {
                    $this->attribute = $associationAlias . '.' . $this->attribute;
                } else if ($alias != '') {
                    $this->attribute = $alias . '.' . $this->attribute;
                }

                //}
            }
        } else {
            $this->attributeMap = $classMap->getAttributeMap($attributeName);
            $this->attribute = $this->criteria->getAlias() . '.' . $attributeName;
        }
    }

    private function checkAttributesToSkip($attribute): bool
    {
        return (($attribute[0] ?? '') == ':') || (in_array(trim($attribute), ['', '=', '?', '(', ')', 'and', 'or', 'not']));
    }


}
