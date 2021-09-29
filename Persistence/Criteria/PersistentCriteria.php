<?php

namespace Orkester\Persistence\Criteria;

use Orkester\Exception\EOrkesterException;
use Orkester\Manager;
use Orkester\Persistence\EPersistenceException;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;
use Orkester\Persistence\Operand\OperandArray;
use Orkester\Persistence\Operand\OperandAttributeMap;
use Orkester\Persistence\Operand\OperandCriteria;
use Orkester\Persistence\Operand\OperandFunction;
use Orkester\Persistence\Operand\OperandList;
use Orkester\Persistence\Operand\OperandNull;
use Orkester\Persistence\Operand\OperandObject;
use Orkester\Persistence\Operand\OperandString;
use Orkester\Persistence\Operand\OperandStringAI;

class PersistentCriteria
{
    /* components */
    protected array /* of AttributeCriteria */
        $columns = [];
    protected array /* of AttributeCriteria */
        $groups = [];
    protected array /* of AttributeCriteria */
        $orders = [];
    protected array /* of AssociationCriteria */
        $associations = [];
    protected array /* of AssociationCriteria */
        $joins = [];
    protected array /* of AssociationCriteria */
        $autoAssociation = [];
    protected ConditionCriteria $whereConditionCriteria;
    protected ConditionCriteria $havingConditionCriteria;
    /* aliases */
    protected array /* of string */
        $classAlias = [];
    protected array /* of string */
        $attributeAlias = [];
    protected array /* of string */
        $associationAlias = [];
    protected array /* of string */
        $actualAssociationAlias = [];
    /* for criteria */
    protected ?ClassMap $classMap = NULL;
    protected string $alias = '';
    protected array $parameters = [];
    protected array /* of ClassMap */
        $classMaps = [];
    protected array /* of ClassMap/RetrieveCriteria */
        $classes = [];

    /*
        protected array $aliases = [];
        protected array $maps = []; // array of classMaps
    */

    protected array $tableCriteria = [];

    //protected array $tableCriteriaColumn = [];

    public function __construct(?ClassMap $classMap = NULL)
    {
        $this->setClassMap($classMap);
        $this->whereConditionCriteria = $this->getNewConditionCriteria();
        $this->havingConditionCriteria = $this->getNewConditionCriteria();
    }

    private function getNewConditionCriteria(): ConditionCriteria
    {
        $conditionCriteria = new ConditionCriteria();
        $conditionCriteria->setCriteria($this);
        return $conditionCriteria;
    }

    public function setClassMap(ClassMap $classMap)
    {
        $this->classMap = $classMap;
        $this->alias = $classMap->getTableName();
        //$this->addClass($classMap, $this->alias);
    }

    public function getClassMap(): ClassMap
    {
        return $this->classMap;
    }


    public function getWhereConditionCriteria(): ConditionCriteria
    {
        return $this->whereConditionCriteria;
    }

    public function getHavingConditionCriteria(): ConditionCriteria
    {
        return $this->havingConditionCriteria;
    }

    public function addClassMap(ClassMap $classMap, string $alias = ''): PersistentCriteria
    {
        $className = trim($classMap->getName());
        //$this->classes[$className] = [$className, $alias];
        $this->classMaps[$className] = $classMap;
        if ($alias != '') {
            $this->classAlias[$alias] = $className;
        }
        return $this;
    }

    public function getOperand(mixed $operand, bool $accentInsensitive = false): OperandObject|OperandNull|OperandString|OperandStringAI|OperandAttributeMap|OperandArray|OperandCriteria|OperandFunction|OperandList
    {
        if (is_null($operand)) {
            $o = new OperandNull($operand);
        } elseif (is_object($operand)) {
            if ($operand instanceof AttributeMap) {
                $o = new OperandAttributeMap('', $operand, $this);
            } elseif ($operand instanceof RetrieveCriteria) {
                $o = new OperandCriteria($operand, $this);
            } else {
                $o = new OperandObject($operand, $this);
            }
        } elseif (is_array($operand)) {
            $o = new OperandArray($operand);
        } else { // string
            if ($accentInsensitive) {
                $o = new OperandStringAI($operand, $this);
            } else {
                $o = new OperandString($operand, $this);
            }
        }
        return $o;
    }

