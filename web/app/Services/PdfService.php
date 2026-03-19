<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

final class PdfService
{
    public function generateInvoice(Invoice $invoice): string
    {
        $invoice->load(['lineItems', 'consultant', 'client', 'timesheet']);

        $agency = [
            'name' => AppService::getSetting('agency_name', 'Matchpointe'),
            'address' => AppService::getSetting('agency_address', ''),
            'city' => AppService::getSetting('agency_city', ''),
            'phone' => AppService::getSetting('agency_phone', ''),
            'email' => AppService::getSetting('agency_email', ''),
            'logoBase64' => AppService::getSetting('agency_logo_base64'),
        ];

        $inv = [
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'consultant_name' => $invoice->consultant?->full_name,
            'pay_period_start' => $invoice->timesheet?->pay_period_start?->format('Y-m-d'),
            'pay_period_end' => $invoice->timesheet?->pay_period_end?->format('Y-m-d'),
            'bill_to_name' => $invoice->bill_to_name,
            'bill_to_contact' => $invoice->bill_to_contact,
            'bill_to_address' => $invoice->bill_to_address,
            'payment_terms' => $invoice->payment_terms,
            'po_number' => $invoice->po_number,
            'subtotal' => (float) $invoice->subtotal,
            'total_amount_due' => (float) $invoice->total_amount_due,
            'notes' => $invoice->notes,
        ];

        $lineItems = $invoice->lineItems->map(fn ($li) => [
            'description' => $li->description,
            'hours' => $li->hours !== null ? (float) $li->hours : null,
            'rate' => (float) $li->rate,
            'multiplier' => (float) $li->multiplier,
            'amount' => (float) $li->amount,
        ])->all();

        return Pdf::loadView('pdf.invoice', [
            'agency' => $agency,
            'invoice' => $inv,
            'lineItems' => $lineItems,
        ])->output();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function generateMonthlyReport(array $data): string
    {
        return Pdf::loadView('pdf.report-monthly', ['data' => $data])->output();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function generateYearEndReport(array $data): string
    {
        return Pdf::loadView('pdf.report-yearend', ['data' => $data])->output();
    }
}
