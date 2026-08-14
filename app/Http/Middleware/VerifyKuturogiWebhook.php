<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyKuturogiWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('kuturogi.inbound_webhook_secret');

        if (empty($secret)) {
            abort(503, 'Webhook secret is not configured.');
        }

        $signature = $request->header('X-Kuturogi-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, (string) $signature)) {
            abort(401, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
