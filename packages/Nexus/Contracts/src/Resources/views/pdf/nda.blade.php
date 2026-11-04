{{-- The Non-disclosure Agreement, from the lead's saved contract fields. --}}
@php
    $blank = fn ($text, $width = 16) => $text !== '' ? e($text) : str_repeat('_', $width);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Non-disclosure Agreement [NDA]</title>
    @include('contracts::partials.pdf-style')
</head>
<body>
    @if ($logo)
        <div class="logo"><img src="{{ $logo }}" alt="12 Nexus"></div>
    @endif

    <h1>Non-disclosure Agreement [NDA]</h1>

    <p>
        This Nondisclosure Agreement (The "Agreement") is entered into by and between
        {!! $v['nda_party'] !== '' ? e($v['nda_party']) : str_repeat('_', 16).' / '.str_repeat('_', 17) !!}
        (&ldquo;Disclosing Party") and <strong>{{ $v['company']['name'] }}</strong>, located at
        {{ $v['company']['address'] }} ("Receiving Party") for the purpose of preventing the
        unauthorized disclosure of Confidential Information as defined below. The parties agree to
        enter into a confidential relationship with respect to the disclosure of certain proprietary
        and confidential information ("Confidential Information").
    </p>

    <h2>1. Definition of Confidential Information:</h2>
    <p>
        For purposes of this Agreement, "Confidential Information" shall include all information or
        material that has or could have commercial value or other utility in the business in which
        Disclosing Party is engaged. If Confidential Information is in written form, the Disclosing Party
        shall label or stamp the materials with the word "Confidential" or some similar warning. If
        Confidential Information is transmitted orally, the Disclosing Party shall promptly provide a
        writing indicating that such oral communication constituted Confidential Information.
    </p>

    <h2>2. Exclusions from Confidential Information:</h2>
    <p>
        Receiving Party&rsquo;s obligations under this Agreement do not extend to information that is: (a)
        publicly known at the time of disclosure or subsequently becomes publicly known through no
        fault of the Receiving Party; (b) discovered or created by the Receiving Party before disclosure
        by Disclosing Party; (c) learned by the Receiving Party through legitimate means other than
        from the Disclosing Party or Disclosing Party&rsquo;s representatives; or (d) is disclosed by Receiving
        Party with Disclosing Party&rsquo;s prior written approval.
    </p>

    <h2>3. Obligations of Receiving Party:</h2>
    <p>
        Receiving Party shall hold and maintain the Confidential Information in strictest confidence for
        the sole and exclusive benefit of the Disclosing Party. Receiving Party shall carefully restrict
        access to Confidential Information to employees, contractors, and third parties as is reasonably
        required and shall require those persons to sign nondisclosure restrictions at least as protective
        as those in this Agreement. Receiving Party shall not, without prior written approval of
        Disclosing Party, use for Receiving Party&rsquo;s own benefit, publish, copy, or otherwise disclose to
        others, or permit the use by others for their benefit or to the detriment of Disclosing Party, any
        Confidential Information. Receiving Party shall return to Disclosing Party any and all records,
        notes, and other written, printed, or tangible materials in its possession pertaining to
        Confidential Information immediately if Disclosing Party requests it in writing.
    </p>

    <h2>4. Time Period:</h2>
    <p>
        The non-disclosure provisions of this Agreement shall survive the termination of this Agreement
        and Receiving Party&rsquo;s duty to hold Confidential Information in confidence shall remain in effect
        until the Confidential Information no longer qualifies as a trade secret or until Disclosing Party
        sends Receiving Party written notice releasing Receiving Party from this Agreement, whichever
        occurs first.
    </p>

    <h2>5. Relationships:</h2>
    <p>
        Nothing contained in this Agreement shall be deemed to constitute either party a partner, joint
        venture or employee of the other party for any purpose.
    </p>

    <h2>6. Severability:</h2>
    <p>
        If a court finds any provision of this Agreement invalid or unenforceable, the remainder of this
        Agreement shall be interpreted so as best to affect the intent of the parties.
    </p>

    <h2>7. Integration:</h2>
    <p>
        This Agreement expresses the complete understanding of the parties with respect to the subject
        matter and supersedes all prior proposals, agreements, representations, and understandings.
        This Agreement may not be amended except in a writing signed by both parties.
    </p>

    <h2>8. Waiver:</h2>
    <p>
        The failure to exercise any right provided in this Agreement shall not be a waiver of prior or
        subsequent rights.
    </p>
    <p>
        This Agreement and each party&rsquo;s obligations shall be binding on the representatives, assigns,
        and successors of such party. Each party has signed this Agreement through its authorized
        representative.
    </p>

    <table class="signatures">
        <tr><td><strong>Disclosing Party</strong></td><td><strong>Receiving Party</strong></td></tr>
        <tr><td class="gap">By: <span class="line">&nbsp;</span></td><td class="gap">By: <span class="line">&nbsp;</span></td></tr>
        <tr><td>Printed Name: {!! $blank($v['client_name'], 14) !!}</td><td>Printed Name: {{ $v['company']['signatory'] }}</td></tr>
        <tr>
            <td>Title: {!! $v['nda_title'] !== '' ? e($v['nda_title']) : str_repeat('_', 14).'<br>('.str_repeat('_', 23).')' !!}</td>
            <td>Title: {{ $v['company']['title'] }}</td>
        </tr>
        <tr><td class="gap">Dated: <span class="line">&nbsp;</span></td><td class="gap">Dated: <span class="line">&nbsp;</span></td></tr>
    </table>
</body>
</html>
