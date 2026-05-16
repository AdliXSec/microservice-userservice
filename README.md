# MedTech Microservices System

Aplikasi ini adalah simulasi sistem manajemen pemesanan (e-commerce) berbasis Microservices menggunakan arsitektur Event-Driven dengan RabbitMQ.

## 🏗 Arsitektur Sistem

Sistem ini terdiri dari 4 microservices utama:

1. **UI Service (Laravel - Port 8000):** Menangani antarmuka pengguna (Frontend).
2. **Order Service (Laravel - Port 8001):** Mengelola transaksi pesanan.
3. **Product Service (Laravel - Port 8002):** Mengelola katalog dan stok produk.
4. **User Service (Python/Flask - Port 5001):** Mengelola autentikasi dan manajemen pengguna.

Sistem juga menggunakan:

- **MySQL:** Database relasional (1 Container, beda database per service).
- **RabbitMQ:** Message Broker untuk komunikasi asinkron antar service.

---

## 🚀 Panduan Instalasi (Untuk Developer Baru)

Ikuti langkah-langkah di bawah ini untuk menjalankan project ini di komputer Anda setelah melakukan _clone_ dari GitHub.

### Prasyarat

Pastikan Anda sudah menginstal:

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- Git

### Langkah 1: Clone & Setup Environment

1. Clone repository ini:

   ```bash
   git clone https://github.com/AdliXSec/microservice-userservice
   cd microservice
   ```

2. Siapkan file konfigurasi (`.env`) untuk masing-masing service. Salin file `.env.example` menjadi `.env` di setiap folder:
   _(Untuk pengguna Windows PowerShell)_
   ```powershell
   copy uiservice\.env.example uiservice\.env
   copy orderservice\.env.example orderservice\.env
   copy productservice\.env.example productservice\.env
   copy userservice\.env.example userservice\.env
   ```

### Langkah 2: Jalankan Docker & Install Dependencies

Karena kita menggunakan _Volume Mapping_ (kode lokal terhubung langsung ke dalam Docker), folder `vendor` yang diabaikan oleh Git harus diinstal secara manual dari dalam container.

1. Bangun dan jalankan semua container di background:

   ```bash
   docker-compose up -d --build
   ```

2. Install dependensi PHP (Laravel) di dalam masing-masing container:

   ```bash
   docker exec -it microservice-uiservice-1 composer install
   docker exec -it microservice-orderservice-1 composer install
   docker exec -it microservice-productservice-1 composer install
   ```

3. Bersihkan cache konfigurasi untuk memastikan `.env` terbaru terbaca:
   ```bash
   docker exec -it microservice-uiservice-1 php artisan config:clear
   docker exec -it microservice-orderservice-1 php artisan config:clear
   docker exec -it microservice-productservice-1 php artisan config:clear
   ```

### Langkah 3: Database & Migrasi (Opsional/Jika Diperlukan)

_Jika project Anda memiliki file migrasi, Anda mungkin perlu menjalankannya. Jika database sudah diinisialisasi melalui `init-db.sql`, lewati langkah ini._

```bash
# Contoh jika butuh migrasi:
# docker exec -it microservice-orderservice-1 php artisan migrate
# docker exec -it microservice-productservice-1 php artisan migrate
```

---

## ⚙️ Menjalankan Sistem Asinkron (RabbitMQ Workers)

Ini adalah bagian paling penting. Agar data antar-service tersinkronisasi (misal: pendaftaran user sinkron ke OrderService, atau stok produk berkurang saat ada pesanan), Anda **WAJIB** menjalankan Worker RabbitMQ.

Buka terminal baru untuk masing-masing perintah di bawah ini dan biarkan berjalan di background:

**Terminal 1: Menjalankan Worker Sinkronisasi User (Di OrderService)**
Ini bertugas menangkap event pendaftaran user baru dari Python (Flask).

```bash
docker exec -it microservice-orderservice-1 php artisan queue:work rabbitmq_users
```

**Terminal 2: Menjalankan Worker Potong Stok (Di ProductService)**
Ini bertugas memotong stok obat secara otomatis setiap kali ada order baru.

```bash
docker exec -it microservice-productservice-1 php artisan queue:work rabbitmq --queue=product_stock_queue
```

_(Opsional) Terminal 3: Tugas Default OrderService_

```bash
docker exec -it microservice-orderservice-1 php artisan queue:work rabbitmq
```

---

## 🌐 Akses Aplikasi

Setelah semuanya berjalan, Anda bisa mengakses layanan melalui browser:

- **Aplikasi Web (UI):** [http://localhost:8000](http://localhost:8000)
- **RabbitMQ Management (Monitor Antrean):** [http://localhost:15672](http://localhost:15672) (User: `guest` | Pass: `guest`)

## 🛑 Cara Mematikan Aplikasi

Untuk mematikan semua sistem dengan bersih tanpa kehilangan data database (karena sudah menggunakan volumes):

```bash
docker-compose down
```
