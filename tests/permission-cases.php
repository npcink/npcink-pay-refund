<?php

function npcink_permission_cases()
{
    return array(
        array('Zfb', 'npcink_pay_refund_zfb_order_query'),
        array('Zfb', 'npcink_pay_refund_zfb_order_refund'),
        array('Wx', 'npcink_pay_refund_wx_order_query'),
        array('Wx', 'npcink_pay_refund_wx_order_refund'),
        array('Wx', 'npcink_pay_refund_wx_refund_status'),
        array('Query', 'ajax_records'),
        array('Query', 'ajax_operational'),
        array('Config', 'npcink_pay_refund_download_data', '您没有权限下载退款记录。'),
        array('Config', 'search_refund_users', '您没有权限搜索退款专员。'),
        array('Config', 'check_payment_config', '您没有权限检测支付配置。'),
    );
}