    public function getTableName(string $className): string
    {
        $classMap = Manager::getPersistentManager()->getClassMap($className);
        return $classMap->getTableName();
    }

    public function getConditionCriteria($op1, $operator = '', $op2 = NULL): ConditionCriteria
    {
        $conditionCriteria = new ConditionCriteria();
        if ($op1 instanceof ConditionCriteria) {
            $conditionCriteria->add($op1);
        } elseif ($op1 instanceof PersistentCondition) {
            $conditionCriteria->add($op1);
        } else {
            $condition = new PersistentCondition($op1, $operator, $op2);
            $condition->setCriteria($conditionCriteria);
            $conditionCriteria->add($condition);
        }
        return $conditionCriteria;
    }


    public function getMap(string $className): ClassMap|null
    {
        $className = trim($className);
        return $this->classMaps[$className] ?? null;
    }


    /**
     * Add class to be used on FROM clause
     * @param string $className
     * @param string $alias
     * @param ClassMap|null $classMap
     * @return $this
     */
    public function addClass(ClassMap|RetrieveCriteria $class, string $alias = ''): PersistentCriteria
    {
        if ($class instanceof ClassMap) {
            $className = $class->getName();
        }
        if ($class instanceof RetrieveCriteria) {
            $className = $class->getClassMap()->getName();
        }
        $this->classes[$className] = [$class, $alias];
        $this->classAlias[$alias] = $className;
        return $this;
    }

    /*
    public function addClass(string $className, string $alias = '', ClassMap $classMap = NULL): PersistentCriteria
    {
        $className = trim($className);
        if (is_null($classMap)) {
            $classMap = Manager::getPersistentManager()->getClassMap($className);
            $this->maps[$className] = $classMap;
        }
        $registerAlias = $alias ?? $classMap->getTableName();
        $this->classes[$className] = [$className, $registerAlias];
        $this->classAlias[$registerAlias] = $className;
        return $this;
    }
    */

    /*
    public function registerAlias(string $alias, string $aliased): void
    {
        if ($aliased instanceof AttributeCriteria) {
            $this->attributeAlias[$alias] = $aliased;
        }
        if ($aliased instanceof AssociationCriteria) {
            $this->associationAlias[$alias] = $aliased;
        }
    }
    */

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function isAlias(string $name): bool
    {
        return (isset($this->associationAlias[$name]) || isset($this->attributeAlias[$name]));
    }

    public function isAssociationAlias(string $name): bool
    {
        return isset($this->associationAlias[$name]);
    }

    public function getActualAssociationAlias(string $alias): string
    {
        if (!isset($this->actualAssociationAlias[$alias])) {
            $this->actualAssociationAlias[$alias] = 0;
        }
        return $alias . '_' . ++$this->actualAssociationAlias[$alias];
    }

    public function isClassAlias(string $name): bool
    {
        return (isset($this->classAlias[$name]));
    }

    public function getClassAlias(string $name): string
    {
        return $this->classAlias[$name] ?? '';
    }

    public function isAttributeAlias(string $name): bool
    {
        return (isset($this->attributeAlias[$name]));
    }

    public function getAttributeAlias(string $name): string
    {
        return $this->attributeAlias[$name] ?? '';
    }

    public function setAttributeAlias(string $alias, string $value): void
    {
        $this->attributeAlias[$alias] = $value;
    }

    public function setAlias(string $alias)
    {
        $this->alias = $alias;
        return $this;
    }

    /*
    public function setAlias(string $alias, ClassMap|string $aliased = null)
    {
        if ($aliased == null) {
            $className = $this->classMap->getName();
            $this->alias = $alias;
            $this->classes[$className] = [$className, $alias];
        } else {
            $this->registerAlias($alias, $aliased);
        }
        return $this;
    }

    public function getMapFromAlias($alias): ClassMap|string
    {
        return $this->aliases[$alias];
    }
    */


    public function getAliases(): array
    {
        return $this->classAlias;
    }


    /**
     * Merge aliases from outer criteria.
     * @param PersistentCriteria $criteria
     */

    public function mergeAliases(PersistentCriteria $criteria)
    {
        $aliases = $criteria->getAliases();
        if (count($aliases) > 0) {
            foreach ($aliases as $alias => $classMap) {
                if (!isset($this->classAlias[$alias])) {
                    $this->classAlias[$alias] = $classMap;
                }
            }
        }
    }


