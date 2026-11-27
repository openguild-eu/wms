<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\IdentifySubdomain;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            IdentifySubdomain::class,
        ]);

        $middleware->api(append: [
            IdentifySubdomain::class,
        ]);

        $middleware->alias([
            'api.key' => AuthenticateApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if ($exception->getModel() !== Warehouse::class) {
                return null;
            }

            $user = $request->user();

            if (! $user) {
                return null;
            }

            $panel = Filament::getCurrentPanel() ?? Filament::getPanel('admin');

            if (! $panel) {
                return null;
            }

            $tenant = $user->getTenants($panel)->first();

            if (! $tenant) {
                return null;
            }

            return redirect()->to((string) $panel->getUrl($tenant));
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            $user = $request->user();

            if (! $user) {
                return null;
            }

            if (! $request->isMethod('get') && ! $request->isMethod('head')) {
                return null;
            }

            $panel = Filament::getCurrentPanel() ?? Filament::getPanel('admin');

            if (! $panel) {
                return null;
            }

            $segment = $request->segment(1);

            if (! $segment) {
                return null;
            }

            if (in_array($segment, ['login', 'logout', 'register', 'password-reset', 'email-verification', 'livewire', 'js', 'css', 'build'], true)) {
                return null;
            }

            $tenants = $user->getTenants($panel);

            if ($tenants->isEmpty()) {
                return null;
            }

            $slugAttribute = $panel->getTenantSlugAttribute();

            $tenantKeys = $slugAttribute
                ? $tenants->pluck($slugAttribute)
                : $tenants->pluck($panel->getTenantSlugAttribute() ?? 'id');

            if ($tenantKeys->contains($segment)) {
                return null;
            }

            return redirect()->to((string) $panel->getUrl($tenants->first()));
        });
    })->create();
