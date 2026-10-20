<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use AlizHarb\ActivityLog\ActivityLogPlugin;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Notes;
use App\Filament\Pages\WarehouseMap;
use App\Http\Middleware\IdentifySubdomain;
use App\Models\Warehouse;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('/')
            ->brandName('Open Warehouse Management System')
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make(),
            ])

            ->spa()
            ->tenant(Warehouse::class, ownershipRelationship: 'warehouse', slugAttribute: 'name')
            ->login()
            ->authGuard('web')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->databaseNotifications()
            ->assets([
                Js::make('onscan', base_path('node_modules/onscan.js/onscan.min.js')),
            ])
            ->plugins([
                ActivityLogPlugin::make()
                    ->navigationGroup('Settings'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/brands/src/Filament/Resources'), for: 'Modules\Brands\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/clients/src/Filament/Resources'), for: 'Modules\Clients\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/orders/src/Filament/Resources'), for: 'Modules\Orders\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/picklists/src/Filament/Resources'), for: 'Modules\Picklists\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/products/src/Filament/Resources'), for: 'Modules\Products\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/settings/src/Filament/Resources'), for: 'Modules\Settings\Filament\Resources')
            ->discoverResources(in: base_path('app-modules/users/src/Filament/Resources'), for: 'Modules\Users\Filament\Resources')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->pages([
                Dashboard::class,
                WarehouseMap::class,
                Notes::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Settings'),
            ])
            ->middleware([
                IdentifySubdomain::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
