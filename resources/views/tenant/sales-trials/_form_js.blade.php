<script>
// Reusable trial-form wiring for a modal: accumulating >=3 photo picker + best-effort geolocation.
window.trialBags = window.trialBags || {};
function initTrialForm(p) {
  if (window.trialBags[p]) return;        // bind once
  var dt = new DataTransfer(); window.trialBags[p] = dt;
  var $ = function (id) { return document.getElementById(p + id); };
  var bag = $('PhotoBag'), pick = $('PhotoPick'), preview = $('PhotoPreview'), count = $('PhotoCount');

  $('AddPhotoBtn').addEventListener('click', function () { pick.click(); });
  pick.addEventListener('change', function () {
    Array.prototype.forEach.call(pick.files, function (f) { dt.items.add(f); });
    pick.value = ''; sync();
  });
  function sync() {
    bag.files = dt.files; preview.innerHTML = '';
    Array.prototype.forEach.call(dt.files, function (f, i) {
      var col = document.createElement('div'); col.className = 'col-4 col-md-3';
      col.innerHTML = '<div class="position-relative"><img src="' + URL.createObjectURL(f) + '" class="rounded-3" style="aspect-ratio:1;object-fit:cover;width:100%;display:block"><button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 p-0 px-1" style="line-height:1" data-i="' + i + '">&times;</button></div>';
      preview.appendChild(col);
    });
    preview.querySelectorAll('button[data-i]').forEach(function (b) { b.addEventListener('click', function () { dt.items.remove(+b.dataset.i); sync(); }); });
    var n = dt.files.length;
    count.textContent = n + ' added' + (n < 3 ? ' (need ' + (3 - n) + ' more)' : ' ✓');
    count.className = 'small ' + (n < 3 ? 'text-danger' : 'text-success');
  }
  var form = $('Form');
  form.addEventListener('submit', function (e) {
    if (dt.files.length < 3) { e.preventDefault(); alert('Please add at least 3 photos of the trial.'); return; }
    var btn = $('SubmitBtn'); if (btn) btn.disabled = true;
  });
}
function captureTrialGeo(p) {
  var status = document.getElementById(p + 'GeoStatus');
  if (!navigator.geolocation) { status.innerHTML = ''; return; }
  status.innerHTML = '<i class="bx bx-map-pin"></i> getting location…';
  navigator.geolocation.getCurrentPosition(function (pos) {
    document.getElementById(p + 'Lat').value = pos.coords.latitude;
    document.getElementById(p + 'Lng').value = pos.coords.longitude;
    document.getElementById(p + 'Acc').value = Math.round(pos.coords.accuracy);
    status.innerHTML = '<i class="bx bx-map-pin text-success"></i> location captured (±' + Math.round(pos.coords.accuracy) + 'm)';
  }, function () { status.innerHTML = '<i class="bx bx-map-pin text-muted"></i> location unavailable — trial still saves'; },
  { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
}
</script>
