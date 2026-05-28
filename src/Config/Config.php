<?php

/*
 * Copyright (c) velkuns
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Eureka\Component\ClientApiGenerator\Config;

use cebe\openapi\spec\Schema;

/**
 * @phpstan-type ConfigData array{
 *     file?: string,
 *     namespace?: string,
 *     destination?: string,
 *     ignorePathsStartWith: list<string>,
 *     overrides?: array{
 *         types?: array<string, array{native?: string, real?: string}>,
 *         schemas: array<string, string>,
 *     },
 *     custom?: array{
 *         schemas?: array<string, array{properties?: array<string, array{type: string, items?: array{type: string}}>}>,
 *     },
 * }
 */
class Config
{
    /**
     * @param list<string> $ignorePaths
     * @param array<string, array{native?: string, real?: string}> $overrideTypes
     * @param array<string, string> $overrideSchemas
     * @param Schema[] $customSchemas
     */
    public function __construct(
        public readonly string $file,
        public readonly string $namespace,
        public readonly string $destination,
        public readonly array $ignorePaths,
        public readonly array $overrideTypes,
        public readonly array $overrideSchemas,
        public readonly array $customSchemas,
    ) {}

    public function isIgnoredPath(string $path): bool
    {
        foreach ($this->ignorePaths as $ignorePath) {
            if (\preg_match('`' . $ignorePath . '`', $path) === 1) {
                return true;
            }
        }

        return false;
    }

    public function overrideNativeType(string $name, string $type): string
    {
        return $this->overrideTypes[$name]['native'] ?? $type;
    }

    public function overrideRealType(string $name, string $type): string
    {
        return $this->overrideTypes[$name]['real'] ?? $type;
    }

    public function overrideSchema(Schema $schema): Schema
    {
        $name = $this->overrideSchemas[$schema->title ?? ''] ?? '';

        return $this->customSchemas[$name] ?? $schema;
    }
}
