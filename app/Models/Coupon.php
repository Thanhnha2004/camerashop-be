<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Coupon
 *
 * @package App\Models
 * @property int $id
 * @property string $code Mã coupon duy nhất
 * @property string $type Loại chiết khấu ('percentage' hoặc 'fixed')
 * @property float $value Giá trị chiết khấu
 * @property float $min_order_value Giá trị đơn hàng tối thiểu để áp dụng
 * @property int|null $usage_limit Giới hạn số lần sử dụng tổng thể (NULL = không giới hạn)
 * @property int $used_count Số lần đã được sử dụng
 * @property \Illuminate\Support\Carbon|null $start_date Ngày bắt đầu có hiệu lực
 * @property \Illuminate\Support\Carbon|null $end_date Ngày hết hạn
 * @property bool $is_active Trạng thái hoạt động (1=active, 0=inactive)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Coupon extends Model
{
    use HasFactory;

    /**
     * Tên bảng trong cơ sở dữ liệu.
     * @var string
     */
    protected $table = 'coupons';

    /**
     * Các trường có thể được gán giá trị hàng loạt (Mass Assignable).
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_value',
        'usage_limit',
        'used_count',
        'start_date',
        'end_date',
        'is_active',
    ];

    /**
     * Định nghĩa các kiểu dữ liệu cho các thuộc tính.
     * Sử dụng 'date' cho start_date và end_date để tự động chuyển đổi thành Carbon instances.
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Định nghĩa hằng số cho các loại chiết khấu.
     */
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

}