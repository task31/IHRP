<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #555; }
    </style>
</head>
<body>

    {{-- HEADER: Agency info (left) + INVOICE title/meta (right) --}}
    <table style="border: none; margin-bottom: 14px;">
        <tr style="border: none;">
            <td style="border: none; width: 55%; vertical-align: top;">
                @if(!empty($agency['logoBase64']))
                    <img src="{{ $agency['logoBase64'] }}" style="max-width: 200px; max-height: 60px; display: block; margin-bottom: 4px;" alt="">
                @endif
                <div style="font-size: 18px;">{{ $agency['name'] }}</div>
                <div class="muted">{{ $agency['address'] }}</div>
                <div class="muted">{{ $agency['city'] }}</div>
            </td>
            <td style="border: none; vertical-align: top; text-align: right;">
                <div style="font-size: 26px; margin-bottom: 8px;">INVOICE</div>
                <table style="border: none; width: auto; margin-left: auto;">
                    <tr style="border: none;">
                        <td style="border: none; padding: 1px 4px;" class="muted">DATE</td>
                        <td style="border: none; padding: 1px 4px;">{{ $invoice['invoice_date'] }}</td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none; padding: 1px 4px;" class="muted">INVOICE #</td>
                        <td style="border: none; padding: 1px 4px;">{{ $invoice['invoice_number'] }}</td>
                    </tr>
                    @if(!empty($invoice['po_number']))
                    <tr style="border: none;">
                        <td style="border: none; padding: 1px 4px;" class="muted">PO#:</td>
                        <td style="border: none; padding: 1px 4px;">{{ $invoice['po_number'] }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- BILL TO (left) + SERVICE TYPE / PAY PERIOD (right) --}}
    <table style="border: none; margin-bottom: 12px;">
        <tr style="border: none;">
            <td style="border: none; width: 55%; vertical-align: top;">
                <div><strong>BILL TO</strong></div>
                <div class="muted">{{ $invoice['bill_to_contact'] }}</div>
                <div>{{ $invoice['bill_to_name'] }}</div>
                <div class="muted">{!! nl2br(e($invoice['bill_to_address'] ?? '')) !!}</div>
            </td>
            <td style="border: none; vertical-align: top;">
                <div>Staffing Services</div>
                @if(!empty($invoice['pay_period_start']) && !empty($invoice['pay_period_end']))
                    <div>Pay Period: {{ $invoice['pay_period_start'] }} - {{ $invoice['pay_period_end'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- PAYMENT TERMS BOX (right-aligned) --}}
    <table style="border: none; width: 38%; margin-left: auto; margin-bottom: 12px;">
        <tr style="border: none;">
            <td style="border: none; border-bottom: 1px solid #333; text-align: center; padding: 3px 8px;">PAYMENT TERMS</td>
        </tr>
        <tr style="border: none;">
            <td style="border: none; border-top: 1px solid #333; border-bottom: 3px solid #333; text-align: center; padding: 3px 8px;">{{ $invoice['payment_terms'] }}</td>
        </tr>
    </table>

    {{-- LINE ITEMS TABLE --}}
    <table style="margin-top: 8px;">
        <thead>
            <tr>
                <th style="border-bottom: 1px solid #333; padding: 4px 6px; text-align: left; font-weight: normal;">Consultant</th>
                <th style="border-bottom: 1px solid #333; padding: 4px 6px; text-align: center; font-weight: normal;">Total Hours</th>
                <th style="border-bottom: 1px solid #333; padding: 4px 6px; text-align: center; font-weight: normal;">Rate</th>
                <th style="border-bottom: 1px solid #333; padding: 4px 6px; text-align: center; font-weight: normal;">Total OT Hours</th>
                <th style="border-bottom: 1px solid #333; padding: 4px 6px; text-align: center; font-weight: normal;">OT Rate</th>
                <th style="border-bottom: 1px solid #333; padding: 4px 6px; text-align: center; font-weight: normal;">Total Payroll</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lineItems as $li)
            <tr>
                <td style="border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; padding: 4px 6px;">{{ $li['consultant_name'] }}</td>
                <td style="border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; padding: 4px 6px; text-align: center;">{{ number_format($li['regular_hours'], 2) }}</td>
                <td style="border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; padding: 4px 6px; text-align: center;">${{ number_format($li['rate'], 2) }}</td>
                <td style="border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; padding: 4px 6px; text-align: center;">{{ number_format($li['ot_hours'], 2) }}</td>
                <td style="border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; padding: 4px 6px; text-align: center;">${{ number_format($li['ot_rate'], 2) }}</td>
                <td style="border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; padding: 4px 6px; text-align: center;">${{ number_format($li['total_payroll'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td style="border-top: 2px solid #333; border-bottom: 1px solid #333; padding: 4px 6px; font-weight: bold;">TOTAL</td>
                <td style="border-top: 2px solid #333; border-bottom: 1px solid #333; padding: 4px 6px; text-align: center;">{{ number_format(array_sum(array_column($lineItems, 'regular_hours')), 2) }}</td>
                <td style="border-top: 2px solid #333; border-bottom: 1px solid #333; padding: 4px 6px;"></td>
                <td style="border-top: 2px solid #333; border-bottom: 1px solid #333; padding: 4px 6px; text-align: center;">{{ number_format(array_sum(array_column($lineItems, 'ot_hours')), 2) }}</td>
                <td style="border-top: 2px solid #333; border-bottom: 1px solid #333; padding: 4px 6px;"></td>
                <td style="border-top: 2px solid #333; border-bottom: 1px solid #333; padding: 4px 6px; text-align: center; font-weight: bold;">${{ number_format($invoice['total_amount_due'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- FOOTER --}}
    <div style="margin-top: 22px; font-size: 10px; color: #333; line-height: 1.8;">
        <div>Make all checks payable to {{ $agency['name'] }}. Thank you for your business!</div>
        <div style="font-size: 8px; color: #555;">If you have any questions concerning this invoice, please contact us: {{ $agency['email'] }} {{ $agency['phone'] }}</div>
        <div style="font-size: 8px; color: #555;">{{ $agency['address'] }}, {{ $agency['city'] }}</div>
    </div>

</body>
</html>
