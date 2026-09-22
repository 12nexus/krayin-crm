@php
    $money = fn ($amount) => '$'.number_format((float) $amount, 2).' '.$invoice->currency;
    $status = $invoice->displayStatus();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        @page { margin: 36px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .title { font-size: 26px; font-weight: bold; color: #011B35; letter-spacing: 1px; }
        .band { background: #011B35; color: #ffffff; }
        .band td { padding: 10px 12px; }
        .items th { background: #EAF0FF; color: #011B35; text-align: left; padding: 8px 10px; font-size: 10px; text-transform: uppercase; }
        .items td { padding: 10px; border-bottom: 1px solid #e5e7eb; }
        .right { text-align: right; }
        .total td { padding: 10px; font-size: 13px; font-weight: bold; }
        .stamp { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .stamp-paid { background: #dcfce7; color: #15803d; }
        .stamp-unpaid { background: #fef3c7; color: #92400e; }
        .stamp-overdue { background: #fee2e2; color: #b91c1c; }
        .stamp-void { background: #e5e7eb; color: #4b5563; }
        .box { border: 1px solid #e5e7eb; padding: 10px 12px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 55%;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $issuer['name'] }}" style="height: 42px;">
                @else
                    <strong style="font-size: 16px;">{{ $issuer['name'] }}</strong>
                @endif

                <div style="margin-top: 10px; line-height: 1.5;">
                    <strong>{{ $issuer['name'] }}</strong><br>
                    @if ($issuer['address'])
                        {!! nl2br(e($issuer['address'])) !!}<br>
                    @endif
                    @if ($issuer['email'])
                        {{ $issuer['email'] }}<br>
                    @endif
                    @if ($issuer['website'])
                        {{ $issuer['website'] }}
                    @endif
                </div>
            </td>
            <td class="right">
                <div class="title">INVOICE</div>
                <div style="margin-top: 6px; font-size: 13px;"><strong>{{ $invoice->number }}</strong></div>
                <div style="margin-top: 8px;">
                    <span class="stamp stamp-{{ $status }}">{{ $status }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="band" style="margin-top: 22px;">
        <tr>
            <td><span style="opacity: .75;">Billing month</span><br><strong>{{ $invoice->billing_month->format('F Y') }}</strong></td>
            <td><span style="opacity: .75;">Issued</span><br><strong>{{ $invoice->issued_on->format('j M Y') }}</strong></td>
            <td><span style="opacity: .75;">Due</span><br><strong>{{ $invoice->due_date->format('j M Y') }}</strong></td>
            <td class="right"><span style="opacity: .75;">Amount due</span><br><strong style="font-size: 14px;">{{ $status === 'paid' || $status === 'void' ? $money(0) : $money($invoice->amount) }}</strong></td>
        </tr>
    </table>

    <table style="margin-top: 22px;">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div class="muted" style="font-size: 10px; text-transform: uppercase;">Bill to</div>
                <div style="margin-top: 4px; line-height: 1.5;">
                    <strong>{{ $client->name }}</strong><br>
                    @if ($client->company)
                        {{ $client->company }}<br>
                    @endif
                    @if ($client->address)
                        {!! nl2br(e($client->address)) !!}<br>
                    @endif
                    @if ($client->email)
                        {{ $client->email }}<br>
                    @endif
                    @if ($client->phone)
                        {{ $client->phone }}
                    @endif
                </div>
            </td>
            <td style="vertical-align: top;">
                @if ($invoice->paid_on)
                    <div class="box">
                        <strong>Paid on {{ $invoice->paid_on->format('j M Y') }}</strong><br>
                        <span class="muted">Thank you.</span>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: 24px;">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right" style="width: 28%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice->description }}</td>
                <td class="right">{{ $money($invoice->amount) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="total">
        <tr>
            <td class="right" style="width: 72%;">Total</td>
            <td class="right">{{ $money($invoice->amount) }}</td>
        </tr>
    </table>

    @if ($invoice->notes)
        <div class="box" style="margin-top: 18px;">
            <div class="muted" style="font-size: 10px; text-transform: uppercase;">Notes</div>
            <div style="margin-top: 4px;">{!! nl2br(e($invoice->notes)) !!}</div>
        </div>
    @endif

    @if ($payment)
        <div class="box" style="margin-top: 18px;">
            <div class="muted" style="font-size: 10px; text-transform: uppercase;">How to pay</div>
            <div style="margin-top: 4px;">{!! nl2br(e($payment)) !!}</div>
        </div>
    @endif

    <p class="muted" style="margin-top: 30px; text-align: center; font-size: 9px;">
        {{ $issuer['name'] }}{{ $issuer['website'] ? ' · '.$issuer['website'] : '' }}
    </p>
</body>
</html>
