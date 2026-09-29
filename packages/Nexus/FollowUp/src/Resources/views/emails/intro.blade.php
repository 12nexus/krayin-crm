@php
    $FONT = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $INK = '#1B2733'; $MUTED = '#5A6B80'; $NAVY = '#011B35'; $BLUE = '#004EF0';
    $p = "margin:0 0 16px 0;font-family:{$FONT};font-size:15px;line-height:23px;color:{$INK};";
@endphp
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ config('follow_up.intro.subject') }}</title></head>
<body style="margin:0;padding:0;background-color:#ffffff;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
<tr><td style="padding:24px 16px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:100%;">
<tr><td>

<p style="{{ $p }}">Hi {{ $firstName !== '' ? $firstName : 'there' }},</p>

<p style="{{ $p }}">It was great connecting with you. As requested, I've attached a poster that outlines our key remote support services for real estate professionals like you.</p>

<p style="{{ $p }}">Whether you need a dedicated Virtual Assistant, specialized Transaction Coordination, or help managing your CRM and Lead Nurturing, 12NexusBPO is ready to act as a reliable extension of your team.</p>

<p style="{{ $p }}">We focus on the operational details, so you can focus on building client relationships and closing deals.</p>

<p style="{{ $p }}">Best of all, you can get started with a Virtual Assistant for as low as <strong>$8 an hour</strong>.</p>

<p style="{{ $p }}">Please let me know by replying to this email if you have any questions or would like to schedule a time to discuss how we can specifically help you scale your business.</p>

<p style="{{ $p }}">Best regards,</p>

@include('follow_up::emails.partials.signature')

</td></tr>
</table>
</td></tr>
</table>
</body></html>
