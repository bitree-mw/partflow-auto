<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * "Take me back to where I was" links: accepts only back-office URLs on this host, so it cannot be used as an open redirect.
 */
class ReturnUrl
{
    public static function from(Request $request, string $key = 'return_to'): ?string
    {
        $url = $request->input($key, $request->query($key));

        if (! is_string($url) || $url === '' || strlen($url) > 2000) {
            return null;
        }

        $parts = parse_url($url);
        $path = $parts['path'] ?? '';

        if ($parts === false || ! str_starts_with($path, '/back-office/')) {
            return null;
        }

        if (isset($parts['host']) && strcasecmp($parts['host'], $request->getHost()) !== 0) {
            return null;
        }

        return $url;
    }
}
