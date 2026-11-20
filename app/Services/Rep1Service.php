<?php


namespace App\Services;


use App\Constants\ConversationConstant;
use App\Constants\CustomerConstant;
use App\Constants\DeliveryConstant;
use App\Constants\TransactionConstant;
use App\Helpers\ConvertHelper;
use App\Helpers\PaginateHelper;
use App\Helpers\RandomHelper;
use App\Http\Resources\Rep\RepResource;
use App\Http\Resources\ListResource;
use App\Models\CustomerDelivery;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\Order;
use App\Models\OrderPackage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

use App\Constants\PackageConstant;


use App\Models\Chat;
use App\Models\Rep;
use App\Constants\RepConstant;
use GuzzleHttp\Client as Guzzle;

class Rep1Service extends BaseService
{
    protected string $_resource = RepResource::class;
    protected array $_relations = [];

    public function storeRep($params, $chatDB, $userOnline)
    {
        // Kết quả phản hồi
        $repAi = null;

        // Lưu lại tin nhắn khách gửi
        $repUser = $this->storeRepUser($params, $chatDB, $userOnline);
        $params['rep_id_new'] = (int) $repUser->id;

        // Check xem khách muốn làm gì
        $intent = $this->getIntent($chatDB, $params);

        // Nếu lỗi tiếng Trung
        if ($intent == 'ERROR_VN') {
            $repAi = $this->handerPro1($repUser, $chatDB, $userOnline);
        }

        // Nếu muốn tìm kiếm mã HS
        if ($intent == 'SEARCH' || $intent == 'FILTER') {
            $repAi = $this->handerPro2($repUser, $chatDB, $userOnline);
        }

        // Nếu muốn tìm 1 sản phẩm khác
        if ($intent == 'SEARCH_NEW') {
            $repAi = $this->handerPro3($repUser, $chatDB, $userOnline);
        }

        // Nếu muốn cấp thông tin của thuế
        if ($intent == 'CHECK_TAX') {
            $repAi = $this->handerPro4($repUser, $chatDB, $userOnline);
        }

        // Cập nhật tin nhắn cuối
        if ($repAi) {
            $chatDB->time_last_send    = date('Y-m-d H:i:s');
            $chatDB->content_last_send = $repAi->content;
            $chatDB->save();
        }

        return $repAi;
    }

    public function storeRepUser($params, $chatDB, $userOnline)
    {
        $data_rep = [
            'organization_id' => (int) $userOnline->organization_id,
            'chat_id'         => (int) $chatDB->id,
            'source'          => RepConstant::SOURCE_USER,
            'user_id'         => (int) $userOnline->id,
            'type'            => $params['type'],
            'content'         => $params['content'],
            'parent_id'       => $params['parent_id']
        ];
        $repUser = Rep::query()->create($data_rep);

        return $repUser;
    }

    public function normalizeText($text) {
        $text = mb_strtolower($text, 'UTF-8');
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        // chuẩn hóa dấu :
        $text = preg_replace('/\s*:\s*/', ':', $text);

        return trim($text);
    }

