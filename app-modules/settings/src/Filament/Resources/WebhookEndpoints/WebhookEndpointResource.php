<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\WebhookEndpoints;

use App\Models\WebhookEndpoint;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Pages\CreateWebhookEndpoint;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Pages\EditWebhookEndpoint;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Pages\ListWebhookEndpoints;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Pages\ManageWebhookEndpointActivities;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Schemas\WebhookEndpointForm;
use Modules\Settings\Filament\Resources\WebhookEndpoints\Tables\WebhookEndpointsTable;
use UnitEnum;

class WebhookEndpointResource extends Resource
{
    protected static ?string $model = WebhookEndpoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Webhook Endpoints';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditWebhookEndpoint::class,
            ManageWebhookEndpointActivities::class,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return WebhookEndpointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebhookEndpointsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhookEndpoints::route('/'),
            'create' => CreateWebhookEndpoint::route('/create'),
            'edit' => EditWebhookEndpoint::route('/{record}/edit'),
            'history' => ManageWebhookEndpointActivities::route('/{record}/history'),
        ];
    }
}
