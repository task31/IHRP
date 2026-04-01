<?php

namespace App\Http\Controllers;

use App\Services\AppService;
use App\Services\ResumeRedactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ResumeRedactionController extends Controller
{
    public function index(): View
    {
        return view('resume-redact.index', [
            'logoBase64' => (string) AppService::getSetting('agency_logo_base64', ''),
        ]);
    }

    public function process(Request $request, ResumeRedactionService $service): Response
    {
        $validated = $request->validate([
            'resume' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'header_mode' => ['required', 'in:text,logo'],
        ]);

        $tempPath = $request->file('resume')->getPathname();
        $headerMode = (string) $validated['header_mode'];
        $logoBase64 = (string) AppService::getSetting('agency_logo_base64', '');
        if ($headerMode === 'logo' && trim($logoBase64) === '') {
            return back()
                ->withErrors(['header_mode' => 'Upload a logo in Settings -> Logo first'])
                ->withInput();
        }

        $candidateName = 'candidate';
        $pdf = '';

        try {
            $lines = $service->extractLines($tempPath);
            if ($lines !== [] && trim($lines[0]) !== '') {
                $candidateName = trim($lines[0]);
            }

            $pdf = $service->buildRedactedPdf($tempPath, $headerMode, $logoBase64, $candidateName);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['resume' => $e->getMessage()])->withInput();
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }

        AppService::auditLog(
            'resume_redact',
            0,
            'RESUME_REDACTED',
            ['header_mode' => $headerMode, 'user' => Auth::id()],
            []
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="mpg-'.Str::slug($candidateName).'.pdf"',
        ]);
    }
}
