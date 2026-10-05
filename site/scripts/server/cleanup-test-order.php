<?php
/** WP-CLI: SS_TEST_ORDER=<id> wp eval-file cleanup-test-order.php */
$id=(int)getenv('SS_TEST_ORDER');
$order=$id ? wc_get_order($id) : false;
if (!$order || !preg_match('/^playwright-.*@example\.test$/',$order->get_billing_email())) {
    WP_CLI::error('Указанный заказ не является тестовым Playwright.');
}
wc_increase_stock_levels($id);
$order->delete(true);
WP_CLI::success('Удалён только тестовый заказ '.$id.'; остаток восстановлен.');
