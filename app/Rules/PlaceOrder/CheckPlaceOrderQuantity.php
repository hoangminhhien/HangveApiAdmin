<?php

namespace App\Rules\PlaceOrder;

use App\Models\AssignSupportOrder;
use App\Models\OrderDetail;
use Illuminate\Contracts\Validation\Rule;

class CheckPlaceOrderQuantity implements Rule
{
    /**
     * Danh sách mọi order_details.id user gửi lên khi đặt hàng
     * Dùng để tìm kiếm order_detail tương ứng với lần validate hiện tại
     *
     * @var array
     */
    private array $orderDetailIds;

    /**
     * Danh sách mọi assign_support_orders.id user gửi lên khi đặt hàng
     * Dùng để tìm kiếm assign_support_order tương ứng với lần validate hiện tại
     *
     * @var array
     */
    private array $assignSupportIds;

    /**
     * Tổng số lượng sản phẩm còn có thể đặt của order_detail hiện tại
     *   + Nếu source = 'place_order_list' thì = (tổng khách đặt - tổng mình đã đặt - tổng đã nhờ)
     *   + Nếu source = 'place_order_assign_support' thì = quantity của assign_support_order tương ứng
     *
     * @var int
     */
    private int $totalAvailableQuantity;

    /**
     * Request được gửi lên từ trang nào
     *   + Danh sách đặt hàng: place_order_list
     *   + Đơn hàng hỗ trợ: place_order_assign_support
     *
     * @var string
     */
    private string $source;
    private array $quantity;

    /**
     * Hàm khởi tạo
     *
     * @return void
     */
    public function __construct(array $orderDetailIds, array $assignSupportIds, array $quantity, string $source)
    {
        $this->orderDetailIds = $orderDetailIds;
        $this->assignSupportIds = $assignSupportIds;
        $this->quantity = $quantity;
        $this->source = $source;
    }

    /**
     * Định nghĩa logic validate thành công
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        $index = (int) explode('.', $attribute)[1];
        $relationshipPostfix = '';
        if ($this->source == 'place_order_list') {
            $model = OrderDetail::query()->find($this->orderDetailIds[$index]);
            $quantity = $this->quantity[$index];
        } else {
            $relationshipPostfix = 'Assignee';
            $model = AssignSupportOrder::query()->find($this->assignSupportIds[$index]);
            $quantity = $model->totalAvailableQuantityAssignee();
        }

        // nếu là OrderDetail thì call method 'totalAvailableQuantity'
        // nếu là AssignSupportOrder thì call method 'totalAvailableQuantityAssignee'
        /*$relationship = "totalAvailableQuantity{$relationshipPostfix}";
        $this->totalAvailableQuantity = $model->{$relationship}();*/
        $this->totalAvailableQuantity = $quantity;

        return $value <= $this->totalAvailableQuantity;
    }

    /**
     * Định nghĩa message lỗi trả về khi validate không thành công
     *
     * @return string
     */
    public function message()
    {
        return 'Số lượng sản phẩm khả dụng để đặt hàng: ' . $this->totalAvailableQuantity;
    }
}
