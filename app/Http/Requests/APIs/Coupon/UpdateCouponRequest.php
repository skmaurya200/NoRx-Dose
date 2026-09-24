<?php

namespace App\Http\Requests\APIs\Coupon;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateCouponRequest extends StoreCouponRequest
{
    /**
     * The code being edited must not collide with itself.
     */
    protected function uniqueCode(): Unique
    {
        return Rule::unique('tbl_coupons', 'code')
            ->ignore($this->route('coupon')->id)
            ->withoutTrashed();
    }
}