    public function setAssociationAlias(string $associationName, string $alias)
    {
        $associationCriteria = $this->getAssociationCriteria($associationName);
        if ($associationCriteria == NULL) {
            $associationCriteria = $this->addAssociationCriteria($associationName);
        }
        $associationCriteria->setAlias($alias);
        $this->associationAlias[$alias] = $associationName;
        //$classMap = $associationCriteria->getAssociationMap()->getToClassMap();
        //$this->setAlias($alias, $classMap);
        return $this;
    }

    public function setAssociationType(string $associationName, string $joinType)
    {
        $associationCriteria = $this->getAssociationCriteria($associationName);
        if ($associationCriteria == NULL) {
            $associationCriteria = $this->addAssociationCriteria($associationName);
        }
        $associationCriteria->setJoinType($joinType);
        return $this;
    }

    public function setAutoAssociation(string $alias1, string $alias2, string $condition = '', string $joinType = 'INNER')
    {
        $className = $this->classMap->getName();
        $this->setAlias($alias1);
        $this->addClassMap($this->classMap, $alias1);
        $this->addClassMap($this->classMap, $alias2);
        $this->autoAssociation = [$className . ' ' . $alias1, $className . ' ' . $alias2, $condition, $joinType];
        return $this;
    }

    /**
     * Build a join array from automatic joins (to be used at sql statement)
     * @return array
     */
    public function getAssociationsJoin(): array
    {
        $join = [];
        // AutoAssociation
        if ($this->autoAssociation) {
            $class1 = $this->getOperand($this->autoAssociation[0])->getSql();
            $class2 = $this->getOperand($this->autoAssociation[1])->getSql();
            $condition = $this->getOperand($this->autoAssociation[2])->getSql();
            $join[] = [$class1, $class2, $condition, $this->autoAssociation[3]];
        }

        // Associations
        if (count($this->associations)) {
            foreach ($this->associations as $associationCriteria) {
                $associationJoins = $associationCriteria->getJoin();
                if (count($associationJoins)) {
                    foreach ($associationJoins as $associationJoin) {
                        $join[] = $associationJoin;
                    }
                }
            }
        }

        return $join;
    }

    /**
     * Build a join array from forced joins (to be used at sql statement)
     * @return array
     */
    public function getForcedJoin(): array
    {
        $join = [];

        // Forced joins
        if (count($this->joins)) {
            foreach ($this->joins as $forcedJoin) {
                list($className0, $alias0) = explode(' ', $forcedJoin[0]);
                list($className1, $alias1) = explode(' ', $forcedJoin[1]);
                $classMap0 = $this->classes[$className0][0];
                $classMap1 = $this->classes[$className1][0];
                $table0 = $classMap0->getTableName();
                if ($table0 != $alias0) {
                    $table0 .= ' ' . $alias0;
                }
                $table1 = $classMap1->getTableName();
                if ($table1 != $alias1) {
                    $table1 .= ' ' . $alias1;
                }
                $op = explode(' ', $forcedJoin[2]);
                $condition = new PersistentCondition($op[0], $op[1], $op[2]);
                $condition->setCriteria($this);
                $conditionSql = $condition->getSql();
                $join[] = [$table0, $table1, $conditionSql, $forcedJoin[3]];
            }
        }
        return $join;
    }

    /*
    public function getAttributeMap(string $attribute): AttributeMap|null
    {
        $attributeCriteria = new AttributeCriteria($attribute);
        $attributeCriteria->setCriteria($this);
        return $attributeCriteria->getAttributeMap();
    }
    */

    public function getAttributeCriteria(string $attribute): AttributeCriteria
    {
        $attributeCriteria = new AttributeCriteria($attribute);
        $attributeCriteria->setCriteria($this);
        return $attributeCriteria;
    }

    public function getAssociationMap(string $attribute): AssociationMap|null
    {
        $associationCriteria = new AssociationCriteria($attribute);
        $associationCriteria->setCriteria($this);
        return $associationCriteria->getAssociationMap();
    }

    /*
     * Attribute methods
     */

    /*
    public function addAttributeCriteria(string $name, string $alias = ''): AttributeCriteria
    {
        $attributeCriteria = new AttributeCriteria($name);
        $attributeCriteria->setCriteria($this);
        if ($alias != '') {
            $attributeCriteria->setAlias($alias);
            //$this->attributeAlias[$alias] = $attributeCriteria;
        }
        return $attributeCriteria;
    }
    */

