<?php

namespace App\Http\Controllers;

use App\Models\InvoiceSequence;
use App\Services\AppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceSequenceController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('admin');
        $row = InvoiceSequence::query()->where('id', 1)->first();
        if (! $row) {
            InvoiceSequence::query()->create([
                'id' => 1,
                'prefix' => '',
                'next_number' => 1,
            ]);
            $row = InvoiceSequence::query()->find(1);
        }

        return response()->json([
            'prefix' => $row->prefix,
            'current_number' => $row->next_number,
        ]);
    }

    public function update(Request $request, string $invoice_sequence): JsonResponse
    {
        $this->authorize('admin');

        $data = $request->validate([
            'prefix' => ['nullable', 'string', 'max:50'],
            'startNumber' => ['required', 'integer', 'min:1'],
        ]);

        $row = InvoiceSequence::query()->firstOrCreate(
            ['id' => 1],
            ['prefix' => '', 'next_number' => 1]
        );

        $old = ['prefix' => $row->prefix, 'next' => $row->next_number];
        $row->update([
            'prefix' => (string) ($data['prefix'] ?? ''),
            'next_number' => $data['startNumber'],
        ]);

        AppService::auditLog('invoice_sequence', 1, 'UPDATE', $old, [
            'prefix' => $row->prefix,
            'next' => $row->next_number,
        ]);

        return response()->json(['ok' => true]);
    }
}
