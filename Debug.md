1. Halaman pergerakan stock yang penjualan, pas pilih customer harusnya pakai search bar seperti di bagian delivery.

2. Audit trail dari bouquet category dan type tidak muncul, di activity log sudah ada.

3. Dibagian deliveries schedulenya seperti ini 	2026-04-05T00:00:00.000000Z 12:02

4. Bagian search customer di order, edit order, stock movement sold, itu bisa search lewat alias juga

5. Class "App\Http\Controllers\StockMovement" not found muncul ketika mau delete order
Reference: 73            // 3. Kembalikan stok item inventory jika ada pergerakan stok penjualan
374            StockMovement::where('order_id', $orderId)
375                ->where('type', 'sold')
376                ->get()
377                ->each(function (StockMovement $movement) {
378                    $item = $movement->item;
379                    if ($item) {
380                        $item->increment('stock', $movement->quantity);
381                    }
382                    $movement->forceDelete();
383                });

6. Tambah fitur export struk order as png

7. Hilangkan tombol notifikasi di navbar

8. Hilangkan (hide, bukan delete) tombol lupa password dan registrasi.

9. Pada landing page, bagian footer itu pakai bahasa inggris, jadi developed with love

10. Pada menu order, di bagian cart, harusnya itu from, dan greeting card, jadi greeting card harus textbox yang bisa newline.

11. Di menu order status, ada opsi buat lihat detail dari setiap item, yang kalau bouquet itu ada greeting card dan from nya.

12. Pada sistem role access, di front end kalau gak punya akses manage harusnya tombol edit, delete,  dan tambah itu tidak tampil.

13. Menu hard delete/ recycle bin itu hanya buat role yang ada access hard delete, sekali centang berlaku buat semua data table.

14. Sistem money bucket belum masuk. Pada bagian order, untuk memasukan dp itu dihitung dari total keseleuruhan beserta money bucket. Kemudian setelah proceed, sistem akan menulis pendapatannya dan terdapat value total money bucket tadi.

15. Menu laporan penjualan, hilangkan kolom gosend.

16. Menu laporan pembelian Entry manual report dan pembelian barang digabung.

17. Di menu stock movement, buat input box untuk ini juga di samping, sama kayak tempat kalau mode sell. Freight, No Resi,Kode,Estimate Arrive.

18. Hilangkan section card supply purchase, biaya toko, biaya bahan baku, shipping and refund di menu laporan pembelian.
