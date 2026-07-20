<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        $coupons = Coupon::latest()->paginate(15);

        return view('manage.coupons.index', compact('coupons'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['code'] = strtoupper($data['code']);
        Coupon::create($data);

        return back()->with('ok', "Coupon {$data['code']} created.");
    }

    public function update(Coupon $coupon, Request $request)
    {
        $data = $this->validated($request, $coupon);
        $data['code'] = strtoupper($data['code']);
        $coupon->update($data);

        return back()->with('ok', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return back()->with('ok', 'Coupon deleted.');
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'code'           => ['required', 'string', 'max:40'],
            'description'    => ['nullable', 'string', 'max:255'],
            'discount_type'  => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'min_spend'      => ['nullable', 'numeric', 'min:0'],
            'max_discount'   => ['nullable', 'numeric', 'min:0'],
            'usage_limit'    => ['nullable', 'integer', 'min:1'],
            'starts_at'      => ['nullable', 'date'],
            'expires_at'     => ['nullable', 'date'],
            'active'         => ['nullable', 'boolean'],
        ]);
        $data['min_spend'] = $data['min_spend'] ?? 0;
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
