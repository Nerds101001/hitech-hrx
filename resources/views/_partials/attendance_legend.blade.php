{{-- Shared attendance status colour legend. Include below the toolbar of any attendance grid/list. --}}
<style>
    .bg-short-leave { background-color: #0dcaf0 !important; }
    .bg-miss-punch { background-color: #e84393 !important; }
    .bg-wfh { background-color: #576574 !important; }
    .bg-label-miss-punch { background-color: rgba(232, 67, 147, .16) !important; color: #e84393 !important; }

    .attendance-legend { font-size: 0.7rem; row-gap: 6px !important; }
    .attendance-legend .legend-chip {
        display: inline-flex; align-items: center; gap: 6px;
        font-weight: 700; color: #566a7f; background: #fff;
        border: 1px solid #eef0f2; border-radius: 50px; padding: 3px 10px;
        cursor: default; white-space: nowrap;
    }
    .attendance-legend .l-swatch { width: 12px; height: 12px; border-radius: 4px; display: inline-block; flex: 0 0 auto; }
    .attendance-legend .sw-holiday { background: rgba(3, 195, 236, .35); }
    .attendance-legend .sw-off { background: #e9ecef; border: 1px solid #d0d5da; }
</style>
<div class="attendance-legend d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-bottom bg-light bg-opacity-50">
    <span class="text-muted fw-bold text-uppercase me-1" style="font-size:0.62rem; letter-spacing:1px;"><i class="bx bx-palette me-1"></i>Legend</span>
    <span class="legend-chip" title="Worked a full day (7:45 hours or more)"><span class="l-swatch bg-teal"></span> Present</span>
    <span class="legend-chip" title="Approved short leave &mdash; employee was present, left early. Counted as Present"><span class="l-swatch bg-short-leave"></span> Short Leave</span>
    <span class="legend-chip" title="Worked on a Sunday (Sunday Present)"><span class="l-swatch bg-success"></span> Sunday Working (SP)</span>
    <span class="legend-chip" title="Arrived late as per shift rules"><span class="l-swatch bg-warning"></span> Late</span>
    <span class="legend-chip" title="Worked less than 7:45 hours"><span class="l-swatch bg-orange"></span> Half Day</span>
    <span class="legend-chip" title="Punched in but never punched out &mdash; counted Absent until HR/Admin corrects the record with remarks"><span class="l-swatch bg-miss-punch"></span> Miss Punch</span>
    <span class="legend-chip" title="No punch on a working day"><span class="l-swatch bg-red"></span> Absent</span>
    <span class="legend-chip" title="Approved paid leave &mdash; counted as Present"><span class="l-swatch bg-indigo-vibrant"></span> Paid Leave</span>
    <span class="legend-chip" title="Approved unpaid leave &mdash; counted as Absent"><span class="l-swatch bg-purple-vibrant"></span> Unpaid Leave</span>
    <span class="legend-chip" title="Work from home"><span class="l-swatch bg-wfh"></span> WFH</span>
    <span class="legend-chip" title="Declared company holiday"><span class="l-swatch sw-holiday"></span> Holiday</span>
    <span class="legend-chip" title="Weekly off / non-working day"><span class="l-swatch sw-off"></span> Weekly Off</span>
</div>
