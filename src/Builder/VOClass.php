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
use Eureka\Component\ClientApiGenerator\BuilderInterface;
use Eureka\Component\ClientApiGenerator\Schema\SchemaResolver;
use Eureka\Component\ClientApiGenerator\Type\TypeResolver;
use Eureka\Component\ClientApiGenerator\Utils\Utils;
use Eureka\Component\ClientApiGenerator\VOBuilder;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Param;
use PhpParser\BuilderFactory;
use PhpParser\Comment\Doc;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\Return_;

class VOClass extends Class_
{
    public function __construct(
        string $name,
        private readonly VOBuilder $voBuilder,
        private readonly SchemaResolver $schemaResolver,
        private readonly TypeResolver $typeResolver,
    ) {
        parent::__construct($name);
    }

    public function addConstructor(Schema $schema): static
    {
        $factory = new BuilderFactory();

        [$params, $phpdoc] = $this->buildConstructorParams($schema);

        $method = $factory
            ->method('__construct')
            ->makePublic()
            ->addParams($params)
        ;

        if ($phpdoc !== []) {
            \array_unshift($phpdoc, "\n", '/**');
            $phpdoc[] = ' */';
            $method->setDocComment(new Doc(\implode("\n", $phpdoc)));
        } else {
            $method->setDocComment(new Doc(''));
        }

        $this->addStmt($method);

        return $this;
    }

    /**
     * @param list<string> $phpstanType
     * @param array<string, string> $phpstanImports
     */
    public function addJsonSerializeMethod(Schema $schema, string $subnamespace = '', array &$phpstanType = [], array &$phpstanImports = []): static
    {
        $factory = new BuilderFactory();

        $method = $factory
            ->method('jsonSerialize')
            ->makePublic()
            ->setReturnType('array')
        ;

        $phpdoc     = [];
        $arrayItems = [];

        /** @var list<string> $required */
        $required   = $schema->type === 'array' ? $schema->items->required ?? [] : $schema->required ?? [];
        /** @var array<string, Reference|Schema> $properties */
        $properties = $schema->type === 'array' ? $schema->items->properties ?? [] : $schema->properties;

        foreach ($properties as $name => $property) {
            $property = $this->schemaResolver->resolve($property);

            $isRequired    = \in_array($name, $required, true);
            $propertyName  = Utils::camelize($name);

            $type          = $this->typeResolver->resolve($property, $isRequired);
            $arrayItem     = new ArrayItem($factory->var("this->$propertyName"), $factory->val($propertyName));
            $arrayItems[]  = $arrayItem;
            $phpdoc[]      = "$propertyName: {$type->phpdoc()}";
            $phpstanType[] = "$name: {$type->phpdoc(suffix: 'InputData')}";

            if ($type->isValueObject()) {
                $phpstanImports[$type->real()] = $type->real(suffix: 'InputData');
                $this->voBuilder->add($property, $type, $subnamespace);
            }
        }

        $method->addStmt(new Return_(new Array_($arrayItems)));
        $method->setDocComment(new Doc("\n/**\n * @return array{\n *     " . implode(",\n *     ", $phpdoc) . ",\n * }\n */"));

        $this->addStmt($method);

        return $this;
    }

    /**
     * @return array{0: Param[], 1: string[]}
     */
    private function buildConstructorParams(Schema $schema): array
    {
        $factory = new BuilderFactory();
        $params  = [];
        $phpdoc  = [];

        /** @var list<string> $required */
        $required   = $schema->type === 'array' ? $schema->items->required ?? [] : $schema->required ?? [];
        /** @var array<string, Reference|Schema> $properties */
        $properties = $schema->type === 'array' ? $schema->items->properties ?? [] : $schema->properties;

        foreach ($properties as $name => $property) {
            if ($property instanceof Reference) {
                throw new \UnexpectedValueException('Cannot handle Reference type, only Schema type is supported!');
            }

            $isRequired   = \in_array($name, $required, true);
            $propertyName = Utils::camelize($name);

            $type         = $this->typeResolver->resolve($property, $isRequired);
            $phpdoc[]     = " * @param {$type->phpdoc()} \$$propertyName";

            $param = $factory->param($propertyName)
                ->setType($type->php())
                ->makePublic()
                ->makeReadonly()
            ;

            //            if (isset($property->default) && \is_scalar($property->default)) {
            //                $param->setDefault($factory->val($property->default));
            //            }

            $params[] = $param;
        }

        return [$params, $phpdoc];
    }
}
