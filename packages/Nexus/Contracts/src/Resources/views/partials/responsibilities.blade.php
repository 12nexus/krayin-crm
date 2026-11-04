{{-- The Virtual Assistant's responsibilities, as in the agreement template. --}}
@php
    $responsibilities = [
        'Transaction Coordination and Management' => [
            'Track transaction deadlines, contingencies, and compliance requirements using Google Calendar. Enter all relevant dates, schedule reminders, and ensure timely follow-ups.',
            'Manage transaction-related tasks and documentation throughout the real estate transaction process.',
            'Send fully executed contracts to the buyer’s lender and title company. Request confirmation of receipt and, for financed transactions, inquire about the appraisal scheduling timeline.',
            'Follow up with the title company to confirm when it receives the lender’s title work order and ensure the process is progressing as expected.',
        ],
        'Document Preparation and Presentation Support' => [
            'Prepare and pre-fill Buyer Agency Agreements, Listing Agreements, and other documents required by the client, subject to the client’s review and approval.',
            'Create and organize buyer packets, seller packets, listing presentations, and buyer presentations.',
            'Maintain accurate, organized, and up-to-date transaction files and related documentation.',
        ],
        'Administrative and Calendar Management' => [
            'Provide general administrative and clerical support to ensure smooth day-to-day business operations.',
            'Manage calendars, schedule tasks, coordinate appointments, and send reminders.',
            'Organize and prioritize daily activities, maintain records, and assist with workflow management.',
        ],
        'CRM and Lead Management' => [
            'Manage and maintain the client’s CRM, including contact organization, data entry, lead tracking, pipeline updates, and database maintenance.',
            'Handle text/calls, lead follow-ups, client communications, and appointment scheduling.',
            'Assist with listing management, property management coordination, and other related operational activities.',
        ],
        'Website Development, Management, and SEO' => [
            'Develop, maintain, and update the client’s website as required.',
            'Implement search engine optimization (SEO) practices to improve website visibility and online presence.',
            'Manage website content, property listings, and relevant updates to ensure accuracy and consistency.',
        ],
        'Newsletter and Market Research' => [
            'Create and distribute monthly newsletters featuring real estate information, Tri-Town Area news, local developments, and real estate market updates.',
            'Research relevant local market trends, property information, and community news to support client communications and marketing efforts.',
        ],
        'Digital Marketing and Social Media Management' => [
            'Manage the client’s social media accounts, including content planning, scheduling, publishing, and engagement.',
            'Develop and execute digital marketing campaigns and online advertising initiatives.',
            'Maintain consistent branding and support the client’s online visibility and audience engagement.',
        ],
        'Email, Text, and Telemarketing' => [
            'Create and manage email marketing campaigns, newsletters, and promotional communications.',
            'Conduct text-message marketing and follow-up communications as directed by the client.',
            'Perform telemarketing, lead outreach, prospect follow-ups, and other client-approved calling activities in accordance with applicable laws and regulations.',
        ],
        'Graphic Design, Video Editing, and Content Creation' => [
            'Design marketing materials, social media graphics, promotional visuals, and other branded content.',
            'Edit videos for social media, property marketing, and promotional campaigns.',
            'Create engaging written and visual content aligned with the client’s brand, marketing strategy, and business objectives.',
        ],
        'Appointment Setting and Coordination' => [
            'Schedule appointments with prospective clients, buyers, sellers, and other relevant parties.',
            'Confirm appointments, send reminders, coordinate schedules, and follow up as needed.',
            'Maintain accurate appointment records and update the client’s calendar and CRM accordingly.',
        ],
        'General Clerical and Business Support' => [
            'Perform general clerical duties, data entry, document organization, recordkeeping, and other administrative tasks.',
            'Assist with routine business operations and provide ongoing support to improve efficiency and organization.',
        ],
        'Additional Client-Assigned Responsibilities' => [
            'Perform any other reasonable duties or responsibilities assigned by the client that are relevant to the client’s industry, business operations, and the Virtual Assistant’s role, skills, and capabilities.',
        ],
    ];
@endphp

@foreach ($responsibilities as $heading => $items)
    <h3>{{ $loop->iteration }}. {{ $heading }}</h3>
    <ul class="indent">
        @foreach ($items as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
@endforeach
