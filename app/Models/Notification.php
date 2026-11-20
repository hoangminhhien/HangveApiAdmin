<?php

namespace App\Models;
use App\Constants\NotifyConstant;
use App\Scopes\Traits\Filterable;
use Illuminate\Support\Facades\Log;


class Notification extends BaseModel
{
    use Filterable;

    protected string $_tableNameFriendly = 'Thông báo';
    protected $_prefixRoute = 'notification';

    public $fillable = [
        'group',
        'name',
        'user_id',
        'data',
        'time_create',
        'time_seen',
        'organization_id',
        'status',
        'customer_id'
    ];

    protected $table = 'notifications';
    public $timestamps = true;

    public const CUSTOMER = 1; // Khách hàng
    public const ORDER = 2; // Đơn hàng Order
    public const PACKAGE = 3; // Kiện hàng
    public const DELIVERY = 4; // Giao hàng
    public const COMPLAIN = 5; // khiếu nại
    public const FINE = 6; // nộp phạt
    public const CART = 7; // giỏ hàng
    public const FINANCE = 8; // tài chính
    public const ENDOW = 9; // Ưu đãi
    public const REFUND = 10; // hoàn tiền
    public const FILE = 11; // tải file
    public const NEWS = 12; // thông báo mới
    public const IMPORTANT = 13; // thông báo quan trọng
    public const ORDERSTATUSRECONFIRM = 14; // đơn cần xác nhận lại
    public const CONSIGNMENT = 15; // đơn ký gửi


    public function getGroupText()
    {
        $list = [
            1 => 'Khách hàng', // done
            2 => 'Đơn hàng Order', //done
            3 => 'Kiện hàng', // done
            4 => 'Giao hàng', // done
            5 => 'Khiếu nại', // done
            6 => 'Nộp phạt', //done
            7 => 'Giỏ hàng', //done
            8 => 'Tài chính', // done
            9 => 'Ưu đãi', // done
            10 => 'Hoàn tiền', // done
            11 => 'Tải file', // file
            12 => 'Thông báo mới',
            13 => 'Thông báo quan trọng',
            14 => 'Đơn cần xác nhận lại',
            15 => 'Đơn ký gửi',
        ];
        return array_key_exists($this->group, $list) ? $list[$this->group] : 'Không xác định';
    }

