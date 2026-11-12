<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Subdomain;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Brands\Models\Brand;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientAddresses;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderProduct;
use Modules\Orders\Models\OrderStatus;
use Modules\Picklists\Models\Picklist;
use Modules\Picklists\Models\PicklistProduct;
use Modules\Products\Models\Product;
use Modules\Products\Models\StockProduct;
use Modules\Settings\Models\Attribute;
use Modules\Settings\Models\AttributeGroup;
use Modules\Settings\Models\VatRate;
use Modules\Users\Models\User;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create philandphae subdomain
        $philandphae = Subdomain::firstOrCreate(
            ['subdomain' => 'philandphae'],
            ['name' => 'Phil & Phae']
        );

        // Create user for philandphae
        $userPhilandphae = User::firstOrCreate(
            ['email' => 'matthijs@philandphae.com'],
            [
                'subdomain_id' => $philandphae->id,
                'name' => 'Matthijs',
                'password' => Hash::make('cool'),
                'email_verified_at' => now(),
            ]
        );

        // Create warehouse for philandphae
        $warehousePhilandphae1 = Warehouse::firstOrCreate(
            ['name' => 'philandphae1', 'subdomain_id' => $philandphae->id]
        );

        // Attach user to warehouse
        $userPhilandphae->warehouses()->syncWithoutDetaching([$warehousePhilandphae1->id]);

        $this->seedVatRates($warehousePhilandphae1);
        $this->seedWarehouseDefaults($warehousePhilandphae1);
        $this->seedClients($warehousePhilandphae1);
        $this->seedOrderStatuses($warehousePhilandphae1);
        $this->seedOrders($warehousePhilandphae1);
        $this->seedPicklists($warehousePhilandphae1);

        // Create phaewomen subdomain
        $phaewomen = Subdomain::firstOrCreate(
            ['subdomain' => 'phaewomen'],
            ['name' => 'Phae Women']
        );

        // Create user for phaewomen
        $userPhaewomen = User::firstOrCreate(
            ['email' => 'matthijs@phaewomen.com'],
            [
                'subdomain_id' => $phaewomen->id,
                'name' => 'Matthijs',
                'password' => Hash::make('cool'),
                'email_verified_at' => now(),
            ]
        );

        // Create warehouses for phaewomen
        $warehousePhae1 = Warehouse::firstOrCreate(
            ['name' => 'phae1', 'subdomain_id' => $phaewomen->id]
        );

        $warehousePhae2 = Warehouse::firstOrCreate(
            ['name' => 'phae2', 'subdomain_id' => $phaewomen->id]
        );

        // Attach user to warehouses
        $userPhaewomen->warehouses()->syncWithoutDetaching([$warehousePhae1->id, $warehousePhae2->id]);

        $this->seedVatRates($warehousePhae1);
        $this->seedVatRates($warehousePhae2);
        $this->seedWarehouseDefaults($warehousePhae1);
        $this->seedWarehouseDefaults($warehousePhae2);
        $this->seedClients($warehousePhae1);
        $this->seedClients($warehousePhae2);
        $this->seedOrderStatuses($warehousePhae1);
        $this->seedOrderStatuses($warehousePhae2);
        $this->seedOrders($warehousePhae1);
        $this->seedOrders($warehousePhae2);
        $this->seedPicklists($warehousePhae1);
        $this->seedPicklists($warehousePhae2);

        $this->command->info('Successfully seeded subdomains, users, and warehouses.');
        $this->command->info('');
        $this->command->info('Phil & Phae:');
        $this->command->info('  URL: http://philandphae.wms.test');
        $this->command->info('  Email: matthijs@philandphae.com');
        $this->command->info('  Password: cool');
        $this->command->info('  Warehouse: philandphae1');
        $this->command->info('');
        $this->command->info('Phae Women:');
        $this->command->info('  URL: http://phaewomen.wms.test');
        $this->command->info('  Email: matthijs@phaewomen.com');
        $this->command->info('  Password: cool');
        $this->command->info('  Warehouses: phae1, phae2');
    }

    private function seedVatRates(Warehouse $warehouse): void
    {
        $rates = [
            ['name' => '6%', 'rate' => 6.0000],
            ['name' => '9%', 'rate' => 9.0000],
            ['name' => '21%', 'rate' => 21.0000],
        ];

        foreach ($rates as $rate) {
            VatRate::firstOrCreate(
                [
                    'warehouse_id' => $warehouse->id,
                    'name' => $rate['name'],
                    'rate' => $rate['rate'],
                ]
            );
        }
    }

    private function seedWarehouseDefaults(Warehouse $warehouse): void
    {
        $brand = Brand::query()->firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'reference_code' => 'BRD-001',
            ],
            [
                'active' => true,
                'name' => 'WMS Essentials',
                'description' => 'Default seeded brand.',
            ]
        );

        $colorGroup = AttributeGroup::query()->firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'name' => 'Color',
            ]
        );

        $sizeGroup = AttributeGroup::query()->firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'name' => 'Size',
            ]
        );

        $red = Attribute::query()->firstOrCreate(
            [
                'attribute_group_id' => $colorGroup->id,
                'name' => 'Red',
            ]
        );

        $blue = Attribute::query()->firstOrCreate(
            [
                'attribute_group_id' => $colorGroup->id,
                'name' => 'Blue',
            ]
        );

        $small = Attribute::query()->firstOrCreate(
            [
                'attribute_group_id' => $sizeGroup->id,
                'name' => 'S',
            ]
        );

        $medium = Attribute::query()->firstOrCreate(
            [
                'attribute_group_id' => $sizeGroup->id,
                'name' => 'M',
            ]
        );

        $vatRate = VatRate::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('name', '21%')
            ->first();

        $product = Product::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_code', 'SKU-001')
            ->first();

        if (! $product) {
            $product = Product::forceCreate([
                'warehouse_id' => $warehouse->id,
                'active' => true,
                'reference_code' => 'REF-001',
                'price' => 19.9900,
                'product_code' => 'SKU-001',
                'stock_unlimited' => true,
                'barcode' => '1234567890123',
                'name' => 'Basic T-Shirt',
                'weight' => '200g',
                'height' => '2cm',
                'length' => '30cm',
                'hs_code' => '61091000',
                'country_of_origin' => 'NL',
                'description' => 'Seeded product for demo purposes.',
                'vat_rate_id' => $vatRate?->id,
                'brand_id' => $brand->id,
            ]);
        }

        $product->attributes()->syncWithoutDetaching([
            $red->id,
            $blue->id,
            $small->id,
            $medium->id,
        ]);

        StockProduct::query()->firstOrCreate(
            ['product_id' => $product->id],
            [
                'on_stock_quantity' => 250,
                'reserved_quantity' => 20,
                'reserved_on_picklists' => 10,
                'free_on_stock_quantity' => 220,
            ]
        );
    }

    private function seedClients(Warehouse $warehouse): void
    {
        // Client 1: John Doe
        $client1 = Client::query()->firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'email' => 'john.doe@example.com',
            ],
            [
                'active' => true,
                'vat_number' => 'NL123456789B01',
                'coc_number' => '12345678',
                'debtor_number' => 'DEB-001',
                'iban_number' => 'NL91ABNA0417164300',
                'company' => 'Doe Enterprises',
                'comments' => 'VIP customer',
            ]
        );

        $deliveryAddress1 = ClientAddresses::query()->firstOrCreate(
            [
                'client_id' => $client1->id,
                'address' => 'Main Street 123 A',
            ],
            [
                'company' => 'Doe Enterprises',
                'gender' => 'male',
                'initials' => 'J.',
                'name' => 'John Doe',
                'address' => 'Main Street 123 A',
                'zipcode' => '1234AB',
                'city' => 'Amsterdam',
                'region' => 'Noord-Holland',
                'country' => 'Netherlands',
                'telephone_number' => '+31201234567',
            ]
        );

        $billAddress1 = ClientAddresses::query()->firstOrCreate(
            [
                'client_id' => $client1->id,
                'address' => 'Business Park 456',
            ],
            [
                'company' => 'Doe Enterprises',
                'gender' => 'male',
                'initials' => 'J.',
                'name' => 'John Doe',
                'address' => 'Business Park 456',
                'zipcode' => '5678CD',
                'city' => 'Rotterdam',
                'region' => 'Zuid-Holland',
                'country' => 'Netherlands',
                'telephone_number' => '+31102345678',
            ]
        );

        $client1->update([
            'delivery_client_address_id' => $deliveryAddress1->id,
            'bill_client_address_id' => $billAddress1->id,
        ]);

        // Client 2: Jane Smith
        $client2 = Client::query()->firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'email' => 'jane.smith@example.com',
            ],
            [
                'active' => true,
                'vat_number' => 'NL987654321B01',
                'coc_number' => '87654321',
                'debtor_number' => 'DEB-002',
                'iban_number' => 'NL20INGB0001234567',
                'company' => 'Smith & Co',
                'comments' => 'Regular customer',
            ]
        );

        $deliveryAddress2 = ClientAddresses::query()->firstOrCreate(
            [
                'client_id' => $client2->id,
                'address' => 'High Street 789',
            ],
            [
                'company' => 'Smith & Co',
                'gender' => 'female',
                'initials' => 'J.',
                'name' => 'Jane Smith',
                'address' => 'High Street 789',
                'zipcode' => '9012EF',
                'city' => 'Utrecht',
                'region' => 'Utrecht',
                'country' => 'Netherlands',
                'telephone_number' => '+31301234567',
            ]
        );

        $billAddress2 = ClientAddresses::query()->firstOrCreate(
            [
                'client_id' => $client2->id,
                'address' => 'Commerce Road 321',
            ],
            [
                'company' => 'Smith & Co',
                'gender' => 'female',
                'initials' => 'J.',
                'name' => 'Jane Smith',
                'address' => 'Commerce Road 321',
                'zipcode' => '3456GH',
                'city' => 'The Hague',
                'region' => 'Zuid-Holland',
                'country' => 'Netherlands',
                'telephone_number' => '+31703456789',
            ]
        );

        $client2->update([
            'delivery_client_address_id' => $deliveryAddress2->id,
            'bill_client_address_id' => $billAddress2->id,
        ]);

        // Client 3: Bob Johnson (Private individual)
        $client3 = Client::query()->firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'email' => 'bob.johnson@personal.com',
            ],
            [
                'active' => true,
                'debtor_number' => 'DEB-003',
                'comments' => 'Private customer - no VAT',
            ]
        );

        $address3 = ClientAddresses::query()->firstOrCreate(
            [
                'client_id' => $client3->id,
                'address' => 'Elm Avenue 42',
            ],
            [
                'gender' => 'male',
                'initials' => 'B.',
                'name' => 'Bob Johnson',
                'address' => 'Elm Avenue 42',
                'zipcode' => '6789IJ',
                'city' => 'Eindhoven',
                'region' => 'Noord-Brabant',
                'country' => 'Netherlands',
                'telephone_number' => '+31623456789',
            ]
        );

        $client3->update([
            'delivery_client_address_id' => $address3->id,
            'bill_client_address_id' => $address3->id,
        ]);
    }

    private function seedOrderStatuses(Warehouse $warehouse): void
    {
        $statuses = [
            [
                'name' => 'New',
                'color' => '#3B82F6',
                'generate_picklist' => false,
                'reserve_stock' => false,
                'concepted' => true,
                'completed' => false,
                'on_hold' => false,
                'delivered' => false,
                'cancelled' => false,
            ],
            [
                'name' => 'Confirmed',
                'color' => '#10B981',
                'generate_picklist' => true,
                'reserve_stock' => true,
                'concepted' => false,
                'completed' => false,
                'on_hold' => false,
                'delivered' => false,
                'cancelled' => false,
            ],
            [
                'name' => 'Backordered',
                'color' => '#F59E0B',
                'generate_picklist' => false,
                'reserve_stock' => false,
                'reduce_stock' => false,
                'concepted' => false,
                'completed' => false,
                'on_hold' => true,
                'delivered' => false,
                'cancelled' => false,
            ],
            [
                'name' => 'Processing',
                'color' => '#F59E0B',
                'generate_picklist' => false,
                'reserve_stock' => true,
                'concepted' => false,
                'completed' => false,
                'on_hold' => false,
                'delivered' => false,
                'cancelled' => false,
            ],
            [
                'name' => 'Shipped',
                'color' => '#8B5CF6',
                'generate_picklist' => false,
                'reserve_stock' => false,
                'concepted' => false,
                'completed' => false,
                'on_hold' => false,
                'delivered' => false,
                'cancelled' => false,
            ],
            [
                'name' => 'Delivered',
                'color' => '#059669',
                'generate_picklist' => false,
                'reserve_stock' => false,
                'concepted' => false,
                'completed' => true,
                'on_hold' => false,
                'delivered' => true,
                'cancelled' => false,
            ],
            [
                'name' => 'On Hold',
                'color' => '#6B7280',
                'generate_picklist' => false,
                'reserve_stock' => true,
                'concepted' => false,
                'completed' => false,
                'on_hold' => true,
                'delivered' => false,
                'cancelled' => false,
            ],
            [
                'name' => 'Cancelled',
                'color' => '#EF4444',
                'generate_picklist' => false,
                'reserve_stock' => false,
                'concepted' => false,
                'completed' => false,
                'on_hold' => false,
                'delivered' => false,
                'cancelled' => true,
            ],
        ];

        foreach ($statuses as $status) {
            OrderStatus::query()->firstOrCreate(
                [
                    'warehouse_id' => $warehouse->id,
                    'name' => $status['name'],
                ],
                $status
            );
        }
    }

    private function seedOrders(Warehouse $warehouse): void
    {
        // Get required data
        $clients = Client::query()->where('warehouse_id', $warehouse->id)->get();
        $products = Product::query()->where('warehouse_id', $warehouse->id)->get();
        $statusNew = OrderStatus::query()->where('warehouse_id', $warehouse->id)->where('name', 'New')->first();

        if ($clients->isEmpty() || $products->isEmpty() || ! $statusNew instanceof OrderStatus) {
            return;
        }

        $statusConfirmed = OrderStatus::query()->where('warehouse_id', $warehouse->id)->where('name', 'Confirmed')->first();
        $statusProcessing = OrderStatus::query()->where('warehouse_id', $warehouse->id)->where('name', 'Processing')->first();
        $statusDelivered = OrderStatus::query()->where('warehouse_id', $warehouse->id)->where('name', 'Delivered')->first();

        $statusConfirmedId = $statusConfirmed instanceof OrderStatus ? $statusConfirmed->id : $statusNew->id;
        $statusProcessingId = $statusProcessing instanceof OrderStatus ? $statusProcessing->id : $statusNew->id;
        $statusDeliveredId = $statusDelivered instanceof OrderStatus ? $statusDelivered->id : $statusNew->id;

        // Order 1: New order from first client
        $client1 = $clients->first();

        $deliveryAddress1 = $client1->clientDeliveryAddress;

        if (! $deliveryAddress1 instanceof ClientAddresses) {
            return;
        }

        $order1 = Order::query()->firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'custom_order_id' => 'CUST-001',
            ],
            [
                'client_id' => $client1->id,
                'order_statuses_id' => $statusNew->id,
                'discount' => 0,
                'invoice_name' => $client1->company ?? $deliveryAddress1->name,
                'invoice_address' => $deliveryAddress1->address,
                'invoice_zipcode' => $deliveryAddress1->zipcode,
                'invoice_region' => $deliveryAddress1->region,
                'invoice_city' => $deliveryAddress1->city,
                'invoice_country' => $deliveryAddress1->country,
                'delivery_name' => $deliveryAddress1->name,
                'delivery_address' => $deliveryAddress1->address,
                'delivery_zipcode' => $deliveryAddress1->zipcode,
                'delivery_region' => $deliveryAddress1->region,
                'delivery_city' => $deliveryAddress1->city,
                'delivery_country' => $deliveryAddress1->country,
                'telephone_number' => $deliveryAddress1->telephone_number,
                'email' => $client1->email,
                'comments' => 'First test order - Rush delivery',
            ]
        );

        // Add products to order 1
        if ($products->count() > 0) {
            $product = $products->first();
            OrderProduct::query()->firstOrCreate(
                [
                    'order_id' => $order1->id,
                    'product_id' => $product->id,
                ],
                [
                    'vat_rate_id' => $product->vat_rate_id,
                    'vat_rate' => $product->vatRate->rate ?? 21,
                    'name' => $product->name,
                    'quantity' => 2,
                    'price' => $product->price,
                    'weight' => 400,
                    'reference_code' => $product->reference_code,
                    'barcode' => $product->barcode,
                ]
            );
        }

        // Order 2: Confirmed order from second client
        if ($clients->count() > 1) {
            $client2 = $clients->skip(1)->first();

            if (! $client2 instanceof Client) {
                return;
            }

            $deliveryAddress2 = $client2->clientDeliveryAddress;

            if ($deliveryAddress2 instanceof ClientAddresses) {
                $order2 = Order::query()->firstOrCreate(
                    [
                        'warehouse_id' => $warehouse->id,
                        'custom_order_id' => 'CUST-002',
                    ],
                    [
                        'client_id' => $client2->id,
                        'order_statuses_id' => $statusNew->id,
                        'discount' => 5.00,
                        'invoice_name' => $client2->company ?? $deliveryAddress2->name,
                        'invoice_address' => $deliveryAddress2->address,
                        'invoice_zipcode' => $deliveryAddress2->zipcode,
                        'invoice_region' => $deliveryAddress2->region,
                        'invoice_city' => $deliveryAddress2->city,
                        'invoice_country' => $deliveryAddress2->country,
                        'delivery_name' => $deliveryAddress2->name,
                        'delivery_address' => $deliveryAddress2->address,
                        'delivery_zipcode' => $deliveryAddress2->zipcode,
                        'delivery_region' => $deliveryAddress2->region,
                        'delivery_city' => $deliveryAddress2->city,
                        'delivery_country' => $deliveryAddress2->country,
                        'telephone_number' => $deliveryAddress2->telephone_number,
                        'email' => $client2->email,
                        'comments' => 'Regular customer - 5% discount applied',
                    ]
                );

                // Add multiple products to order 2. Products can only be modified while
                // an order is in a concept status, so only run this once per order: skip
                // it entirely (including the status transition) once the product exists.
                if ($products->count() > 0 && ! OrderProduct::query()->where('order_id', $order2->id)->exists()) {
                    $product = $products->first();

                    $order2->update(['order_statuses_id' => $statusNew->id]);

                    OrderProduct::query()->create([
                        'order_id' => $order2->id,
                        'product_id' => $product->id,
                        'vat_rate_id' => $product->vat_rate_id,
                        'vat_rate' => $product->vatRate->rate ?? 21,
                        'name' => $product->name,
                        'quantity' => 5,
                        'price' => $product->price,
                        'weight' => 1000,
                        'reference_code' => $product->reference_code,
                        'barcode' => $product->barcode,
                    ]);

                    $order2->update(['order_statuses_id' => $statusConfirmedId]);
                }
            }
        }

        // Order 3: Processing order
        if ($clients->count() > 2) {
            $client3 = $clients->skip(2)->first();

            if (! $client3 instanceof Client) {
                return;
            }

            $deliveryAddress3 = $client3->clientDeliveryAddress;

            if ($deliveryAddress3 instanceof ClientAddresses) {
                $order3 = Order::query()->firstOrCreate(
                    [
                        'warehouse_id' => $warehouse->id,
                        'custom_order_id' => 'CUST-003',
                    ],
                    [
                        'client_id' => $client3->id,
                        'order_statuses_id' => $statusNew->id,
                        'discount' => 0,
                        'invoice_name' => $deliveryAddress3->name,
                        'invoice_address' => $deliveryAddress3->address,
                        'invoice_zipcode' => $deliveryAddress3->zipcode,
                        'invoice_region' => $deliveryAddress3->region,
                        'invoice_city' => $deliveryAddress3->city,
                        'invoice_country' => $deliveryAddress3->country,
                        'delivery_name' => $deliveryAddress3->name,
                        'delivery_address' => $deliveryAddress3->address,
                        'delivery_zipcode' => $deliveryAddress3->zipcode,
                        'delivery_region' => $deliveryAddress3->region,
                        'delivery_city' => $deliveryAddress3->city,
                        'delivery_country' => $deliveryAddress3->country,
                        'telephone_number' => $deliveryAddress3->telephone_number,
                        'email' => $client3->email,
                        'comments' => 'Private customer - currently being processed',
                    ]
                );

                // Add product to order 3. Products can only be modified while an order
                // is in a concept status, so only run this once per order: skip it
                // entirely (including the status transition) once the product exists.
                if ($products->count() > 0 && ! OrderProduct::query()->where('order_id', $order3->id)->exists()) {
                    $product = $products->first();

                    $order3->update(['order_statuses_id' => $statusNew->id]);

                    OrderProduct::query()->create([
                        'order_id' => $order3->id,
                        'product_id' => $product->id,
                        'vat_rate_id' => $product->vat_rate_id,
                        'vat_rate' => $product->vatRate->rate ?? 21,
                        'name' => $product->name,
                        'quantity' => 1,
                        'price' => $product->price,
                        'weight' => 200,
                        'reference_code' => $product->reference_code,
                        'barcode' => $product->barcode,
                    ]);

                    $order3->update(['order_statuses_id' => $statusProcessingId]);
                }
            }
        }

        // Order 4: Delivered order (older)
        $order4 = Order::query()->firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'custom_order_id' => 'CUST-004',
            ],
            [
                'client_id' => $client1->id,
                'order_statuses_id' => $statusNew->id,
                'discount' => 0,
                'invoice_name' => $client1->company ?? $deliveryAddress1->name,
                'invoice_address' => $deliveryAddress1->address,
                'invoice_zipcode' => $deliveryAddress1->zipcode,
                'invoice_region' => $deliveryAddress1->region,
                'invoice_city' => $deliveryAddress1->city,
                'invoice_country' => $deliveryAddress1->country,
                'delivery_name' => $deliveryAddress1->name,
                'delivery_address' => $deliveryAddress1->address,
                'delivery_zipcode' => $deliveryAddress1->zipcode,
                'delivery_region' => $deliveryAddress1->region,
                'delivery_city' => $deliveryAddress1->city,
                'delivery_country' => $deliveryAddress1->country,
                'telephone_number' => $deliveryAddress1->telephone_number,
                'email' => $client1->email,
                'comments' => 'Successfully delivered - completed order',
                'created_at' => now()->subDays(7),
            ]
        );

        // Add products to order 4. Products can only be modified while an order is in
        // a concept status, so only run this once per order: skip it entirely
        // (including the status transition) once the product exists.
        if ($products->count() > 0 && ! OrderProduct::query()->where('order_id', $order4->id)->exists()) {
            $product = $products->first();

            $order4->update(['order_statuses_id' => $statusNew->id]);

            OrderProduct::query()->create([
                'order_id' => $order4->id,
                'product_id' => $product->id,
                'vat_rate_id' => $product->vat_rate_id,
                'vat_rate' => $product->vatRate->rate ?? 21,
                'name' => $product->name,
                'quantity' => 3,
                'price' => $product->price,
                'weight' => 600,
                'reference_code' => $product->reference_code,
                'barcode' => $product->barcode,
            ]);

            $order4->update(['order_statuses_id' => $statusDeliveredId]);
        }
    }

    private function seedPicklists(Warehouse $warehouse): void
    {
        $orders = Order::query()->where('warehouse_id', $warehouse->id)->get();

        if ($orders->isEmpty()) {
            return;
        }

        $statuses = OrderStatus::query()
            ->where('warehouse_id', $warehouse->id)
            ->get()
            ->keyBy('id');

        foreach ($orders as $order) {
            $status = $statuses->get($order->order_statuses_id);

            if (! $status instanceof OrderStatus || ! $status->generate_picklist) {
                continue;
            }

            $picklist = Picklist::query()->firstOrCreate(
                [
                    'order_id' => $order->id,
                    'warehouse_id' => $warehouse->id,
                ],
                [
                    'completed' => false,
                    'back_order' => false,
                    'comments' => 'Auto-generated picklist for seeded data.',
                ]
            );

            if (PicklistProduct::query()->where('picklist_id', $picklist->id)->exists()) {
                continue;
            }

            $orderProducts = $order->products;

            if ($orderProducts->isEmpty()) {
                continue;
            }

            foreach ($orderProducts as $orderProduct) {
                if (! $orderProduct instanceof OrderProduct) {
                    continue;
                }

                $quantity = max(1, (int) $orderProduct->quantity);
                $orderProductSource = $orderProduct->product;

                if ($orderProductSource instanceof Product) {
                    $barcode = $orderProduct->barcode
                        ?? $orderProductSource->barcode
                        ?? $orderProduct->reference_code
                        ?? 'UNKNOWN';
                    $referenceCode = $orderProduct->reference_code ?? $orderProductSource->reference_code;
                    $productTitle = $orderProduct->name ?? $orderProductSource->name ?? 'Product';
                } else {
                    $barcode = $orderProduct->barcode ?? $orderProduct->reference_code ?? 'UNKNOWN';
                    $referenceCode = $orderProduct->reference_code;
                    $productTitle = $orderProduct->name ?? 'Product';
                }

                for ($i = 0; $i < $quantity; $i++) {
                    PicklistProduct::query()->create([
                        'picklist_id' => $picklist->id,
                        'show_for_supplier' => false,
                        'barcode' => $barcode,
                        'reference_code' => $referenceCode,
                        'color' => null,
                        'size' => null,
                        'product_title' => $productTitle,
                        'scanned' => false,
                    ]);
                }
            }
        }
    }
}
