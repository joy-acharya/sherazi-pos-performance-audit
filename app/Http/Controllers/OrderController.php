<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $order = DB::transaction(function () use ($request) {
            $totalAmount = 0;

            $order = Order::create([
                'customer_id'  => $request->customer_id,
                'total_amount' => 0,
                'status'       => 'pending',
            ]);

            $productIds = collect($request->items)
                ->pluck('product_id')
                ->unique()
                ->values();

            $products = Product::whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            foreach ($request->items as $item) {
                $product = $products->get($item['product_id']);

                if (!$product || $product->stock < $item['quantity']) {
                    abort(422, 'Product unavailable or insufficient stock');
                }

                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'unit_price' => $product->price,
                ]);

                $product->decrement('stock', $item['quantity']);
                $product->increment('sold_count', $item['quantity']);

                $totalAmount += $product->price * $item['quantity'];
            }

            $order->update([
                'total_amount' => round($totalAmount, 2),
            ]);

            return $order->load('customer', 'items');
        });

        for ($i = 1; $i <= 50; $i++) {
            Cache::forget("products_page_{$i}");
        }

        Cache::forget('products_dashboard');

        return response()->json($order, 201);
    }

    public function index()
    {
        $orders = Order::with('customer')
            ->withCount('items')
            ->select('id', 'customer_id', 'total_amount', 'status', 'created_at')
            ->latest()
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    public function filterByStatus(Request $request)
    {
        $request->validate([
            'status' => 'required|in:pending,completed,cancelled',
        ]);

        $orders = Order::with('customer')
            ->withCount('items')
            ->where('status', $request->status)
            ->latest()
            ->paginate(15);

        return OrderResource::collection($orders);
    }
}
