<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Order;
use App\Http\Resources\OrderResource;

class OrderController extends Controller
{
    // URL microservice lain
    private string $userServiceUrl = 'http://127.0.0.1:5000';
    private string $productServiceUrl = 'http://127.0.0.1:8000';

    /**
     * Ambil data user dari User Service (Flask).
     */
    private function getUserData($userId)
    {
        try {
            $response = Http::timeout(5)->get("{$this->userServiceUrl}/users/{$userId}");

            if ($response->successful()) {
                return $response->json()['data'] ?? $response->json();
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
        try {
            $response = Http::timeout(5)->get("{$this->productServiceUrl}/api/obat/{$productId}");

            if ($response->successful()) {
                return $response->json()['data'] ?? $response->json();
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Gabungkan data order dengan data user & product dari service lain.
     */
    private function enrichOrder($order)
    {
        $orderData = $order->toArray();
        $orderData['user'] = $this->getUserData($order->user_id);
        $orderData['product'] = $this->getProductData($order->product_id);

        return $orderData;
    }

    /**
     * Menampilkan semua data order (dengan data user & product).
     */
    public function index()
    {
        $orders = Order::all();

        // Enrich setiap order dengan data dari service lain
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
     * user_id otomatis dari data login (middleware verify.login).
     * Cukup kirim product_id dan quantity saja.
     */
    public function store(Request $request)
    {
        // user_id otomatis dari data login (sudah diverifikasi middleware)
        $authUser = $request->auth_user;
        $userId = $authUser['id'];

        // Validasi: cek apakah product ada di Product Service
        $productData = $this->getProductData($request->product_id);
        if (!$productData) {
            return new OrderResource(null, 'gagal', 'Produk tidak ditemukan di Product Service');
        }

        // Hitung total_price otomatis dari harga produk x quantity
        $harga = $productData['harga'] ?? 0;
        $totalPrice = $harga * $request->quantity;

        $order = Order::create([
            'user_id'     => $userId,
            'product_id'  => $request->product_id,
            'quantity'    => $request->quantity,
            'total_price' => $totalPrice,
            'status'      => $request->status ?? 'pending',
        ]);

        // Response dengan data lengkap dari semua service
        $orderData = $order->toArray();
        $orderData['user'] = $authUser;
        $orderData['product'] = $productData;

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

        $order->update($request->all());

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