    /*
     * Associations methods
     */
    public function getAssociationCriteria(string $associationName, ClassMap $classMap = NULL): AssociationCriteria|null
    {
        $associationCriteria = $this->associations[$associationName] ?? null;
        /*
        if ($classMap == NULL) {
            $classMap = $this->classMap;
        }
        $associationCriteria = NULL;
        foreach ($this->associations as $tempAssociationCriteria) {
            $classMapName = $classMap->getName();
            $prefixedAssociationName = $classMapName . '.' . $associationName;
            $associationCriteriaName = $tempAssociationCriteria->getName();
            $associationCriteriaAlias = $tempAssociationCriteria->getAlias();
            if (($associationCriteriaName == $prefixedAssociationName)
                || ($associationCriteriaAlias == $associationName)
                || ($associationCriteriaName == $associationName)
            ) {
                $associationCriteria = $tempAssociationCriteria;
                break;
            }
        }
        */
        return $associationCriteria;
    }

    public function addAssociationCriteria(
        string $name,
        string $joinType = 'INNER',
        ClassMap $classMap = null,
        string $fromAlias = null
    ): AssociationCriteria|null
    {
        if ($classMap == NULL) {
            $classMap = $this->classMap;
        } else {
            $this->addClassMap($classMap);
        }
        $tokens = preg_split('/[.]+/', $name);
        if (count($tokens) > 1) { // associação indireta: baseClass.x.y.assoc
            $fromAlias ??= $classMap->getTableName();
            for ($i = 0; $i < count($tokens); $i++) {
                $associationName = $tokens[$i];
                // add prefix, to avoid collision
                $composedName = $fromAlias . '.' . $associationName;
                // associationCriteria already exists?
                $associationCriteria = $this->getAssociationCriteria($composedName, $classMap);
                if ($associationCriteria == NULL) {
                    $associationMap = $classMap->getAssociationMap($associationName);
                    $a = $this->newAssociationCriteria($composedName, $associationMap);
                    $classMap = $associationMap->getToClassMap();
                    $this->addClassMap($classMap);
                } else {
                    $associationMap = $classMap->getAssociationMap($associationName);
                    $classMap = $associationMap->getToClassMap();
                    $a = $associationCriteria;
                }
                $alias = $this->getActualAssociationAlias($associationName);
                $a->setAlias($alias);
                $a->setFromAlias($fromAlias);
                //mdump('== '. $a->getName() . ' -  from Alias = ' . $a->getFromAlias() . ' -  alias = ' . $a->getAlias());
                $fromAlias = $alias;
            }
            return $a;
        } else {  // associação direta: baseClass.assoc
            $fromAlias ??= $classMap->getTableName();
            $associationMap = $classMap->getAssociationMap($name);
            if (is_null($associationMap)) {
                throw new EPersistenceException('Association not found: ' . $name);
            }
            $composedName = $fromAlias . '.' . $name;
            $a = $associationCriteria = $this->newAssociationCriteria($composedName, $associationMap, $joinType);
            $alias = $this->getActualAssociationAlias($name);
            $a->setAlias($alias);
            $associationCriteria->setFromAlias($fromAlias);
            //mdump('== direct  '. $a->getName() . ' -  from Alias = ' . $a->getFromAlias() . ' -  alias = ' . $a->getAlias());
            // test if toClass has additional conditions
            if ($associationMap != NULL) {
                $toClassMap = $associationMap->getToClassMap();
                $conditions = $toClassMap->getConditions();
                foreach ($conditions as $condition) {
                    //$this->whereConditionCriteria->and_($alias . '.' . $condition[0], $condition[1], $condition[2]);
                    $op2 = $this->getOperand($condition[2])->getSqlWhere();
                    $associationCriteria->addCondition($alias . '.' . $condition[0], $condition[1], $op2);
                }
            }
            return $associationCriteria;
        }
    }

    public function newAssociationCriteria(string $name, AssociationMap $associationMap, string $joinType = 'INNER'): AssociationCriteria
    {
        $associationCriteria = new AssociationCriteria($name);
        $associationCriteria->setCriteria($this);
        //$associationCriteria->setJoinType($joinType);
        $associationCriteria->setJoinType($associationMap->getJoinType());
        $associationCriteria->setAssociationMap($associationMap);
        $this->associations[$name] = $associationCriteria;
        return $this->associations[$name];
    }


