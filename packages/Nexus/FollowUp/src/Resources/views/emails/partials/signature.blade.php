{{-- Built from the 12nexusbpo.com footer: logo, tagline, phone, website, office. --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:4px;border-top:2px solid {{ $BLUE }};">
<tr>
  <td valign="top" style="padding:14px 16px 0 0;">
    <img src="{{ $message->embed($logoPath) }}" alt="12NexusBPO" width="96"
         style="display:block;width:96px;height:auto;border:0;">
  </td>
  <td valign="top" style="padding:12px 0 0 16px;border-left:1px solid #DCE4F5;font-family:{{ $FONT }};">
    @if ($ownerName !== '')
      <div style="font-size:15px;line-height:21px;font-weight:700;color:{{ $NAVY }};">{{ $ownerName }}</div>
    @endif
    <div style="font-size:13px;line-height:19px;color:{{ $MUTED }};padding-bottom:6px;">
      {{ $signature['company'] }} · {{ $signature['tagline'] }}
    </div>
    <div style="font-size:13px;line-height:20px;color:{{ $INK }};">
      <span style="color:{{ $MUTED }};">Phone:</span>
      <a href="tel:{{ $signature['phone_e164'] }}" style="color:{{ $INK }};text-decoration:none;">{{ $signature['phone'] }}</a>
    </div>
    @if ($ownerEmail)
      <div style="font-size:13px;line-height:20px;color:{{ $INK }};">
        <span style="color:{{ $MUTED }};">Email:</span>
        <a href="mailto:{{ $ownerEmail }}" style="color:{{ $BLUE }};text-decoration:none;">{{ $ownerEmail }}</a>
      </div>
    @endif
    <div style="font-size:13px;line-height:20px;color:{{ $INK }};">
      <span style="color:{{ $MUTED }};">Web:</span>
      <a href="{{ $signature['website'] }}" style="color:{{ $BLUE }};text-decoration:none;">{{ preg_replace('#^https?://#', '', $signature['website']) }}</a>
    </div>
    <div style="font-size:12px;line-height:18px;color:{{ $MUTED }};padding-top:6px;">{{ $signature['office'] }}</div>
  </td>
</tr>
</table>
