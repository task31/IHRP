<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\InvoiceSequence;
use App\Services\AppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('admin');
        $rows = DB::table('settings')->pluck('value', 'key');
        $sequence = InvoiceSequence::query()->firstOrCreate(
            ['id' => 1],
            ['prefix' => '', 'next_number' => 1]
        );

        if ($request->expectsJson()) {
            return response()->json($rows);
        }

        return view('settings.index', [
            'settings' => $rows,
            'sequence' => $sequence,
            'backups' => Backup::query()->orderByDesc('created_at')->limit(50)->get(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize('admin');
        $data = $request->validate([
            'key' => ['required', 'string', Rule::in([
                'agency_name',
                'agency_address',
                'agency_city',
                'agency_email',
                'agency_phone',
                'smtp_host',
                'smtp_port',
                'smtp_user',
                'smtp_password',
                'smtp_from_address',
                'smtp_from_name',
                'smtp_encryption',
                'budget_alert_threshold_warning',
                'budget_alert_threshold_critical',
                'timesheet_import_column_mapping',
            ])],
            'value' => ['nullable'],
        ]);

        $old = AppService::getSetting($data['key']);
        AppService::setSetting($data['key'], $data['value'] ?? '');
        AppService::auditLog('settings', 0, 'SETTINGS_CHANGE', [$data['key'] => $old], [$data['key'] => $data['value'] ?? '']);

        return response()->json(['ok' => true]);
    }

    public function setLogo(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('admin');
        $request->validate(['logo' => ['required', 'image', 'max:5120']]);

        $path = $request->file('logo')->getRealPath();
        if ($path === false) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'error' => 'Invalid file'], 422)
                : back()->with('error', 'Invalid file');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'error' => 'Could not read file'], 422)
                : back()->with('error', 'Could not read file');
        }

        $img = @imagecreatefromstring($contents);
        if ($img === false) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'error' => 'Could not load image'], 422)
                : back()->with('error', 'Could not load image');
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
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'error' => 'Resize failed'], 422)
                : back()->with('error', 'Resize failed');
        }

        ob_start();
        imagepng($resized);
        $png = ob_get_clean();
        imagedestroy($resized);
        if ($png === false) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'error' => 'Encode failed'], 422)
                : back()->with('error', 'Encode failed');
        }

        $dataUri = 'data:image/png;base64,'.base64_encode($png);
        AppService::setSetting('agency_logo_base64', $dataUri);
        AppService::auditLog('settings', 0, 'SETTINGS_CHANGE', ['agency_logo_base64' => '[updated]'], []);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'dataUri' => $dataUri]);
        }

        return redirect()->route('settings.index')->with('success', 'Logo updated');
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
            Log::error('SMTP test failed', ['user' => $request->user()->email, 'error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'SMTP connection failed — check server logs.']);
        }
    }
}
