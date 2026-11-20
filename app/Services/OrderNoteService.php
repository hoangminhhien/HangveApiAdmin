<?php

namespace App\Services;

use App\Abstracts\AbstractBaseService;
use App\Constants\OrderNoteConstant;
use App\Models\Customer;
use App\Models\OrderHistory;
use App\Models\Staff;

class OrderNoteService extends AbstractBaseService
{

    protected function setModel()
    {
        $namespaceModel = 'App\Models\\' . str_replace('Service', '', class_basename($this));
        $this->_model = class_exists($namespaceModel) ? new $namespaceModel() : null;
    }

    public function updateOrderNote($order_id, $params)
    {
        if (isset($params['suppliers'])) {
            foreach ($params['suppliers'] as  $supplier) {
                if (isset($supplier['notes'])) {
                    foreach ($supplier['notes'] as $key => $value) {
                        if ($key == 'order_provisional_date') {
                            $public = 2;
                        } else if ($key == 'public') {
                            $public = 1;
                        }
                        else if ($key == 'business') {
                            $public = OrderNoteConstant::INTERNAL_BUSINESS;
                        }
                        else {
                            $public = 0;
                        }
                        $orderNoteItem = $this->_model->query()
                            ->where('order_id', $order_id)
                            ->where('supplier_id', $supplier['id'])
                            ->where('is_public', $public)
                            ->orderBy('id', 'DESC')
                            ->first();
                        if (!$orderNoteItem) {
                            if (!empty($value)) {
                                $this->store([
                                    'order_id' => $order_id,
                                    'subject_type' => Staff::class,
                                    'content' => $public != 2 ? $value : '',
                                    'subject_id' => getCurrentUser()->id ?? 0,
                                    'supplier_id' => $supplier['id'],
                                    'is_public' => $public,
                                    'order_provisional_date' => $public == 2 ? date('Y-m-d H:i:s', strtotime($value)) : ''
                                ]);
                                if ($key == 'private') {
                                    $data = [
                                        'old_value' => '',
                                        'new_value' => $value,
                                        'order_id' => $order_id,
                                        'history_type' => OrderHistory::ORDER_HISTORY_NOTE_TYPE,
                                        'user_id' => getCurrentUser()->id ?? 0,
                                        'date' => date('Y-m-d H:i:s'),
                                        'content' => 'đã thêm mới Ghi chú nội bộ [ ' .  $value . ' ] '
                                    ];
                                    OrderHistory::query()->create($data);
                                }
                                if ($key == 'business') {
                                    $data = [
                                        'old_value' => '',
                                        'new_value' => $value,
                                        'order_id' => $order_id,
                                        'history_type' => OrderHistory::ORDER_HISTORY_NOTE_BUSINESS_TYPE,
                                        'user_id' => getCurrentUser()->id ?? 0,
                                        'date' => date('Y-m-d H:i:s'),
                                        'content' => 'đã thêm mới ghi chú nội bộ kinh doanh [ ' .  $value . ' ] '
                                    ];
                                    OrderHistory::query()->create($data);
                                }
                            }
                        } else {
                            if ($public == 0) {
                                if ($orderNoteItem->content != $value) {
                                    $this->createOrderHistory($orderNoteItem->content, $value, $order_id);
                                }
                            }
                            if ($public == OrderNoteConstant::INTERNAL_BUSINESS) {
                                if ($orderNoteItem->content != $value) {
                                    $this->createOrderHistoryBusiness($orderNoteItem->content, $value, $order_id);
                                }
                            }
                            if ($public == 2) {
                                $orderNoteItem->order_provisional_date = date('Y-m-d H:i:s', strtotime($value));
                            } else {
                                $orderNoteItem->content = $value;
                            }
//                            $orderNoteItem->subject_type = Staff::class;
                            // Nếu là note của khách thì k update k lại sai
                            if($orderNoteItem->subject_type == Customer::class){
                                continue;
                            }
                            $orderNoteItem->subject_id = getCurrentUser()->id ?? 0;
                            $orderNoteItem->update();
                        }
                    }
                }
            }
        }
    }

    private function createOrderHistory($old_value, $new_value, $order_id){
        $data = [
            'old_value' => $old_value,
            'new_value' => $new_value,
            'order_id' => $order_id,
            'history_type' => OrderHistory::ORDER_HISTORY_NOTE_TYPE,
            'user_id' => getCurrentUser()->id ?? 0,
            'date' => date('Y-m-d H:i:s'),
            'content' => 'đã thay đổi Ghi chú nội bộ từ [ ' .  $old_value . ' ] thành [ ' .  $new_value . ' ] '
        ];
        OrderHistory::query()->create($data);
    }

    private function createOrderHistoryBusiness($old_value, $new_value, $order_id){
        $data = [
            'old_value' => $old_value,
            'new_value' => $new_value,
            'order_id' => $order_id,
            'history_type' => OrderHistory::ORDER_HISTORY_NOTE_BUSINESS_TYPE,
            'user_id' => getCurrentUser()->id ?? 0,
            'date' => date('Y-m-d H:i:s'),
            'content' => 'đã thay đổi Ghi chú nội bộ kinh doanh từ [ ' .  $old_value . ' ] thành [ ' .  $new_value . ' ] '
        ];
        OrderHistory::query()->create($data);
    }
}
