<?php

/*
 * Copyright (c) velkuns
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Eureka\Component\ClientApiGenerator\Schema;

use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Schema;

class ApiSchema
{
    public function getResponseSchema(Operation $operation, int $code): Schema
    {
        /** @var Response|null $response */
        $response = $operation->responses[(string) $code] ?? null;

        if ($response === null) {
            throw new \UnexpectedValueException("No response for code '$code', cannot process endpoint !");
        }

        $schema = $response->content['application/json']->schema ?? $response->content['text/plain']->schema ?? null;

        if ($schema === null) {
            throw new \UnexpectedValueException("No schema found in response ! (tried for application/json and text/plain)");
        }

        if ($schema instanceof Reference) {
            throw new \UnexpectedValueException("Schema 'Reference' type not supported !");
        }

        return $schema;
    }

    public function getBodySchema(Operation $operation): ?Schema
    {
        /** @var Schema|Reference|null $body */
        $body = $operation->requestBody->content['application/json']->schema ?? null;

        if ($body instanceof Reference) {
            $body = null;
        }

        return $body;
    }
}
