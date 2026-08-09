<div class="report-hub-panel">
    @if($showReport && $report)
        @include('reports._period-closing-body')
    @else
        @include('reports.hub.partials._empty')
    @endif
</div>
