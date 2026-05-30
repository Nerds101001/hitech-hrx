<?php
$file = 'd:\live_server\resources\views\_partials\_modals\employees\edit_work_info.blade.php';
$content = file_get_contents($file);

$oldHtml = <<<HTML
              <div class="col-md-6" id="ccAgentEditDiv" style="display: none;">
                <label class="form-label-hitech" for="cc_agent_id">@lang('Assign CC Agent') <i class="bx bx-info-circle text-muted" title="Only for Sales Dept"></i></label>
                <select class="form-select form-select-hitech select2" id="cc_agent_id" name="cc_agent_id">
                  <option value="">Select CC Agent (Optional)</option>
                  @foreach(\$ccUsers ?? [] as \$cc)
                    <option value="{{ \$cc->id }}" {{ (\$currentCcMapping->cc_user_id ?? null) == \$cc->id ? 'selected' : '' }}>
                      {{ \$cc->first_name }} {{ \$cc->last_name }}
                    </option>
                  @endforeach
                </select>
              </div>
HTML;

$newHtml = <<<HTML
              <div class="col-md-6 ccAgentEditDiv" style="display: none;">
                <label class="form-label-hitech" for="ccare_agent_id">@lang('Assign CCARE Agent') <i class="bx bx-info-circle text-muted" title="Only for Sales Dept"></i></label>
                <select class="form-select form-select-hitech select2" id="ccare_agent_id" name="ccare_agent_id">
                  <option value="">Select CCARE Agent (Optional)</option>
                  @foreach(\$ccareUsers ?? [] as \$cc)
                    <option value="{{ \$cc->id }}" {{ (\$currentCcareId ?? null) == \$cc->id ? 'selected' : '' }}>
                      {{ \$cc->first_name }} {{ \$cc->last_name }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 ccAgentEditDiv" style="display: none;">
                <label class="form-label-hitech" for="newbiz_agent_id">@lang('Assign New Biz Agent') <i class="bx bx-info-circle text-muted" title="Only for Sales Dept"></i></label>
                <select class="form-select form-select-hitech select2" id="newbiz_agent_id" name="newbiz_agent_id">
                  <option value="">Select New Biz Agent (Optional)</option>
                  @foreach(\$newbizUsers ?? [] as \$cc)
                    <option value="{{ \$cc->id }}" {{ (\$currentNewbizId ?? null) == \$cc->id ? 'selected' : '' }}>
                      {{ \$cc->first_name }} {{ \$cc->last_name }}
                    </option>
                  @endforeach
                </select>
              </div>
HTML;

$content = str_replace($oldHtml, $newHtml, $content);

$oldJs = <<<JS
          var ccDiv = document.getElementById('ccAgentEditDiv');
          if(!deptSelect) return;
          var deptText = deptSelect.options[deptSelect.selectedIndex]?.text.toLowerCase().trim() || '';
          
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
          var ccDivs = document.querySelectorAll('.ccAgentEditDiv');
          if(!deptSelect) return;
          var deptText = deptSelect.options[deptSelect.selectedIndex]?.text.toLowerCase().trim() || '';
          
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
echo "edit_work_info.blade.php patched successfully!\n";
