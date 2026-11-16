<?php

declare(strict_types=1);

namespace Modules\Clients\Filament\Resources\Clients\Pages;

use BackedEnum;
use Filament\Resources\Pages\ViewRecord;
use Modules\Clients\Filament\Resources\Clients\ClientResource;
use Modules\Clients\Filament\Resources\Clients\Widgets\ClientOrderStatsOverview;
use Modules\Clients\Filament\Resources\Clients\Widgets\RecentClientOrdersTable;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return null;
    }

    public static function getNavigationLabel(): string
    {
        return __('Dashboard');
    }

    public function getTitle(): string
    {
        return __('Dashboard');
    }

    public function getBreadcrumb(): string
    {
        return __('Dashboard');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClientOrderStatsOverview::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            RecentClientOrdersTable::class,
        ];
    }
}
