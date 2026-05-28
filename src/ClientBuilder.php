<?php

/*
 * Copyright (c) velkuns
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Eureka\Component\ClientApiGenerator;

use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Eureka\Component\ClientApiGenerator\Schema\JsonApiSchema;
use Eureka\Component\ClientApiGenerator\Schema\SchemaResolver;
use Eureka\Component\ClientApiGenerator\Type\Type;
use Eureka\Component\ClientApiGenerator\Type\TypeResolver;
use Eureka\Component\ClientApiGenerator\Utils\Utils;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Param;
use PhpParser\BuilderFactory;
use PhpParser\Comment\Doc;
use PhpParser\Node\DeclareItem;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Declare_;
use Eureka\Component\ClientApiGenerator\Builder\ClientMethod;
use Eureka\Component\ClientApiGenerator\Enum\OperationType;
use Eureka\Component\ClientApiGenerator\Printer\CustomStandard;

class ClientBuilder
{
    /** @var Class_[] */
    private array $classes = [];

    public function __construct(
        private readonly BuilderFactory $factory,
        private readonly FormatterBuilder $formatterBuilder,
        private readonly VOBuilder $voBuilder,
        private readonly JsonApiSchema $apiSchema,
        private readonly SchemaResolver $schemaResolver,
        private readonly TypeResolver $typeResolver,
    ) {}

    public function generate(string $destination, string $baseNamespace): void
    {
        $prettyPrinter = new CustomStandard(['shortArraySyntax' => true]);
        $declare       = new Declare_([new DeclareItem('strict_types', new Int_(1))]);

        foreach ($this->classes as $className => $class) {
            $namespace = $this->factory
                ->namespace("$baseNamespace\Client")
                ->setDocComment(new Doc(''))
                ->addStmt($this->factory->use('Psr\Http\Client\ClientExceptionInterface'))
                ->addStmt($this->factory->use("$baseNamespace\Exception\ClientException"))
                ->addStmt($this->factory->use("$baseNamespace\Exception\ComponentException"))
                ->addStmt($this->factory->use("$baseNamespace\Formatter"))
                ->addStmt($this->factory->use("$baseNamespace\VO"))
                ->addStmt($this->factory->use('JsonException'))
                ->addStmt($class);
            $content   = $prettyPrinter->prettyPrintFile([$declare, $namespace->getNode()]);
            \file_put_contents($destination . "/Client/$className.php", $content);
        }

    }

    public function add(string $path, PathItem $pathItem): void
    {
        $parts = \explode('/', \trim($path, '/'), 2);
        $name = \count($parts) === 1 ? '' : Utils::pascalize($parts[0]);

        if (!isset($this->classes["{$name}Client"])) {
            $class = $this->factory->class("{$name}Client")
                ->extend('AbstractClient')
                ->setDocComment(new Doc(''))
            ;
        } else {
            $class = $this->classes["{$name}Client"];
        }

        foreach (OperationType::cases() as $operationType) {
            if (!isset($pathItem->{$operationType->value})) {
                continue;
            }

            $operation = $pathItem->{$operationType->value};
            $class->addStmt($this->generateMethod($path, $operation, $operationType));
        }

        $this->classes["{$name}Client"] = $class;
    }

    private function generateMethod(string $path, Operation $operation, OperationType $operationType): ClientMethod
    {
        $methodName  = $this->getClientMethodName($operation);

        $pathParams  = $this->generatePathParams($operation);
        $bodyParams  = $this->generateBodyParams($operation);
        $queryParams = $this->generateQueryParams($operation);

        $schema = $this->apiSchema->getResponseSchema($operation, 200);
        $schema = $this->schemaResolver->resolve($schema);

        $data   = $this->apiSchema->getDataSchema($schema);
        $data   = $this->schemaResolver->resolve($data ?? $schema);

        $type   = $this->typeResolver->resolve($data, true);
        $phpdoc = $this->getPhpdocTypedArray($operation, $type);

        $this->formatterBuilder->add($data, $type);
        $this->voBuilder->add($data, $type, subnamespace: 'VO');

        return (new ClientMethod($methodName))
            ->makePublic()
            ->addParams([...$pathParams, ...$bodyParams, ...$queryParams])
            ->setReturnType($type->return('VO'))
            ->setDocComment($phpdoc)
            //~ Specific client methods
            ->addAssignEndpoint($path, $this->filterPathParams($operation))
            ->addCallBuilder($queryParams !== [], $bodyParams !== [], $operationType)
            ->addReturnData($type)
        ;
    }

    /**
     * @return list<Param>
     */
    private function generatePathParams(Operation $operation): array
    {
        $params = [];

        foreach ($operation->parameters as $param) {
            if ($param instanceof Reference || !$param->schema instanceof Schema || $param->in !== "path") {
                continue;
            }

            $schema = $this->schemaResolver->resolve($param->schema);

            $type = $this->typeResolver->resolve($schema, true);
            $params[] = $this->factory
                ->param($param->name)
                ->setType($type->real())
            ;
        }

        return $params;
    }

    /**
     * @return list<Param>
     */
    private function generateQueryParams(Operation $operation): array
    {
        $queryParams = $this->filterQueryParams($operation);
        if ($queryParams === []) {
            return [];
        }

        $param = $this->factory
            ->param('query')
            ->setType('array')
            ->setDefault([])
        ;

        return [$param];
    }

    /**
     * @return list<Param>
     */
    private function generateBodyParams(Operation $operation): array
    {
        $bodySchema = $this->apiSchema->getBodySchema($operation);

        if ($bodySchema === null) {
            return [];
        }

        $bodySchema = $this->schemaResolver->resolve($bodySchema);
        $bodyType   = $this->typeResolver->resolve($bodySchema, true);
        $param = $this->factory
            ->param('body')
            ->setType($bodyType->real('VO\\RequestBody'))
        ;

        $this->voBuilder->add($bodySchema, $bodyType, subnamespace: 'VO\RequestBody');

        return [$param];
    }

    /**
     * @return list<Parameter>
     */
    private function filterPathParams(Operation $operation): array
    {
        $params = [];

        foreach ($operation->parameters as $param) {
            if ($param instanceof Reference || $param->in !== "path") {
                continue;
            }

            $params[] = $param;
        }

        return $params;
    }

    /**
     * @return list<Parameter>
     */
    private function filterQueryParams(Operation $operation): array
    {
        $params = [];

        foreach ($operation->parameters as $param) {
            if ($param instanceof Reference || $param->in !== "query") {
                continue;
            }

            $params[] = $param;
        }

        return $params;
    }

    private function getPhpdocTypedArray(Operation $operation, Type $type): Doc
    {
        $phpdoc = [
            'new'    => '',
            'top'    => '/**',
            'query'  => ' * @param array{#ARRAY_TYPES#} $query',
            'return' => ' * @return ' . $type->phpdoc('VO'),
            'throws' => ' * @throws ClientException|ComponentException|ClientExceptionInterface|JsonException',
            'end'    => ' */',
        ];

        $params = $this->filterQueryParams($operation);
        if ($params !== []) {
            $types = [];
            foreach ($params as $param) {
                if (!isset($param->schema) || $param->schema instanceof Reference) {
                    continue;
                }
                $schema = $this->schemaResolver->resolve($param->schema);
                $paramType = $this->typeResolver->resolve($schema, $param->required);
                $types[] = $param->name . (!$param->required ? '?:' : ':') . $paramType->real;
            }

            $phpdoc['query'] = \str_replace('#ARRAY_TYPES#', \implode(', ', $types), $phpdoc['query']);
        } else {
            unset($phpdoc['query']);
        }

        if (!$type->isArray()) {
            unset($phpdoc['return']); // Remove useless phpdoc
        }

        return new Doc(\implode("\n", $phpdoc));
    }

    private function getClientMethodName(Operation $operation): string
    {
        $name = $operation->summary !== '' && $operation->summary !== null ? $operation->summary : $operation->operationId;
        $name = \ltrim($name, 'post');

        return Utils::camelize($name);
    }
}
