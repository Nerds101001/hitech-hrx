<?php
$file = 'd:\live_server\resources\views\tenant\employees\create.blade.php';
$content = file_get_contents($file);

$oldHtml = <<<HTML
                <div class="col-sm-6" id="ccAgentDiv" style="display: none;">
                  <label class="form-label-hitech" for="cc_agent_id">Assign CC Agent <i class="bx bx-info-circle text-muted" title="Only for Sales Dept"></i></label>
                  <select class="select2 w-100 hitech-input-group" id="cc_agent_id" data-style="btn-default"
                          data-icon-base="bx" data-tick-icon="bx-check text-success" name="cc_agent_id">
                    <option value="" selected>Select CC Agent (Optional)</option>
                    @foreach (\$ccUsers as \$cc)
                      <option value="{{\$cc->id}}">{{\$cc->first_name.' '.\$cc->last_name}}</option>
                    @endforeach
                  </select>
                </div>
HTML;

$newHtml = <<<HTML
                <div class="col-sm-6 ccAgentDiv" style="display: none;">
                  <label class="form-label-hitech" for="ccare_agent_id">Assign CCARE Agent <i class="bx bx-info-circle text-muted" title="Only for Sales Dept"></i></label>
                  <select class="select2 w-100 hitech-input-group" id="ccare_agent_id" data-style="btn-default"
                          data-icon-base="bx" data-tick-icon="bx-check text-success" name="ccare_agent_id">
                    <option value="" selected>Select CCARE Agent (Optional)</option>
                    @foreach (\$ccareUsers as \$cc)
                      <option value="{{\$cc->id}}">{{\$cc->first_name.' '.\$cc->last_name}}</option>
                    @endforeach
                  </select>
                </div>

                <div class="col-sm-6 ccAgentDiv" style="display: none;">
                  <label class="form-label-hitech" for="newbiz_agent_id">Assign New Biz Agent <i class="bx bx-info-circle text-muted" title="Only for Sales Dept"></i></label>
                  <select class="select2 w-100 hitech-input-group" id="newbiz_agent_id" data-style="btn-default"
                          data-icon-base="bx" data-tick-icon="bx-check text-success" name="newbiz_agent_id">
                    <option value="" selected>Select New Biz Agent (Optional)</option>
                    @foreach (\$newbizUsers as \$cc)
                      <option value="{{\$cc->id}}">{{\$cc->first_name.' '.\$cc->last_name}}</option>
                    @endforeach
                  </select>
                </div>
HTML;

$content = str_replace($oldHtml, $newHtml, $content);

$oldJs = <<<JS
          var ccDiv = document.getElementById('ccAgentDiv');
          var allowedDepts = ['sales', 'sale', 'sale department', 'sales department'];
          if (allowedDepts.includes(deptText)) {
              ccDiv.style.display = 'block';
          } else {
              ccDiv.style.display = 'none';
              var ccSelect = window.jQuery ? window.jQuery('#cc_agent_id') : null;
              if(ccSelect) {
                  ccSelect.val('').trigger('change');
              } else {
                  document.getElementById('cc_agent_id').value = '';
              }
          }
JS;

$newJs = <<<JS
          var ccDivs = document.querySelectorAll('.ccAgentDiv');
          var allowedDepts = ['sales', 'sale', 'sale department', 'sales department'];
          if (allowedDepts.includes(deptText)) {
              ccDivs.forEach(div => div.style.display = 'block');
          } else {
              ccDivs.forEach(div => div.style.display = 'none');
              var ccareSelect = window.jQuery ? window.jQuery('#ccare_agent_id') : null;
              if(ccareSelect) { ccareSelect.val('').trigger('change'); } else { document.getElementById('ccare_agent_id').value = ''; }
              var newbizSelect = window.jQuery ? window.jQuery('#newbiz_agent_id') : null;
              if(newbizSelect) { newbizSelect.val('').trigger('change'); } else { document.getElementById('newbiz_agent_id').value = ''; }
          }
JS;

$content = str_replace($oldJs, $newJs, $content);

file_put_contents($file, $content);
echo "create.blade.php patched successfully!\n";