    public function isProductQuery($input) {
        $text = $this->normalizeText($input);
        $prefixes = [
            'ten hang',
            'toi muon tim',
            'toi can tim',
            'can tim',
            'tim cho toi',
            'tim san pham',
            'tim sp',
        ];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($text, $prefix)) {
                return true;
            }
        }
        return false;
    }

    public function getIntent($chatDB, $params)
    {
        // Nếu có parant_id > 0 thì là seach luôn
        if ($params['parent_id'] > 0) {
            return 'SEARCH';
        }

        // Check xem muốn tìm sản phẩm khác không
        $resetNew = $this->isProductQuery($params['content']);

        // Nếu gửi sản phẩm thì nó là seach
        if ($params['type'] == RepConstant::TYPE_ITEM || $resetNew == true) {
            $chatDB->rep_id_new = $params['rep_id_new'];
            $chatDB->summary  = json_encode([], JSON_UNESCAPED_UNICODE);
            $chatDB->options  = json_encode([], JSON_UNESCAPED_UNICODE);
            $chatDB->items    = json_encode([], JSON_UNESCAPED_UNICODE);
            $chatDB->items_cache    = json_encode([], JSON_UNESCAPED_UNICODE);
            $chatDB->item_ids = json_encode([], JSON_UNESCAPED_UNICODE);
            $chatDB->save();

            return 'SEARCH';
        }

        // Nếu muốn tìm sản phẩm khác
        $user_message = $params['content'];
        $pattern = "/khác|nữa|đổi|thay|sang|muốn hỏi|cần hỏi|muốn tìm|cần tìm|tôi tìm|tôi cần|tôi muốn|cho tôi/i";

        if (preg_match($pattern, $user_message)) {
            return 'SEARCH_NEW';
            // // Kết thục phải là 'mới, khác, lại thì la seach new. Còn không là nó dang tìm mới'
            // $keywords = ['mới','khác','lại'];
            // $text     = trim(preg_replace('/[.,!?;:]+$/u', '', $user_message));
            // $words    = preg_split('/\s+/u', $text);
            // $lastWord = mb_strtolower(end($words));
            // if (in_array($lastWord, $keywords)){
            //     return 'SEARCH_NEW';
            // }
        }

        $historyItems = $this->getHistoryItems($chatDB, 6);
        $guzzle = new Guzzle;
        $options = [
            'json' => [
                'arr_rep_old'    => $historyItems,
            ],
            'verify' => false
        ];
        $result = $guzzle->request('POST', $this->getUrlAi() . '/api/v1/m2-get-intent-m070326', $options)->getBody()->getContents();
        $result = json_decode($result, true);

        return $result['data'];
    }

    public function getHistoryItems($chatDB, $count = 10)
    {
        $arrType = [
            RepConstant::SOURCE_AI,
            RepConstant::SOURCE_USER,
            RepConstant::TYPE_ITEM,
            RepConstant::TYPE_OPTION,
        ];
        $repDBs = Rep::query()->where('chat_id', $chatDB->id)
                                ->where('id', '>=', $chatDB->rep_id_new)
                                ->whereIn('type', $arrType)
                                ->where('clear', RepConstant::CLEAR_INACTIVE)
                                ->orderBy('id', 'DESC')
                                ->whereNotNull('content')
                                ->limit($count)
                                ->offset(0)
                                ->get();
        $historyItems = [];
        if (count($repDBs) > 0) {
            foreach ($repDBs as $key => $messageDB) {
                $content = $messageDB->content;
                if ($messageDB->type == RepConstant::TYPE_ITEM) {
                    $arr_content = json_decode($content, true);
                    $content = $arr_content['name'];
                }
                if ($messageDB->type == RepConstant::TYPE_OPTION) {
                    $arr_content = json_decode($content, true);
                    $content = $arr_content['reply'];
                }
                $temp = [
                    "role"    => $messageDB->source == RepConstant::SOURCE_AI ? "assistant" : "user",
                    "content" => $content
                ];

                $historyItems[] = $temp;
            }
        }
        $historyItems = array_reverse($historyItems);
        return $historyItems;
    }

    // Nếu lỗi tiếng Trung
    public function handerPro1($repUser, $chatDB, $userOnline)
    {
        $repUser->clear = RepConstant::CLEAR_ACTIVE;
        $repUser->save();

        $data_rep = [
            'organization_id' => (int) $userOnline->organization_id,
            'chat_id'         => (int) $chatDB->id,
            'source'          => RepConstant::SOURCE_AI,
            'type'            => RepConstant::TYPE_TEXT,
            'content'         => 'Hãy cung cấp thông tin sản phẩm bằng tiếng việt',
            'parent_id'       => 0,
            'clear'           => RepConstant::CLEAR_ACTIVE,
        ];

        $repAi = Rep::query()->create($data_rep);

        return $repAi;
    }

    // Nếu muốn tìm kiếm mã HS
    public function handerPro2($repUser, $chatDB, $userOnline)
    {
        $historyItems = $this->getHistoryItems($chatDB, 10);
        $dataSum      = $this->updateSummary($repUser, $chatDB, $userOnline);

        if (count($dataSum['summary'])) {
            // Nếu không tìm được items nào
            if (count($dataSum['items']) ==  0) {
                return $this->handerPro2Case0($repUser, $chatDB, $userOnline);
            }

            // Nếu tìm được 1 bản ghi
            if (count($dataSum['items']) ==  1) {
                return $this->handerPro2Case1($repUser, $chatDB, $userOnline, $dataSum);
            }

            // Nếu tìm được nhiều hơn 1 nhưng các bản ghi giống nhau
            if (count($dataSum['items']) >  1 && $this->checkSameName($dataSum) == true) {
                return $this->handerPro2Case1($repUser, $chatDB, $userOnline, $dataSum);
            }
        }

        // Nếu nhiều hơn 2 thì lọc tiếp
        $guzzle = new Guzzle;
        $options = [
            'json' => [
                'arr_rep_old' => $historyItems,
                'summary'     => $dataSum['summary'],
                'items'       => $dataSum['items'],
            ],
            'verify' => false
        ];
        $result = $guzzle->request('POST', $this->getUrlAi() . '/api/v1/m2-get-hs-code-m070326', $options)->getBody()->getContents();
        $result = json_decode($result, true);

        $response = $result['data'];

        // Validate
        $response['options'] = $this->changeOption($response);

        // Lưu thông tin vào chat
        $chatDB->summary  = json_encode($response['summary'], JSON_UNESCAPED_UNICODE);
        $chatDB->items    = json_encode($response['items'], JSON_UNESCAPED_UNICODE);
        $chatDB->items_cache    = json_encode($response['items'], JSON_UNESCAPED_UNICODE);
        $chatDB->item_ids = json_encode($response['item_ids'], JSON_UNESCAPED_UNICODE);
        $chatDB->options  = json_encode($response['options'], JSON_UNESCAPED_UNICODE);

        $arr_summary = json_decode($chatDB->summary, true);
        if ($response['attribute_name']) {
            $arr_summary[$response['attribute_name']] = '';
            $chatDB->attribute_name = $response['attribute_name'];
        }
        $chatDB->summary = json_encode($arr_summary, JSON_UNESCAPED_UNICODE);
        $chatDB->save();

        // Tạo tin nhắn AI
        $repAi = $this->storeRepAi2($response, $chatDB, $userOnline);
        return $repAi;
    }

    public function changeOption($response)
    {
        $keywords = $response['options'];
        // dump($keywords);
        $data = $response['items'];

        // Chuyển toàn bộ 'name' trong data thành một chuỗi lớn để tìm kiếm nhanh hơn (tùy chọn)
        $allNames = implode(" ", array_column($data, 'name'));
        // dump($allNames);

        // Lọc mảng keywords
        $existingKeywords = array_filter($keywords, function($brand) use ($allNames) {
            // Kiểm tra xem brand này có xuất hiện trong chuỗi tổng hợp không
            return stripos($allNames, $brand) !== false;
        });

        $existingKeywords = array_values($existingKeywords);


        // Sửa lại cả keywork cho đúng data
        $existingKeywords = array_filter($keywords, function($brand) use ($allNames, &$result) {
            if (preg_match('/' . preg_quote($brand, '/') . '/i', $allNames, $matches)) {
                $result[] = $matches[0]; // lấy đúng chữ trong data
                return true;
            }
            return false;
        });

        if ($result == null) {
            $result = [];
        }

        $existingKeywords = array_values($result);

        // dd($existingKeywords);
        return $existingKeywords;
    }

    public function storeRepAi2($response, $chatDB, $userOnline)
    {
        // Mặc định
        $action  = RepConstant::ACTION_QUESTION;
        $type    = RepConstant::TYPE_OPTION;
        $content = [
            'reply'          => $response['reply'],
            'attribute_name' => $response['attribute_name'],
            'options'        => $response['options'],
            'option_select'  => '',
        ];
        $content = json_encode($content, JSON_UNESCAPED_UNICODE);


        // Nếu tìm được nhiều hơn 1 nhưng các bản ghi giống nhau
        $sameSame = false;
        if (count($response['items']) >  1 && $this->checkSameName($response) == true) {
            $sameSame = true;
        }

        // Nếu còn 1 đáp án hoặc AI bảo đúng luôn rồi
        if (count($response['item_ids']) == 1 ||
                (
                    $response['is_final'] == true &&
                    count($response['item_ids']) > 1
                ) ||

                count($response['items']) == 1 ||
                $sameSame
            )
        {
            $action   = RepConstant::ACTION_DEFAULT;
            $type     = RepConstant::TYPE_TEXT;
            $arr_item = $response['items'][0];
            $content  = "Mã HS tìm dc là: " . $arr_item['code_hs'] . "\n Tên sản phẩm là: " . $arr_item['name'];

            // Tìm thuế nếu có
            $taxQuote = $this->searchTaxInQuote($arr_item['code_hs'], $arr_item['name']);
            if (count($taxQuote) > 0) {
                $content .= "\n - Thuế VAT: <strong>" . $taxQuote['vat'] . '</strong>';
                if ($taxQuote['tax_more']) {
                    $content .= "\n - Ngoài ra phải đóng thêm các loại thuế: <strong>" . $taxQuote['tax_more'] . '</strong>';
                }
                if ($taxQuote['policy'] == null) {
                    $content .= "\n - Chính sách: <strong>Không có</strong>" ;
                } else {
                    $content .= "\n - Chính sách: <strong>" . $taxQuote['policy'] . '</strong>';
                }
                $content .= "\n - " . $taxQuote['bao_ho'];
            }

        }

        if (count($response['items']) == 0) {
            $action  = RepConstant::ACTION_DEFAULT;
            $type    = RepConstant::TYPE_TEXT;
            $content = $response['reply'];
        }

        $data_rep = [
            'organization_id' => (int) $userOnline->organization_id,
            'chat_id'         => (int) $chatDB->id,
            'source'          => RepConstant::SOURCE_AI,
            'type'            => $type,
            'action'          => $action,
            'content'         => $content,
            'parent_id'       => 0,
        ];
        $repAi = Rep::query()->create($data_rep);

        return $repAi;
    }

    public function handerPro2Case0($repUser, $chatDB, $userOnline)
    {
        $repUser->clear = RepConstant::CLEAR_ACTIVE;
        $repUser->save();

        // Lưu lại data mới vào hội thoại
        // $chatDB->summary  = json_encode([], JSON_UNESCAPED_UNICODE);
        // $chatDB->items    = json_encode([], JSON_UNESCAPED_UNICODE);
        // $chatDB->item_ids = json_encode([], JSON_UNESCAPED_UNICODE);
        // $chatDB->options  = json_encode([], JSON_UNESCAPED_UNICODE);
        // $chatDB->save();

        $data_rep = [
            'organization_id' => (int) $userOnline->organization_id,
            'chat_id'         => (int) $chatDB->id,
            'source'          => RepConstant::SOURCE_AI,
            'type'            => RepConstant::TYPE_TEXT,
            'content'         => 'Không tìm được hàng hóa nào thỏa mãn.',
            'parent_id'       => 0,
            'clear'           => RepConstant::CLEAR_ACTIVE,
        ];

        $repAi = Rep::query()->create($data_rep);

        return $repAi;
    }

    public function handerPro2Case1($repUser, $chatDB, $userOnline, $dataSum)
    {
        // Lưu lại data mới vào hội thoại
        $chatDB->summary  = json_encode($dataSum['summary'], JSON_UNESCAPED_UNICODE);
        $chatDB->items    = json_encode($dataSum['items'], JSON_UNESCAPED_UNICODE);
        $chatDB->item_ids = json_encode([$dataSum['items'][0]['id']], JSON_UNESCAPED_UNICODE);
        $chatDB->options  = json_encode([], JSON_UNESCAPED_UNICODE);
        $chatDB->save();

        $arr_item = $dataSum['items'][0];
        $content = "Mã HS tìm dc là: " . $arr_item['code_hs'] . "\n - Tên sản phẩm là: " . $arr_item['name'] ;

        // Tìm thuế nếu có
        $taxQuote = $this->searchTaxInQuote($arr_item['code_hs'], $arr_item['name']);
        if (count($taxQuote) > 0) {
            $content .= "\n - Thuế VAT: <strong>" . $taxQuote['vat'] . '</strong>';
            if ($taxQuote['tax_more']) {
                $content .= "\n - Ngoài ra phải đóng thêm các loại thuế: <strong>" . $taxQuote['tax_more'] . '</strong>';
            }
            if ($taxQuote['policy'] == null) {
                $content .= "\n - Chính sách: <strong>Không có</strong>" ;
            } else {
                $content .= "\n - Chính sách: <strong>" . $taxQuote['policy'] . '</strong>';
            }
            $content .= "\n - " . $taxQuote['bao_ho'];
        }

        $data_rep = [
            'organization_id' => (int) $userOnline->organization_id,
            'chat_id'         => (int) $chatDB->id,
            'source'          => RepConstant::SOURCE_AI,
            'type'            => RepConstant::TYPE_TEXT,
            'content'         => $content,
            'parent_id'       => 0,
        ];

        $repAi = Rep::query()->create($data_rep);

        return $repAi;
    }

    public function searchTaxInQuote($code_hs, $name)
    {
        $guzzle = new Guzzle;
        $url = env('URL_QUOTE') . 'tax-log/seach-tax-m280226';
        $options = [
            'json' => [
                'hs_code' => $code_hs,
                'name'    => $name,
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        $data_result = [];
        if ($result && $result['code'] == 200) {
            $data_result = $result['data'];
        }
        return $data_result;
    }

    public function checkSameName($dataSum)
    {
        $items = $dataSum['items'];
        if (empty($items)) {
            return true;
        }
        $firstName = $this->normalizeProductName($items[0]['name'] ?? '');
        foreach ($items as $item) {
            if ($this->normalizeProductName($item['name']) !== $firstName) {
                return false;
            }
        }
        return true;
        // Bỏ logic dưới

        $items = $dataSum['items'];

        $firstName = $items[0]['name'] ?? null;
        $allSame = true;
        foreach ($items as $item) {
            if ($item['name'] !== $firstName) {
                $allSame = false;
                break;
            }
        }

        return $allSame;
    }

    public function normalizeProductName($name)
    {
        $name = mb_strtolower($name, 'UTF-8');

        // bỏ dấu câu
        $name = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $name);

        // chuẩn hóa khoảng trắng
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    public function updateSummary($repUser, $chatDB, $userOnline)
    {
        $summary = json_decode($chatDB->summary, true);
        $items   = json_decode($chatDB->items, true);
        $options = json_decode($chatDB->options, true);
        $key_null = null;

        foreach ($summary as $key => $item) {
            if ($item == '') {
                $key_null = $key;
            }
        }

        if (!is_null($key_null)) {
            $repAILast = Rep::query()->where('chat_id', $chatDB->id)
                                    ->where('source', RepConstant::SOURCE_AI)
                                    ->where('clear', RepConstant::CLEAR_INACTIVE)
                                    ->orderBy('id', 'DESC')
                                    ->first();

            if ($repAILast && $repAILast->action == RepConstant::ACTION_QUESTION) {

                $items = $this->filterStrictMatch($items, $options, $repUser->content);
                if (count($items) > 0) {
                    $summary[$key_null] = $repUser->content;
                }
            }
        } else {
            // Nếu người dùng rep vào tin nhắn có thuộc tính trùng với thuộc tính cuối của Chat thì reset
            if ($repUser->parent_id > 0) {
                $repParentDB = Rep::query()->where('id', $repUser->parent_id)
                                        ->where('type', RepConstant::TYPE_OPTION)
                                        ->first();
                if ($repParentDB) {
                    $arr_rep = json_decode($repParentDB->content, true);
                    if ($arr_rep['attribute_name'] == $chatDB->attribute_name) {
                        $items   = json_decode($chatDB->items_cache, true);
                        $options = $arr_rep['options'];

                        $items = $this->filterStrictMatch($items, $options, $repUser->content);
                        if (count($items) > 0) {
                            $summary[$chatDB->attribute_name] = $repUser->content;
                        }
                    }
                }
            }
        }

        return [
            'summary' => $summary,
            'items'   => $items,
        ];
    }

    /**
     * @param array $items Danh sách sản phẩm đầu vào
     * @param array $options Danh sách các lựa chọn mà AI đã đưa ra (ví dụ: ["họa tiết kẻ sọc", "họa tiết kẻ sọc gắn khăn lau tay"])
     * @param string $userSelection Lựa chọn thực tế của người dùng (ví dụ: "họa tiết kẻ sọc")
     */
    public function filterStrictMatch($items, $options, $userSelection)
    {
        // 1. Xác định các options KHÔNG được chọn nhưng lại chứa từ khóa của option ĐƯỢC chọn
        // Ví dụ: Người dùng chọn "A", trong danh sách có "A" và "A + B". Ta tìm ra "A + B" để loại trừ.
        $excludedOptions = [];
        foreach ($options as $opt) {
            if ($opt !== $userSelection && mb_strpos($opt, $userSelection) !== false) {
                $excludedOptions[] = $opt;
            }
        }
        $excludedOptions = [];

        // 2. Lọc danh sách items
        $filteredItems = array_filter($items, function($item) use ($userSelection, $excludedOptions) {
            // Tên sản phẩm phải chứa giá trị người dùng chọn
            $containsSelection = mb_strpos($item['name'], $userSelection) !== false;

            // Tên sản phẩm KHÔNG ĐƯỢC chứa các option dài hơn mà người dùng không chọn
            $containsExcluded = false;
            foreach ($excludedOptions as $exOpt) {
                if (mb_strpos($item['name'], $exOpt) !== false) {
                    $containsExcluded = true;
                    break;
                }
            }

            return $containsSelection && !$containsExcluded;
        });

        return array_values($filteredItems);
    }


    public function handerPro3($repUser, $chatDB, $userOnline)
    {
        $data_rep = [
            'organization_id' => (int) $userOnline->organization_id,
            'chat_id'         => (int) $chatDB->id,
            'source'          => RepConstant::SOURCE_AI,
            'type'            => RepConstant::TYPE_TEXT,
            'content'         => 'Hãy cung cấp thông tin sản phẩm để tra cứu mã HS',
            'parent_id'       => 0,
        ];
        $repAi = Rep::query()->create($data_rep);

        $chatDB->rep_id_new = (int) $repAi->id;
        $chatDB->summary    = json_encode([], JSON_UNESCAPED_UNICODE);
        $chatDB->options    = json_encode([], JSON_UNESCAPED_UNICODE);
        $chatDB->items      = json_encode([], JSON_UNESCAPED_UNICODE);
        $chatDB->items_cache      = json_encode([], JSON_UNESCAPED_UNICODE);
        $chatDB->item_ids   = json_encode([], JSON_UNESCAPED_UNICODE);
        $chatDB->save();

        return $repAi;
    }

    public function handerPro4($repUser, $chatDB, $userOnline)
    {
        $historyItems = $this->getHistoryItems($chatDB, 6);
        $guzzle = new Guzzle;
        $options = [
            'json' => [
                'arr_rep_old' => $historyItems,
            ],
            'verify' => false
        ];
        $result = $guzzle->request('POST', $this->getUrlAi() . '/api/v1/m2-get-vat-m070326', $options)->getBody()->getContents();
        $result = json_decode($result, true);

        $data_rep = [
            'organization_id' => (int) $userOnline->organization_id,
            'chat_id'         => (int) $chatDB->id,
            'source'          => RepConstant::SOURCE_AI,
            'type'            => RepConstant::TYPE_TEXT,
            'content'         => $result['data']['message'],
            'parent_id'       => 0,
        ];
        $repAi = Rep::query()->create($data_rep);

        return $repAi;
    }

    public function getUrlAi()
    {
        $url = env('URL_AI');
        return $url;
    }

    public function makeToken()
    {
        return 'n4vhq1zst8mea6wbp2ry';
    }
}
