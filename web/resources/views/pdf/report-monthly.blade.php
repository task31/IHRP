<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #333; padding: 5px; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h2>Monthly report</h2>
    @if(isset($data['title']))
        <p>{{ $data['title'] }}</p>
    @endif
    @if(isset($data['rows']) && is_array($data['rows']))
        @php
            $moneyCols = \App\Support\ReportMoney::moneyColumnKeys();
            $headers = array_keys($data['rows'][0] ?? []);
            $headerLabels = [
                'pay_period_start' => 'Pay period start',
                'pay_period_end' => 'Pay period end',
                'consultant' => 'Consultant',
                'client' => 'Client',
                'total_client_billable' => 'Billed',
                'total_consultant_cost' => 'Cost',
            ];
        @endphp
        <table>
            <thead>
                <tr>
                    @foreach($headers as $col)
                        <th>{{ $headerLabels[$col] ?? $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($data['rows'] as $row)
                    <tr>
                        @foreach($row as $key => $cell)
                            <td>
                                @if(in_array($key, $moneyCols, true))
                                    {{ \App\Support\ReportMoney::usd($cell) }}
                                @else
                                    {{ is_scalar($cell) ? $cell : json_encode($cell) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">No row data supplied.</p>
    @endif
</body>
</html>
