<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use YezzMedia\Ops\Support\OpsAuthorizationResolver;

/**
 * Enforces the ops panel boundary after authentication succeeds.
 */
final class AuthorizeOpsPanelAccess
{
    public function __construct(private readonly OpsAuthorizationResolver $authorization) {}

    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null || ! $this->authorization->canAccessPanel($panel)) {
            throw new AuthorizationException('This operator cannot access the ops panel.');
        }

        return $next($request);
    }
}
