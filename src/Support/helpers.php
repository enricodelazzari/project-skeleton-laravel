<?php

if (! function_exists('pagination_limit')) {
    function pagination_limit(int $default = 16, int $max = 48): int
    {
        $limit = request()->get('limit') ?? $default;

        return min($limit, $max);
    }
}
