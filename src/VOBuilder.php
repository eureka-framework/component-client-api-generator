<?php

/*
 * Copyright (c) velkuns
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Eureka\Component\ClientApiGenerator;

use cebe\openapi\spec\Schema;
use Eureka\Component\ClientApiGenerator\Schema\SchemaResolver;
use Eureka\Component\ClientApiGenerator\Type\Type;
use Eureka\Component\ClientApiGenerator\Type\TypeResolver;
use PhpParser\Builder\Class_;
use PhpParser\BuilderFactory;
use PhpParser\Comment\Doc;
use PhpParser\Node\DeclareItem;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Declare_;
use Eureka\Component\ClientApiGenerator\Builder\VOClass;
use Eureka\Component\ClientApiGenerator\Printer\CustomStandard;

class VOBuilder implements BuilderInterface
{
    /** @var array<string, array{className: string, class: Class_, subnamespace: string}> */
    private array $classes = [];

    public function __construct(
        private readonly BuilderFactory $factory,
        private readonly SchemaResolver $schemaResolver,
        private readonly TypeResolver $typeResolver,
    ) {}

    public function generate(string $destination, string $baseNamespace): void
    {
        $prettyPrinter = new CustomStandard(['shortArraySyntax' => true, 'arrayMultiline' => true, 'methodMultiline' => true]);
        $declare       = new Declare_([new DeclareItem('strict_types', new Int_(1))]);

        foreach ($this->classes as ['className' => $className, 'subnamespace' => $subnamespace, 'class' => $class]) {
            $path = $this->initDirectory($destination, $subnamespace);

            $namespace = $this->factory
                ->namespace("$baseNamespace\\$subnamespace")
                ->addStmt($this->factory->use('Eureka\Component\Serializer\JsonSerializableTrait'))
                ->addStmt($this->factory->use('JsonSerializable'))
                ->setDocComment(new Doc(''))
                ->addStmt($class);
            $content = $prettyPrinter->prettyPrintFile([$declare, $namespace->getNode()]);
            \file_put_contents("$path/$className.php", $content);
        }
    }

    public function add(Schema $schema, Type $type, string $subnamespace = ''): void
    {
        if (!$type->isValueObject()) {
            return;
        }

        if (isset($this->classes[$type->real($subnamespace)])) {
            return;
        }

        $phpstanType    = [];
        $phpstanImports = [];
        $class = (new VOClass($type->real, $this, $this->schemaResolver, $this->typeResolver))
            ->implement('JsonSerializable')
            ->addStmt($this->factory->useTrait('JsonSerializableTrait'))
            ->addConstructor($schema)
            ->addJsonSerializeMethod($schema, $subnamespace, $phpstanType, $phpstanImports)
        ;

        $doc = new Doc('');
        if ($phpstanType !== []) {
            $phpstanTypeDoc    = " * @phpstan-type {$type->real(suffix: 'InputData')} object{" . \implode(', ', $phpstanType) . '}&\stdClass';
            $phpstanImportsDoc = [];
            foreach ($phpstanImports as $from => $phpstanImport) {
                $phpstanImportsDoc[] = " * @phpstan-import-type $phpstanImport from $from";
            }
            $importDoc = ($phpstanImportsDoc !== [] ? "\n" : '') . implode("\n", $phpstanImportsDoc);
            $doc = new Doc("\n/**$importDoc\n$phpstanTypeDoc\n */");
        }


        $class->setDocComment($doc);

        $this->classes[$type->real($subnamespace)] = ['className' => $type->real, 'subnamespace' => $subnamespace, 'class' => $class];
    }

    private function initDirectory(string $destination, string $namespace): string
    {
        $subpath = str_replace('\\', '/', $namespace);

        if (!\is_dir("$destination/$subpath") && !\mkdir("$destination/$subpath", 0755, true)) {
            throw new \UnexpectedValueException("Failed to create directory: $destination/$subpath");
        }

        return "$destination/$subpath";
    }
}
