<?php

namespace App\Support;

/**
 * Page size chosen with the pagination bar's "Per page" control
 * (?per_page=). Only listed sizes are accepted so a request cannot ask for
 * thousands of rows at once.
 */
class PerPage
{
    public const LIST = [10, 20, 50, 100];

    /** @param int[] $options */
    public static function resolve(int $default = 20, array $options = self::LIST): int
    {
        $requested = (int) request()->query('per_page', $default);

        return in_array($requested, $options, true) ? $requested : $default;
    }

    /** Sizes that fill whole rows of a grid with $columns columns. */
    public static function grid(int $default): array
    {
        return [$default, $default * 2, $default * 4, $default * 8];
    }
}
