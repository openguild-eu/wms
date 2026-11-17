<?php

declare(strict_types=1);

use App\Models\Subdomain;
use App\Models\Warehouse;
use Modules\Products\Models\Product;
use Modules\Users\Models\User;

it('downloads a png barcode for a product in the users warehouse', function () {
    $subdomain = Subdomain::query()->create([
        'subdomain' => 'barcode',
        'name' => 'Barcode',
    ]);

    $warehouse = Warehouse::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => 'barcode-warehouse',
        'currency' => 'EUR',
    ]);

    $user = User::query()->create([
        'subdomain_id' => $subdomain->id,
        'name' => 'Barcode Manager',
        'email' => 'barcode-manager@example.com',
        'password' => 'password',
    ]);

    $user->warehouses()->attach($warehouse);

    $product = Product::query()->create([
        'warehouse_id' => $warehouse->id,
        'active' => true,
        'reference_code' => 'REF-BARCODE',
        'product_code' => 'PROD-BARCODE',
        'barcode' => '8712345678906',
        'name' => 'Barcode Product',
        'description' => 'Description',
    ]);

    $response = $this->actingAs($user)
        ->get('http://barcode.'.config('app.central_domain')."/{$warehouse->name}/products/{$product->id}/barcode.png");

    $response->assertOk()
        ->assertDownload('barcode-8712345678906.png');

    expect(substr($response->streamedContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});
