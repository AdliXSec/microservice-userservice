<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    private string $userServiceUrl;
    private string $productServiceUrl;

    public function __construct()
    {
        $this->userServiceUrl = config('services.user_service.url');
        $this->productServiceUrl = config('services.product_service.url');
    }

    public function index()
    {
        $orders = Order::all();
        return new OrderResource($orders, 'Success', 'List of orders');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            'user_id' => 'required',
            'quantity' => 'required',
        ]);

        if ($validator->fails()) {
            return new OrderResource(null, 'Failed', $validator->errors());
        }

        $userResponse = Http::get("http://127.0.0.1:5000/users/{$request->user_id}");
        $user = $userResponse->json()['data'] ?? null;

        if (!$user) {
            return new OrderResource(null, 'Failed', 'User not found');
        }

        $productResponse = Http::get("http://127.0.0.1:8001/api/products/{$request->product_id}");
        $productData = $productResponse->json()['data'] ?? null;

        if (!$productData) {
            return new OrderResource(null, 'Failed', 'Product not found');
        }

        $order = Order::create([
            'order_code' => $this->generateOrderCode($user, $productData),
            'user_id' => $request->user_id,
            'customer_name' => $user['name'] ?? ($user['username'] ?? 'Unknown'),
            'customer_email' => $user['email'] ?? '-',
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'total_price' => ($productData['price'] ?? 0) * $request->quantity,
            'status' => 'pending',
        ]);

        Http::post("http://127.0.0.1:8001/api/products/{$request->product_id}", [
            'stock' => $request->quantity,
        ]);

        return new OrderResource($order, 'Success', 'Order created successfully');
    }

    public function show($id)
    {
        $order = Order::find($id);
        if ($order) {
            $data = $order->toArray();

            // Get the product details (consume)
            $productResponse = Http::get("{$this->productServiceUrl}/api/products/{$order->product_id}");
            $data['product'] = $productResponse->json()['data'] ?? null;

            // Get the user details (consume)
            $userResponse = Http::get("{$this->userServiceUrl}/users/{$order->user_id}");
            $data['user'] = $userResponse->json()['data'] ?? null;

            return new OrderResource($data, 'Success', 'Order found');
        } else {
            return new OrderResource(null, 'Failed', 'Order not found');
        }
    }

    public function update(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return new OrderResource(null, 'Failed', 'Order not found');
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            'quantity' => 'required',
            'status' => 'required',
        ]);

        if ($validator->fails()) {
            return new OrderResource(null, 'Failed', $validator->errors());
        }

        // Get Product Info for price
        $productResponse = Http::get("{$this->productServiceUrl}/api/products/{$request->product_id}");
        $productData = $productResponse->json()['data'] ?? null;

        if (!$productData) {
            return new OrderResource(null, 'Failed', 'Product not found');
        }

        $order->update([
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'total_price' => ($productData['price'] ?? 0) * $request->quantity,
            'status' => $request->status,
        ]);

        return new OrderResource($order, 'Success', 'Order updated successfully');
    }

    public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return new OrderResource(null, 'Failed', 'Order not found');
        }

        $order->update(['status' => $request->status]);
        return new OrderResource($order, 'Success', 'Status updated successfully');
    }

    public function destroy($id)
    {
        $order = Order::find($id);
        if (!$order) {
            return new OrderResource(null, 'Failed', 'Order not found');
        }

        $order->delete();
        return new OrderResource(null, 'Success', 'Order deleted successfully');
    }

    private function generateOrderCode($user, $product)
    {
        $name = Str::slug($user['name'] ?? ($user['username'] ?? 'user'));
        $item = Str::slug($product['name'] ?? ($product['nama_obat'] ?? 'item'));
        return strtoupper("ORD-{$name}-{$item}-" . rand(1000, 9999));
    }
}