    public function getNameText()
    {
        $list = [
            1001 => 'Khách hàng tạo tài khoản',
            1002 => 'Thay đổi nhân viên tư vấn, chăm sóc',
            1003 => 'Update đơn - Chờ báo giá',
            1004 => 'Yêu cầu nhân viên tư vấn',
            1005 => 'Được gán để tư vấn',

            2001 => 'Thay đổi nhân viên',
            2002 => 'Tạo đơn mới - Chờ báo giá',
            2003 => 'Chuyển trạng thái -> Chờ đặt cọc',
            2004 => 'Chuyển trạng thái -> Đã hủy',
            2005 => 'Chuyển trạng thái -> Đã đặt cọc',
            2006 => 'Chuyển trạng thái -> các trạng thái còn lại',
            2007 => 'Tạo mới kiện hàng',
            2008 => 'Thêm nộp phạt',
            2009 => 'Yêu cầu kí hợp đồng đơn hàng',
            2010 => 'Khách hàng kí hợp đồng thành công',
            2011 => 'Yêu cầu báo giá lại',
            2012 => 'Yêu cầu thanh toán thêm',
            2013 => 'Tạo ứng tiền',
            2014 => 'Duyệt ứng tiền',
            2015 => 'Ghi chú đơn hàng',
            2016 => 'Chuyển trạng thái cần xác nhận lại',
            2017 => 'Thanh toán tiền hàng chênh lệch',
            3001 => 'Thêm mới kiện hàng',
            3002 => 'Update trạng thái kiện',
            3003 => 'Cân duyệt kiểm hàng',
            3004 => 'Xác nhận thông tin',
            3005 => 'Chênh lệch số kiện thực tế khi nhập kho',
            3007 => 'Kiểm hàng sai',
            3008 => 'Có kiện chờ xác nhận',
            3009 => 'Đã duyệt đơn giá',
            3010 => 'Từ chối đơn giá',
            3011 => 'Yêu cầu duyệt đơn giá bên Hàng Về',
            3012 => 'Yêu cầu duyệt đơn giá bên Báo giá',

            4001 => 'Khách tạo mới giao hàng ',
            4002 => 'Update trạng thái giao hàng',
            4003 => 'Tạo mới đơn hàng bên hãng vận chuyển',
            4004 => 'Hủy đơn hàng bên hãng vận chuyển',

            5001 => 'Khách tạo mới khiếu nại',
            5002 => 'Phản hồi từ khách hàng',
            5003 => 'Phàn hồi từ nhóm nội bộ',
            5004 => 'Update trạng thái khiếu nại',
            5005 => 'Update giải pháp khiếu nại',
            5006 => 'Phản hồi của nhân viên',

            6001 => 'Thêm mới nộp phạt',
            6002 => 'Ý kiến từ user khác',
            6003 => 'Update trạng thái',

            7001 => 'Sản phẩm add vào trong giỏ quá 15p',

            8001 => 'Nạp tiền vào ví điện tử',
            8002 => 'Hủy nạp tiền vào ví điện tử',
            8003 => 'Đặt cọc',
            8004 => 'Thanh toán',
            8005 => 'Hủy rút tiền',
            8006 => 'Gửi yêu cầu rút tiền',
            8007 => 'Đổi tiền',
            8008 => 'Thanh toán tiền hàng còn thiếu',
            8009 => 'Xóa đổi tiền',
            8010 => 'Hoàn tiền sửa giao hàng',
            8011 => 'Hoàn tiền xóa giao hàng',
            8012 => 'Tạo giao hàng',
            8013 => 'Hoàn tiền khi thêm thanh toán',
            8014 => 'Hoàn tiền đơn hàng',
            8015 => 'Hoàn tiền kiện hàng',
            8016 => 'Hoàn tiền còn thiếu của đơn hàng',

            9001 => 'Thay đổi ưu đãi',

            10001 => 'Hoàn tiền',
            11001 => 'Tải file danh sách khách hàng thành công',
            11002 => 'Tải file danh sách khách hàng thất bại',
            11003 => 'Tải file danh sách khách hàng đang hoạt động thành công',
            11004 => 'Tải file danh sách khách hàng đang hoạt động thất bại',
            11005 => 'Tải file danh sách đơn hàng thành công',
            11006 => 'Tải file danh sách đơn hàng thất bại',
            11007 => 'Tải file danh sách kiện hàng thành công',
            11008 => 'Tải file danh sách kiện hàng thất bại',
            11009 => 'Tải file thanh toán thực tế NCC thành công',
            11010 => 'Tải file thanh toán thực tế NCC thất bại',
            11011 => 'Tải file danh sách giao hàng thất bại',
        ];
        return array_key_exists($this->name, $list) ? $list[$this->name] : 'Không xác định';
    }

