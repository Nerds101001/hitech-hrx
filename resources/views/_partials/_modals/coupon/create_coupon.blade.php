<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasCreateCoupon"
    aria-labelledby="offcanvasCreateCouponLabel">
    <div class="offcanvas-header border-bottom">
        <h5 id="offcanvasCouponLabel" class="offcanvas-title">@lang('Create Plan')</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body mx-0 flex-grow-0 p-6 h-100">
        <form class="pt-0" id="couponForm">
            <input type="hidden" name="id" id="id">
            <input type="hidden" name="status" id="status">
            <div class="mb-6">
                <label class="form-label" for="discountType">@lang('Discount Type')<span class="text-danger">*</span></label>
                <select class="form-select" id="discountType" name="discountType">
                    <option value="">Select Duration Type</option>
                    <option value="percentage">Percentage</option>
                    <option value="fixed">Fixed</option>
                </select>
            </div>
            <div class="mb-6">
                <label class="form-label" for="code">@lang('Code')<span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="code" placeholder="@lang('Enter code')"
                    name="code" />
            </div>
            <div class="mb-6">
                <label class="form-label" for="expiryDate">@lang('Expiry Date')<span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="expiryDate" placeholder="@lang('Pick a date')"
                    name="expiryDate" />
            </div>
            <div class="mb-6">
                <label class="form-label" for="discount">@lang('Discount')<span class="text-danger">*</span></label>
                <input type="number" step="0.01" class="form-control" id="discount" placeholder="@lang('Enter discount')"
                    name="discount"/>
            </div>
            <div class="mb-6">
                <label class="form-label" for="limit">@lang('Limit')<span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="limit" placeholder="@lang('Enter limit')"
                    name="limit" />
            </div>
            <div class="mb-6">
                <label class="form-label" for="description">@lang('Description')</label>
                <textarea class="form-control" id="description" placeholder="@lang('Enter description')" name="description"></textarea>
            </div>

            <button type="submit" class="btn btn-primary me-3 data-submit">@lang('Create')</button>
            <button type="reset" class="btn btn-label-danger" data-bs-dismiss="offcanvas">@lang('Cancel')</button>


    </div>
</div>
