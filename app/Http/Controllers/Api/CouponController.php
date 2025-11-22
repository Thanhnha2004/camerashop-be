<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;

class CouponController extends Controller
{
    /**
     * Xác thực coupon và tính toán chiết khấu.
     * Đây là API chính để kiểm tra tính hợp lệ của mã coupon.
     * * @param string $code Mã coupon cần kiểm tra
     * @param \Illuminate\Http\Request $request Yêu cầu chứa order_amount
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateCoupon($code, Request $request)
    {
        // Lấy giá trị đơn hàng từ request. Nếu không có, mặc định là 0.
        $order_amount = $request->order_amount ?? 0;

        // Tìm coupon theo mã
        $coupon = Coupon::where('code', $code)->first();

        // 1. Kiểm tra tồn tại
        if (!$coupon) {
            return response()->json(['valid' => false, 'message' => 'Coupon không tồn tại'], 404);
        }

        // 2. Kiểm tra trạng thái hoạt động
        if (!$coupon->is_active) {
            return response()->json(['valid' => false, 'message' => 'Coupon không hoạt động']);
        }
        
        // 3. Kiểm tra thời hạn sử dụng
        // Giả định `start_date` và `end_date` đã được cast thành Carbon trong Coupon Model
        $now = now();
        if ($now->isBefore($coupon->start_date) || $now->isAfter($coupon->end_date)) {
            return response()->json(['valid' => false, 'message' => 'Coupon đã hết hạn sử dụng']);
        }

        // 4. Kiểm tra giới hạn sử dụng
        if ($coupon->usage_limit > 0 && $coupon->used_count >= $coupon->usage_limit) {
            return response()->json(['valid' => false, 'message' => 'Coupon đã đạt giới hạn sử dụng']);
        }

        // 5. Kiểm tra giá trị đơn hàng tối thiểu
        if ($order_amount < $coupon->min_order_value) {
            $minOrderFormatted = number_format($coupon->min_order_value, 0, ',', '.') . ' VNĐ';
            return response()->json(['valid' => false, 'message' => "Đơn hàng tối thiểu để áp dụng là {$minOrderFormatted}"]);
        }

        // 6. Tính toán chiết khấu (Discount)
        $discount = $coupon->type === 'percentage'
            ? $order_amount * ($coupon->value / 100)
            : $coupon->value;

        // Đảm bảo chiết khấu không vượt quá giá trị đơn hàng
        $discount = min($discount, $order_amount);

        // 7. Trả về kết quả hợp lệ
        return response()->json([
            'valid' => true,
            'discount' => round($discount), // Chiết khấu đã làm tròn
            'coupon_code' => $coupon->code, // Trả lại code để client tiện sử dụng
            'message' => 'Coupon hợp lệ'
        ]);
    }
}