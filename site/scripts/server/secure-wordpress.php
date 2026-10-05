<?php
/** Первый перенос: никаких локальных паролей/сессий/тестовых заказов на VPS. */
$admins = get_users(['role' => 'administrator']);
if (count($admins) !== 1 || (int)$admins[0]->ID !== 1) {
    WP_CLI::error('Ожидался один локальный администратор ID=1. Требуется проверка пользователей.');
}
$login = getenv('WP_ADMIN_USER');
$password = getenv('WP_ADMIN_PASSWORD');
if (!$login || strlen($password) < 20 || $password === 'admin') WP_CLI::error('Нет надёжного пароля.');
global $wpdb;
$changes = ['user_login'=>$login, 'user_nicename'=>sanitize_title($login), 'display_name'=>'Администратор магазина'];
if (is_email($login)) {
    $changes['user_email']=$login;
    update_option('admin_email', $login);
}
$wpdb->update($wpdb->users, $changes, ['ID'=>1]);
clean_user_cache(1);
wp_set_password($password, 1);
foreach (get_users(['fields'=>'ID']) as $id) {
    WP_Session_Tokens::get_instance($id)->destroy_all();
    WP_Application_Passwords::delete_all_application_passwords($id);
}
// Только созданные локальным E2E заказы, реальные заказы не затрагиваются.
foreach (wc_get_orders(['limit'=>-1]) as $order) {
    if (preg_match('/^playwright-.*@example\.test$/', $order->get_billing_email())) $order->delete(true);
}
$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}woocommerce_sessions");
WP_CLI::success('Пароль администратора заменён; локальные сессии и тестовые заказы очищены.');
