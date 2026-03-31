<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 20mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111;
            font-size: 12px;
            line-height: 1.35;
        }
        .candidate-name {
            text-align: center;
            font-size: 22px;
            font-variant: small-caps;
            letter-spacing: 2px;
            font-weight: bold;
            margin: 0 0 6px 0;
        }
        .line {
            margin: 3px 0 0 0;
        }
        .spacer {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    @if($headerMode === 'logo' && $logoBase64)
        <img src="{{ $logoBase64 }}" alt="MatchPointe Group" style="max-height:40px; margin-bottom:8px;" />
    @else
        <p style="color:#c0392b; font-size:13px; font-weight:bold; margin:0 0 8px 0;">MatchPointe Group</p>
    @endif

    <h1 class="candidate-name">{{ $candidateName }}</h1>
    <hr style="border:none; border-top:1px solid #555; margin:0 0 14px 0;" />

    @foreach($redactedLines as $line)
        @if(trim($line) === '')
            <p class="spacer">&nbsp;</p>
        @else
            <p class="line">{{ $line }}</p>
        @endif
    @endforeach
</body>
</html>
