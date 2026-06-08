<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /** URL gambar QRIS toko (untuk layar pembayaran QRIS di kasir). */
    public function qris(): JsonResponse
    {
        $path = Setting::get('qris_image');

        return response()->json([
            'qris_image_url' => $path ? Storage::disk('public')->url($path) : null,
        ]);
    }
}
