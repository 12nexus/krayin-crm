Hi {{ $firstName !== '' ? $firstName : 'there' }},

It was great connecting with you. As requested, I've attached a poster that outlines our key remote support services for real estate professionals like you.

Whether you need a dedicated Virtual Assistant, specialized Transaction Coordination, or help managing your CRM and Lead Nurturing, 12NexusBPO is ready to act as a reliable extension of your team.

We focus on the operational details, so you can focus on building client relationships and closing deals.

Best of all, you can get started with a Virtual Assistant for as low as $8 an hour.

Please let me know by replying to this email if you have any questions or would like to schedule a time to discuss how we can specifically help you scale your business.

Best regards,

--
@if ($ownerName !== '')
{{ $ownerName }}
@endif
{{ $signature['company'] }} · {{ $signature['tagline'] }}
Phone: {{ $signature['phone'] }}
@if ($ownerEmail)
Email: {{ $ownerEmail }}
@endif
Web: {{ preg_replace('#^https?://#', '', $signature['website']) }}
{{ $signature['office'] }}
