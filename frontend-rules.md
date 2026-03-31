KING OF THE RULES: Read what inside package.json to make sure dont recreate the feature the package already provide. It also have shadcn theme, please create components using shadcn theme.

APP-THEME: Sistem Informasi Kasir Terintegrasi Manajemen Stok Berbasis SKU Pada Bisnis Florist. Vibe aplikasi tenang dan nyaman, gunakan warna pastel yang lembut, hindari warna yang terlalu cerah atau mencolok. Gunakan font yang mudah dibaca dan ukuran font yang cukup besar. Gunakan layout yang bersih dan rapi, hindari layout yang terlalu ramai atau penuh dengan elemen. Gunakan spacing yang cukup antar elemen, hindari layout yang terlalu padat. Gunakan shadow dan border yang halus untuk memisahkan elemen, hindari shadow dan border yang terlalu tebal atau mencolok. Gunakan icon yang sesuai dengan tema aplikasi, hindari icon yang terlalu besar atau mencolok. Gunakan warna yang konsisten di seluruh aplikasi, hindari penggunaan warna yang tidak konsisten. Gunakan warna yang kontras untuk elemen penting, hindari penggunaan warna yang tidak kontras. Gunakan warna yang sesuai dengan tema aplikasi, hindari penggunaan warna yang tidak sesuai dengan tema aplikasi. Warna tema aplikasi adalah Pink, Putih, dan Hitam.

1. Terapkan "Mobile-First Approach" pada Tailwind (Mobile Compatible, Rapi)
Instruksikan agen untuk selalu mendesain komponen untuk layar smartphone terlebih dahulu tanpa awalan breakpoint. Baru setelah itu, gunakan breakpoint bawaan Tailwind (sm:, md:, lg:, xl:) untuk menyesuaikan tata letak pada layar tablet dan desktop. Ini mencegah tampilan hancur saat dibuka di HP klien.

2. Sentralisasi Warna dengan CSS Variables (Gampang Ganti Tema/Warna)
Larang agen untuk melakukan hardcode warna palet langsung di class HTML (seperti bg-blue-600 atau text-red-500). Wajibkan penggunaan CSS variables (misal: var(--color-primary)) yang didefinisikan di app.css dan disambungkan ke tailwind.config.js. Jika klien tiba-tiba minta ganti warna brand dari biru ke hijau, kamu cukup mengubah kodenya di satu file saja, dan seluruh aplikasi akan otomatis berubah.

3. Ekstraksi Elemen Menjadi "Reusable Components" (Satu Jenis Tema Bentuk)
Agar desain selalu konsisten dan kode tidak penuh dengan class Tailwind yang berulang-ulang, agen wajib membungkus elemen UI ke dalam komponen Vue terpisah. Alih-alih menulis kode tombol yang panjang di setiap halaman, agen harus membuat komponen <PrimaryButton>, <BaseInput>, atau <DataCard>, lalu menggunakannya kembali (reuse) di seluruh aplikasi.

4. Wajibkan Indikator "Loading State" (UX Smooth, Komunikatif)
Setiap kali ada aksi yang membutuhkan interaksi dengan database (seperti submit form, memuat tabel, atau menghapus data), agen harus menyertakan visualisasi loading. Gunakan atribut :disabled pada tombol saat proses berlangsung, tambahkan spinner kecil, atau tampilkan Skeleton Loader (blok abu-abu yang berkedip) saat memuat halaman, agar pengguna tahu bahwa aplikasi sedang bekerja, bukan hang.

5. Gunakan Transisi Bawaan untuk Interaksi (Animasi, Smooth UX)
Hindari perubahan instan yang kasar pada layar. Wajibkan agen untuk selalu menambahkan class utilitas transisi dari Tailwind (contoh: transition-all duration-200 ease-in-out) pada elemen interaktif. Ini akan memberikan efek halus (smooth) saat tombol di-hover, dropdown dibuka, atau modal pop-up muncul.

6. Terapkan "Debounce" pada Input Pencarian (Cepat, Hemat Resource)
Saat membuat fitur pencarian langsung (live search) di dalam tabel data, agen tidak boleh mengirimkan request ke server pada setiap huruf yang diketik pengguna. Instruksikan penggunaan fungsi lodash/debounce untuk menunda pencarian selama 300-500 milidetik setelah pengguna berhenti mengetik. Ini membuat antarmuka terasa ringan dan mencegah server kelebihan beban.