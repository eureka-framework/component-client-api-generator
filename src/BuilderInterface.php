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
use Eureka\Component\ClientApiGenerator\Type\Type;

interface BuilderInterface
{
    public function generate(string $destination, string $baseNamespace): void;

    public function add(Schema $schema, Type $type): void;
}
