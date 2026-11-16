<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\WebhookEndpoints\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Modules\Settings\Filament\Resources\WebhookEndpoints\WebhookEndpointResource;

class ManageWebhookEndpointActivities extends ManageRelatedRecords
{
    protected static string $resource = WebhookEndpointResource::class;

    protected static string $relationship = 'activitiesAsSubject';

    protected static ?string $relatedResource = ActivityLogResource::class;

    protected static ?string $navigationLabel = 'History';

    protected static ?string $breadcrumb = 'History';

    protected static ?string $title = 'History';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return null;
    }
}
