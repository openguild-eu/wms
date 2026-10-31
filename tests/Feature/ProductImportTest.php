<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use League\Csv\Reader as CsvReader;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Products\Filament\Actions\ImportWithSpreadsheetAction;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls as XlsWriter;

/**
 * @var array<int, array<int, string>>
 */
const PRODUCT_IMPORT_ROWS = [
    ['reference_code', 'name', 'barcode'],
    ['REF-001', 'First Product', '1234567890123'],
    ['REF-002', 'Second Product', '9876543210987'],
];

beforeEach(function () {
    Storage::fake(FileUploadConfiguration::disk());
});

function makeTemporaryUpload(string $originalName, string $contents): TemporaryUploadedFile
{
    $storedName = Str::random(30)
        .str('-meta'.base64_encode($originalName).'-')->replace('/', '_')
        .'.'.pathinfo($originalName, PATHINFO_EXTENSION);

    Storage::disk(FileUploadConfiguration::disk())->put(FileUploadConfiguration::path($storedName, false), $contents);

    return new TemporaryUploadedFile($storedName, FileUploadConfiguration::disk());
}

function makeXlsContents(): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray(PRODUCT_IMPORT_ROWS);

    $path = tempnam(sys_get_temp_dir(), 'xls');
    (new XlsWriter($spreadsheet))->save($path);

    return tap(file_get_contents($path), fn () => unlink($path));
}

function makeXlsxContents(): string
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';

    $writer = new XlsxWriter;
    $writer->openToFile($path);

    foreach (PRODUCT_IMPORT_ROWS as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return tap(file_get_contents($path), fn () => unlink($path));
}

/**
 * @param  resource  $stream
 * @return array<int, array<int, string>>
 */
function readCsvRows($stream): array
{
    return array_map(array_values(...), iterator_to_array(CsvReader::from($stream)->getRecords(), false));
}

it('converts an xls upload to a csv stream', function () {
    $file = makeTemporaryUpload('products.xls', makeXlsContents());

    expect(readCsvRows(ImportWithSpreadsheetAction::make()->getUploadedFileStream($file)))
        ->toBe(PRODUCT_IMPORT_ROWS);
});

it('converts an xlsx upload to a csv stream', function () {
    $file = makeTemporaryUpload('products.xlsx', makeXlsxContents());

    expect(readCsvRows(ImportWithSpreadsheetAction::make()->getUploadedFileStream($file)))
        ->toBe(PRODUCT_IMPORT_ROWS);
});

it('passes a csv upload through unchanged', function () {
    $file = makeTemporaryUpload('products.csv', implode(PHP_EOL, array_map(
        fn (array $row): string => implode(',', $row),
        PRODUCT_IMPORT_ROWS,
    )));

    expect(readCsvRows(ImportWithSpreadsheetAction::make()->getUploadedFileStream($file)))
        ->toBe(PRODUCT_IMPORT_ROWS);
});

it('accepts csv, xls and xlsx uploads in the file validation rules', function (string $originalName, Closure $contents) {
    $file = makeTemporaryUpload($originalName, $contents());

    $validator = Validator::make(
        ['file' => $file],
        ['file' => ImportWithSpreadsheetAction::make()->getFileValidationRules()],
    );

    expect($validator->passes())->toBeTrue();
})->with([
    'csv' => ['products.csv', fn (): string => "reference_code,name\nREF-001,First Product"],
    'xls' => ['products.xls', fn (): string => makeXlsContents()],
    'xlsx' => ['products.xlsx', fn (): string => makeXlsxContents()],
]);

it('accepts csv, xls and xlsx uploads by detected mime type', function (string $originalName, Closure $contents) {
    $file = makeTemporaryUpload($originalName, $contents());

    $validator = Validator::make(
        ['file' => $file],
        ['file' => 'mimetypes:'.implode(',', ImportWithSpreadsheetAction::make()->getAcceptedFileTypes())],
    );

    expect($validator->passes())->toBeTrue();
})->with([
    'csv' => ['products.csv', fn (): string => "reference_code,name\nREF-001,First Product"],
    'xls' => ['products.xls', fn (): string => makeXlsContents()],
    'xlsx' => ['products.xlsx', fn (): string => makeXlsxContents()],
]);

it('accepts the generic ole mime types that excel saved xls files are detected as', function (string $mimeType) {
    expect(ImportWithSpreadsheetAction::make()->getAcceptedFileTypes())->toContain($mimeType);
})->with([
    'application/vnd.ms-office',
    'application/x-ole-storage',
    'application/CDFV2',
]);

it('rejects uploads with an unsupported extension', function () {
    $file = makeTemporaryUpload('products.pdf', 'not a spreadsheet');

    $validator = Validator::make(
        ['file' => $file],
        ['file' => ImportWithSpreadsheetAction::make()->getFileValidationRules()],
    );

    expect($validator->errors()->keys())->toBe(['file']);
});
