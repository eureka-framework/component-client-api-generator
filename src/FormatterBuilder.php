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
use PhpParser\BuilderFactory;
use PhpParser\Comment\Doc;
use PhpParser\Node\DeclareItem;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\TraitUse;
use Eureka\Component\ClientApiGenerator\Builder\FormatterClass;
use Eureka\Component\ClientApiGenerator\Printer\CustomStandard;

class FormatterBuilder implements BuilderInterface
{
    /** @var array<string, array{class: FormatterClass, type: Type}> */
    private array $formatters = [];

    /** @var string[][] */
    private array $formattersImplements = [];

    public function __construct(
        private readonly BuilderFactory $factory,
        private readonly VOBuilder $voBuilder,
        private readonly SchemaResolver $schemaResolver,
        private readonly TypeResolver $typeResolver,
    ) {}

    public function generate(string $destination, string $baseNamespace): void
    {
        $prettyPrinter = new CustomStandard(['shortArraySyntax' => true, 'newMultiline' => true]);
        $declare       = new Declare_([new DeclareItem('strict_types', new Int_(1))]);

        foreach ($this->formatters as $className => ['class' => $class, 'type' => $type]) {
            /** @var FormatterClass $class */
            $class
                ->implement(...$this->formattersImplements[$className])
                ->setPhpDocWithGeneric($type, $this->formattersImplements[$className])
            ;

            $namespace = $this->factory->namespace("$baseNamespace\Formatter");

            if ($type->isValueObject()) {
                $namespace = $namespace->addStmt($this->factory->use("$baseNamespace\VO"));
            }

            $namespace
                ->setDocComment(new Doc(''))
                ->addStmt($class)
            ;

            $content   = $prettyPrinter->prettyPrintFile([$declare, $namespace->getNode()]);
            \file_put_contents("$destination/Formatter/$className.php", $content);
        }
    }

    public function add(Schema $schema, Type $type): void
    {
        $className = $type->formatter();

        if (!isset($this->formatters[$className])) {
            $class = new FormatterClass($className, $schema, $this, $this->voBuilder, $this->schemaResolver, $this->typeResolver);
            $class = $this->generateMethods($class, $type);

            $this->formatters[$className] = ['class' => $class, 'type' => $type];
        }

        $implements = $type->isArray() ? 'ListFormatterInterface' : 'FormatterInterface';
        $this->formattersImplements[$className][$implements] = $implements;
    }

    private function generateMethods(FormatterClass $class, Type $type): FormatterClass
    {
        $trait  = new TraitUse([new Name('FormatterTrait')]);
        $trait->setDocComment(new Doc("/** @use FormatterTrait<{$type->real('VO')}> */"));

        $class
            ->addStmt($trait)
            ->addFormatItemMethod($type)
        ;

        return $class;
    }
}
