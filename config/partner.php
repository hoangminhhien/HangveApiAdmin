<?php

return [
    'buyder' => [
        'domain' => env('PARTNER_BUYDER_CLIENT_URL', 'https://api.buyder.com'),
        'endpoint' => env('PARTNER_BUYDER_CLIENT_URL', 'https://api.buyder.com'), //http://127.0.0.1:8003 https://api.buyder.com
        'tokenAuth' => env('BUYDER_SYNC_AUTH_TOKEN', 'g83wu4jdme67wiamq5fx1j1y7n6c1ek3dte3dpiottxc2k7dutvfz44lkr8ttnqyzdsab0xuxkd0oaa9w26gu0du068sh49y2hka'),
        'tokenAuth1688' => env('BUYDER_SYNC_AUTH_TOKEN_1688', 'we7a08c21zjpd08tqdae25w4p68fvpwx4vmenz7l08t8ye4il08mgtymmg3atusluh9j0c9v9eoy7ak2qv6q15vgmdrayme5g39l'),
        'userId' => env('BUYDER_USER_ID', '2100010404541'),
        'userId1688' => env('BUYDER_USER_ID_1688', '2218998220969'),
    ],
    'rapidApi' => [
        'endpoint' => env('PARTNER_RAPID_API_URL', 'https://taobao-api.p.rapidapi.com/api'),
        'tokenAuth' => env('X_RAPID_API_KEY', '2b08ecbb77msh70a5a3069f167edp1cdbe1jsnafd0a92da666'),
    ],
    'hangveClient' => [
        'endpoint' => env('PARTNER_HANGVE_CLIENT_URL', 'http://127.0.0.1:8001/api'),
        'loginPath' => env('PARTNER_HANGVE_CLIENT_LOGIN_URL', '/auth/sign-in'),
        'loginAccount' => env('PARTNER_HANGVE_CLIENT_LOGIN_ACCOUNT', 'maiquyn8898@gmail.com'),
        'loginPassword' => env('PARTNER_HANGVE_CLIENT_LOGIN_PASSWORD', '123456'),
        'searchPath' => env('PARTNER_HANGVE_CLIENT_SEARCH_URL', '/item/search'),
        'detailPath' => env('PARTNER_HANGVE_CLIENT_DETAIL_URL', '/item/detail/'),
        'uploadImagePath' => env('PARTNER_HANGVE_CLIENT_UPLOAD_IMAGE_URL', '/media/upload'),
    ],
    'buyder_taobao' => [
        'list-order' => env('PARTNER_BUYDER_TAOBAO_LIST_ORDER', '/open/taobao/list-order'),
        'pay-order' => env('PARTNER_BUYDER_TAOBAO_PAY_ORDER', '/open/taobao/pay-order'),
        'order_create_preview' => env('PARTNER_BUYDER_TAOBAO_PAY_ORDER', '/open/taobao/order-create-preview'),
        'order-detail' => env('PARTNER_BUYDER_TAOBAO_ORDER_DETAIL', '/open/taobao/order-detail'),
        'user_id' => env('PARTNER_BUYDER_TAOBAO_USER_ID', '2100010404541'),
        'item_search' => env('PARTNER_BUYDER_TAOBAO_SEARCH_URL', '/open/taobao/search-item'),
        'item_detail' => env('PARTNER_BUYDER_TAOBAO_DETAIL_URL', '/open/taobao/get-item'),
        'item_search_image' => env('PARTNER_BUYDER_TAOBAO_SEARCH_IMAGE_URL', '/open/taobao/query-image'),
    ],
    'buyder_1688' => [
        'user_id' => env('PARTNER_BUYDER_1688_USER_ID', '2218998220969'),
        'order_create_preview' => env('PARTNER_BUYDER_1688_ORDER_CREATE_PREVIEW_URL', '/open/1688/order-create-preview'),
        'order_create' => env('PARTNER_BUYDER_1688_ORDER_CREATE_URL', '/open/1688/order-create'),
        'order_cancel' => env('PARTNER_BUYDER_1688_ORDER_CANCEL_URL', '/open/1688/order-cancel'),
        'pay-order' => env('PARTNER_BUYDER_1688_PAY_ORDER', '/open/1688/order-pay'),
        'pay-orders' => env('PARTNER_BUYDER_1688_PAY_ORDERS', '/open/1688/order-pay-arr'),
        'order_detail' => env('PARTNER_BUYDER_1688_ORDER_DETAIL', '/open/1688/order-detail'),
        'connect_chat' => env('PARTNER_BUYDER_1688_CONNECT_CHAT_URL', '/open/1688/product-openuid-decrypt'),

        'item_search' => env('PARTNER_BUYDER_1688_SEARCH_URL', '/open/1688/query-item'),
        'item_detail' => env('PARTNER_BUYDER_1688_DETAIL_URL', '/open/1688/detail-item'),
        'item_search_image' => env('PARTNER_BUYDER_1688_SEARCH_IMAGE_URL', '/open/1688/query-image-item'),
    ],
    'fcmApi' => [
        'endpoint' => env('PARTNER_FCM_API_URL', 'https://exp.host/--/api/v2/'),
    ],
];
