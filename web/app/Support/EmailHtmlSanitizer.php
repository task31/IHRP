<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

final class EmailHtmlSanitizer
{
    public static function sanitize(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return null;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,a[href|title],ul,ol,li,span,div,blockquote,table,thead,tbody,tr,th,td');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);

        $purifier = new HTMLPurifier($config);

        return $purifier->purify($html);
    }
}
