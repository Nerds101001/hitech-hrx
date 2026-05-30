<?php
$file = 'd:\live_server\resources\views\tenant\employees\view.blade.php';
$content = file_get_contents($file);

$oldHtml = <<<HTML
                                        @if(in_array(\$deptName, \$allowedDepts) || isset(\$currentCcMapping))
                                        <!-- CC Agent -->
                                        <div class="col-md-4">
                                            <div class="emp-field-box">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bx-headphone text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.65rem; letter-spacing: 0.05em;">Assigned CC Agent
                                                        </p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            {{ isset(\$currentCcMapping) && \$currentCcMapping->ccUser ? \$currentCcMapping->ccUser->first_name . ' ' . \$currentCcMapping->ccUser->last_name : 'Not Assigned' }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
HTML;

$newHtml = <<<HTML
                                        @if(in_array(\$deptName, \$allowedDepts) || \$currentCcareId || \$currentNewbizId)
                                        <!-- CC Agent -->
                                        <div class="col-md-4">
                                            <div class="emp-field-box mb-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bx-headphone text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.65rem; letter-spacing: 0.05em;">CCARE Agent
                                                        </p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            @php
                                                                \$ccareUser = \$currentCcareId ? \App\Models\User::find(\$currentCcareId) : null;
                                                            @endphp
                                                            {{ \$ccareUser ? \$ccareUser->first_name . ' ' . \$ccareUser->last_name : 'Not Assigned' }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="emp-field-box">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bx-briefcase-alt-2 text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.65rem; letter-spacing: 0.05em;">New Biz Agent
                                                        </p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            @php
                                                                \$newbizUser = \$currentNewbizId ? \App\Models\User::find(\$currentNewbizId) : null;
                                                            @endphp
                                                            {{ \$newbizUser ? \$newbizUser->first_name . ' ' . \$newbizUser->last_name : 'Not Assigned' }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
HTML;

$content = str_replace($oldHtml, $newHtml, $content);

// Also patch edit modal inside view.blade.php (if it exists)
// But I saw there's no cc_agent_id in view.blade.php. Just in case, I'll search for it again.

file_put_contents($file, $content);
echo "view.blade.php patched successfully!\n";
