# 🏥 Apotek Microservices System

Sistem Microservice sederhana yang terdiri dari **Order Service (Laravel)** dan **User Service (Flask)**. Sistem ini mengintegrasikan manajemen user (Authentication/JWT) dengan sistem pemesanan obat (Inventory & Sales).

## 🚀 Fitur Utama

- **Role-Based Access Control (RBAC):** Admin (Full Access) & User (Limited Access).
- **JWT Authentication:** Secure login dengan fitur blacklist token & refresh token.
- **Microservice Communication:** Validasi stok dan pengurangan stok antar service secara otomatis.
- **Creative Order System:** Auto-generate unique order codes.

---

## 🔐 User Service (Flask - Port 5000)

Service ini menangani pendaftaran, login, dan manajemen user.

### 1. Register User

- **Endpoint:** `POST /register`
- **Request Body:**

```json
{
  "name": "Naufal Adli",
  "email": "adli@gmail.com",
  "password": "password123",
  "role": "admin"
}
```

- **Response (201):**

```json
{
  "status": "berhasil",
  "message": "User berhasil didaftarkan",
  "data": {
    "id": 1,
    "name": "Naufal Adli",
    "email": "adli@gmail.com",
    "role": "admin"
  }
}
```

### 2. Login

- **Endpoint:** `POST /login`
- **Request Body:** `{ "email": "adli@gmail.com", "password": "password123" }`
- **Response (200):** Memberikan `access_token` yang berlaku selama 12 jam.

### 3. Logout (Revoke Token)

- **Endpoint:** `POST /logout` (Auth Required)
- **Fungsi:** Memasukkan token saat ini ke daftar hitam (blacklist) agar tidak bisa digunakan lagi.

---

## 📦 Order Service (Laravel - Port 8000)

Service ini menangani transaksi pesanan obat.

### 1. Create Order (User & Admin)

- **Endpoint:** `POST /api/orders` (Auth Required)
- **Request Body:**

```json
{
  "product_id": 1,
  "quantity": 2
}
```

- **Response (201):**

```json
{
  "status": "berhasil",
  "message": "Order berhasil dibuat",
  "data": {
    "order_code": "ORD-NAUFAL-ADLI-PARACETAMOL-4321",
    "product_name": "Paracetamol Sirup",
    "quantity": 2,
    "total_price": 50000,
    "customer_name": "Naufal Adli",
    "status": "pending"
  }
}
```

### 2. Get List Order (Filtered)

- **Endpoint:** `GET /api/orders?status=pending&user_id=1`
- **Params:** `order_code`, `user_id`, `status` (Optional).

### 3. Update Status (Admin Only)

- **Endpoint:** `PATCH /api/orders/{id}/status`
- **Request Body:** `{ "status": "processing" }`
- **Aturan:** Status `completed` & `cancelled` tidak dapat diubah lagi.

### 4. Edit Order (Admin Only)

- **Endpoint:** `PUT /api/orders/{id}`
- **Fungsi:** Mengubah produk atau jumlah. Stok di Product Service akan otomatis disesuaikan (dikembalikan/dikurangi ulang).

---

## 🛠 Cara Menjalankan

1. **User Service:**
   ```bash
   cd userservice
   pip install -r requirements.txt
   python init_db.py
   python run.py
   ```
2. **Order Service:**
   ```bash
   cd orderservice
   composer install
   php artisan migrate:fresh --seed
   php artisan serve --port=8000
   ```

---

_Dibuat dengan ❤️ untuk sistem microservice yang lebih aman dan terstruktur._
