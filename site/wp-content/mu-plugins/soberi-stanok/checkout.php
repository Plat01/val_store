<?php
/**
 * Оформление заказа под самовывоз: от покупателя нужны имя, телефон, email.
 * Адрес необязателен, регион/индекс/компания/«квартира» скрыты.
 * Когда появится доставка (СДЭК и т. п.), адресные поля вернуть в required.
 */

defined('ABSPATH') || exit;

add_filter('woocommerce_get_country_locale', static function (array $locale): array {
    $locale['RU'] = array_merge($locale['RU'] ?? [], [
        'state'     => ['required' => false, 'hidden' => true],
        'postcode'  => ['required' => false, 'hidden' => true],
        'city'      => ['required' => false],
        'address_1' => ['required' => false],
        'address_2' => ['required' => false, 'hidden' => true],
        'company'   => ['required' => false, 'hidden' => true],
    ]);
    return $locale;
});

// Классическое оформление (на случай шорткода) — то же самое.
add_filter('woocommerce_billing_fields', static function (array $fields): array {
    foreach (['billing_state', 'billing_postcode', 'billing_company', 'billing_address_2'] as $key) {
        unset($fields[$key]);
    }
    foreach (['billing_city', 'billing_address_1'] as $key) {
        if (isset($fields[$key])) {
            $fields[$key]['required'] = false;
        }
    }
    if (isset($fields['billing_phone'])) {
        $fields['billing_phone']['required'] = true;
    }
    return $fields;
});