    public function getContent()
    {
        $content = 'Nội dung thông báo';
        $data = json_decode($this->data, true);

        if ($this->name == 1001) {
            $content = 'Khách hàng ' . $data['customer_name'] . ' mới tạo tài khoản';
        }
        if ($this->name == 10) {
            $content = $data['content'] ?? '';
        }
        if ($this->name == 1002) {
            $content = 'Khách hàng ' . data_get($data, 'customer_name') . ' được chuyển cho bạn';
        }
        if ($this->name == 1003) {
            $content = 'Đơn hàng ' . $data['order_code'] . ' được cập nhật';
        }
        if ($this->name == 1004) {
            $content = 'Có yêu cầu tư vấn chính ngạch mới. Vui lòng gán nhân viên xử lý!';
        }
        if ($this->name == 1005) {
            $content = 'Bạn có ' . $data['count_advise_cn'] . ' yêu cầu tư vấn chính ngạch mới. Vui lòng kiểm tra ngay!';
        }
        if ($this->name == 2001) {
            $content = 'Đơn hàng ' . $data['order_code'] . ' được chuyển cho bạn';
        }
        if ($this->name == 2002) {
            $content = 'Đơn hàng ' . $data['order_code'] . ' được tạo thành công';
        }
        if (in_array($this->name, [2003,2004,2005,2006])) {
           $content = 'Đơn hàng ' . $data['order_code'] . ' chuyển sang trạng thái ' . $data['order_status_txt'];
        }
        if ($this->name == 2007) {
            $content = 'Kiện hàng mới được thêm vào đơn hàng ' . $data['order_code'];
        }
        if ($this->name == 2008) {
            $content = 'Phiếu phạt mới được tạo cho bạn';
        }
        if ($this->name == 2009) {
            $content = 'Yêu cầu kí hợp đồng';
        }
        if ($this->name == 2010) {
            $content = data_get($data, 'customer_name').' đã kí thành công hợp đồng đơn hàng có mã đơn hàng '.$data['order_code'];
        }
        if ($this->name == 2011) {
            $content = 'Đơn hàng ' .data_get($data, 'order_code').' được yêu cầu báo giá lại';
        }
        if ($this->name == 2013) {
            $content = 'Đơn hàng ' .data_get($data, 'order_code').' cần duyệt số tiền ứng là '.number_format(data_get($data, 'amount'),0,".",".") . ' đ';
        }
        if ($this->name == 2014) {
            $content = 'Đơn hàng ' .data_get($data, 'order_code').' đã được ứng số tiền là '.number_format(data_get($data, 'amount'),0,".",".") . ' đ';
        }
        if ($this->name == 2015) {
            $content = data_get($data, 'content');
        }
        if ($this->name == 2016) {
            $content = data_get($data, 'content');
        }
        if ($this->name == 3001) {
            $content = 'Kiện hàng ' . $data['pack_code'] . ' được tạo mới và gán cho bạn';
        }
        if ($this->name == 3002) {
            $content = 'Kiện hàng ' . $data['pack_code'] . ' chuyển sang trạng thái ' . $data['pack_status_txt'];
        }
        if ($this->name == 3003) {
            $content = 'Kiện hàng ' . $data['pack_code'] . ' cần duyệt kiểm hàng';
        }
        if ($this->name == 3004) {
            $content = 'Kiện hàng ' . data_get($data, 'bill_code') . ' đang chờ xác nhận thông tin';
        }
        if ($this->name == 3005) {
            $content = 'Kiện hàng ' . data_get($data, 'bill_code') . ' bị chệnh lệch số kiện thực tế khi nhập kho. Vui lòng kiểm tra lại !';
        }
        if ($this->name == 3007) {
            $content = 'Mã vận đơn '. data_get($data, 'bill_code') .' có thông tin chưa khớp khi kiểm hàng. Vui lòng kiểm tra ngay!';
        }
        if ($this->name == 3008) {
            $content = 'Kiện hàng '. data_get($data, 'bill_code') .' đã được nhân viên kho gán cho bạn!';
        }
        if ($this->name == 3009) {
            $content = 'Mã vận đơn '. data_get($data, 'bill_code') .' đã được duyệt đơn giá';
        }
        if ($this->name == 3010) {
            $content = 'Mã vận đơn '. data_get($data, 'bill_code') .' đã bị từ chối duyệt đơn giá với lý do '.data_get($data, 'cancel_reason');
        }
        if ($this->name == 3011) {
            $content = 'Mã vận đơn '. data_get($data, 'bill_code') . ' có yêu cầu duyệt đơn giá';
        }
        if ($this->name == 3012) {
            $content = 'Mã báo giá '. data_get($data, 'quote_code') . ' có yêu cầu duyệt đơn giá';
        }
        if ($this->name == 4001) {
            $content = 'Giao hàng mới được tạo';
        }
        if ($this->name == 4002) {
            $code = $data['delivery_postcode'];
            if (is_null($data['delivery_postcode'])) {
                $code = $data['delivery_code'];
            }
            $content = 'Phiếu giao hàng ' . $code . ' được chuyển trạng thái thành ' . $data['delivery_status_txt'];
        }
        if($this->name == 4003) {
            $code = $data['delivery_code'];
            $transporter = $data['transporter_name'];
            $content = 'Mã giao hàng ' . $code . ' đã được bàn giao cho bên vận chuyển ' . $transporter;
        }
        if($this->name == 4004) {
            $code = $data['delivery_code'];
            $cancelBy = $data['cancel_by'];
            $content = 'Mã giao hàng ' . $code . ' đã bị hủy bởi nhân viên ' . $cancelBy;
        }
        if ($this->name == 5001) {
            $content = 'Khiếu nại đơn hàng ' . $data['order_code'] . ' được tạo mới';
        }
        if ($this->name == 5002) {
            $content = 'Phản hồi từ khách hàng ' . $data['customer_name'] . ' : ' . $data['feedback_content'];
        }
        if ($this->name == 5003) {
            $content = 'Phản hồi từ nv ' . $data['user_chat_name'] . ' : ' . $data['content'];
        }
        if ($this->name == 5007) {
            $content = 'Khách hàng có mã '.$data['customer_code'].' đã yêu cầu thay đổi nhân viên quản lý';
        }
        if ($this->name == 6001) {
            $content = 'Phiếu phạt mới được tạo cho bạn';
        }
        if ($this->name == 6002) {
            $content = 'Phản hồi từ phiếu phạt của bạn : ' . $data['content'];
        }
        if ($this->name == 6003) {
            $content = 'Phiếu phạt của bạn chuyển sang trạng thái : ' . $data['status_txt'];
        }

        if ($this->name == 8001 || $this->name == 8002) {
            $content = $data['content'];
        }
        if ($this->name == 8003) {
            $content = 'Yêu cầu rút tiền được chuyển sang trạng thái : ' . $data['status_txt'];
        }
        if ($this->name == 8014) {
            $amount =  $data['amount'];
            $orderCode = $data['order_code'];
            $content = "Hoàn lại $amount - cho đơn hàng $orderCode";
        }
        if ($this->name == 10001) {
            $content = $data['content'];
        }
        if ($this->name == 11001) {
            $content = "Tải file danh sách khách hàng thành công. Vui lòng bấm vào đây để tải file về máy!";
        }
        if ($this->name == 11002) {
            $content = "Tải file danh sách khách hàng thất bại. Vui lòng liên hệ với kĩ thuật để được hỗ trợ!";
        }
        if ($this->name == 11003) {
            $content = "Tải file danh sách khách hàng đang hoạt động thành công. Vui lòng bấm vào đây để tải file về máy!";
        }
        if ($this->name == 11004) {
            $content = "Tải file danh sách khách hàng đang hoạt động thất bại. Vui lòng liên hệ với kĩ thuật để được hỗ trợ!";
        }
        if ($this->name == 11005) {
            $content = "Tải file danh sách đơn hàng thành công. Vui lòng bấm vào đây để tải file về máy!";
        }
        if ($this->name == 11006) {
            $content = "Tải file danh sách đơn hàng thất bại. Vui lòng liên hệ với kĩ thuật để được hỗ trợ!";
        }
        if ($this->name == 11007) {
            $content = "Tải file danh sách kiện hàng thành công. Vui lòng bấm vào đây để tải file về máy!";
        }
        if ($this->name == 11008) {
            $content = "Tải file danh sách kiện hàng thất bại. Vui lòng liên hệ với kĩ thuật để được hỗ trợ!";
        }
        if ($this->name == 11009) {
            $content = "Tải file thanh toán thực tế NCC thành công. Vui lòng bấm vào đây để tải file về máy!";
        }
        if ($this->name == 11010) {
            $content = "Tải file thanh toán thực tế NCC thất bại. Vui lòng liên hệ với kĩ thuật để được hỗ trợ!";
        }
        if ($this->name == 11011) {
            $content = "Tải file danh sách giao hàng thất bại. Vui lòng liên hệ với kĩ thuật để được hỗ trợ!";
        }
        if ($this->name == 11012) {
            $content = "Tải file danh sách giao hàng thành công. Vui lòng bấm vào đây để tải file về máy!";
        }

        return $content;
    }

