<?php

/*
 * Copyright (c) velkuns
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Eureka\Component\ClientApiGenerator\Schema;

use cebe\openapi\spec\Schema;

class JsonApiSchema extends ApiSchema
{
    public function getDataSchema(Schema $schema): ?Schema
    {
        //~ Handle oneOf list of sub-schema
        if (\is_array($schema->oneOf ?? null)) {
            foreach ($schema->oneOf as $oneSchema) {
                if (!($oneSchema instanceof Schema)) {
                    continue;
                }

                if (isset($oneSchema->properties['data'])) {
                    $schema = $oneSchema;
                    break;
                }
            }
        }

        $data = $schema->properties['data'] ?? null;

        if (\is_array($data->allOf ?? null)) {
            $data = $data->allOf[0] ?? null;
        }

        return $data instanceof Schema ? $data : null;
    }

    public function getErrorSchema(Schema $schema): ?Schema
    {
        //~ Handle oneOf list of sub-schema
        if (\is_array($schema->oneOf ?? null)) {
            foreach ($schema->oneOf as $oneSchema) {
                if (!($oneSchema instanceof Schema)) {
                    continue;
                }

                if (isset($oneSchema->properties['errors'])) {
                    $schema = $oneSchema;
                    break;
                }
            }
        }

        $data = $schema->properties['errors'] ?? null;

        return $data instanceof Schema ? $data : null;
    }
}