    /*
     * getters
     */

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getOrders(): array
    {
        return $this->orders;
    }

    public function getJoins(): array
    {
        return $this->joins;
    }

    public function getClasses(): array
    {
        return $this->classes;
    }

    public function getTableCriteria(): array
    {
        return $this->tableCriteria;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }


    /*
     * Criteria clauses
     */

    /*
    public function addColumnAttribute($attribute, $label = '')
    {
        $attribute = trim($attribute);
        if ($attribute == '*') {
            $classMap = $this->classMap;
            for ($i = 0; $i < $classMap->getSize(); $i++) {
                $am = $classMap->getAttributeMap($i);
                $this->columns[] = $am->getName() . ($label = $am->getAlias() ? ' as ' . $label : '');
            }
        } else {
            $this->columns[] = $attribute . ($label ? ' as "' . $label . '"' : '');
        }
    }

    public function addCriteria($op1, $operator = '', $op2 = NULL)
    {
        $this->whereCondition->and_($op1, $operator, $op2);
    }

    public function addOrCriteria($op1, $operator = '', $op2 = NULL)
    {
        $this->whereCondition->or_($op1, $operator, $op2);
    }

    private function convertMultiCriteria($condition, &$criteriaCondition)
    {
        if (is_array($condition)) {
            foreach ($condition as $c) {
                if (is_array($c[1])) {
                    $cc = new ConditionCriteria();
                    $this->convertMultiCriteria($c[1], $cc);
                    $criteriaCondition->add($cc, $c[0]);
                } else {
                    $base = new PersistentCondition($c[1], $c[2], $c[3]);
                    $base->setCriteria($this);
                    $criteriaCondition->add($base, $c[0]);
                }
            }
        }
    }

    public function addMultiCriteria($condition)
    {
        $this->convertMultiCriteria($condition, $this->whereCondition);
    }

    public function tableCriteria($criteria, $alias)
    {
        $sql = $criteria->getSqlStatement();
        $sql->setDb($this->getClassMap()->getDb());
        $this->tableCriteria[] = array($sql->select()->getCommand(), $alias);
        return $this;
    }

    public function joinCriteria($criteria, $condition, $joinType = 'INNER')
    {
        $this->joins[] = array($this->classMap->getName(), $criteria, $condition, $joinType);
    }

    public function getCriteria($op1, $operator = '', $op2 = NULL)
    {
        $operand1 = $this->getOperand($op1);
        $operand2 = $this->getOperand($op2);
        $criteria = BaseCriteria::getCondition($operand1, $operator, $operand2);
        return $criteria;
    }

    public function addGroupAttribute($attribute)
    {
        $this->groups[] = $attribute;
    }

    public function addHavingCriteria($op1, $operator, $op2)
    {
        $this->havingConditionCriteria->addCriteria($this->getCriteria($op1, $operator, $op2));
    }

    public function addOrHavingCriteria($op1, $operator, $op2)
    {
        $this->havingConditionCriteria->addOrCriteria($this->getCriteria($op1, $operator, $op2));
    }

    public function addOrderAttribute($attribute, $ascend = TRUE)
    {
        $this->orders[] = $attribute . ($ascend ? ' ASC' : ' DESC');
    }

    public function addParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    public function addParameter($value, $name = '')
    {
        if ($name != '') {
            $this->parameters[$name] = $value;
        } else {
            $this->parameters[] = $value;
        }
    }
    */


    /*
     * Retrieve methods
     */

    /*
    public function retrieveAsQuery($parameters = null)
    {
        return $this->manager->processCriteriaAsQuery($this, $parameters);
    }

    public function retrieveAsArrayModel($parameters = null)
    {
        return $this->manager->processCriteriaAsArrayModel($this, $parameters);
    }

    public function retrieveAsCursor($parameters = null)
    {
        return $this->manager->processCriteriaAsCursor($this, $parameters);
    }

    public function retrieveAsProxyQuery($parameters = null)
    {
        return $this->manager->processCriteriaAsProxyQuery($this, $parameters);
    }

    public function retrieveAsProxyCursor($parameters = null)
    {
        return $this->manager->processCriteriaAsProxyCursor($this, $parameters);
    }
    */
}
