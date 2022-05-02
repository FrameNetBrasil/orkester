<?php

namespace Orkester\GraphQL\CLI;

use Orkester\Manager;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateSchemaCommand extends \Symfony\Component\Console\Command\Command
{
    protected static $defaultName = 'generate:schema';

    public function getAllModels($namespace): array
    {
        $files = scandir(Manager::getAppPath() . "/Models");

        $classes = array_map(function ($file) use ($namespace) {
            return $namespace . '\\' . str_replace('.php', '', $file);
        }, $files);
        return array_filter($classes, function ($possibleClass) {
            return class_exists($possibleClass);
        });
    }

    public function createQuery(string $modelKey, string $typename): string
    {
        return <<<EOD
Query {
  $modelKey(
    id: ID, 
    limit: Int,
    offset: Int
    order_by: [OrderBy!]
    group: [String!]
    join: [Join!]
    where: Where$typename
  ): [$typename!]!
}
EOD;
    }

    public function createWhere(string $typename, array $gqlAttributes): string
    {
        $where = <<<EOD
type Where$typename {
  or: Where$typename
  and: Where$typename

EOD;
        foreach ($gqlAttributes as $attribute => $type) {
            $where .= "  $attribute: Where$type" . PHP_EOL;
        }
        $where .= '}' . PHP_EOL;
        return $where;
    }

    public function getTypename(string $model, OutputInterface $output)
    {
        if (preg_match_all("/([\w\d_]+)Model$/", $model, $matches)) {
            return $matches[1][0];
        } else {
            $output->write("Could not find model typename");
            throw new \InvalidArgumentException();
        }
    }


    public function createSchema($key, $model, OutputInterface $output): array
    {
        /** @var ClassMap $classMap */
        $classMap = $model::getClassMap();

        $typename = $this->getTypename($model, $output);
        $schema = "type $typename {" . PHP_EOL;
        $schema .= "  id: ID!" . PHP_EOL;
        $gqlAttributes = [];
        /** @var AttributeMap $attributeMap */
        foreach ($classMap->getAttributesMap() as $attributeMap) {
            if ($attributeMap->getKeyType() != 'none') {
                $type = 'ID';
            } else {
                $type = match ($attributeMap->getType()) {
                    'int', 'integer' => 'Int',
                    'boolean', 'bool' => 'Boolean',
                    default => 'String'
                };
            }
            $gqlAttributes[$attributeMap->getName()] = $type;
            $null = ($attributeMap->getKeyType() == 'primary' || !$attributeMap->isNullable())
                ? '!' : '';
            $arguments = [];
            if (in_array($attributeMap->getType(), ['date', 'datetime', 'time'])) {
                $arguments[] = "format: String";
            }
            $args = "";
            if (!empty($arguments)) {
                $args = "(" . implode(' ', $arguments) . ")";
            }
            $schema .= "  {$attributeMap->getName()}$args: $type$null" . PHP_EOL;
        }
        /** @var AssociationMap $associationMap */
        foreach($classMap->getAssociationMaps() as $associationMap) {
            $toTypename = $this->getTypename($associationMap->getToClassName(), $output);
            $cardinality = $associationMap->getCardinality() == 'oneToMany' ?
                "[$toTypename!]!" : $toTypename;
            $schema .= "  {$associationMap->getName()}: {$cardinality}" . PHP_EOL;
        }
        $schema .= "}" . PHP_EOL;
        return [
            'type' => $typename,
            'schema' => $schema,
            'where' => $this->createWhere($typename, $gqlAttributes),
            'query' => $this->createQuery($key, $typename)
        ];
    }

    public function configure()
    {
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $conf = require Manager::getConfPath() . '/graphql.php';
        $path = Manager::getBasePath() . "/app/Schema/Generated";
        if (!file_exists($path)) {
            mkdir($path, recursive: true);
        }
        foreach ($conf['models'] as $key => $model) {
            [
                'type' => $type,
                'schema' => $schema,
                'where' => $where,
                'query' => $query
            ] = $this->createSchema($key, $model, $output);
            $filename = "$path/$type.graphql";
            file_put_contents($filename,
                $schema . PHP_EOL .
                $query . PHP_EOL . PHP_EOL .
                $where . PHP_EOL
            );
        }
        return Command::SUCCESS;
    }
}
