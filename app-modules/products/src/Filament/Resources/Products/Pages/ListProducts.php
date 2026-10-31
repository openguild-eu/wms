<?php

declare(strict_types=1);

namespace Modules\Products\Filament\Resources\Products\Pages;

use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Modules\Products\Filament\Actions\ImportWithSpreadsheetAction;
use Modules\Products\Filament\Imports\ProductImporter;
use Modules\Products\Filament\Resources\Products\ProductResource;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportWithSpreadsheetAction::make()
                ->importer(ProductImporter::class)
                ->options(fn (): array => [
                    'warehouse_id' => Filament::getTenant()?->getKey(),
                ]),
            CreateAction::make(),
        ];
    }
}
