<?php

/*
 * Copyright (c) velkuns
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Eureka\Component\ClientApiGenerator\Builder;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Eureka\Component\ClientApiGenerator\FormatterBuilder;
use Eureka\Component\ClientApiGenerator\Schema\SchemaResolver;
use Eureka\Component\ClientApiGenerator\Type\Type;
use Eureka\Component\ClientApiGenerator\Type\TypeResolver;
use Eureka\Component\ClientApiGenerator\VOBuilder;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Param;
use PhpParser\BuilderFactory;
use PhpParser\Comment\Doc;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Cast\Array_;
use PhpParser\Node\Expr\Cast\Bool_;
use PhpParser\Node\Expr\Cast\Double;
use PhpParser\Node\Expr\Cast\Int_;
use PhpParser\Node\Expr\Cast\String_;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Return_;

class FormatterClass extends Class_
{
    public function __construct(
        string $name,
        private readonly Schema $schema,
        private readonly FormatterBuilder $formatterBuilder,
        private readonly VOBuilder $voBuilder,
        private readonly SchemaResolver $schemaResolver,
        private readonly TypeResolver $typeResolver,
    ) {
        parent::__construct($name);
    }

    /**
     * @param string[] $interfaces
     */
    public function setPhpDocWithGeneric(Type $type, array $interfaces): self
    {
        $phpdoc = ['/**'];
        if (isset($interfaces['FormatterInterface'])) {
            $phpdoc[] = " * @implements FormatterInterface<{$type->real('VO')}>";
        }

        if (isset($interfaces['ListFormatterInterface'])) {
            $phpdoc[] = " * @implements ListFormatterInterface<{$type->real('VO')}>";
        }

        if ($type->isValueObject()) {
            $phpdoc[] = " * @phpstan-import-type {$type->real(suffix: 'InputData')} from {$type->real('VO')}";
        }

        $phpdoc[] = ' */';

        $this->setDocComment(new Doc(\implode("\n", $phpdoc)));

        return $this;
    }

    public function addFormatItemMethod(Type $type): self
    {
        $factory = new BuilderFactory();

        $method = $factory->method('formatItem')
            ->makeStatic()
            ->makePublic()
            ->addParams([$factory->param('data')])
            ->setReturnType($type->real('VO'))
            ->setDocComment(new Doc("\n/**\n * @param {$type->real(suffix: 'InputData')} \$data\n */"))
        ;

        if ($type->isValueObject()) {
            $phpdoc = [];
            $args   = $this->buildConstructorArgs($this->schema, $phpdoc);
            $method->addStmt(new Return_(new New_(new Name($type->real('VO')), $args)));
        } else {
            $var  = new Variable('data');
            $cast = match ($type->real()) {
                'int' => new Int_($var),
                'bool' => new Bool_($var),
                'float' => new Double($var),
                'array' => new Array_($var),
                default => new String_($var),
            };
            $method->addStmt(new Return_($cast));
        }

        $this->addStmt($method);

        return $this;
    }

    /**
     * @param list<string> $phpdoc
     * @return Arg[]
     */
    private function buildConstructorArgs(Schema $schema, array &$phpdoc): array
    {
        $factory = new BuilderFactory();
        $params  = [];

        /** @var list<string> $required */
        $required   = $schema->type === 'array' ? $schema->items->required ?? [] : $schema->required ?? [];
        /** @var array<string, Reference|Schema> $properties */
        $properties = $schema->type === 'array' ? $schema->items->properties ?? [] : $schema->properties;

        foreach ($properties as $name => $property) {
            $property = $this->schemaResolver->resolve($property);

            $isVO = false;
            $type = $this->typeResolver->resolve($property, \in_array($name, $required, true));
            $phpdoc[] = "$name: {$type->php()}";

            if ($type->isArray() && ($type->subType?->isValueObject() ?? false)) {
                $dataVar    = new Variable("data->$name" . ($type->nullable ? ' ?? []' : ''));
                $expression = $factory->staticCall($type->formatter(), 'formatItemList', [$dataVar]);
                $isVO       = true;
            } elseif ($type->isValueObject()) {
                $dataVar    = new Variable("data->$name");
                $callMethod = $factory->staticCall($type->formatter(), 'formatItem', [$dataVar]);
                $expression = $type->nullable ? new Ternary(new Isset_([$dataVar]), $callMethod, $factory->val(null)) : $callMethod;
                $isVO       = true;
            } else {
                $expression = new Variable("data->$name" . ($type->nullable ? ' ?? null' : ''));
            }

            $params[] = new Arg($expression);

            if ($isVO) {
                //~ Be sure we also have formatter & VO classes for this VO property
                $this->formatterBuilder->add($property, $type);
                $this->voBuilder->add($property, $type, 'VO'); // Be sure we also have for subtype
            }
        }

        return $params;
    }
}
