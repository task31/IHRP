<?php

namespace App\Http\Controllers;

use App\Services\AppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('admin');
        $rows = DB::table('settings')->select('key', 'value')->get();

        return response()->json($rows->pluck('value', 'key'));
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize('admin');
        $data = $request->validate([
            'key' => ['required', 'string', 'max:191'],
            'value' => ['nullable'],
        ]);

        $old = AppService::getSetting($data['key']);
        AppService::setSetting($data['key'], $data['value'] ?? '');
        AppService::auditLog('settings', 0, 'SETTINGS_CHANGE', [$data['key'] => $old], [$data['key'] => $data['value'] ?? '']);

        return response()->json(['ok' => true]);
    }

    public function setLogo(Request $request): JsonResponse
    {
        $this->authorize('admin');
        $request->validate(['logo' => ['required', 'image', 'max:5120']]);

        $path = $request->file('logo')->getRealPath();
        if ($path === false) {
            return response()->json(['ok' => false, 'error' => 'Invalid file'], 422);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return response()->json(['ok' => false, 'error' => 'Could not read file'], 422);
        }

        $img = @imagecreatefromstring($contents);
        if ($img === false) {
            return response()->json(['ok' => false, 'error' => 'Could not load image'], 422);
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $maxW = 400;
        $maxH = 200;
        $ratio = min($maxW / $w, $maxH / $h, 1);
        $newW = (int) round($w * $ratio);
        $newH = (int) round($h * $ratio);
        $resized = imagescale($img, $newW, $newH);
        imagedestroy($img);
        if ($resized === false) {
            return response()->json(['ok' => false, 'error' => 'Resize failed'], 422);
        }

        ob_start();
        imagepng($resized);
        $png = ob_get_clean();
        imagedestroy($resized);
        if ($png === false) {
            return response()->json(['ok' => false, 'error' => 'Encode failed'], 422);
        }

        $dataUri = 'data:image/png;base64,'.base64_encode($png);
        AppService::setSetting('agency_logo_base64', $dataUri);
        AppService::auditLog('settings', 0, 'SETTINGS_CHANGE', ['agency_logo_base64' => '[updated]'], []);

        return response()->json(['ok' => true, 'dataUri' => $dataUri]);
    }

    public function testSmtp(Request $request): JsonResponse
    {
        $this->authorize('admin');
        try {
            AppService::applySmtpSettings();

            Mail::raw('IHRP SMTP test', function ($message) use ($request) {
                $message->to($request->user()->email)
                    ->subject('IHRP SMTP test');
            });

            Log::info('SMTP test succeeded', ['user' => $request->user()->email]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
