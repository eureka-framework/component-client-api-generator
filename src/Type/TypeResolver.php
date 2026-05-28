<?php

/*
 * Copyright (c) velkuns
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Eureka\Component\ClientApiGenerator\Type;

use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Eureka\Component\ClientApiGenerator\BuilderInterface;
use Eureka\Component\ClientApiGenerator\Config\Config;
use Eureka\Component\ClientApiGenerator\Utils\Utils;

readonly class TypeResolver
{
    public function __construct(private Config $config) {}

    public function resolve(Schema $schema, bool $isRequired, bool $isCustom = false): Type
    {
        if (isset($schema->oneOf)) {
            $types = $this->resolveOneOf($schema, $isRequired);
            if (count($types) === 1) {
                return $types[0];
            }

            //throw new \UnexpectedValueException('TODO: Handle multiple types for schema: ' . ($schema->title ?? ' unknown'));
        }

        if (isset($schema->allOf)) {
            if ($schema->allOf[0] instanceof Schema) {
                return $this->resolve($schema->allOf[0], $isRequired);
            }

            throw new \UnexpectedValueException('allOf without Schema type is not supported for schema: ' . ($schema->title ?? ' unknown'));
        }

        $type = $schema->type ?? '';
        if ($type === '') {
            $type = 'string';
            echo 'Missing type for schema: ' . ($schema->title ?? ' unknown') . ', defaulting to string' . "\n";
            //throw new \UnexpectedValueException('No type specified for schema: ' . ($schema->title ?? ' unknown'));
        }

        $native = match ($type) {
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            default => $type,
        };

        $title = isset($schema->title) ? \str_replace([' ', '-', '_'], '', \ucwords($schema->title, " -_")) : null;
        $name = match ($native) {
            'string', 'int', 'float', 'bool', 'array' => $native,
            default => $title ?? $native,
        };

        $native   = $this->config->overrideNativeType($name, $native);
        $real     = $this->config->overrideRealType($name, $name);

        $nullable = !$isRequired;
        $default  = isset($schema->default) && \is_scalar($schema->default) ? $schema->default : null;
        $subType  = null;

        if ($native === 'array' && isset($schema->items) && $schema->items instanceof Schema) {
            $subType = $this->resolve($schema->items, true);
            $real    = $subType->real;
        }

        return new Type(
            $native,
            $real,
            $nullable,
            $default,
            $subType,
        );
    }

    /**
     * @return list<Type>
     */
    private function resolveOneOf(Schema $schema, bool $isRequired): array
    {
        $types = [];
        foreach ($schema->oneOf as $oneOf) {
            if ($oneOf instanceof Schema) {
                $type = $this->resolve($oneOf, $isRequired);
                $types["$type->native.$type->real"] = $type;
            }
        }

        return  \array_values($types);
    }
}
