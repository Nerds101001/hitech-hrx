{{-- A real sidebar of named report sections, sticky, for a single round-report page (Dashboard's live round,
     or any Past round). Jumps within the SAME page — one URL, one load, no tab-hopping — but reads and looks
     like a proper reporting app's nav. $sections = [[id, label, icon], ...]. --}}
<div class="ndg-side">
  <div class="ndg-side-h">Reports</div>
  @foreach($sections as [$id, $label, $icon])
    <a href="#{{ $id }}" class="ndg-side-l" data-ndg-jump="{{ $id }}"><i class="bx {{ $icon }}"></i>{{ $label }}</a>
  @endforeach
</div>

<style>
  .ndg-side { position: sticky; top: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px; display: flex; flex-direction: column; gap: 2px; }
  .ndg-side-h { font-size: .66rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; padding: 8px 10px 6px; }
  .ndg-side-l { display: flex; align-items: center; gap: 9px; padding: 9px 10px; border-radius: 8px; color: #334155; text-decoration: none; font-size: .85rem; font-weight: 600; }
  .ndg-side-l i { font-size: 1.05rem; color: #94a3b8; }
  .ndg-side-l:hover { background: #f0f7f6; color: #007a7a; }
  .ndg-side-l:hover i { color: #007a7a; }
  .ndg-side-l.on { background: #007a7a; color: #fff; }
  .ndg-side-l.on i { color: #fff; }
  .ndg-report-section { scroll-margin-top: 20px; }
  @media (max-width: 767px) {
    .ndg-side { position: static; flex-direction: row; overflow-x: auto; }
    .ndg-side-h { display: none; }
  }
</style>

<script>
  (function () {
    if (window.__ndgSideBound) return; window.__ndgSideBound = true;
    var links = document.querySelectorAll('[data-ndg-jump]');
    var sections = Array.prototype.map.call(links, function (l) { return document.getElementById(l.dataset.ndgJump); }).filter(Boolean);
    function mark() {
      var pos = window.scrollY + 100, current = sections[0];
      sections.forEach(function (s) { if (s.offsetTop <= pos) current = s; });
      links.forEach(function (l) { l.classList.toggle('on', current && l.dataset.ndgJump === current.id); });
    }
    window.addEventListener('scroll', mark, { passive: true });
    mark();
  })();
</script>
