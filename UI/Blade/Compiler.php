<?php

namespace Orkester\UI\Blade;

use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Contracts\View\Factory;

class Compiler extends BladeCompiler
{
    protected BladeCompiler $compiler;

    public function __construct(
        protected ?ContainerContract $container = null,
        public Factory $viewFactory
    ) {
        $this->compiler = $container->get('blade.compiler');
    }

    public function __call(string $name, array $arguments)
    {
        if (method_exists($this->compiler, $name)) {
            return $this->compiler->{$name}(...$arguments);
        }
    }

    public function componentPath(string $path, string $prefix = null)
    {
        $prefixHash = md5($prefix ?: $path);

        $this->anonymousComponentPaths[] = [
            'path' => $path,
            'prefix' => $prefix,
            'prefixHash' => $prefixHash,
        ];

//        Container::getInstance()
//            ->make(ViewFactory::class)
//            ->addNamespace($prefixHash, $path);
        $this->viewFactory->addNamespace($prefixHash, $path);
    }

    protected function compileComponentTags($value)
    {
        if (! $this->compilesComponentTags) {
            return $value;
        }

        return (new ComponentTagCompiler(
            $this->classComponentAliases, $this->classComponentNamespaces, $this
        ))->compile($value);
    }

}
