<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    // Konstanta Status untuk menghindari error penulisan
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    private string $userServiceUrl;
    private string $productServiceUrl;

    // Cache local untuk performa
    private array $userCache = [];
    private array $productCache = [];

    public function __construct()
    {
        $this->userServiceUrl = config('services.user_service.url');
        $this->productServiceUrl = config('services.product_service.url');
    }

    /**
     * Tampilkan daftar order dengan filter.
     */
    public function index(Request $request)
    {
        $orders = Order::query()
            ->when($request->order_code, fn($q) => $q->where('order_code', 'like', "%{$request->order_code}%"))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->get();

        $enrichedOrders = $orders->map(fn($order) => $this->transformOrder($order));

        return new OrderResource($enrichedOrders, 'berhasil', 'List data order');
    }

    /**
     * Simpan order baru.
     */
    public function store(Request $request)
    {
        $validator = $this->validateOrderRequest($request);
        if ($validator->fails()) {
            return new OrderResource(null, 'gagal', $validator->errors()->first());
        }

        $userId = $request->user_id ?? ($request->auth_user['id'] ?? null);
        $userData = $this->fetchUserData($userId);

        if (!$userData) {
            return new OrderResource(null, 'gagal', "User tidak ditemukan");
        }

        $productData = $this->fetchProductData($request->product_id);
        if (!$productData) {
            return new OrderResource(null, 'gagal', 'Produk tidak ditemukan');
        }

        if (!$this->hasEnoughStock($productData, $request->quantity)) {
            return new OrderResource(null, 'gagal', "Stok tidak cukup. Tersedia: " . ($productData['stock'] ?? 0));
        }

        if (!$this->deductProductStock($request->product_id, $productData, $request->quantity)) {
            return new OrderResource(null, 'gagal', 'Gagal sinkronisasi stok ke Product Service');
        }

        $order = Order::create([
            'order_code' => $this->generateOrderCode($userData, $productData),
            'user_id' => $userId,
            'customer_name' => $userData['name'] ?? ($userData['username'] ?? 'Unknown'),
            'customer_email' => $userData['email'] ?? '-',
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'total_price' => ($productData['price'] ?? 0) * $request->quantity,
            'status' => self::STATUS_PENDING,
        ]);

        return new OrderResource($this->transformOrder($order), 'berhasil', 'Order berhasil dibuat');
    }

    /**
     * Update status dengan pengamanan workflow.
     */
    public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order)
            return new OrderResource(null, 'gagal', 'Order tidak ditemukan');

        // Proteksi status akhir
        if (in_array($order->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            return new OrderResource(null, 'gagal', "Pesanan sudah dalam status permanen ({$order->status})");
        }

        // Validasi transisi (Pending tidak bisa langsung Completed)
        if ($request->status === self::STATUS_COMPLETED && $order->status === self::STATUS_PENDING) {
            return new OrderResource(null, 'gagal', 'Harus melalui tahap processing dulu');
        }

        $order->update(['status' => $request->status]);

        return new OrderResource($this->transformOrder($order), 'berhasil', 'Status diperbarui');
    }

    /**
     * Detail Order.
     */
    public function show($id)
    {
        $order = Order::find($id);
        return $order
            ? new OrderResource($this->transformOrder($order), 'berhasil', 'Detail order')
            : new OrderResource(null, 'gagal', 'Order tidak ditemukan');
    }


    private function validateOrderRequest($request)
    {
        return Validator::make($request->all(), [
            'product_id' => 'required',
            'quantity' => 'required|integer|min:1',
            'user_id' => 'nullable|integer',
        ]);
    }

    private function fetchUserData($userId)
    {
        if (!$userId)
            return null;
        if (isset($this->userCache[$userId]))
            return $this->userCache[$userId];

        $response = Http::timeout(5)->get("{$this->userServiceUrl}/users/{$userId}");
        return $this->userCache[$userId] = $response->successful() ? ($response->json()['data'] ?? $response->json()) : null;
    }

    private function fetchProductData($productId)
    {
        if (isset($this->productCache[$productId]))
            return $this->productCache[$productId];

        $response = Http::timeout(5)->get("{$this->productServiceUrl}/api/obat/{$productId}");
        return $this->productCache[$productId] = $response->successful() ? ($response->json()['data'] ?? $response->json()) : null;
    }

    private function hasEnoughStock($product, $qty)
    {
        $stock = $product['stock'] ?? ($product['stok'] ?? 0);
        return $stock >= $qty;
    }

    private function deductProductStock($productId, $product, $qty)
    {
        $newStock = ($product['stock'] ?? $product['stok']) - $qty;
        return Http::timeout(5)->put("{$this->productServiceUrl}/api/obat/{$productId}", ['stock' => $newStock])->successful();
    }

    private function generateOrderCode($user, $product)
    {
        $name = Str::slug($user['name'] ?? ($user['username'] ?? 'user'));
        $item = Str::slug($product['name'] ?? ($product['nama_obat'] ?? 'item'));
        return strtoupper("ORD-{$name}-{$item}-" . rand(1000, 9999));
    }

    private function transformOrder($order)
    {
        $prod = $this->fetchProductData($order->product_id);

        return [
            'id' => $order->id,
            'order_code' => $order->order_code,
            'product_name' => $prod['name'] ?? 'Produk dihapus',
            'category' => $prod['category'] ?? 'Kategori dihapus',
            'quantity' => $order->quantity,
            'total_price' => (float) $order->total_price,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'status' => $order->status,
            'created_at' => $order->created_at->toDateTimeString(),
        ];
    }

    /**
     * Update data order (Admin Only).
     */
    public function update(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order)
            return new OrderResource(null, 'gagal', 'Order tidak ditemukan');

        if (in_array($order->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            return new OrderResource(null, 'gagal', 'Order yang sudah selesai atau dibatalkan tidak dapat diedit');
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable',
            'quantity' => 'nullable|integer|min:1',
            'status' => 'nullable|in:pending,processing,completed,cancelled'
        ]);

        if ($validator->fails()) {
            return new OrderResource(null, 'gagal', $validator->errors()->first());
        }

        $productId = $request->product_id ?? $order->product_id;
        $quantity = $request->quantity ?? $order->quantity;

        $productData = $this->fetchProductData($productId);
        if (!$productData)
            return new OrderResource(null, 'gagal', 'Produk tidak ditemukan');

        if ($request->has('quantity') || $request->has('product_id')) {
            $oldProductData = $this->fetchProductData($order->product_id);

            $this->deductProductStock($order->product_id, $oldProductData, -$order->quantity);

            $currentProductData = $this->fetchProductData($productId);
            if (!$this->hasEnoughStock($currentProductData, $quantity)) {
                $this->deductProductStock($order->product_id, $oldProductData, $order->quantity);
                return new OrderResource(null, 'gagal', 'Stok baru tidak mencukupi');
            }

            $this->deductProductStock($productId, $currentProductData, $quantity);
        }

        $order->update([
            'product_id' => $productId,
            'quantity' => $quantity,
            'total_price' => ($productData['price'] ?? 0) * $quantity,
            'status' => $request->status ?? $order->status,
        ]);

        return new OrderResource($this->transformOrder($order), 'berhasil', 'Order berhasil diperbarui');
    }

    public function destroy($id)
    {
        $order = Order::find($id);
        if (!$order)
            return new OrderResource(null, 'gagal', 'Order tidak ditemukan');
        $order->delete();
        return new OrderResource(null, 'berhasil', 'Order dihapus');
    }
}