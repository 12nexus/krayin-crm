{{-- The Agreement to Hire Virtual Assistant, from the lead's saved contract fields. --}}
@php
    $blank = fn ($text, $width = 16) => $text !== '' ? e($text) : str_repeat('_', $width);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Agreement to Hire Virtual Assistant</title>
    @include('contracts::partials.pdf-style')
</head>
<body>
    @if ($logo)
        <div class="logo"><img src="{{ $logo }}" alt="12 Nexus"></div>
    @endif

    <h1>AGREEMENT TO HIRE VIRTUAL ASSISTANT</h1>

    <p style="margin-bottom: 2px;">THIS AGREEMENT, made as of {!! $blank($v['agreement_date'], 13) !!}</p>
    <p class="indent" style="margin-bottom: 2px;"><strong>Between</strong></p>
    <p style="margin-bottom: 2px;">
        <strong>{{ $v['company']['name'] }}</strong>, a company duly constituted, having its registered office at
        {{ $v['company']['address'] }} (here-in-referred as "The Company")
    </p>
    <p class="indent" style="margin-bottom: 2px;"><strong>AND</strong></p>
    <p>
        {!! $v['agreement_party'] !== '' ? e($v['agreement_party']) : str_repeat('_', 14).' - '.str_repeat('_', 29) !!}
        (here-in-referred as "The Client")
    </p>

    <p style="margin-top: 14px; margin-bottom: 2px;">
        <strong>WHEREAS</strong> the Client desires to obtain the benefit of the services of the Virtual Assistant, and
        the Company desires to render such services on the terms and conditions set forth.
    </p>
    <p>
        <strong>THIS AGREEMENT</strong> in consideration of the premises and mutual covenants and agreements
        outlined in this document and for other good and valuable consideration, it is agreed by and
        between the parties as follows:
    </p>

    <h2>1 &ndash; Paid Human Resource</h2>
    <p style="margin-bottom: 2px;"><strong>Position:</strong> Virtual Assistant</p>
    <p style="margin-bottom: 2px;">
        <strong>No of resources:</strong> {{ $v['resources'] }}
        [{{ $v['hours_per_day'] }} working hours a day &ndash; {{ $v['days_per_week'] }} Days a week / {{ $v['hours_per_week'] }} hours a week]
    </p>
    <p><strong>Responsibilities:</strong></p>

    @include('contracts::partials.responsibilities')

    <p style="margin-top: 10px;">
        <em>*Social media paid Campaigns, and APP development if any, then its cost will be pre-approved
        and paid by Client.</em>
    </p>

    <h2>2 &ndash; Charges:</h2>
    <ul>
        <li>Regular Virtual Assistant charges per month {{ $v['monthly_charge'] }} for {{ $v['plan'] }} ({{ $v['hours_per_week'] }} hours a week)</li>
        <li>No extra fee involved</li>
    </ul>

    <h2>3 &ndash; Free of cost services</h2>
    <ul>
        <li>Shortlisting and Interview process</li>
        <li>IT hardware and infrastructure</li>
        <li>Dedicated premises</li>
        <li>HR and Payroll services for the resource provided</li>
        <li>All social media posts creation by graphic designer</li>
        <li>Free IT consultation provided by the company</li>
        <li>Website assistance from software development team</li>
    </ul>

    <h2>4 &ndash; Payment mode and method</h2>
    <ul>
        <li>Company will send invoices through email</li>
        <li>Every month&rsquo;s payment will be in advance</li>
        <li>Client will be responsible for payment upon the generation of invoice</li>
        <li>Incentives or bonuses from the Client will go to the VA in Full amount</li>
        <li>Currency: US Dollar</li>
    </ul>

    <h2>5 &ndash; Agreement Duration:</h2>
    <ul>
        <li>Agreement duration: {{ $v['duration'] }}</li>
        <li>Agreement start date: {!! $blank($v['start_date']) !!}</li>
        <li>Agreement renewal date: {!! $blank($v['renewal_date']) !!}</li>
    </ul>

    <h2>6 &ndash; Agreement Continuation</h2>
    <ul>
        <li>On successful completion of this agreement, both parties can agree to renew the
            Agreement as per the negotiated terms and conditions from both sides</li>
    </ul>

    <h2>7 &ndash; Exit Clause</h2>
    <ul>
        <li>If Clients want to exit before the end of the agreement period, later every month 30 days&rsquo;
            notice is mandatory. Clients will pay for that month, and services will continue during the
            notice period. The Company will return all credentials and data gathered in such an event</li>
        <li>If the Virtual Assistant is not able to continue, the Company will provide a replacement,
            share training videos, and obtain approval from the Client to ensure the new employee
            meets their standards</li>
        <li>If the Company wants to terminate the agreement before the end of the period, they must
            provide 30 days&rsquo; notice and continue providing services during the notice period until the
            Client completes the transition and gives consent</li>
        <li>Filing a dispute without first contacting us and a maximum of 3-business-day window for
            scheduling the resolution call is a breach of terms and may result in denied claims.</li>
    </ul>

    <h2>8 &ndash; Reporting</h2>
    <p style="margin-bottom: 4px;">
        Company will share following main reports and matrices with Client to exhibit smooth execution
        of operations.
    </p>
    <ul>
        <li>Appointments Report</li>
        <li>Recording of each call made</li>
        <li>Activity report / any report required by Client in writing</li>
        <li>Maintaining excel sheet for calls</li>
        <li>Managing the CRM or any other software</li>
        <li>Any other form of reporting that Client wants</li>
    </ul>

    <h2>9 &ndash; Setup Fee:</h2>
    <p>One-time setup: {{ $v['setup_fee'] }}</p>

    <h2>10 &ndash; Training:</h2>
    <p>
        1st time training will be provided by the Client, company will record those sessions after getting
        approval from the Client and will share those with any new hires (required by the Client as
        replacement or addition to the Client&rsquo;s team) if needed.
    </p>

    <h2>11 &ndash; Data Security:</h2>
    <p>
        Company and Client have already signed a Non-Disclosure Agreement [NDA] (by email)
        through which company will be responsible to take steps to ensure security of Client&rsquo;s sensitive
        data.
    </p>

    <h2>12 &ndash; Public Holidays:</h2>
    <p>
        Virtual Assistant will observe 3 local holidays in a year&rsquo;s time [Mid of April, Mid of June and End
        of July] and for any other holiday, Client&rsquo;s consent will be taken. Virtual Assistant will observe
        US holidays after confirmation from the Client.
    </p>
    <p style="margin-bottom: 2px;"><strong>Note:</strong></p>
    <p>
        If there is any item not mentioned in the agreement, both Company and Client can discuss and
        made that part of agreement. If required both the parties will agree to the said by email.
        Company will be flexible to the needs of Client and accommodate those.
    </p>

    <table class="signatures">
        <tr><td><strong>On behalf of the Company:</strong></td><td><strong>On behalf of Client:</strong></td></tr>
        <tr><td class="gap">Signature: <span class="line">&nbsp;</span></td><td class="gap">Signature: <span class="line">&nbsp;</span></td></tr>
        <tr><td>Name: Mr. {{ $v['company']['signatory'] }}</td><td>Name: {!! $blank($v['client_name']) !!}</td></tr>
        <tr><td>Designation: {{ $v['company']['title'] }}</td><td>Designation: {!! $blank($v['client_title'], 17) !!}</td></tr>
        <tr><td></td><td>Company: {!! $blank($v['client_company'], 21) !!}</td></tr>
        <tr><td class="gap">Date: <span class="line">&nbsp;</span></td><td class="gap">Date: <span class="line">&nbsp;</span></td></tr>
    </table>
</body>
</html>
