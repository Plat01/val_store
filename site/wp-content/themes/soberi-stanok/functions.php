<?php
/** Тема варианта A. Динамические секции сохраняют данные WooCommerce и магазина. */
defined('ABSPATH') || exit;
require_once __DIR__ . '/inc/views.php';
add_action('after_setup_theme', static function () {
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    add_theme_support('editor-styles');
    add_editor_style('assets/store.css');
});
add_action('wp_enqueue_scripts', static function () {
    wp_enqueue_style('ss-store', get_theme_file_uri('assets/store.css'), [], filemtime(__DIR__ . '/assets/store.css'));
    wp_enqueue_script('ss-store', get_theme_file_uri('assets/store.js'), [], filemtime(__DIR__ . '/assets/store.js'), true);
});
add_action('init', static function () {
    wp_register_script('ss-view-editor', get_theme_file_uri('assets/editor.js'), ['wp-blocks','wp-element','wp-server-side-render','wp-block-editor','wp-components'], filemtime(__DIR__ . '/assets/editor.js'), true);
    register_block_type('ss/view', [
        'api_version' => 3,
        'attributes' => ['view' => ['type' => 'string', 'default' => 'benefits']],
        'render_callback' => static function ($attrs) { return ss_theme_view($attrs['view'] ?? 'benefits'); },
        'editor_script' => 'ss-view-editor',
    ]);
    register_block_pattern_category('soberi-stanok', ['label' => 'Собери станок']);
});
add_action('wp_head', static function () {
    $base = get_theme_file_uri('assets/');
    echo '<link rel="preload" href="' . esc_url($base . 'fonts/manrope-variable.woff2') . '" as="font" type="font/woff2" crossorigin>';
    echo '<link rel="icon" type="image/svg+xml" href="' . esc_url($base . 'images/soberi-stanok-mark-ss.svg') . '">';
    echo '<link rel="apple-touch-icon" href="' . esc_url($base . 'images/apple-touch-icon.png') . '">';
});
add_filter('woocommerce_placeholder_img_src', static fn() => get_theme_file_uri('assets/images/soberi-stanok-mark-ss.svg'));
add_filter('rank_math/opengraph/facebook/image', static fn($image) => $image ?: get_theme_file_uri('assets/images/og-default.png'));
add_filter('rank_math/opengraph/twitter/image', static fn($image) => $image ?: get_theme_file_uri('assets/images/og-default.png'));
// Короткие характеристики в карточке, без изменения импортированных данных.
add_action('woocommerce_after_shop_loop_item_title', static function () {
    global $product;
    if (!$product) return;
    echo '<dl class="ss-card-specs">';
    $count = 0;
    foreach ($product->get_attributes() as $attribute) {
        if (!$attribute->get_visible()) continue;
        $value = $product->get_attribute($attribute->get_name());
        if (!$value) continue;
        printf('<div><dt>%s</dt><dd>%s</dd></div>', esc_html(wc_attribute_label($attribute->get_name())), esc_html($value));
        if (++$count >= 2) break;
    }
    echo '</dl>';
}, 8);
add_filter('woocommerce_product_related_products_heading', static fn() => 'С этим покупают');
add_filter('woocommerce_breadcrumb_defaults', static function ($args) {
    $args['delimiter'] = '<span aria-hidden="true"> / </span>';
    $args['home'] = 'Главная';
    return $args;
});
// Описание категории находится под товарами.
remove_action('woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10);
add_filter('loop_shop_per_page', static fn() => 12);
// В каталоге доступны только явно названные способы сортировки.
add_filter('woocommerce_catalog_orderby', static function (array $options): array {
    unset($options['menu_order']);
    return $options;
});
// Поиск точного артикула, включая артикул вариации (ведёт к её товару).
add_filter('posts_search', static function ($search,$query) {
    if(is_admin() || !$query->is_main_query() || !$query->is_search() || $query->get('post_type')!=='product') return $search;
    $id=wc_get_product_id_by_sku(sanitize_text_field($query->get('s')));
    if(!$id) return $search;
    $parent=wp_get_post_parent_id($id); if($parent) $id=$parent;
    global $wpdb;
    $original=preg_replace('/^\s*AND\s*/i','',$search);
    return $wpdb->prepare(" AND (({$original}) OR {$wpdb->posts}.ID = %d) AND {$wpdb->posts}.post_password = '' ",$id);
},20,2);
add_filter('woocommerce_add_to_cart_fragments', static function ($fragments) {
    $fragments['.ss-cart-count']='<b class="ss-cart-count">' . WC()->cart->get_cart_contents_count() . '</b>';
    return $fragments;
});
// Наши динамические секции вызывают PHP-хуки WooCommerce сами.
// Слой совместимости блочных шаблонов иначе удаляет штатные callbacks карточки.
add_filter('woocommerce_disable_compatibility_layer', static fn($disabled) => $disabled || is_product() || is_shop() || is_product_taxonomy());
add_action('wp_enqueue_scripts', static function () {
    if(!is_product()) return;
    wp_enqueue_script('wc-single-product');
    wp_enqueue_script('wc-zoom');
    wp_enqueue_script('wc-flexslider');
    wp_enqueue_script('wc-photoswipe-ui-default');
    wp_enqueue_style('photoswipe-default-skin');
},30);
add_action('wp_footer', static function () {if(is_product())wc_get_template('single-product/photoswipe.php');});
// Пустая верхняя граница означает отсутствие ограничения, а не цену 0.
add_action('init', static function () {
    foreach(['min_price','max_price'] as $key) if(isset($_GET[$key]) && $_GET[$key]==='') unset($_GET[$key]);
},5);
remove_action('woocommerce_before_shop_loop_item','woocommerce_template_loop_product_link_open',10);
add_action('woocommerce_before_shop_loop_item',static function(){global $product;if($product)printf('<a href="%s" class="woocommerce-LoopProduct-link woocommerce-loop-product__link" aria-label="%s">',esc_url($product->get_permalink()),esc_attr($product->get_name()));},10);
add_filter('woocommerce_redirect_single_search_result','__return_false');
