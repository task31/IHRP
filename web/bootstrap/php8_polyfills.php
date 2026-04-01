<?php

/**
 * PHP 8 compatibility polyfills.
 *
 * get_magic_quotes_runtime() and set_magic_quotes_runtime() were removed in
 * PHP 8.0. setasign/fpdf 1.8 still calls them in its Output() method.
 * Magic quotes were always disabled in practice; these stubs preserve that
 * behaviour so FPDF can generate PDFs on PHP 8.x without modification.
 */
if (! function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime(): int
    {
        return 0;
    }
}

if (! function_exists('set_magic_quotes_runtime')) {
    function set_magic_quotes_runtime(int $value): bool
    {
        return true;
    }
}
