<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
  
class OrderService
{
    public function createOrder(int $customerId, array $items): Order
    {
        return DB::transaction(function () use ($customerId, $items) {
            $order = Order::create([
                'customer_id' => $customerId,
                'order_number' => Order::generateOrderNumber(),
                'status' => OrderStatus::PENDING,
                'total_amount' => 0,
            ]);

            $totalAmount = 0;

            foreach ($items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                if (! $product->hasStock($item['quantity'])) {
                    throw new InvalidArgumentException(
                        "Insufficient stock for product: {$product->name}. Available: {$product->stock_quantity}, Requested: {$item['quantity']}"
                    );
                }

                $unitPrice = $product->price;
                $totalPrice = $unitPrice * $item['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ]);

                $product->decrementStock($item['quantity']);
                $totalAmount += $totalPrice;
            }

            $order->update(['total_amount' => $totalAmount]);

            return $order->load('items.product', 'customer');
        });
    }

    public function cancelOrder(Order $order): Order
    {
        if (! $order->canBeCancelled()) {
            throw new InvalidArgumentException('This order cannot be cancelled');
        }

        return DB::transaction(function () use ($order) {
            $order->load('items');

            foreach ($order->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);

                if ($product) {
                    $product->incrementStock($item->quantity);
                }
            }

            $order->update(['status' => OrderStatus::CANCELLED]);

            return $order->load('items.product', 'customer');
        });
    }

    public function updateStatus(Order $order, OrderStatus $newStatus): Order
    {
        if (! $order->status->canBeUpdatedTo($newStatus)) {
            throw new InvalidArgumentException(
                "Cannot update order status from {$order->status->value} to {$newStatus->value}"
            );
        }

        if ($newStatus === OrderStatus::CANCELLED) {
            return $this->cancelOrder($order);
        }

        $order->update(['status' => $newStatus]);

        return $order->load('items.product', 'customer');
    }
}
