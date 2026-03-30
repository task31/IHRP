<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: letter portrait; margin: 0.75in; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #555; }
        hr { border: none; border-top: 1px solid #333; margin: 12px 0; }

        /* Line items table */
        .items th {
            background-color: #F0F4F8;
            border-bottom: 1px solid #CCCCCC;
            padding: 5px 6px;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .items td {
            border-top: 1px solid #CCCCCC;
            border-bottom: 1px solid #CCCCCC;
            padding: 5px 6px;
        }
        .items tfoot td {
            border-top: 2px solid #333;
            border-bottom: 1px solid #333;
            font-weight: bold;
            font-size: 12px;
        }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
    </style>
</head>
<body>

    {{-- HEADER: Agency info (left) + INVOICE meta (right) --}}
    <table style="border: none; margin-bottom: 4px;">
        <tr style="border: none;">
            <td style="border: none; width: 55%; vertical-align: top;">
                @if(!empty($agency['logoBase64']))
                    <img src="{{ $agency['logoBase64'] }}" style="max-width: 200px; max-height: 60px; display: block; margin-bottom: 6px;" alt="">
                @else
                    <div style="font-size: 22px; font-weight: bold; margin-bottom: 6px;">{{ $agency['name'] }}</div>
                @endif
                <div style="font-size: 14px; font-weight: bold;">{{ $agency['name'] }}</div>
                <div class="muted">{{ $agency['address'] }}</div>
                <div class="muted">{{ $agency['city'] }}</div>
                @if(!empty($agency['phone']) || !empty($agency['email']))
                    <div class="muted" style="margin-top: 2px;">
                        {{ $agency['phone'] }}@if(!empty($agency['phone']) && !empty($agency['email'])) | @endif{{ $agency['email'] }}
                    </div>
                @endif
            </td>
            <td style="border: none; vertical-align: top; text-align: right;">
                <div style="font-size: 30px; font-weight: bold; letter-spacing: 0.05em; margin-bottom: 10px;">INVOICE</div>
                <table style="border: none; width: auto; margin-left: auto;">
                    <tr style="border: none;">
                        <td style="border: none; padding: 2px 4px;" class="muted">Invoice #</td>
                        <td style="border: none; padding: 2px 4px; font-weight: bold;">{{ $invoice['invoice_number'] }}</td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none; padding: 2px 4px;" class="muted">Date</td>
                        <td style="border: none; padding: 2px 4px;">{{ $invoice['invoice_date'] }}</td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none; padding: 2px 4px;" class="muted">Due Date</td>
                        <td style="border: none; padding: 2px 4px;">{{ $invoice['due_date'] }}</td>
                    </tr>
                    @if(!empty($invoice['po_number']))
                    <tr style="border: none;">
                        <td style="border: none; padding: 2px 4px;" class="muted">PO #</td>
                        <td style="border: none; padding: 2px 4px;">{{ $invoice['po_number'] }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <hr>

    {{-- BILL TO (left) + CONSULTANT & PERIOD (right) --}}
    <table style="border: none; margin-bottom: 14px;">
        <tr style="border: none;">
            <td style="border: none; width: 55%; vertical-align: top;">
                <div style="font-size: 10px; font-weight: bold; letter-spacing: 0.08em; margin-bottom: 3px;">BILL TO</div>
                <div>{{ $invoice['bill_to_name'] }}</div>
                @if(!empty($invoice['bill_to_contact']))
                    <div class="muted">{{ $invoice['bill_to_contact'] }}</div>
                @endif
                @if(!empty($invoice['bill_to_address']))
                    <div class="muted">{!! nl2br(e($invoice['bill_to_address'])) !!}</div>
                @endif
            </td>
            <td style="border: none; vertical-align: top;">
                <div style="font-size: 10px; font-weight: bold; letter-spacing: 0.08em; margin-bottom: 3px;">SERVICE</div>
                <div>Staffing Services</div>
                @if(!empty($invoice['consultant_name']))
                    <div style="margin-top: 6px;">
                        <span class="muted">Consultant:</span> {{ $invoice['consultant_name'] }}
                    </div>
                @endif
                @if(!empty($invoice['pay_period_start']) && !empty($invoice['pay_period_end']))
                    <div>
                        <span class="muted">Pay Period:</span> {{ $invoice['pay_period_start'] }} &ndash; {{ $invoice['pay_period_end'] }}
                    </div>
                @endif
                <div style="margin-top: 6px;">
                    <span class="muted">Payment Terms:</span> {{ $invoice['payment_terms'] }}
                </div>
            </td>
        </tr>
    </table>

    {{-- LINE ITEMS TABLE --}}
    <table class="items" style="margin-top: 4px;">
        <thead>
            <tr>
                <th>Consultant</th>
                <th class="text-center">Reg. Hours</th>
                <th class="text-center">Bill Rate</th>
                <th class="text-center">OT Hours</th>
                <th class="text-center">OT Rate</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lineItems as $li)
            <tr>
                <td>{{ $li['consultant_name'] }}</td>
                <td class="text-center">{{ number_format($li['regular_hours'], 2) }}</td>
                <td class="text-center">${{ number_format($li['rate'], 2) }}</td>
                <td class="text-center">{{ number_format($li['ot_hours'], 2) }}</td>
                <td class="text-center">${{ number_format($li['ot_rate'], 2) }}</td>
                <td class="text-right">${{ number_format($li['total_payroll'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align: right; padding-right: 12px; letter-spacing: 0.05em;">TOTAL DUE</td>
                <td class="text-right">${{ number_format($invoice['total_amount_due'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if(!empty($invoice['notes']))
    <div style="margin-top: 14px; font-size: 10px; color: #333;">
        <strong>Notes:</strong> {{ $invoice['notes'] }}
    </div>
    @endif

    {{-- FOOTER --}}
    <div style="margin-top: 24px; font-size: 9px; color: #555; line-height: 1.7; border-top: 1px solid #ddd; padding-top: 8px;">
        <div>Make all checks payable to <strong>{{ $agency['name'] }}</strong>. Thank you for your business!</div>
        @if(!empty($agency['email']) || !empty($agency['phone']))
            <div>Questions? Contact us: {{ $agency['email'] }}{{ !empty($agency['email']) && !empty($agency['phone']) ? ' · ' : '' }}{{ $agency['phone'] }}</div>
        @endif
        @if(!empty($agency['address']))
            <div>{{ $agency['address'] }}, {{ $agency['city'] }}</div>
        @endif
    </div>

</body>
</html>
