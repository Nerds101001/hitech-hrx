<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasAddAdjustmentGlobal" aria-labelledby="offcanvasAddAdjustmentGlobalLabel">
    <div class="offcanvas-header bg-label-primary py-3 border-bottom shadow-sm">
        <h5 id="offcanvasAddAdjustmentGlobalLabel" class="offcanvas-title fw-bold text-primary">Global Payroll Adjustment</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body mx-0 flex-grow-0 p-5">
        <form action="{{ route('settings.addOrUpdatePayrollAdjustment') }}" method="POST" id="globalAdjustmentForm">
            @csrf
            <input type="hidden" name="id" id="adjustmentIdGlobal">

            <div class="mb-5">
                <label class="form-label-hitech" for="adjustmentNameGlobal">Adjustment Name</label>
                <input type="text" class="form-control form-control-hitech" id="adjustmentNameGlobal" name="adjustmentName" placeholder="e.g. Health Insurance (Company Share)" required />
            </div>

            <div class="mb-5">
                <label class="form-label-hitech" for="adjustmentCodeGlobal">Unique Code</label>
                <input type="text" class="form-control form-control-hitech" id="adjustmentCodeGlobal" name="adjustmentCode" placeholder="e.g. HI_COMP" required />
                <small class="text-muted opacity-75">Used for payslip identifiers</small>
            </div>

            <div class="row mb-5">
                <div class="col-6">
                    <label class="form-label-hitech" for="adjustmentTypeGlobal">Type</label>
                    <select id="adjustmentTypeGlobal" name="adjustmentType" class="form-select form-select-hitech" required>
                        <option value="benefit">Addition (+)</option>
                        <option value="deduction">Deduction (-)</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label-hitech" for="adjustmentCategoryGlobal">Category</label>
                    <select id="adjustmentCategoryGlobal" class="form-select form-select-hitech">
                        <option value="fixed">Fixed Flat</option>
                        <option value="percentage">% of Basic</option>
                    </select>
                </div>
            </div>

            <div class="mb-5 d-none" id="percentageDivGlobal">
                <label class="form-label-hitech" for="adjustmentPercentageGlobal">Percentage Value (%)</label>
                <div class="input-group input-group-hitech">
                    <input type="number" step="0.01" class="form-control" id="adjustmentPercentageGlobal" name="adjustmentPercentage" placeholder="0.00" />
                    <span class="input-group-text">%</span>
                </div>
            </div>

            <div class="mb-5" id="amountDivGlobal">
                <label class="form-label-hitech" for="adjustmentAmountGlobal">Amount ({{ $settings->currency_symbol }})</label>
                <div class="input-group input-group-hitech">
                    <span class="input-group-text">{{ $settings->currency_symbol }}</span>
                    <input type="number" step="0.01" class="form-control" id="adjustmentAmountGlobal" name="adjustmentAmount" placeholder="0.00" />
                </div>
            </div>

            <div class="mb-5">
                <label class="form-label-hitech" for="adjustmentNotesGlobal">Description / Remarks</label>
                <textarea class="form-control form-control-hitech" id="adjustmentNotesGlobal" name="adjustmentNotes" rows="3" placeholder="Optional notes for internal reference..."></textarea>
            </div>

            <div class="mt-6 pt-2">
                <button type="submit" class="btn btn-hitech w-100 py-3 rounded-pill shadow-lg">
                    <i class="bx bx-check-circle me-2 scaleX-n1-rtl"></i> <span>Save Adjustment</span>
                </button>
                <button type="button" class="btn btn-label-secondary w-100 mt-3 rounded-pill" data-bs-dismiss="offcanvas">
                    Discard Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const categorySelect = document.getElementById('adjustmentCategoryGlobal');
        const percentageDiv = document.getElementById('percentageDivGlobal');
        const amountDiv = document.getElementById('amountDivGlobal');

        categorySelect.addEventListener('change', function() {
            if (this.value === 'percentage') {
                percentageDiv.classList.remove('d-none');
                amountDiv.classList.add('d-none');
            } else {
                amountDiv.classList.remove('d-none');
                percentageDiv.classList.add('d-none');
            }
        });
        
        window.editAdjustmentGlobal = function(adjustment) {
            document.getElementById('offcanvasAddAdjustmentGlobalLabel').innerText = 'Edit Global Adjustment';
            document.getElementById('adjustmentIdGlobal').value = adjustment.id;
            document.getElementById('adjustmentNameGlobal').value = adjustment.name;
            document.getElementById('adjustmentCodeGlobal').value = adjustment.code;
            document.getElementById('adjustmentTypeGlobal').value = adjustment.type;
            document.getElementById('adjustmentNotesGlobal').value = adjustment.notes;
            
            const btnSpan = document.querySelector('#globalAdjustmentForm button[type="submit"] span');
            if(btnSpan) btnSpan.innerText = 'Update Adjustment';

            if (adjustment.percentage) {
                categorySelect.value = 'percentage';
                percentageDiv.classList.remove('d-none');
                amountDiv.classList.add('d-none');
                document.getElementById('adjustmentPercentageGlobal').value = adjustment.percentage;
            } else {
                categorySelect.value = 'fixed';
                amountDiv.classList.remove('d-none');
                percentageDiv.classList.add('d-none');
                document.getElementById('adjustmentAmountGlobal').value = adjustment.amount;
            }
            
            new bootstrap.Offcanvas(document.getElementById('offcanvasAddAdjustmentGlobal')).show();
        };

        // Reset title and button when not editing
        document.getElementById('offcanvasAddAdjustmentGlobal').addEventListener('hidden.bs.offcanvas', function () {
            document.getElementById('offcanvasAddAdjustmentGlobalLabel').innerText = 'Add Global Payroll Adjustment';
            const btnSpan = document.querySelector('#globalAdjustmentForm button[type="submit"] span');
            if(btnSpan) btnSpan.innerText = 'Save Adjustment';
        });
    });
</script>
