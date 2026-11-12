<?php

declare(strict_types=1);

namespace Modules\Backorders\Filament\Resources\Backorders\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Backorders\Filament\Resources\Backorders\BackorderResource;

class ListBackorders extends ListRecords
{
    protected static string $resource = BackorderResource::class;
}
