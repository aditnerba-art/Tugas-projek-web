<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO;

    public function handle(Request $request, Closure $next)
    {
        $proxies = config('app.trusted_proxies');
        $this->proxies = is_string($proxies)
            ? array_values(array_filter(
                array_map('trim', explode(',', $proxies)),
                fn (string $proxy): bool => $proxy !== ''
            ))
            : $proxies;

        return parent::handle($request, $next);
    }
}