    public function getTimeCreate()
    {
        $time = '';
        $diff = time() - $this->time_create;
        if ($diff  > 0) {
            $time = 'Vừa xong';
        }

        if ($diff > 60) {
            $phut = round($diff / 60);
            $time = $phut .' phút trước';
        }

        if ($diff > (60 * 60)) {
            $phut = round($diff / (60 * 60));
            $time = $phut .' giờ trước';
        }

        if ($diff > (60 * 60 * 24)) {
            $phut = round($diff / (60 * 60 * 24));
            $time = $phut .' ngày trước';
        }

        return $time;
    }

    // Sử dụng gửi notification cho khách hàng bên Client
    public function getGroupTextCustomer(): string
    {
        $list = [
            1 => 'Khách hàng',
            2 => 'Đơn hàng Order',
            3 => 'Kiện hàng',
            4 => 'Giao hàng',
            5 => 'Khiếu nại',
            6 => 'Nộp phạt',
            7 => 'Giỏ hàng',
            8 => 'Tài chính',
            9 => 'Ưu đãi',
            10 => 'Thông báo mới',
            11 => 'Góp ý'
        ];
        return array_key_exists($this->group, $list) ? $list[$this->group] : 'Không xác định';
    }

    public function getNameTextCustomer(): string
    {
        $list = [
            1001 => 'Khách hàng tạo tài khoản',
            1002 => 'Thay đổi nhân viên tư vấn, chăm sóc',
            1003 => 'Update đơn - Chờ báo giá',
            2001 => 'Thay đổi nhân viên',
            2002 => 'Tạo đơn mới - Chờ báo giá',
            2003 => 'Chuyển trạng thái -> Chờ đặt cọc',
            2004 => 'Chuyển trạng thái -> Đã hủy',
            2005 => 'Chuyển trạng thái -> Đã đặt cọc',
            2006 => 'Chuyển trạng thái -> các trạng thái còn lại',
            2007 => 'Tạo mới kiện hàng',
            2008 => 'Thêm nộp phạt',
            2009 => 'Yêu cầu kí hợp đồng',
            2010 => 'Khách hàng kí hợp đồng thành công',
            2011 => 'Yêu cầu báo giá lại',
            3001 => 'Thêm mới kiện hàng',
            3002 => 'Update trạng thái kiện',
            3006 => 'Thêm mới kiện hàng Ký gủi',
            4001 => 'Khách tạo mới giao hàng ',
            4002 => 'Update trạng thái giao hàng',
            5001 => 'Khách tạo mới khiếu nại',
            5002 => 'Phản hồi từ khách hàng',
            5003 => 'Phàn hồi từ nhóm nội bộ',
            5004 => 'Update trạng thái khiếu nại',
            5005 => 'Update giải pháp khiếu nại',
            5006 => 'Phản hồi của nhân viên',
            5007 => 'Yêu cầu thay đổi nhân viên',
            6001 => 'Thêm mới nộp phạt',
            6002 => 'Ý kiến từ user khác',
            6003 => 'Update trạng thái',
            7001 => 'Sản phẩm add vào trong giỏ quá 15p  ',
            8001 => 'Nạp tiền vào ví điện tử',
            8002 => 'Hủy nạp tiền vào ví điện tử',
            8003 => 'Đặt cọc',
            8004 => 'Thanh toán',
            8005 => 'Hủy rút tiền',
            8006 => 'Gửi yêu cầu rút tiền',
            8007 => 'Đổi tiền',
            8008 => 'Thanh toán tiền hàng còn thiếu',
            8009 => 'Xóa đổi tiền',
            8010 => 'Hoàn tiền sửa giao hàng',
            8011 => 'Hoàn tiền xóa giao hàng',
            8012 => 'Thanh toán tiền hàng còn thiếu khi tạo giao hàng cho 2 kiện trở lên',
            8013 => 'Hoàn tiền khi thêm thanh toán',
            9001 => 'Thay đổi ưu đãi',
        ];
        return array_key_exists($this->group_category, $list) ? $list[$this->group_category] : 'Không xác định';
    }

