# Kasentra API

REST API untuk aplikasi mobile (Flutter). Autentikasi memakai **token Laravel Sanctum**.

- **Base URL (live):** `https://kasentra-production.up.railway.app/api`
- **Base URL (lokal):** `http://127.0.0.1:8000/api`
- Semua request & response memakai `application/json`. Sertakan header `Accept: application/json`.
- Endpoint terproteksi butuh header: `Authorization: Bearer <token>`.

---

## Autentikasi

### `POST /login`
Publik. Dibatasi 6 percobaan/menit.

```json
{ "email": "admin@kasentra.test", "password": "password", "device_name": "Samsung A14" }
```

Response `200`:
```json
{
  "token": "1|xxxxxxxx",
  "user": { "id": 1, "name": "Admin Kasentra", "email": "admin@kasentra.test", "role": "admin", "is_admin": true }
}
```
Kredensial salah / akun nonaktif → `422` dengan pesan di `errors.email`.

### `GET /me`
Data user yang sedang login.

### `POST /logout`
Mencabut token yang sedang dipakai.

---

## Katalog

### `GET /products`
Daftar produk aktif (paginated). Query opsional:

| Param | Keterangan |
|-------|-----------|
| `search` | cari nama atau SKU |
| `category_id` | filter kategori |
| `per_page` | jumlah per halaman (default 50) |
| `page` | halaman |

Item:
```json
{
  "id": 5, "name": "Air Mineral 600ml", "sku": "MIN-006",
  "price": 3000, "stock": 200, "is_low_stock": false, "is_active": true,
  "image_url": null, "category": { "id": 2, "name": "Minuman" }
}
```

### `GET /categories`
Daftar kategori.

### `GET /settings/qris`
URL gambar QRIS toko (untuk layar pembayaran QRIS).
```json
{ "qris_image_url": "https://.../storage/settings/xxx.jpg" }
```

---

## Transaksi

### `POST /transactions`
Checkout. Stok dikunci & dikurangi otomatis, invoice unik dibuat, harga di-snapshot.

```json
{
  "items": [ { "id": 5, "qty": 2 }, { "id": 2, "qty": 1 } ],
  "paid": 100000,
  "payment_method": "tunai"
}
```
`payment_method`: `tunai` | `qris` | `transfer`.

Response `201`/`200`:
```json
{
  "data": {
    "id": 3, "invoice_number": "INV-20260608-00003",
    "total": 6000, "paid": 100000, "change": 94000,
    "payment_method": "tunai", "payment_method_label": "Tunai",
    "cashier": { "id": 1, "name": "Admin Kasentra" },
    "items": [ { "id": 4, "product_id": 5, "product_name": "Air Mineral 600ml", "price": 3000, "qty": 2, "subtotal": 6000 } ],
    "created_at": "2026-06-08T18:43:34+00:00"
  }
}
```
Stok kurang / uang kurang → `422` dengan pesan di `errors`.

### `GET /transactions`
Riwayat (paginated). Kasir hanya melihat miliknya; admin melihat semua. Query: `dari`, `sampai` (YYYY-MM-DD), `per_page`, `page`.

### `GET /transactions/{id}`
Detail satu transaksi (admin atau pemilik transaksi). Lainnya → `403`.

---

## Laporan (admin)

### `GET /reports/summary`
Khusus admin (`403` bila bukan admin). Query: `dari`, `sampai` (default: awal bulan s/d hari ini).
```json
{
  "periode": { "dari": "2026-06-01", "sampai": "2026-06-08" },
  "omzet": 83000, "jumlah_transaksi": 3, "rata_rata": 27666.67,
  "produk_terlaris": [ { "product_name": "Air Mineral 600ml", "qty": 5, "omzet": 15000 } ]
}
```

---

## Catatan implementasi

- Token tidak kedaluwarsa otomatis; simpan aman di perangkat (mis. `flutter_secure_storage`) dan hapus saat logout.
- Endpoint `401` berarti token hilang/invalid → arahkan kembali ke layar login.
- Validasi gagal selalu `422` dengan struktur `{ "message": "...", "errors": { "field": ["..."] } }`.
