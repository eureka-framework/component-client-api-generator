<?php

/*
 * Copyright (c) velkuns
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Eureka\Component\ClientApiGenerator\Schema;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Eureka\Component\ClientApiGenerator\Config\Config;

class SchemaResolver
{
    public function __construct(private readonly Config $config) {}

    public function resolve(Schema|Reference|null $schema): Schema
    {
        if ($schema === null || $schema instanceof Reference) {
            throw new \UnexpectedValueException('Givent  type, only Schema type is supported!');
        }

        if (isset($schema->allOf) && \is_array($schema->allOf)) {
            $schema = $this->resolve($schema->allOf[0] ?? null);
        }

        return $this->config->overrideSchema($schema);
    }
}