    public function getContentCustomer()
    {
        $content = 'Nội dung thông báo';
        $data = json_decode($this->data, true);

        if ($this->name == 2015) {
            $content = data_get($data, 'content');
        }
        if (in_array($this->name, [2003, 2004, 2005, 2006])) {
            $content = 'Đơn hàng '.$data['order_code'].' chuyển sang trạng thái '.$data['order_status_txt'];
        }
        if ($this->name == 3002) {
            $content = 'Kiện hàng '.$data['pack_code'].' được chuyển sang trạng thái '.$data['pack_status_txt'];
        }
        if ($this->name == 3005) {
            $content = 'Kiện hàng ' . data_get($data, 'bill_code') . ' bị chệnh lệch số kiện thực tế khi nhập kho. Vui lòng kiểm tra lại !';
        }
        if ($this->name == 5004) {
            $content = 'Khiếu nại cho đơn hàng '.$data['order_code'].' được chuyển sang trạng thái '.$data['status_txt'];
        }

        if ($this->name === 5005) {
            if (isset($data['solution_code']) && $data['solution_code'] == Solution::CODE_NBT) {
                $content = 'Khiếu nại cho đơn hàng '.$data['order_code'].' được đổi phương án xử lý thành '.$data['solution_txt'].' với số tiền là '.number_format($data['compensation']).'vnđ';
            } else {
                $content = 'Khiếu nại cho đơn hàng '.$data['order_code'].' được đổi phương án xử lý thành '.$data['solution_txt'];
            }
        }
        if ($this->name == 5006) {
            $content = 'Bạn có phản hồi của đơn khiếu nại cho đơn hàng '.$data['order_code'];
        }

        if (in_array($this->name, [8001, 8002, 8003, 8004, 8005, 8006, 8007, 8009, 8010, 8011, 8012, 8013, 3006])) {
            if (isset($data['content'])) {
                $content = $data['content'];
            } else {
                $content = 'Nội dung thông báo mã '.$this->name;
            }
        }
        if ($this->name == 8014) {
            $amount =  $data['amount'];
            $orderCode = $data['order_code'];
            $content = "Hoàn lại $amount - cho đơn hàng $orderCode";
        }
        if ($this->name === 8008) {
            $content = 'Thanh toán tiền hàng còn thiếu';
        }
        if ($this->name === 9001) {
            if (array_key_first($data) == 'deposit_rate' || array_key_first($data) == 'offer_rate') {
                $content = $this->getFieldEndow(array_key_first($data))
                    .' được thay đổi từ '
                    .$data[array_key_first($data)]['old'].'%'
                    .' sang '.$data[array_key_first($data)]['new'].'%';
            } else {
                $content = $this->getFieldEndow(array_key_first($data))
                    .' được thay đổi từ '
                    .number_format($data[array_key_first($data)]['old']).' đ'
                    .' sang '.number_format($data[array_key_first($data)]['new']).' đ';
            }
        }
        if ($this->name == NotifyConstant::KEY_NAME_CUSTOMER_CHANGE_STAFF &&
            $this->group == NotifyConstant::CUSTOMER_KEY_GROUP_NEWS &&
            isset($data["content"])
        ) {
            $content = $data["content"];
        }

        if ($this->name == NotifyConstant::KEY_NAME_CUSTOMER_CHANGE_STAFF &&
            $this->group == NotifyConstant::KEY_GROUP_COMPLAIN &&
            isset($data["content"])
        ) {
            $content = $data["content"];
        }

        return $content;
    }

