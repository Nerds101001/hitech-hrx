{{-- Shared attendance status colour legend. Include below the toolbar of any attendance grid/list.

     The chips double as filters: clicking one keeps only the employees who have
     that status at least once in the shown period, and dims every other day cell
     so the matching days stand out. Clicking the active chip (or "Clear filter")
     restores everyone. A page that only wants the colour key can include this
     with $legendFilterable = false. --}}
@php($legendFilterable = $legendFilterable ?? true)
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
        transition: border-color .12s ease, box-shadow .12s ease, transform .12s ease;
    }
    .attendance-legend .l-swatch { width: 12px; height: 12px; border-radius: 4px; display: inline-block; flex: 0 0 auto; }
    .attendance-legend .sw-holiday { background: rgba(3, 195, 236, .35); }
    .attendance-legend .sw-off { background: #e9ecef; border: 1px solid #d0d5da; }

    /* --- clickable filter behaviour --- */
    .attendance-legend.is-filterable .legend-chip[data-status] { cursor: pointer; user-select: none; }
    .attendance-legend.is-filterable .legend-chip[data-status]:hover {
        border-color: #0f6e6e; box-shadow: 0 2px 6px rgba(15,110,110,.16); transform: translateY(-1px);
    }
    .attendance-legend .legend-chip.is-active {
        background: #0f6e6e; color: #fff; border-color: #0f6e6e;
        box-shadow: 0 2px 8px rgba(15,110,110,.30);
    }
    .attendance-legend .legend-chip.is-active .l-swatch { box-shadow: 0 0 0 2px rgba(255,255,255,.75); }
    .attendance-legend.has-filter .legend-chip[data-status]:not(.is-active) { opacity: .45; }

    .legend-clear {
        display: none; align-items: center; gap: 5px; border: 0; border-radius: 50px;
        background: #ffe9e9; color: #c0392b; font-weight: 800; font-size: 0.65rem;
        padding: 4px 11px; white-space: nowrap;
    }
    .attendance-legend.has-filter .legend-clear { display: inline-flex; }

    /* day cells that don't match the active filter recede; matches get an outline */
    .attendance-box.legend-dimmed { opacity: .16; filter: grayscale(85%); }
    .attendance-box.legend-hit { outline: 2px solid #0f6e6e; outline-offset: 1px; }
</style>
<div class="attendance-legend d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-bottom bg-light bg-opacity-50 {{ $legendFilterable ? 'is-filterable' : '' }}"
     id="attendanceLegend" @if($legendFilterable) data-filterable="1" @endif>
    <span class="text-muted fw-bold text-uppercase me-1" style="font-size:0.62rem; letter-spacing:1px;">
        <i class="bx bx-palette me-1"></i>{{ $legendFilterable ? 'Filter' : 'Legend' }}
    </span>
    <span class="legend-chip" data-status="Present" title="Worked a full day (7:45 hours or more). Click to show only these."><span class="l-swatch bg-teal"></span> Present</span>
    <span class="legend-chip" data-status="Short Leave" title="Approved short leave &mdash; employee was present, left early. Counted as Present"><span class="l-swatch bg-short-leave"></span> Short Leave</span>
    <span class="legend-chip" data-status="SP" title="Worked on a Sunday (Sunday Present)"><span class="l-swatch bg-success"></span> Sunday Working (SP)</span>
    <span class="legend-chip" data-status="Late" title="Arrived late as per shift rules"><span class="l-swatch bg-warning"></span> Late</span>
    <span class="legend-chip" data-status="Half Day" title="Worked less than 7:45 hours"><span class="l-swatch bg-orange"></span> Half Day</span>
    <span class="legend-chip" data-status="Miss Punch" title="Punched in but never punched out &mdash; counted Absent until HR/Admin corrects the record with remarks"><span class="l-swatch bg-miss-punch"></span> Miss Punch</span>
    <span class="legend-chip" data-status="Absent" title="No punch on a working day"><span class="l-swatch bg-red"></span> Absent</span>
    <span class="legend-chip" data-status="Paid Leave" title="Approved paid leave &mdash; counted as Present"><span class="l-swatch bg-indigo-vibrant"></span> Paid Leave</span>
    <span class="legend-chip" data-status="Unpaid Leave" title="Approved unpaid leave &mdash; counted as Absent"><span class="l-swatch bg-purple-vibrant"></span> Unpaid Leave</span>
    <span class="legend-chip" data-status="WFH" title="Work from home"><span class="l-swatch bg-wfh"></span> WFH</span>
    <span class="legend-chip" data-status="Holiday" title="Declared company holiday"><span class="l-swatch sw-holiday"></span> Holiday</span>
    <span class="legend-chip" data-status="OFF" title="Weekly off / non-working day"><span class="l-swatch sw-off"></span> Weekly Off</span>
    @if($legendFilterable)
        <button type="button" class="legend-clear" id="legendClear" title="Show everyone again">
            <i class="bx bx-x"></i> Clear filter <span id="legendCount" class="ms-1 opacity-75"></span>
        </button>
    @endif
</div>
