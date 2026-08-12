<?php
/**
 * ClassicPOS Desktop — PHP Polyfills for PHP 8.4+
 *
 * mb_split() was removed in PHP 8.4. Reimplement using preg_split().
 */

// mb_split — removed in PHP 8.4
if (!function_exists('mb_split')) {
    function mb_split(string $pattern, string $string, int $limit = -1): array
    {
        $flags = PREG_SPLIT_NO_EMPTY;
        if ($limit === -1) {
            $limit = 0;
        }
        $result = preg_split('/' . $pattern . '/', $string, $limit, $flags);
        return $result !== false ? $result : [];
    }
}
