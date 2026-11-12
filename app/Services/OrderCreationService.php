<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Modules\Backorders\Services\BackorderCreationService;
use Modules\Clients\Models\Client;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderProduct;
use Modules\Orders\Models\OrderStatus;
use Modules\Products\Models\Product;
use Modules\Products\Models\StockProduct;

class OrderCreationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Warehouse $warehouse, array $data): Order
    {
        if (! empty($data['custom_order_id'])) {
            $existingOrder = Order::withoutGlobalScopes()
                ->where('warehouse_id', $warehouse->id)
                ->where('custom_order_id', $data['custom_order_id'])
                ->first();

            if ($existingOrder) {
                return $existingOrder;
            }
        }

        $stockCheck = $this->loadAndValidateStock($warehouse, $data['items']);
        $products = $stockCheck['products'];
        $shortfalls = $stockCheck['shortfalls'];

        $client = $this->findOrCreateClient($warehouse, $data['client']);

        $order = DB::transaction(function () use ($warehouse, $data, $products, $client): Order {
            $order = Order::query()->create([
                'warehouse_id' => $warehouse->id,
                'client_id' => $client->id,
                'custom_order_id' => $data['custom_order_id'] ?? null,
                'discount' => $data['discount'] ?? null,
                'comments' => $data['comments'] ?? null,
                'delivery_date' => $data['delivery_date'] ?? null,
                'telephone_number' => $data['telephone_number'] ?? null,
                'email' => $data['email'] ?? null,
                'delivery_name' => $data['delivery_name'],
                'delivery_address' => $data['delivery_address'],
                'delivery_zipcode' => $data['delivery_zipcode'],
                'delivery_region' => $data['delivery_region'] ?? null,
                'delivery_city' => $data['delivery_city'],
                'delivery_country' => $data['delivery_country'],
                'invoice_name' => $data['invoice_name'] ?? $data['delivery_name'],
                'invoice_address' => $data['invoice_address'] ?? $data['delivery_address'],
                'invoice_zipcode' => $data['invoice_zipcode'] ?? $data['delivery_zipcode'],
                'invoice_region' => $data['invoice_region'] ?? ($data['delivery_region'] ?? null),
                'invoice_city' => $data['invoice_city'] ?? $data['delivery_city'],
                'invoice_country' => $data['invoice_country'] ?? $data['delivery_country'],
            ]);

            foreach ($data['items'] as $item) {
                /** @var Product $product */
                $product = $products[$item['product_id']];

                OrderProduct::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'vat_rate_id' => $product->vat_rate_id,
                    'vat_rate' => $product->vatRate?->rate,
                    'name' => $product->name,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'barcode' => $product->barcode,
                    'reference_code' => $product->reference_code,
                ]);
            }

            return $order;
        });

        $intendedOrderStatusId = (int) $data['order_status_id'];
        $orderStatusId = $intendedOrderStatusId;

        if ($shortfalls !== []) {
            $backorderedStatus = OrderStatus::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('name', 'Backordered')
                ->first();

            if (! $backorderedStatus) {
                throw new \RuntimeException(
                    "Warehouse #{$warehouse->id} is missing its 'Backordered' order status - run the backorders module migrations."
                );
            }

            $orderStatusId = $backorderedStatus->id;
        }

        // Deliberately a separate write from the transaction above: OrderObserver
        // only dispatches OrderStatusChanged from its updated() hook, guarded by
        // wasChanged('order_statuses_id') - setting the status in the same
        // create() call would never trigger it (no "previous" value exists on
        // insert), and setting it inside the transaction above prevents the
        // ShouldQueueAfterCommit listener from ever running within a test's
        // wrapping transaction. This must stay a genuine post-transaction update.
        $order->update(['order_statuses_id' => $orderStatusId]);

        if ($shortfalls !== []) {
            app(BackorderCreationService::class)->createForOrder($order, $shortfalls, $intendedOrderStatusId);
        }

        return $order;
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @return array{products: array<int, Product>, shortfalls: array<int, array{product_id: int, requested: int, available: int}>}
     */
    private function loadAndValidateStock(Warehouse $warehouse, array $items): array
    {
        $products = [];
        $shortfalls = [];

        foreach ($items as $item) {
            if (! array_key_exists($item['product_id'], $products)) {
                $products[$item['product_id']] = Product::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->with('vatRate', 'stockProduct')
                    ->findOrFail($item['product_id']);
            }

            /** @var Product $product */
            $product = $products[$item['product_id']];

            if ($product->stock_unlimited) {
                continue;
            }

            $stockProduct = $product->stockProduct;
            $available = $stockProduct instanceof StockProduct ? $stockProduct->free_on_stock_quantity : 0;

            if ($item['quantity'] > $available) {
                $shortfalls[] = [
                    'product_id' => $product->id,
                    'requested' => $item['quantity'],
                    'available' => $available,
                ];
            }
        }

        return [
            'products' => $products,
            'shortfalls' => $shortfalls,
        ];
    }

    /**
     * @param  array<string, mixed>  $clientData
     */
    private function findOrCreateClient(Warehouse $warehouse, array $clientData): Client
    {
        $client = Client::withoutGlobalScopes()
            ->where('warehouse_id', $warehouse->id)
            ->where('email', $clientData['email'])
            ->first();

        if ($client) {
            return $client;
        }

        return Client::query()->create([
            'warehouse_id' => $warehouse->id,
            'active' => true,
            'email' => $clientData['email'],
            'company' => $clientData['company'] ?? null,
            'vat_number' => $clientData['vat_number'] ?? null,
            'coc_number' => $clientData['coc_number'] ?? null,
            'debtor_number' => $clientData['debtor_number'] ?? null,
            'iban_number' => $clientData['iban_number'] ?? null,
            'comments' => $clientData['comments'] ?? null,
        ]);
    }
}
