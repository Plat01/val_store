<?php
/**
 * «Данные магазина» — единое хранилище контактов, реквизитов и интеграций.
 * Шапка, подвал, юр. страницы, schema.org, письма берут данные отсюда: ss_shop('phone').
 * Страница: Настройки → Данные магазина.
 */

defined('ABSPATH') || exit;

const SS_SHOP_OPTION = 'ss_shop';

/** Поля: ключ => [подпись, группа, тип]. */
function ss_shop_fields(): array {
    return [
        'brand'          => ['Название магазина', 'Контакты', 'text'],
        'phone'          => ['Телефон', 'Контакты', 'text'],
        'phone_2'        => ['Телефон 2', 'Контакты', 'text'],
        'email'          => ['Email для клиентов', 'Контакты', 'email'],
        'city'           => ['Город', 'Контакты', 'text'],
        'pickup_address' => ['Адрес самовывоза', 'Контакты', 'text'],
        'pickup_details' => ['Как найти / пояснение к самовывозу', 'Контакты', 'textarea'],
        'hours'          => ['Часы работы (по строкам: «Пн–Пт: 10:00–19:00»)', 'Контакты', 'textarea'],
        'map_embed'      => ['Карта (ссылка на конструктор Яндекс.Карт)', 'Контакты', 'url'],

        'telegram'       => ['Telegram (ссылка t.me/…)', 'Мессенджеры', 'url'],
        'whatsapp'       => ['WhatsApp (ссылка wa.me/…)', 'Мессенджеры', 'url'],
        'max'            => ['MAX (ссылка)', 'Мессенджеры', 'url'],
        'vk'             => ['ВКонтакте (ссылка)', 'Мессенджеры', 'url'],

        'legal_name'     => ['Юр. название (ИП Иванов И. И. / ООО «…»)', 'Реквизиты', 'text'],
        'inn'            => ['ИНН', 'Реквизиты', 'text'],
        'ogrn'           => ['ОГРН / ОГРНИП', 'Реквизиты', 'text'],
        'legal_address'  => ['Юридический адрес', 'Реквизиты', 'text'],

        'order_emails'   => ['Куда слать уведомления о заказах (через запятую)', 'Уведомления', 'text'],

        'metrika_id'     => ['Номер счётчика Яндекс.Метрики', 'Аналитика', 'text'],
        'webmaster_meta' => ['Код подтверждения Яндекс.Вебмастера (content)', 'Аналитика', 'text'],
    ];
}

function ss_shop(string $key, string $default = ''): string {
    $data = get_option(SS_SHOP_OPTION, []);
    $value = is_array($data) && isset($data[$key]) ? trim((string) $data[$key]) : '';
    return $value !== '' ? $value : $default;
}

/** Телефон для href="tel:…". */
function ss_shop_tel(string $key = 'phone'): string {
    return preg_replace('/[^\d+]/', '', ss_shop($key));
}

/** Список адресов уведомлений о заказах. */
function ss_shop_order_emails(): array {
    $list = array_map('trim', explode(',', ss_shop('order_emails')));
    return array_values(array_filter($list, 'is_email'));
}

/** Пока данные не заполнены пользователем (стоят заглушки из setup.sh). */
function ss_shop_is_placeholder(): bool {
    return (bool) get_option('ss_shop_placeholders');
}

// --- Админка --------------------------------------------------------------

add_action('admin_menu', static function () {
    add_options_page('Данные магазина', 'Данные магазина', 'manage_options', 'ss-shop', 'ss_shop_render_page');
});

add_action('admin_init', static function () {
    register_setting('ss_shop', SS_SHOP_OPTION, [
        'type'              => 'array',
        'sanitize_callback' => 'ss_shop_sanitize',
    ]);
});

function ss_shop_sanitize($input): array {
    $out = [];
    foreach (ss_shop_fields() as $key => [, , $type]) {
        $raw = is_array($input) && isset($input[$key]) ? wp_unslash($input[$key]) : '';
        $out[$key] = match ($type) {
            'email'    => sanitize_email($raw),
            'url'      => esc_url_raw($raw),
            'textarea' => sanitize_textarea_field($raw),
            default    => sanitize_text_field($raw),
        };
    }
    delete_option('ss_shop_placeholders');
    return $out;
}

function ss_shop_render_page(): void {
    $groups = [];
    foreach (ss_shop_fields() as $key => [$label, $group, $type]) {
        $groups[$group][$key] = [$label, $type];
    }
    echo '<div class="wrap"><h1>Данные магазина</h1>';
    echo '<p>Используются в шапке, подвале, на страницах «Контакты», «Доставка», в юридических текстах, микроразметке и письмах.</p>';
    echo '<form method="post" action="options.php">';
    settings_fields('ss_shop');
    foreach ($groups as $group => $fields) {
        echo '<h2>' . esc_html($group) . '</h2><table class="form-table" role="presentation">';
        foreach ($fields as $key => [$label, $type]) {
            $name  = SS_SHOP_OPTION . '[' . $key . ']';
            $value = ss_shop($key);
            echo '<tr><th scope="row"><label for="ss-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
            if ($type === 'textarea') {
                printf('<textarea id="ss-%s" name="%s" rows="3" class="large-text">%s</textarea>', esc_attr($key), esc_attr($name), esc_textarea($value));
            } else {
                printf('<input id="ss-%s" name="%s" type="%s" value="%s" class="regular-text">', esc_attr($key), esc_attr($name), $type === 'email' ? 'email' : 'text', esc_attr($value));
            }
            echo '</td></tr>';
        }
        echo '</table>';
    }
    submit_button();
    echo '</form></div>';
}

add_action('admin_notices', static function () {
    if (!ss_shop_is_placeholder() || !current_user_can('manage_options')) {
        return;
    }
    printf(
        '<div class="notice notice-warning"><p>На сайте стоят <strong>заглушки</strong> контактов и реквизитов. Заполните <a href="%s">Настройки → Данные магазина</a>.</p></div>',
        esc_url(admin_url('options-general.php?page=ss-shop'))
    );
});

// --- Связь с WooCommerce ---------------------------------------------------

// Уведомления о новых/отменённых/неудачных заказах — на адреса из «Данных магазина».
foreach (['new_order', 'cancelled_order', 'failed_order'] as $ss_email_id) {
    add_filter("woocommerce_email_recipient_{$ss_email_id}", static function ($recipient) {
        $emails = ss_shop_order_emails();
        return $emails ? implode(',', $emails) : $recipient;
    });
}
unset($ss_email_id);

// Пункт самовывоза (блочное оформление заказа) синхронизируется с адресом из «Данных магазина».
add_action('update_option_' . SS_SHOP_OPTION, 'ss_shop_sync_pickup');
add_action('add_option_' . SS_SHOP_OPTION, 'ss_shop_sync_pickup');

function ss_shop_sync_pickup(): void {
    update_option('pickup_location_pickup_locations', [[
        'name'    => ss_shop('pickup_address', 'Пункт выдачи'),
        'address' => [
            'address_1' => ss_shop('pickup_address'),
            'city'      => ss_shop('city'),
            'state'     => '',
            'postcode'  => '',
            'country'   => 'RU',
        ],
        'details' => trim(ss_shop('pickup_details') . "\n" . ss_shop('hours')),
        'enabled' => true,
    ]]);
}