    // Các field text của phần ưu đãi trong admin
    public function getFieldEndow($key): string
    {
        switch ($key) {
            case 'deposit_rate':
                return '% Đặt cọc';
            case 'offer_rate':
                return '% Đặt hàng';
            case 'trans_volumn_hcm_fee':
                return 'Phí vận chuyển HCM (/m3)';
            case 'trans_volumn_hn_fee':
                return 'Phí vận chuyển HN (/m3)';
            case 'trans_volumn_hp_fee':
                return 'Phí vận chuyển HP (/m3)';
            case 'trans_weight_hcm_fee':
                return 'Phí vận chuyển HCM (/kg)';
            case 'trans_weight_hn_fee':
                return 'Phí vận chuyển HN (/kg)';
            case 'trans_weight_hp_fee':
                return 'Phí vận chuyển HP (/kg)';
            default:
                return '';
        }
    }

    public function scopeName($query)
    {
        $name = request()->query('name');
        return $query->where('name', 'like', "%{$name}%");
    }

    public function scopeType($query)
    {
        return $query->where('type', request()->query('type'));
    }

    public function scopeIdSort($query)
    {
        return $query->orderBy('id', request()->query('id_sort'));
    }

    public function scopeStatus($query)
    {
        return $query->where('status', request()->query('status'));
    }

    public function scopeHideType($query)
    {
        return $query->where('type', '>', 0);
    }

    public function scopeUserId($query)
    {
        $userOnline = \Auth::user();
        return $query->where('user_id', $userOnline->id);
    }

    public function scopceGroup($query)
    {
        return $query->where('group', request()->query('group'));
    }

}
