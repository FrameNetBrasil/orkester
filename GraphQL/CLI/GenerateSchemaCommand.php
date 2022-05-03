<?php

namespace Orkester\GraphQL\CLI;

use Orkester\Manager;
use Orkester\Persistence\Map\AssociationMap;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateSchemaCommand extends \Symfony\Component\Console\Command\Command
{
    protected static $defaultName = 'graphql:generate_schema';

    public function __construct(
        protected string $outDir = '',
        protected bool   $generateWhere = false,
        protected bool   $generateQuery = false,
        protected bool   $generateType = false
    )
    {
        parent::__construct();
    }

    public function configure()
    {
        $this
            ->addOption('schema', null, InputOption::VALUE_NONE)
            ->addOption('query', null, InputOption::VALUE_NONE)
            ->addOption('where', null, InputOption::VALUE_NONE)
            ->addOption('all', null, InputOption::VALUE_NONE)
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED);
    }


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
  $modelKey(
    id: ID, 
    limit: Int,
    offset: Int
    order_by: [OrderBy!]
    group: [String!]
    join: [Join!]
    where: Where$typename
  ): [$typename!]!
EOD;
    }

    public function createWhere(string $typename, array $gqlAttributes): string
    {
        $where = <<<EOD
input Where$typename {
  or: Where$typename
  and: Where$typename

EOD;
        foreach ($gqlAttributes as $attribute => $type) {
            $type = $type == 'ID' ? 'Int' : $type;
            $where .= "  $attribute: Where$type" . PHP_EOL;
        }
        $where .= '}' . PHP_EOL;
        return $where;
    }

    public function getTypename(string $model, ?OutputInterface $output)
    {
        if (preg_match_all("/([\w\d_]+)Model$/", $model, $matches)) {
            return $matches[1][0];
        } else {
            $output->write("Could not find model typename");
            throw new \InvalidArgumentException();
        }
    }

    public function isModelAvailable(string $className): bool
    {
        $conf = require Manager::getConfPath() . '/graphql.php';
        return in_array($className, $conf['models']);
    }


    public function createSchema($key, $model, ?OutputInterface $output): array
    {
        /** @var ClassMap $classMap */
        $classMap = $model::getClassMap();

        $typename = $this->getTypename($model, $output);
        $typedef = "type $typename {" . PHP_EOL;
        $typedef .= "  id: ID!" . PHP_EOL;
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
            $typedef .= "  {$attributeMap->getName()}$args: $type$null" . PHP_EOL;
        }
        /** @var AssociationMap $associationMap */
        foreach ($classMap->getAssociationMaps() as $associationMap) {
            if ($this->isModelAvailable($associationMap->getToClassName())) {
                $toTypename = $this->getTypename($associationMap->getToClassName(), $output);
                $cardinality = $associationMap->getCardinality() == 'oneToMany' ?
                    "[$toTypename!]!" : $toTypename;
                $typedef .= "  {$associationMap->getName()}: {$cardinality}" . PHP_EOL;
            }
        }
        $typedef .= "}" . PHP_EOL;
        return [
            'type' => $typename,
            'typedef' => $typedef,
            'where' => $this->createWhere($typename, $gqlAttributes),
            'query' => $this->createQuery($key, $typename)
        ];
    }

    public function execute(?InputInterface $input, ?OutputInterface $output)
    {
        if (!is_null($input)) {
            $all = $input->getOption('all');
            $this->generateWhere = $all || $input->getOption('where');
            $this->generateQuery = $all || $input->getOption('query');
            $this->generateType = $all || $input->getOption('schema');
            $this->outDir = $input->getOption('out');
        }
        $conf = require Manager::getConfPath() . '/graphql.php';
        if (!file_exists($this->outDir)) {
            mkdir($this->outDir, recursive: true);
        }
        $queries = [];
        foreach ($conf['models'] as $key => $model) {
            [
                'type' => $type,
                'typedef' => $typedef,
                'where' => $where,
                'query' => $queries[]
            ] = $this->createSchema($key, $model, $output);

            $filename = "$this->outDir/$type.graphql";
            $content =
                ($this->generateType ? $typedef . PHP_EOL : '') .
                ($this->generateWhere ? $where . PHP_EOL : '');
            if ($content) {
                file_put_contents($filename, $content);
            }
        }
        if ($this->generateQuery) {
            $allQueries = implode(PHP_EOL, $queries);
            $query = <<<EOD
type Query {
  $allQueries
}
EOD;
            file_put_contents("$this->outDir/Query.graphql", $query);
        }
        return Command::SUCCESS;
    }

}
