<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; }
        th { background: #eee; }
        .right { text-align: right; }
        .muted { color: #555; }
        .header-row { margin-bottom: 8px; }
    </style>
</head>
<body>
    <table class="header-row" style="border: none;">
        <tr style="border: none;">
            <td style="border: none; width: 50%; vertical-align: top;">
                @if(!empty($agency['logoBase64']))
                    <img src="{{ $agency['logoBase64'] }}" style="max-width: 200px; max-height: 80px;" alt="">
                @endif
                <div><strong>{{ $agency['name'] }}</strong></div>
                <div class="muted">{{ $agency['address'] }}</div>
                <div class="muted">{{ $agency['city'] }}</div>
                <div class="muted">{{ $agency['phone'] }} {{ $agency['email'] }}</div>
            </td>
            <td style="border: none; vertical-align: top;" class="right">
                <h2 style="margin: 0;">INVOICE</h2>
                <div><strong>#</strong> {{ $invoice['invoice_number'] }}</div>
                <div><strong>Date:</strong> {{ $invoice['invoice_date'] }}</div>
                <div><strong>Due:</strong> {{ $invoice['due_date'] }}</div>
                @if(!empty($invoice['po_number']))
                    <div><strong>PO:</strong> {{ $invoice['po_number'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    <p><strong>Bill to</strong></p>
    <div>{{ $invoice['bill_to_name'] }}</div>
    <div class="muted">{{ $invoice['bill_to_contact'] }}</div>
    <div class="muted">{!! nl2br(e($invoice['bill_to_address'] ?? '')) !!}</div>
    <div class="muted">Terms: {{ $invoice['payment_terms'] }}</div>

    @if(!empty($invoice['consultant_name']))
        <p><strong>Consultant:</strong> {{ $invoice['consultant_name'] }}</p>
    @endif
    @if(!empty($invoice['pay_period_start']) && !empty($invoice['pay_period_end']))
        <p class="muted">Pay period: {{ $invoice['pay_period_start'] }} – {{ $invoice['pay_period_end'] }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Hours</th>
                <th class="right">Rate</th>
                <th class="right">Mult.</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lineItems as $li)
                <tr>
                    <td>{{ $li['description'] }}</td>
                    <td class="right">{{ $li['hours'] !== null ? number_format($li['hours'], 2) : '—' }}</td>
                    <td class="right">{{ number_format($li['rate'], 2) }}</td>
                    <td class="right">{{ number_format($li['multiplier'], 2) }}</td>
                    <td class="right">{{ number_format($li['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="border: none; margin-top: 16px; width: 40%; margin-left: auto;">
        <tr style="border: none;">
            <td style="border: none;" class="right"><strong>Subtotal</strong></td>
            <td style="border: none;" class="right">{{ number_format($invoice['subtotal'], 2) }}</td>
        </tr>
        <tr style="border: none;">
            <td style="border: none;" class="right"><strong>Total due</strong></td>
            <td style="border: none;" class="right"><strong>{{ number_format($invoice['total_amount_due'], 2) }}</strong></td>
        </tr>
    </table>

    @if(!empty($invoice['notes']))
        <p style="margin-top: 20px;"><strong>Notes</strong><br>{!! nl2br(e($invoice['notes'])) !!}</p>
    @endif
</body>
</html>
