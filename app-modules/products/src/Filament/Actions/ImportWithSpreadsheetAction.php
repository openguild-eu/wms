<?php

declare(strict_types=1);

namespace Modules\Products\Filament\Actions;

use Filament\Actions\ImportAction;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\View\ActionsIconAlias;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Csv\Reader as CsvReader;
use League\Csv\Writer;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class ImportWithXlsxAction extends ImportAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (ImportAction $action): string => __('filament-actions::import.label', ['label' => $action->getPluralModelLabel()]));

        $this->modalHeading(fn (ImportAction $action): string => __('filament-actions::import.modal.heading', ['label' => $action->getTitleCasePluralModelLabel()]));

        $this->modalDescription(fn (ImportAction $action): Htmlable => $action->getModalAction('downloadExample') ?? new HtmlString(''));

        $this->modalSubmitActionLabel(__('filament-actions::import.modal.actions.import.label'));

        $this->groupedIcon(FilamentIcon::resolve(ActionsIconAlias::IMPORT_ACTION_GROUPED) ?? Heroicon::ArrowUpTray);

        $this->schema(fn (ImportAction $action): array => array_merge([
            FileUpload::make('file')
                ->label(__('filament-actions::import.modal.form.file.label'))
                ->placeholder(__('filament-actions::import.modal.form.file.placeholder'))
                ->acceptedFileTypes([
                    'text/csv',
                    'text/x-csv',
                    'application/csv',
                    'application/x-csv',
                    'text/comma-separated-values',
                    'text/x-comma-separated-values',
                    'text/plain',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->rules($action->getFileValidationRules())
                ->afterStateUpdated(function (FileUpload $component, Component $livewire, Set $set, ?TemporaryUploadedFile $state) use ($action): void {
                    if (! $state instanceof TemporaryUploadedFile) {
                        return;
                    }

                    try {
                        $livewire->validateOnly($component->getStatePath());
                    } catch (ValidationException $exception) {
                        $component->state([]);

                        throw $exception;
                    }

                    $csvStream = $this->getUploadedFileStream($state);

                    if (! $csvStream) {
                        return;
                    }

                    $csvReader = CsvReader::from($csvStream);

                    if (filled($csvDelimiter = $this->getCsvDelimiter($csvReader))) {
                        $csvReader->setDelimiter($csvDelimiter);
                    }

                    $csvReader->setHeaderOffset($action->getHeaderOffset() ?? 0);

                    $csvColumns = $csvReader->getHeader();

                    $lowercaseCsvColumnValues = array_map(Str::lower(...), $csvColumns);
                    $lowercaseCsvColumnKeys = array_combine(
                        $lowercaseCsvColumnValues,
                        $csvColumns,
                    );

                    $set('columnMap', array_reduce($action->getImporter()::getColumns(), function (array $carry, ImportColumn $column) use ($lowercaseCsvColumnKeys, $lowercaseCsvColumnValues) {
                        $carry[$column->getName()] = $lowercaseCsvColumnKeys[
                        Arr::first(
                            array_intersect(
                                $lowercaseCsvColumnValues,
                                $column->getGuesses(),
                            ),
                        )
                        ] ?? null;

                        return $carry;
                    }, []));
                })
                ->storeFiles(false)
                ->visibility('private')
                ->required()
                ->hiddenLabel(),
            Fieldset::make(__('filament-actions::import.modal.form.columns.label'))
                ->columns(1)
                ->inlineLabel()
                ->schema(function (Get $get) use ($action): array {
                    $csvFile = $get('file');

                    if (! $csvFile instanceof TemporaryUploadedFile) {
                        return [];
                    }

                    $csvStream = $this->getUploadedFileStream($csvFile);

                    if (! $csvStream) {
                        return [];
                    }

                    $csvReader = CsvReader::from($csvStream);

                    if (filled($csvDelimiter = $this->getCsvDelimiter($csvReader))) {
                        $csvReader->setDelimiter($csvDelimiter);
                    }

                    $csvReader->setHeaderOffset($action->getHeaderOffset() ?? 0);

                    $csvColumns = $csvReader->getHeader();
                    $csvColumnOptions = array_combine($csvColumns, $csvColumns);

                    return array_map(
                        fn (ImportColumn $column): Select => $column->getSelect()->options($csvColumnOptions),
                        $action->getImporter()::getColumns(),
                    );
                })
                ->statePath('columnMap')
                ->visible(fn (Get $get): bool => $get('file') instanceof TemporaryUploadedFile),
        ], $action->getImporter()::getOptionsFormComponents()));

        $this->successNotificationTitle(__('filament-actions::import.notifications.started.title'));

        $this->model(fn (ImportAction $action): string => $action->getImporter()::getModel());
    }

    /**
     * @return resource|false
     */
    public function getUploadedFileStream(TemporaryUploadedFile $file)
    {
        if ($this->isXlsxFile($file)) {
            return $this->convertXlsxToCsvStream($file);
        }

        return parent::getUploadedFileStream($file);
    }

    protected function isXlsxFile(TemporaryUploadedFile $file): bool
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        return $extension === 'xlsx'
            || $mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    /**
     * @return resource
     */
    protected function convertXlsxToCsvStream(TemporaryUploadedFile $file)
    {
        $xlsxReader = new XlsxReader;
        $xlsxReader->open($file->getRealPath());

        $csvResource = fopen('php://temp', 'r+');

        if ($csvResource === false) {
            throw new \RuntimeException('Failed to create temporary CSV stream');
        }

        $csvWriter = Writer::createFromStream($csvResource);

        foreach ($xlsxReader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $csvWriter->insertOne($row->toArray());
            }

            break;
        }

        $xlsxReader->close();

        rewind($csvResource);

        return $csvResource;
    }
}
