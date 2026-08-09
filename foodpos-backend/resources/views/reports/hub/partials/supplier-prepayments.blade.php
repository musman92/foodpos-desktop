<div class="report-hub-panel">
    @if($showReport && $report)
        @include('reports._outstanding-report-body')
    @else
        @include('reports.hub.partials._empty')
    @endif
</div>
