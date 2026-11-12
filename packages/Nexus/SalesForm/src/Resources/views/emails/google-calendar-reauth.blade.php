@php
    $NAVY='#011B35'; $BLUE='#004EF0'; $T100='#D7E2FF'; $T300='#7EA2FF';
    $PAGE='#F3F6FF'; $BORDER='#DCE4F5'; $INK='#1B2733'; $MUTED='#5A6B80';
    $FONT="-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

    $p = "font-family:{$FONT};font-size:14px;line-height:22px;color:{$INK};margin:0 0 14px 0;";
    $li = "font-family:{$FONT};font-size:14px;line-height:22px;color:{$INK};padding-bottom:8px;";

    $headline = match ($status) {
        'due'     => 'Time to renew the Google Calendar sign-in',
        'missing' => 'Google Calendar is not connected',
        default   => 'The Google Calendar sign-in has expired',
    };
@endphp
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $headline }}</title></head>
<body style="margin:0;padding:0;background-color:{{ $PAGE }};">

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:{{ $PAGE }};">
<tr><td align="center" style="padding:28px 12px;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600"
         style="width:600px;max-width:100%;background-color:#ffffff;border:1px solid {{ $BORDER }};border-radius:12px;overflow:hidden;">

    <tr><td align="center" style="padding:26px 24px 20px 24px;">
      <img src="{{ $message->embed($logoPath) }}" alt="12NexusBPO" width="150"
           style="display:block;width:150px;max-width:55%;height:auto;border:0;">
    </td></tr>

    <tr><td bgcolor="{{ $NAVY }}" style="padding:24px 32px;background-color:{{ $NAVY }};">
      <div style="font-family:{{ $FONT }};font-size:12px;font-weight:600;letter-spacing:1.2px;text-transform:uppercase;color:{{ $T300 }};padding-bottom:8px;">
        CRM &middot; Action needed
      </div>
      <div style="font-family:{{ $FONT }};font-size:23px;line-height:31px;font-weight:700;color:#ffffff;">
        {{ $headline }}
      </div>
      <div style="font-family:{{ $FONT }};font-size:14px;line-height:22px;color:{{ $T100 }};padding-top:9px;">
        @if ($status === 'due')
          Google sign-ins for the CRM lapse after a while. Renewing it now keeps everything below working.
        @elseif ($status === 'missing')
          No Google account is connected to the CRM.
        @else
          Google stopped accepting the CRM's sign-in{{ $accountEmail ? ' for '.$accountEmail : '' }}{{ $expiredAt ? ' on '.\Carbon\Carbon::parse($expiredAt)->format('j M Y') : '' }}.
        @endif
      </div>
    </td></tr>

    <tr><td style="padding:26px 32px 8px 32px;">
      <p style="{{ $p }}">{{ $status === 'due' ? 'The CRM uses this sign-in to:' : 'Until it is reconnected, the CRM cannot:' }}</p>
      <ul style="margin:0 0 18px 0;padding-left:20px;">
        <li style="{{ $li }}">move a rescheduled meeting's event in the sales calendar (Google sends the guests the new time);</li>
        <li style="{{ $li }}">copy Google Meet notes and recordings onto the leads.</li>
      </ul>

      <p style="{{ $p }}"><strong>To reconnect (about a minute):</strong></p>
      <ol style="margin:0 0 18px 0;padding-left:20px;">
        <li style="{{ $li }}">Open the button below and sign in to the CRM if asked.</li>
        <li style="{{ $li }}">Click <strong>Reconnect Google account</strong>.</li>
        <li style="{{ $li }}">Choose {!! $accountEmail ? '<strong>'.e($accountEmail).'</strong>' : 'the Google account that can edit the sales calendar' !!}, then click <strong>Continue</strong> / <strong>Allow</strong> on Google's screens.</li>
        <li style="{{ $li }}">Back in the CRM you should see <em>&ldquo;Google Calendar connected as &hellip;&rdquo;</em>. That's it.</li>
      </ol>
    </td></tr>

    <tr><td align="center" style="padding:0 32px 28px 32px;">
      <a href="{{ $settingsUrl }}"
         style="display:inline-block;background-color:{{ $BLUE }};color:#ffffff;font-family:{{ $FONT }};font-size:15px;font-weight:600;text-decoration:none;padding:12px 26px;border-radius:8px;">
        Open Google Calendar settings
      </a>
    </td></tr>

    <tr><td style="padding:16px 32px 24px 32px;border-top:1px solid {{ $BORDER }};">
      <div style="font-family:{{ $FONT }};font-size:12px;line-height:18px;color:{{ $MUTED }};">
        This reminder repeats once a day until the account is reconnected. If the account shown is not the right one, connect the account that has &ldquo;Make changes to events&rdquo; on the sales calendar.
      </div>
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
