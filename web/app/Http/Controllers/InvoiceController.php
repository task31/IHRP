<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceMailable;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\InvoiceSequence;
use App\Models\Timesheet;
use App\Services\AppService;
use App\Services\InvoiceFormatter;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('account_manager');

        $q = Invoice::query()
            ->with(['consultant:id,full_name', 'client:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('clientId')) {
            $q->where('client_id', $request->integer('clientId'));
        }
        if ($request->filled('consultantId')) {
            $q->where('consultant_id', $request->integer('consultantId'));
        }
        if ($request->filled('startDate')) {
            $q->whereDate('invoice_date', '>=', $request->date('startDate'));
        }
        if ($request->filled('endDate')) {
            $q->whereDate('invoice_date', '<=', $request->date('endDate'));
        }

        $rows = $q->get()->map(function (Invoice $inv) {
            $a = $inv->toArray();
            $a['consultant_name'] = $inv->consultant?->full_name;
            $a['client_name'] = $inv->client?->name;

            return $a;
        });

        return response()->json($rows);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('account_manager');
        $invoice = Invoice::query()->with(['lineItems', 'consultant', 'client'])->find($id);
        if (! $invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        return response()->json($invoice);
    }

    public function generate(Request $request): JsonResponse
    {
        $this->authorize('admin');
        $data = $request->validate(['timesheetId' => ['required', 'integer', 'exists:timesheets,id']]);

        $ts = Timesheet::query()
            ->with(['consultant', 'client'])
            ->find($data['timesheetId']);

        if (! $ts) {
            return response()->json(['error' => 'Timesheet not found'], 404);
        }

        if ($ts->invoice_id) {
            $existing = Invoice::query()->find($ts->invoice_id);
            if ($existing) {
                return response()->json($existing);
            }
        }

        $consultant = $ts->consultant;
        $client = $ts->client;
        if (! $consultant || ! $client) {
            return response()->json(['error' => 'Missing consultant or client'], 422);
        }

        $lineItems = InvoiceFormatter::buildLineItems($ts);
        $subtotal = array_sum(array_column($lineItems, 'amount'));

        $invoiceDate = now()->format('Y-m-d');
        $payTerms = $client->payment_terms ?? 'Net 30';
        $dueDate = InvoiceFormatter::calcDueDate($invoiceDate, $payTerms);

        $invoice = DB::transaction(function () use ($ts, $consultant, $client, $lineItems, $subtotal, $invoiceDate, $dueDate) {
            $seq = InvoiceSequence::query()->lockForUpdate()->where('id', 1)->first();
            if (! $seq) {
                DB::table('invoice_sequence')->insert([
                    'id' => 1,
                    'prefix' => '',
                    'next_number' => 1,
                    'fiscal_year_start' => null,
                ]);
                $seq = InvoiceSequence::query()->lockForUpdate()->where('id', 1)->firstOrFail();
            }

            $num = (int) $seq->next_number;
            $invoiceNumber = InvoiceFormatter::formatInvoiceNumber((string) $seq->prefix, $num);

            $inv = Invoice::query()->create([
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'consultant_id' => $consultant->id,
                'client_id' => $client->id,
                'timesheet_id' => $ts->id,
                'bill_to_name' => $client->name,
                'bill_to_contact' => $client->billing_contact_name,
                'bill_to_address' => $client->billing_address,
                'payment_terms' => $payTerms,
                'po_number' => $client->po_number,
                'subtotal' => round($subtotal, 4),
                'total_amount_due' => round($subtotal, 4),
                'status' => 'pending',
                'pdf_path' => null,
            ]);

            foreach ($lineItems as $li) {
                InvoiceLineItem::query()->create([
                    'invoice_id' => $inv->id,
                    'week_number' => $li['week_number'],
                    'description' => $li['description'],
                    'hours' => $li['hours'],
                    'rate' => $li['rate'],
                    'multiplier' => $li['multiplier'],
                    'amount' => $li['amount'],
                    'sort_order' => $li['sort_order'],
                ]);
            }

            $ts->update(['invoice_id' => $inv->id]);
            $seq->update(['next_number' => $num + 1]);

            AppService::auditLog('invoices', (int) $inv->id, 'INVOICE_GENERATED', [], [
                'number' => $invoiceNumber,
                'timesheet_id' => $ts->id,
            ]);

            return $inv->fresh(['lineItems', 'consultant', 'client', 'timesheet']);
        });

        $invoice->load(['lineItems', 'consultant', 'client', 'timesheet']);
        $pdf = (new PdfService)->generateInvoice($invoice);
        $relPath = 'invoices/'.$invoice->invoice_number.'.pdf';
        Storage::disk('local')->put($relPath, $pdf);
        $invoice->update(['pdf_path' => $relPath]);

        return response()->json($invoice->fresh(['lineItems', 'consultant', 'client', 'timesheet']));
    }

    public function preview(string $id): Response
    {
        $this->authorize('account_manager');
        $invoice = Invoice::query()->with(['lineItems', 'consultant', 'client', 'timesheet'])->findOrFail($id);
        $pdf = (new PdfService)->generateInvoice($invoice);

        return response($pdf, 200)->header('Content-Type', 'application/pdf');
    }

    public function export(string $id): BinaryFileResponse
    {
        $this->authorize('account_manager');
        $invoice = Invoice::query()->findOrFail($id);
        $pdf = (new PdfService)->generateInvoice($invoice->load(['lineItems', 'consultant', 'client', 'timesheet']));
        $tmp = tempnam(sys_get_temp_dir(), 'inv');
        file_put_contents($tmp, $pdf);

        return response()->download($tmp, 'invoice_'.$invoice->id.'.pdf', ['Content-Type' => 'application/pdf'])->deleteFileAfterSend(true);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $this->authorize('admin');
        $data = $request->validate(['status' => ['required', 'in:pending,sent,paid']]);
        $invoice = Invoice::query()->find($id);
        if (! $invoice) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $old = $invoice->status;
        $invoice->update(['status' => $data['status']]);
        AppService::auditLog('invoices', (int) $id, 'STATUS', ['status' => $old], ['status' => $data['status']]);

        return response()->json($invoice->fresh());
    }

    public function updatePo(Request $request): JsonResponse
    {
        $this->authorize('admin');
        $data = $request->validate([
            'invoiceId' => ['required', 'integer', 'exists:invoices,id'],
            'poNumber' => ['nullable', 'string', 'max:255'],
        ]);

        $invoice = Invoice::query()->find($data['invoiceId']);
        $old = $invoice->po_number;
        $invoice->update(['po_number' => $data['poNumber']]);
        AppService::auditLog('invoices', (int) $invoice->id, 'UPDATE_PO', ['po_number' => $old], ['po_number' => $data['poNumber']]);

        return response()->json(['ok' => true]);
    }

    public function send(Request $request): JsonResponse
    {
        $this->authorize('admin');
        $data = $request->validate([
            'invoiceId' => ['required', 'integer', 'exists:invoices,id'],
            'recipientEmail' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $invoice = Invoice::query()->with(['lineItems', 'consultant', 'client', 'timesheet'])->findOrFail($data['invoiceId']);

        AppService::applySmtpSettings();

        Mail::to($data['recipientEmail'])->send(new InvoiceMailable(
            $invoice,
            $data['recipientEmail'],
            $data['subject'],
            (string) ($data['note'] ?? '')
        ));

        $oldStatus = $invoice->status;
        $invoice->update(['status' => 'sent']);

        AppService::auditLog('invoices', (int) $invoice->id, 'INVOICE_SENT', ['status' => $oldStatus], [
            'status' => 'sent',
            'sent_to' => $data['recipientEmail'],
        ]);

        Log::info('Invoice email dispatched', ['invoice_id' => $invoice->id, 'to' => $data['recipientEmail']]);

        return response()->json(['ok' => true]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['error' => 'Use POST /invoices/generate'], 405);
    }

    public function update(Request $request, string $invoice): JsonResponse
    {
        return response()->json(['error' => 'Use updateStatus or updatePo'], 405);
    }

    public function destroy(string $id): JsonResponse
    {
        return response()->json(['error' => 'Not implemented'], 405);
    }
}
