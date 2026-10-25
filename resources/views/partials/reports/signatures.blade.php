<section class="report-signatures" aria-label="Report signatures">
    <div class="report-signature-person">
        <div>Prepared by:</div>
        <div class="report-signature-name">{{ $reportMeta['prepared_by_name'] ?? '' }}</div>
        <div class="report-signature-role">Faculty</div>
        @if (empty($reportMeta['prepared_by_name']))
            <div class="report-signature-missing">Faculty name not recorded</div>
        @endif
    </div>
    <div class="report-signature-person">
        <div>Reviewed by:</div>
        <div class="report-signature-name">{{ $reportMeta['reviewed_by_name'] ?? '' }}</div>
        <div class="report-signature-role">Department Chairperson</div>
        @if (empty($reportMeta['reviewed_by_name']))
            <div class="report-signature-missing">Chair name not recorded</div>
        @endif
    </div>
</section>
