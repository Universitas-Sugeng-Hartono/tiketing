# Deploy SuperTicket (Sprint 3)

Target produksi saat ini: DomaiNesia / cPanel (`sugengha`), domain kampus via Cloudflare.

## 1. Deploy via Git (cukup `git pull`)

### Setup sekali (kalau belum)

```bash
cd ~
git clone https://github.com/Universitas-Sugeng-Hartono/tiketing.git
cd tiketing
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Document root domain diarahkan ke:

`/home/sugengha/tiketing/public`

### Update rutin (setelah kode masuk `main`)

```bash
cd ~/tiketing
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Kalau ada perubahan frontend (Vite):

```bash
npm ci
npm run build
```

### Migrate aman

- **Boleh:** `php artisan migrate --force` (hanya menambah/alter kolom sesuai migration baru)
- **Jangan** di production yang sudah berisi data nyata:
  - `migrate:fresh` / `migrate:refresh`
  - `db:wipe`
  - `migrate --seed` (kecuali environment baru kosong)
- **Tidak wajib** `artisan clear` / `optimize:clear` setiap deploy; cukup `config:cache` setelah ubah `.env`

Cek status migrasi:

```bash
php artisan migrate:status
```

## 2. Domain `tiketing.ush.ac.id`

Production memakai **`tiketing.ush.ac.id`** (ejaan ini yang dipakai).

1. Pastikan di Cloudflare sudah ada record `tiketing` → hosting DomaiNesia (sudah aktif)
2. DomaiNesia → Domains: `tiketing.ush.ac.id`, document root = `.../tiketing/public`
3. SSL Let’s Encrypt untuk `tiketing.ush.ac.id`
4. `.env` production:

```env
APP_URL=https://tiketing.ush.ac.id
APP_ENV=production
APP_DEBUG=false
```

Tidak perlu subdomain `ticketing.ush.ac.id` kecuali suatu saat diminta khusus.

## 3. Object Storage (S3-compatible)

Kode sudah siap. **Butuh akses dari Pak Deny** (endpoint, bucket, access key, secret).

### `.env` production (contoh)

```env
FILESYSTEM_UPLOAD_DISK=s3

AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=idn
AWS_BUCKET=nama-bucket
AWS_ENDPOINT=https://endpoint-object-storage-anda
AWS_URL=https://endpoint-object-storage-anda/nama-bucket
AWS_USE_PATH_STYLE_ENDPOINT=true
```

Lokal tetap:

```env
FILESYSTEM_UPLOAD_DISK=public
```

### Migrasi file lama (agar gambar/lampiran tidak hilang)

Jalankan **sebelum** memutuskan ganti disk ke `s3`, atau saat `FILESYSTEM_UPLOAD_DISK` masih `public`:

```bash
# Cek dulu
php artisan uploads:migrate-to-object-storage --dry-run

# Salin attachments, completion-proofs, category-templates, avatars
php artisan uploads:migrate-to-object-storage

# Setelah yakin, baru:
# FILESYSTEM_UPLOAD_DISK=s3
php artisan config:clear
php artisan config:cache
```

Opsional hapus salinan lokal setelah sukses: `--delete` (hati-hati; pastikan backup).

Path di database **tidak berubah** — hanya lokasi fisik file yang pindah.
