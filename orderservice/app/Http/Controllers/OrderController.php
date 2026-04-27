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

    private array $usersCache = [];

    public function __construct()
    {
        $this->userServiceUrl = config('services.user_service.url');
        $this->productServiceUrl = config('services.product_service.url');
    }

    protected function fetchUserData($id)
    {
        if (!$id)
            return null;
        if (isset($this->usersCache[$id]))
            return $this->usersCache[$id];

        $response = Http::timeout(5)->get("http://127.0.0.1:5000/users/{$id}");
        return $this->usersCache[$id] = $response->successful() ? ($response->json()['data'] ?? $response->json()) : null;
    }

    public function index()
    {
        $orders = Order::all();
        return new OrderResource($orders, 'Success', 'List of orders');
    }

    public function store(Request $request)
    {

        $userId = $request->user_id ?? ($request->auth_user['id'] ?? null);
        $userData = $this->fetchUserData($userId);

        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            // 'user_id' => '',
            'quantity' => 'required',
        ]);

        if ($validator->fails()) {
            return new OrderResource(null, 'Failed', $validator->errors());
        }

        $userResponse = Http::get("http://127.0.0.1:5000/users/{$request->user_id}");
        // $user = $userResponse->json()['data'] ?? null;

        if (!$userData) {
            return new OrderResource(null, 'Failed', 'User not found');
        }

        $productResponse = Http::get("http://127.0.0.1:8000/api/obat/{$request->product_id}");
        $productData = $productResponse->json()['data'] ?? null;

        if (!$productData) {
            return new OrderResource(null, 'Failed', 'Product not found');
        }

        $order = Order::create([
            'order_code' => $this->generateOrderCode($userData, $productData),
            'user_id' => $userId,
            'customer_name' => $userData['name'] ?? ($userData['username'] ?? 'Unknown'),
            'customer_email' => $userData['email'] ?? '-',
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'total_price' => ($productData['price'] ?? 0) * $request->quantity,
            'status' => 'pending',
        ]);

        Http::put("http://127.0.0.1:8000/api/obat/{$request->product_id}", [
            'stock' => $productData['stock'] - $request->quantity,
        ]);

        return new OrderResource($order, 'Success', 'Order created successfully');
    }

    public function show($id)
    {
        $order = Order::find($id);
        if ($order) {
            $data = $order->toArray();

            // Get the product details (consume)
            $productResponse = Http::get("{$this->productServiceUrl}/api/obat/{$order->product_id}");
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
        $productResponse = Http::get("{$this->productServiceUrl}/api/obat/{$request->product_id}");
        $productData = $productResponse->json()['data'] ?? null;

        if (!$productData) {
            return new OrderResource(null, 'Failed', 'Product not found');
        }

        if ($order->quantity > $request->quantity) {
            $qtt = $order->quantity - $request->quantity;
            Http::put("http://127.0.0.1:8000/api/obat/{$request->product_id}", [
                'stock' => $productData['stock'] + $qtt,
            ]);
        } else {
            $qtt = $request->quantity - $order->quantity;
            Http::put("http://127.0.0.1:8000/api/obat/{$request->product_id}", [
                'stock' => $productData['stock'] - $qtt,
            ]);
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
