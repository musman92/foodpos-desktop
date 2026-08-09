@php
    $watermarkSrc = report_pdf_watermark_data_uri();
    $watermarkWidth = (int) config('reports.pdf.watermark.width', 420);
    $watermarkOpacity = (float) config('reports.pdf.watermark.opacity', 0.12);
@endphp
@if($watermarkSrc)
    <div style="position: fixed; top: 36%; left: 0; width: 100%; text-align: center;">
        <img src="{{ $watermarkSrc }}" alt="" width="{{ $watermarkWidth }}" style="opacity: {{ $watermarkOpacity }};">
    </div>
@endif
