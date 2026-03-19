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
    <h2>Year-end report</h2>
    @if(isset($data['year']))
        <p>Fiscal year: {{ $data['year'] }}</p>
    @endif
    @if(isset($data['rows']) && is_array($data['rows']))
        <table>
            <thead>
                <tr>
                    @foreach(array_keys($data['rows'][0] ?? []) as $col)
                        <th>{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($data['rows'] as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td>{{ is_scalar($cell) ? $cell : json_encode($cell) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No row data supplied.</p>
    @endif
</body>
</html>
