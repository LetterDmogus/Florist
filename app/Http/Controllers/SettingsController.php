<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'settings' => [
                'store_name' => SiteSetting::getValue('store_name', 'Bees Fleur Florist'),
                'address' => SiteSetting::getValue('address', 'Jl. Mawar No. 123, Jakarta'),
                'phone' => SiteSetting::getValue('phone', '081234567890'),
                'receipt_note' => SiteSetting::getValue('receipt_note', 'Terima kasih telah berbelanja di Bees Fleur!'),
            ],
            'business_hours' => [
                'mon_fri' => SiteSetting::getValue('mon_fri', '09:00 - 18:00'),
                'sat_sun' => SiteSetting::getValue('sat_sun', '10:00 - 16:00'),
            ]
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_name' => 'required|string|max:255',
            'address' => 'required|string',
            'phone' => 'required|string|max:20',
            'receipt_note' => 'nullable|string',
            'mon_fri' => 'required|string',
            'sat_sun' => 'required|string',
        ]);

        foreach ($validated as $key => $value) {
            SiteSetting::setValue($key, $value);
        }

        return redirect()->back()->with('success', 'Pengaturan berhasil diperbarui.');
    }
}
