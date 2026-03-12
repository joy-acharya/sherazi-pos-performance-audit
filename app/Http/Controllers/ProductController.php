<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Http\Resources\SalesReportResource;

use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index()
    {
        $page = request()->get('page', 1);

        $products = Cache::remember("products_page_{$page}", 300, function () {
            return Product::with('category')
                ->select('id', 'name', 'price', 'stock', 'category_id')
                ->paginate(15);
        });

        return ProductResource::collection($products);
    }

    public function salesReport()
    {
        $items = OrderItem::with(['product:id,name', 'order.customer:id,name'])
            ->select('id', 'order_id', 'product_id', 'quantity', 'unit_price')
            ->paginate(15);

        return SalesReportResource::collection($items);
    }

    public function dashboard()
    {
        $data = Cache::remember('products_dashboard', 300, function () {
            return [
                'total_products' => Product::count(),
                'total_orders' => Order::count(),
                'total_revenue' => Order::sum('total_amount'),
                'categories' => Category::select('id', 'name')->get(),
                'top_products' => Product::select('id', 'name', 'price', 'stock', 'sold_count', 'category_id')
                    ->orderByDesc('sold_count')
                    ->take(5)
                    ->get(),
            ];
        });

        return response()->json($data);
    }

    public function search(Request $request)
    {
        $keyword  = $request->input('q');
        $products = Product::where('name', 'LIKE', '%' . $keyword . '%')
                           ->orWhere('description', 'LIKE', '%' . $keyword . '%')
                           ->get();

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
        ]);

        $product = Product::create($request->only([
            'name',
            'description',
            'price',
            'stock',
            'category_id',
        ]) + [
            'sold_count' => 0,
        ]);

        for ($i = 1; $i <= 50; $i++) {
            Cache::forget("products_page_{$i}");
        }

        Cache::forget('products_dashboard');

        return response()->json($product, 201);
    }
}
