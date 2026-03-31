KING OF THE RULES: Read what inside composer to make sure dont recreate the feature the package already provide.


1. Gunakan Form Request Validation (Aman, Bersih)
Jangan pernah mempercayai input pengguna atau melakukan validasi langsung di dalam Controller. Buat class khusus menggunakan php artisan make:request. Ini memastikan data yang masuk ke database sudah tersaring dari injeksi berbahaya, dan membuat file Controller tetap pendek dan mudah dibaca.

2. Tentukan $fillable pada Model Eloquent (Aman)
Selalu definisikan property $fillable di dalam file Model untuk membatasi kolom mana saja yang boleh diisi secara massal (Mass Assignment). Tanpa ini, peretas bisa memanipulasi form request untuk mengubah kolom sensitif, seperti menyisipkan role_id = admin saat mendaftar.

3. Gunakan Eager Loading dengan with() (Cepat, Scalable)
Hindari masalah N+1 Query. Saat memanggil data yang memiliki relasi (misalnya memanggil data Klien beserta riwayat transaksinya), selalu gunakan Model::with('relasi')->get(). Jika tidak, Laravel akan melakukan tembakan query ke database berulang kali sebanyak jumlah baris data, yang akan membuat aplikasi sangat lambat ketika data sudah ratusan.

4. Gunakan DB::transaction() (Stabil, Aman)
Wajib digunakan untuk operasi query yang beruntun atau saling bergantung. Contoh: Menyimpan data Klien baru, menyimpan alamatnya di tabel terpisah, lalu mencatatnya di Activity Log. Jika salah satu proses gagal atau error di tengah jalan, transaksi akan otomatis di-rollback (dibatalkan seluruhnya) sehingga tidak ada data "setengah jadi" atau corrupt di dalam database.

5. Terapkan Database Indexing di Migration (Cepat, Scalable)
Saat merancang struktur tabel, pastikan untuk menambahkan method ->index() pada kolom-kolom yang sering dijadikan parameter pencarian atau filter (seperti kolom email, status, atau kategori). Ini akan menghemat waktu eksekusi query database secara drastis saat ukuran data klien nantinya membengkak.

6. Pindahkan Tugas Berat ke Queue / Job (Cepat, Scalable)
Jangan biarkan pengguna melihat loading screen yang lama. Proses yang memakan waktu seperti pengiriman email notifikasi, ekspor laporan Excel, atau eksekusi cadangan (backup) harus dibungkus dalam Job dan dijalankan di background menggunakan Queue Laravel.

7. Gunakan Action Classes atau Service Pattern (Stabil, Scalable)
Jangan menumpuk logika bisnis yang panjang dan rumit di dalam Controller. Pecah logika tersebut menjadi class PHP tersendiri yang hanya bertugas melakukan satu hal spesifik (Misal: CreateClientAction.php). Ini membuat kodemu sangat mudah untuk dites menggunakan Pest dan bisa digunakan ulang di berbagai endpoint.

8. Manfaatkan Caching untuk Data Statis (Cepat)
Untuk data yang sering dibaca namun sangat jarang berubah (seperti daftar kategori, pengaturan aplikasi, atau opsi dropdown provinsi), gunakan Cache::remember(). Alih-alih menembak database setiap kali halaman di-refresh, aplikasi akan langsung mengambil datanya dari memori server.

9. Tuliskan Strict Typing pada PHP 8+ (Stabil, Aman)
Instruksikan agen untuk selalu menggunakan deklarasi tipe data pada parameter dan nilai kembalian fungsi (contoh: public function hitungDiskon(int $harga): float). Karena Laravel 12 berjalan di versi PHP modern, mendisiplinkan tipe data sejak awal akan mencegah bug fatal akibat tipe variabel yang tidak cocok saat aplikasi berjalan.

10. Gunakan softdelete dan activity log (Stabil, aman)
Selalu gunakan softdelete pada model yang memiliki relasi dengan model lain. Gunakan activity log untuk mencatat setiap perubahan yang terjadi pada data.