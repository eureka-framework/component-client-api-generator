<?php

/*
 * Copyright (c) velkuns
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Eureka\Component\ClientApiGenerator\Type;

readonly class Type implements \Stringable
{
    public function __construct(
        public string $native,
        public string $real,
        public bool $nullable,
        public string|int|float|bool|null $default = null,
        public ?Type $subType = null,
    ) {}

    public function isScalar(): bool
    {
        return match ($this->native) {
            'string', 'int', 'float', 'bool' => true,
            default => false,
        };
    }

    public function isArray(): bool
    {
        return $this->native === 'array';
    }

    public function isValueObject(): bool
    {
        $type = $this->subType === null ? $this->real : $this->subType->real;

        return match ($type) {
            'string', 'int', 'float', 'bool', 'array', 'object' => false,
            default => true,
        };
    }

    public function formatter(): string
    {
        return ucfirst($this->subType === null ? $this->real : $this->subType->real) . 'Formatter';
    }

    public function real(string $namespace = '', string $suffix = ''): string
    {
        $type = $this->subType === null ? $this->real : $this->subType->real;

        return match ($type) {
            'string', 'int', 'float', 'bool', 'array', 'object' => $type,
            default => \trim(\trim($namespace, '\\') . '\\' . $type . $suffix, '\\'),
        };
    }

    public function return(string $namespace = ''): string
    {
        return $this->isArray() ? $this->native : $this->real($namespace);
    }

    public function php(string $namespace = ''): string
    {
        $type = $this->subType === null ? $this : $this->subType;
        $real = $this->native === 'array' ? $this->native : $type->real($namespace);

        return ($this->nullable ? '?' : '') . $real;
    }

    public function phpdoc(string $namespace = '', string $suffix = ''): string
    {
        return ($this->nullable ? '?' : '') . $this->real($namespace, $suffix) . ($this->isArray() ? '[]' : '');
    }

    public function __toString(): string
    {
        return $this->php() . ' | ' . $this->phpdoc() . ' | ' . $this->real() . ' | ' . $this->native;
    }
}
