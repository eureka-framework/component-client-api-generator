<?php

/*
 * Copyright (c) velkuns
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Eureka\Component\ClientApiGenerator\Utils;

class Utils
{
    public static function camelize(string $string): string
    {
        return \lcfirst(
            \str_replace(
                [' ', '_', '-'],
                '',
                \ucwords(\strtolower($string), " _-"),
            ),
        );
    }

    public static function pascalize(string $string): string
    {
        return \str_replace(
            [' ', '_', '-'],
            '',
            \ucwords(\strtolower($string), " _-"),
        );
    }
}
