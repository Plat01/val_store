# Настройки магазина (этап 2). Подключается из setup.sh, выполняется в контейнере wpcli.

# --- Заглушки «Данных магазина» (только при первом запуске) -----------------
if ! wp option get ss_shop >/dev/null 2>&1; then
  wp option add ss_shop '{
    "brand": "собери станок",
    "phone": "+7 (900) 000-00-00",
    "email": "info@example.test",
    "city": "Москва",
    "pickup_address": "ул. Примерная, д. 1",
    "pickup_details": "Заказ можно забрать после звонка менеджера.",
    "hours": "Пн–Пт: 10:00–19:00\nСб–Вс: выходной",
    "telegram": "https://t.me/example",
    "whatsapp": "https://wa.me/79000000000",
    "legal_name": "ИП Фамилия И. О.",
    "inn": "000000000000",
    "ogrn": "000000000000000",
    "legal_address": "г. Москва, ул. Примерная, д. 1",
    "order_emails": "orders@example.test"
  }' --format=json
  wp option update ss_shop_placeholders 1
fi

# --- Общие ------------------------------------------------------------------
wp option update woocommerce_default_country 'RU'
wp option update woocommerce_store_city "$(wp eval 'echo ss_shop("city");')"
wp option update woocommerce_allowed_countries 'specific'
wp option update woocommerce_specific_allowed_countries '["RU"]' --format=json
wp option update woocommerce_ship_to_countries ''
wp option update woocommerce_currency 'RUB'
wp option update woocommerce_currency_pos 'right_space'
wp option update woocommerce_price_thousand_sep ' '
wp option update woocommerce_price_decimal_sep ','
wp option update woocommerce_price_num_decimals 0
wp option update woocommerce_weight_unit 'kg'
wp option update woocommerce_dimension_unit 'cm'
wp option update woocommerce_calc_taxes 'no'
wp option update woocommerce_enable_coupons 'no'
wp option update woocommerce_enable_guest_checkout 'yes'
wp option update woocommerce_enable_checkout_login_reminder 'yes'
wp option update woocommerce_enable_signup_and_login_from_checkout 'no'
wp option update woocommerce_enable_myaccount_registration 'no'
wp option update woocommerce_manage_stock 'yes'
wp option update woocommerce_notify_no_stock_amount 0
wp option update woocommerce_hide_out_of_stock_items 'no'
wp option update woocommerce_enable_reviews 'yes'
wp option update woocommerce_review_rating_verification_label 'yes'
wp option update woocommerce_enable_review_rating 'yes'
wp option update comment_moderation 1

# Без онбординга, трекинга, маркетплейса и режима «скоро открытие».
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json >/dev/null 2>&1 || true
wp option update woocommerce_task_list_hidden 'yes' >/dev/null 2>&1 || true
wp option update woocommerce_extended_task_list_hidden 'yes' >/dev/null 2>&1 || true
wp option update woocommerce_show_marketplace_suggestions 'no' >/dev/null 2>&1 || true
wp option update woocommerce_allow_tracking 'no' >/dev/null 2>&1 || true
wp option update woocommerce_coming_soon 'no' >/dev/null 2>&1 || true

# --- Страницы магазина ------------------------------------------------------
wp wc tool run install_pages --user=1 >/dev/null
ss_page() { # ss_page <woo option> <slug> <title>
  id=$(wp option get "$1")
  wp post update "$id" --post_name="$2" --post_title="$3" >/dev/null
}
ss_page woocommerce_shop_page_id      catalog    'Каталог'
ss_page woocommerce_cart_page_id      cart       'Корзина'
ss_page woocommerce_checkout_page_id  checkout   'Оформление заказа'
ss_page woocommerce_myaccount_page_id my-account 'Личный кабинет'

# --- Оплата: только «при получении» -----------------------------------------
wp option update woocommerce_cod_settings '{
  "enabled": "yes",
  "title": "Оплата при получении",
  "description": "Наличными или картой при самовывозе.",
  "instructions": "Оплата наличными или картой при получении заказа.",
  "enable_for_methods": [],
  "enable_for_virtual": "no"
}' --format=json
for gw in bacs cheque; do
  wp option patch insert "woocommerce_${gw}_settings" enabled no >/dev/null 2>&1 \
    || wp option update "woocommerce_${gw}_settings" '{"enabled":"no"}' --format=json
done

# --- Доставка: только самовывоз ----------------------------------------------
wp option update woocommerce_pickup_location_settings '{
  "enabled": "yes",
  "title": "Самовывоз",
  "tax_status": "none",
  "cost": ""
}' --format=json
wp eval 'ss_shop_sync_pickup();'

# --- URL каталога: /catalog/категория/товар/ ---------------------------------
wp plugin deactivate woo-permalink-manager >/dev/null 2>&1 || true
wp option update woocommerce_permalinks '{
  "product_base": "/catalog/%product_cat%/",
  "category_base": "catalog",
  "tag_base": "product-tag",
  "attribute_base": "",
  "use_verbose_page_rules": false
}' --format=json
# Работа без облачного аккаунта Rank Math (штатный пропуск регистрации).
wp option update rank_math_registration_skip 1
wp option update rank_math_is_configured 1
# Модуль редиректов доступен бесплатно; импорт записывает в него старые адреса.
wp eval '\RankMath\Installer::create_tables(["redirections"]); $modules = get_option("rank_math_modules", []); $modules[] = "redirections"; update_option("rank_math_modules", array_values(array_unique($modules)));'

# --- Поля оформления (блочный checkout) --------------------------------------
wp option update woocommerce_checkout_phone_field 'required'
wp option update woocommerce_checkout_company_field 'hidden'
wp option update woocommerce_checkout_address_2_field 'hidden'
