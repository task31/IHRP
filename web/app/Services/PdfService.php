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
            'invoice_date' => $invoice->invoice_date?->format('m-d-Y'),
            'due_date' => $invoice->due_date?->format('m-d-Y'),
            'consultant_name' => $invoice->consultant?->full_name,
            'pay_period_start' => $invoice->timesheet?->pay_period_start?->format('m/d/Y'),
            'pay_period_end' => $invoice->timesheet?->pay_period_end?->format('m/d/Y'),
            'bill_to_name' => $invoice->bill_to_name,
            'bill_to_contact' => $invoice->bill_to_contact,
            'bill_to_address' => $invoice->bill_to_address,
            'payment_terms' => $invoice->payment_terms,
            'po_number' => $invoice->po_number,
            'subtotal' => (float) $invoice->subtotal,
            'total_amount_due' => (float) $invoice->total_amount_due,
            'notes' => $invoice->notes,
        ];

        // Consolidate stored line items into a single per-consultant row matching the Excel template.
        // Regular hours = multiplier 1.0; OT hours = all premium multipliers (1.5, 2.0, etc.)
        $regularHours = 0.0;
        $otHours = 0.0;
        $baseRate = 0.0;
        $totalPayroll = 0.0;

        foreach ($invoice->lineItems as $li) {
            $mult = (float) $li->multiplier;
            $totalPayroll += (float) $li->amount;
            if ($mult <= 1.0) {
                $regularHours += (float) $li->hours;
                $baseRate = (float) $li->rate;
            } else {
                $otHours += (float) $li->hours;
            }
        }

        $lineItems = [[
            'consultant_name' => $invoice->consultant?->full_name ?? '',
            'regular_hours'   => $regularHours,
            'rate'            => $baseRate,
            'ot_hours'        => $otHours,
            'ot_rate'         => round($baseRate * 1.5, 4),
            'total_payroll'   => $totalPayroll,
        ]];

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
