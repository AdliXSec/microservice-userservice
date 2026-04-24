<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Order;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    // URL microservice lain
    private string $userServiceUrl;
    private string $productServiceUrl;

    // Cache local untuk efisiensi saat index (memoization)
    private array $userCache = [];
    private array $productCache = [];

    public function __construct()
    {
        $this->userServiceUrl = config('services.user_service.url');
        $this->productServiceUrl = config('services.product_service.url');
    }

    /**
     * Ambil data user dari User Service (Flask).
     */
    private function getUserData($userId)
    {
        if (isset($this->userCache[$userId])) {
            return $this->userCache[$userId];
        }

        try {
            $response = Http::timeout(5)->get("{$this->userServiceUrl}/users/{$userId}");

            if ($response->successful()) {
                $data = $response->json()['data'] ?? $response->json();
                $this->userCache[$userId] = $data;
                return $data;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Ambil data produk (obat) dari Product Service (Laravel).
     */
    private function getProductData($productId)
    {
        if (isset($this->productCache[$productId])) {
            return $this->productCache[$productId];
        }

        try {
            $response = Http::timeout(5)->get("{$this->productServiceUrl}/api/obat/{$productId}");

            if ($response->successful()) {
                $data = $response->json()['data'] ?? $response->json();
                $this->productCache[$productId] = $data;
                return $data;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Gabungkan data order dengan data user & product dari service lain (Field terbatas).
     */
    private function enrichOrder($order)
    {
        $userData = $this->getUserData($order->user_id);
        $productData = $this->getProductData($order->product_id);

        return [
            'id'             => $order->id,
            'order_code'     => $order->order_code,
            'nama_produk'    => $productData['name'] ?? ($productData['nama_obat'] ?? 'Produk tidak ditemukan'),
            'quantity'       => $order->quantity,
            'total_price'    => $order->total_price,
            'nama_pemesan'   => $userData['name'] ?? ($userData['username'] ?? 'User tidak ditemukan'),
            'email_pemesan'  => $userData['email'] ?? '-',
            'status'         => $order->status,
            'tanggal_order'  => $order->created_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Menampilkan semua data order (dengan data user & product).
     */
    public function index()
    {
        $orders = Order::latest()->get();

        // Enrich setiap order dengan data dari service lain (menggunakan cache)
        $enrichedOrders = $orders->map(function ($order) {
            return $this->enrichOrder($order);
        });

        return new OrderResource($enrichedOrders, 'berhasil', 'List data order');
    }

    /**
     * Menampilkan detail order berdasarkan ID (dengan data user & product).
     */
    public function show($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return new OrderResource(null, 'gagal', 'Data order tidak ditemukan');
        }

        $enrichedOrder = $this->enrichOrder($order);

        return new OrderResource($enrichedOrder, 'berhasil', 'Detail data order');
    }

    /**
     * Membuat order baru.
     */
    public function store(Request $request)
    {
        // Pastikan validasi mengembalikan JSON jika gagal
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'product_id' => 'required',
            'quantity'   => 'required|integer|min:1',
            'user_id'    => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return new OrderResource(null, 'gagal', $validator->errors()->first());
        }

        // Prioritas user_id: dari request, jika tidak ada baru dari data login
        $authUser = $request->auth_user;
        $userId = $request->user_id ?? ($authUser['id'] ?? null);

        if (!$userId) {
            return new OrderResource(null, 'gagal', 'User ID tidak ditemukan. Silahkan login atau lampirkan user_id');
        }

        // --- VALIDASI USER EXISTENCE ---
        $userData = $this->getUserData($userId);
        if (!$userData || (is_array($userData) && empty($userData))) {
            return new OrderResource(null, 'gagal', "User dengan ID {$userId} tidak ditemukan di User Service");
        }

        // --- VALIDASI PRODUCT EXISTENCE ---
        $productData = $this->getProductData($request->product_id);
        if (!$productData || (is_array($productData) && empty($productData))) {
            return new OrderResource(null, 'gagal', 'Produk tidak ditemukan di Product Service');
        }

        // --- VALIDASI STOK ---
        $stokTersedia = $productData['stock'] ?? ($productData['stok'] ?? 0);
        if ($stokTersedia < $request->quantity) {
            return new OrderResource(null, 'gagal', "Stok tidak mencukupi. Stok tersedia: {$stokTersedia}");
        }

        // Hitung total_price otomatis dari harga produk x quantity
        $harga = $productData['price'] ?? ($productData['harga'] ?? 0);
        $totalPrice = $harga * $request->quantity;

        // --- GENERATE CREATIVE ORDER CODE ---
        // Format: ORD-namapemesan-namaobat-randomint
        $namaPemesan = Str::slug($userData['name'] ?? ($userData['username'] ?? 'user'));
        $namaProduk = Str::slug($productData['name'] ?? ($productData['nama_obat'] ?? 'produk'));
        $randomInt = rand(1000, 9999);
        $orderCode = "ORD-{$namaPemesan}-{$namaProduk}-{$randomInt}";

        // --- PENGURANGAN STOK DI PRODUCT SERVICE ---
        try {
            $newStok = $stokTersedia - $request->quantity;
            $updateStockResponse = Http::timeout(5)->put("{$this->productServiceUrl}/api/obat/{$request->product_id}", [
                'stock' => $newStok
            ]);

            if (!$updateStockResponse->successful()) {
                return new OrderResource(null, 'gagal', 'Gagal memperbarui stok di Product Service');
            }
        } catch (\Exception $e) {
            return new OrderResource(null, 'gagal', 'Terjadi kesalahan saat menghubungi Product Service untuk update stok');
        }

        $order = Order::create([
            'order_code'  => $orderCode,
            'user_id'     => $userId,
            'product_id'  => $request->product_id,
            'quantity'    => $request->quantity,
            'total_price' => $totalPrice,
            'status'      => $request->status ?? 'pending',
        ]);

        // Response dengan data terpilih (menggunakan enrichOrder)
        $orderData = $this->enrichOrder($order);

        return new OrderResource($orderData, 'berhasil', 'Data order berhasil ditambahkan');
    }

    /**
     * Mengupdate data order.
     */
    public function update(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return new OrderResource(null, 'gagal', 'Data order tidak ditemukan');
        }

        $data = $request->all();

        // Jika ada perubahan product_id atau quantity, hitung ulang total_price
        if ($request->has('product_id') || $request->has('quantity')) {
            $productId = $request->product_id ?? $order->product_id;
            $quantity = $request->quantity ?? $order->quantity;

            $productData = $this->getProductData($productId);
            if (!$productData) {
                return new OrderResource(null, 'gagal', 'Produk tidak ditemukan di Product Service');
            }

            $harga = $productData['harga'] ?? 0;
            $data['total_price'] = $harga * $quantity;
        }

        $order->update($data);

        // Reset cache agar data terbaru diambil jika ada perubahan
        $this->productCache = [];

        $enrichedOrder = $this->enrichOrder($order);

        return new OrderResource($enrichedOrder, 'berhasil', 'Data order berhasil diupdate');
    }

    /**
     * Menghapus data order.
     */
    public function destroy($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return new OrderResource(null, 'gagal', 'Data order tidak ditemukan');
        }

        $order->delete();

        return new OrderResource(null, 'berhasil', 'Data order berhasil dihapus');
    }
}