<?php
/**
 * Импорт товаров, категорий, атрибутов и картинок из базы старого сайта.
 *
 *   ./scripts/import-old.sh            (загрузит дамп в БД `old` и запустит этот файл)
 *
 * Идемпотентен: сущности сопоставляются по мета `_ss_old_id`, повторный запуск обновляет их.
 * Переносится только каталог. Страницы, записи, меню, настройки старого сайта — нет.
 */

defined('ABSPATH') || exit;

const SS_OLD_PREFIX = 'wp_';

/** Переименование слагов атрибутов (опечатки старого сайта). */
const SS_ATTR_RENAME = [
    'lenght'   => 'length',
    'stlenght' => 'std_length',
];

/** Атрибут, который становится брендом WooCommerce (product_brand). */
const SS_BRAND_ATTR = 'brand';

// eval-file выполняет файл внутри функции — общие объекты кладём в $GLOBALS.
$GLOBALS['ss_log'] = ['warnings' => []];
function ss_log(string $msg): void { WP_CLI::log($msg); }
function ss_warn(string $msg): void { global $ss_log; $ss_log['warnings'][] = $msg; WP_CLI::warning($msg); }

$old = $GLOBALS['old'] = new wpdb(DB_USER, DB_PASSWORD, getenv('OLD_DB') ?: 'old', DB_HOST);
$old->set_prefix(SS_OLD_PREFIX);
$old->suppress_errors(false);
if (!$old->get_var("SELECT COUNT(*) FROM {$old->posts}")) {
    WP_CLI::error('База старого сайта пуста или недоступна.');
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

// Импорт без лишней работы: не слать письма и не пересчитывать на каждом шаге.
wp_defer_term_counting(true);
add_filter('woocommerce_email_enabled_new_order', '__return_false');

// ---------------------------------------------------------------------------
// Поиск ранее импортированных сущностей
// ---------------------------------------------------------------------------

function ss_find_post(int $old_id, string $post_type): int {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
         WHERE m.meta_key = '_ss_old_id' AND m.meta_value = %d AND p.post_type = %s LIMIT 1",
        $old_id, $post_type
    ));
}

function ss_find_term(int $old_id, string $taxonomy): int {
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids',
        'meta_query' => [['key' => '_ss_old_id', 'value' => $old_id]]]);
    return $terms ? (int) $terms[0] : 0;
}

// ---------------------------------------------------------------------------
// Очистка HTML описаний
// ---------------------------------------------------------------------------

function ss_clean_html(string $html, string $context_title = ''): string {
    if (trim($html) === '') {
        return '';
    }
    // Шорткоды конструкторов (WPBakery, WoodMart, слайдеры) — снимаем, содержимое оставляем.
    $html = preg_replace('~\[/?(vc_|woodmart_|rev_slider|html_block|social_buttons|products?\b|contact-form)[^\]]*\]~u', '', $html);
    // Ссылки старого домена → относительные; картинки из контента импортируем.
    $html = preg_replace_callback('~<img[^>]+src=["\']([^"\']+)["\'][^>]*>~iu', static function ($m) use ($context_title) {
        $url = ss_import_content_image($m[1], $context_title);
        return $url ? '<img src="' . esc_url($url) . '" alt="' . esc_attr($context_title) . '">' : '';
    }, $html);
    $html = preg_replace('~https?://(www\.)?cncmaster\.org(?=/)~iu', '', $html);
    // Один H1 на странице — у товара это название.
    $html = preg_replace('~<(/?)h1\b~iu', '<$1h2', $html);

    $allowed = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'sub' => [], 'sup' => [], 'blockquote' => [], 'hr' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'caption' => [],
        'tr' => [], 'th' => ['colspan' => true, 'rowspan' => true, 'scope' => true],
        'td' => ['colspan' => true, 'rowspan' => true],
        'a'  => ['href' => true, 'title' => true],
        'img' => ['src' => true, 'alt' => true],
        'figure' => [], 'figcaption' => [],
    ];
    $html = wp_kses($html, $allowed);
    // Таблица без заголовка: первая строка становится <thead> с <th>.
    $html = preg_replace_callback('~<table>\s*(?:<tbody>)?\s*(<tr>.*?</tr>)~su', static function ($m) {
        if (str_contains($m[0], '<th')) {
            return $m[0];
        }
        $head = preg_replace(['~<td\b~u', '~</td>~u'], ['<th scope="col"', '</th>'], $m[1]);
        return '<table><thead>' . $head . '</thead><tbody>';
    }, $html);
    // Переносы внутри ячеек — <br>, чтобы wpautop не плодил абзацы.
    $html = preg_replace_callback('~<(td|th)(\s[^>]*)?>(.*?)</\1>~su', static fn ($m) => '<' . $m[1] . $m[2] . '>' . preg_replace('~\s*\n\s*\n?\s*~u', '<br>', trim($m[3])) . '</' . $m[1] . '>', $html);
    $html = str_replace(['&nbsp;', "\xc2\xa0"], ' ', $html);
    // Пустые абзацы/ячейки-разделители и лишние пробелы.
    $html = preg_replace('~<p>\s*</p>~u', '', $html);
    $html = preg_replace('~<(strong|b|em|span)>\s*</\1>~u', '', $html);
    $html = preg_replace("~[ \t]+~u", ' ', $html);
    $html = preg_replace("~\n{3,}~u", "\n\n", $html);
    return trim($html);
}

// ---------------------------------------------------------------------------
// Картинки
// ---------------------------------------------------------------------------

/** Импорт вложения старого сайта. $name_hint — слаг товара/категории для имени файла. */
function ss_import_attachment(int $old_att_id, string $name_hint, string $alt_hint): int {
    global $old;
    if (!$old_att_id) {
        return 0;
    }
    if ($existing = ss_find_post($old_att_id, 'attachment')) {
        return $existing;
    }
    $file = $old->get_var($old->prepare("SELECT meta_value FROM {$old->postmeta} WHERE post_id = %d AND meta_key = '_wp_attached_file'", $old_att_id));
    if (!$file) {
        ss_warn("Вложение #$old_att_id: нет записи о файле");
        return 0;
    }
    $alt = (string) $old->get_var($old->prepare("SELECT meta_value FROM {$old->postmeta} WHERE post_id = %d AND meta_key = '_wp_attachment_image_alt'", $old_att_id));
    $new_id = ss_sideload('/old-files/' . basename($file), $name_hint, $alt !== '' ? $alt : $alt_hint);
    if ($new_id) {
        update_post_meta($new_id, '_ss_old_id', $old_att_id);
    }
    return $new_id;
}

/** Копирует локальный файл в медиатеку под SEO-именем. */
function ss_sideload(string $path, string $name_hint, string $alt): int {
    if (!is_readable($path)) {
        ss_warn('Нет файла ' . basename($path));
        return 0;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $base = sanitize_title($name_hint) ?: 'image';
    $tmp = wp_tempnam($base);
    copy($path, $tmp);
    $id = media_handle_sideload(['name' => "$base.$ext", 'tmp_name' => $tmp], 0, $alt);
    if (is_wp_error($id)) {
        @unlink($tmp);
        ss_warn('Не загрузился ' . basename($path) . ': ' . $id->get_error_message());
        return 0;
    }
    update_post_meta($id, '_wp_attachment_image_alt', $alt);
    update_post_meta($id, '_ss_old_file', basename($path));
    return (int) $id;
}

/** Картинка, вставленная в текст описания старого сайта. */
function ss_import_content_image(string $url, string $title): string {
    global $wpdb;
    $basename = basename(parse_url($url, PHP_URL_PATH) ?: '');
    // Убираем суффикс размера (-300x200), чтобы взять оригинал.
    $original = preg_replace('~-\d+x\d+(?=\.\w+$)~', '', $basename);
    $id = (int) $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ss_old_file' AND meta_value = %s LIMIT 1", $original));
    if (!$id) {
        $src = is_readable("/old-files/$original") ? "/old-files/$original" : "/old-files/$basename";
        $id = ss_sideload($src, $title, $title);
    }
    return $id ? (string) wp_get_attachment_url($id) : '';
}

// ---------------------------------------------------------------------------
// 1. Категории
// ---------------------------------------------------------------------------

ss_log('— Категории');
$old_cats = $old->get_results("SELECT t.term_id, t.name, t.slug, tt.parent, tt.description, tt.count
    FROM {$old->terms} t JOIN {$old->term_taxonomy} tt USING (term_id)
    WHERE tt.taxonomy = 'product_cat'", OBJECT_K);

// Количество опубликованных товаров в ветке — пустые ветки не переносим.
$direct = [];
foreach ($old->get_results("SELECT tt.term_id, COUNT(DISTINCT p.ID) n FROM {$old->term_relationships} r
    JOIN {$old->term_taxonomy} tt USING (term_taxonomy_id) JOIN {$old->posts} p ON p.ID = r.object_id
    WHERE tt.taxonomy = 'product_cat' AND p.post_type = 'product' AND p.post_status = 'publish'
    GROUP BY tt.term_id") as $row) {
    $direct[(int) $row->term_id] = (int) $row->n;
}
$branch_count = static function (int $id) use (&$branch_count, $old_cats, $direct): int {
    $n = $direct[$id] ?? 0;
    foreach ($old_cats as $c) {
        if ((int) $c->parent === $id) {
            $n += $branch_count((int) $c->term_id);
        }
    }
    return $n;
};

$cat_map = [];
$old_cat_meta = static function (int $term_id, string $key) use ($old) {
    return $old->get_var($old->prepare("SELECT meta_value FROM {$old->termmeta} WHERE term_id = %d AND meta_key = %s", $term_id, $key));
};
$import_cat = static function (object $c) use (&$import_cat, &$cat_map, $old_cats, $branch_count, $old_cat_meta) {
    $id = (int) $c->term_id;
    if (isset($cat_map[$id])) {
        return $cat_map[$id];
    }
    if ($branch_count($id) === 0) {
        ss_log("  пропуск пустой категории: {$c->name}");
        return $cat_map[$id] = 0;
    }
    $parent = $c->parent && isset($old_cats[$c->parent]) ? $import_cat($old_cats[$c->parent]) : 0;
    $args = [
        'slug'        => $c->slug,
        'parent'      => $parent,
        'description' => ss_clean_html((string) $c->description, $c->name),
    ];
    $new_id = ss_find_term($id, 'product_cat');
    if ($new_id) {
        wp_update_term($new_id, 'product_cat', $args + ['name' => $c->name]);
    } else {
        $res = wp_insert_term($c->name, 'product_cat', $args);
        if (is_wp_error($res)) {
            ss_warn("Категория {$c->name}: " . $res->get_error_message());
            return $cat_map[$id] = 0;
        }
        $new_id = (int) $res['term_id'];
        update_term_meta($new_id, '_ss_old_id', $id);
    }
    update_term_meta($new_id, 'order', (int) $old_cat_meta($id, 'order'));
    $thumb = ss_import_attachment((int) $old_cat_meta($id, 'thumbnail_id'), $c->slug, $c->name);
    if ($thumb) {
        update_term_meta($new_id, 'thumbnail_id', $thumb);
    }
    return $cat_map[$id] = $new_id;
};
foreach ($old_cats as $c) {
    $import_cat($c);
}
ss_log('  перенесено: ' . count(array_filter($cat_map)));

// ---------------------------------------------------------------------------
// 2. Глобальные атрибуты и их значения
// ---------------------------------------------------------------------------

ss_log('— Атрибуты');
$attr_map = []; // old slug (без pa_) => new slug (без pa_)
foreach ($old->get_results("SELECT * FROM {$old->prefix}woocommerce_attribute_taxonomies") as $a) {
    $old_slug = $a->attribute_name;
    if ($old_slug === SS_BRAND_ATTR) {
        continue;
    }
    $new_slug = SS_ATTR_RENAME[$old_slug] ?? $old_slug;
    $label = rtrim($a->attribute_label, '* ');
    $attr_map[$old_slug] = $new_slug;
    $existing = wc_attribute_taxonomy_id_by_name($new_slug);
    $args = ['name' => $label, 'slug' => $new_slug, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false];
    $existing ? wc_update_attribute($existing, $args) : wc_create_attribute($args);
    $tax = 'pa_' . $new_slug;
    if (!taxonomy_exists($tax)) {
        register_taxonomy($tax, ['product'], ['hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false]);
    }
    $terms = $old->get_results($old->prepare("SELECT t.term_id, t.name, t.slug FROM {$old->terms} t
        JOIN {$old->term_taxonomy} tt USING (term_id) WHERE tt.taxonomy = %s", 'pa_' . $old_slug));
    foreach ($terms as $t) {
        if (!term_exists($t->slug, $tax)) {
            wp_insert_term($t->name, $tax, ['slug' => $t->slug]);
        }
        $nt = get_term_by('slug', $t->slug, $tax);
        $order = $old->get_var($old->prepare("SELECT meta_value FROM {$old->termmeta} WHERE term_id = %d AND meta_key = %s", $t->term_id, 'order_pa_' . $old_slug));
        if ($nt && $order !== null) {
            update_term_meta($nt->term_id, 'order', (int) $order);
        }
    }
}
delete_transient('wc_attribute_taxonomies');
ss_log('  перенесено: ' . count($attr_map));

// ---------------------------------------------------------------------------
// 3. Товары
// ---------------------------------------------------------------------------

ss_log('— Товары');
$products = $old->get_results("SELECT * FROM {$old->posts} WHERE post_type = 'product' AND post_status IN ('publish', 'pending') ORDER BY ID");
$product_map = [];
$stats = ['simple' => 0, 'variable' => 0, 'variations' => 0, 'images' => 0];

$meta_of = static function (int $post_id) use ($old): array {
    $rows = $old->get_results($old->prepare("SELECT meta_key, meta_value FROM {$old->postmeta} WHERE post_id = %d", $post_id));
    $out = [];
    foreach ($rows as $r) {
        $out[$r->meta_key] = $r->meta_value;
    }
    return $out;
};
$terms_of = static function (int $post_id, string $taxonomy) use ($old): array {
    return $old->get_results($old->prepare("SELECT t.term_id, t.slug, t.name FROM {$old->terms} t
        JOIN {$old->term_taxonomy} tt USING (term_id) JOIN {$old->term_relationships} r USING (term_taxonomy_id)
        WHERE r.object_id = %d AND tt.taxonomy = %s ORDER BY t.term_id", $post_id, $taxonomy));
};
$set_sku = static function (WC_Product $p, string $sku, string $where) {
    if ($sku === '') {
        return;
    }
    try {
        $p->set_sku($sku);
    } catch (WC_Data_Exception $e) {
        ss_warn("$where: артикул «{$sku}» не уникален — пропущен");
    }
};
$num = static fn ($v) => ($v === null || $v === '') ? '' : wc_format_decimal($v);

foreach ($products as $op) {
    $m = $meta_of((int) $op->ID);
    $type_terms = $terms_of((int) $op->ID, 'product_type');
    $type = $type_terms ? $type_terms[0]->slug : 'simple';
    if (!in_array($type, ['simple', 'variable'], true)) {
        ss_warn("Товар «{$op->post_title}»: тип $type не поддержан, импорт как simple");
        $type = 'simple';
    }

    $new_id = ss_find_post((int) $op->ID, 'product');
    $product = $type === 'variable' ? new WC_Product_Variable($new_id) : new WC_Product_Simple($new_id);
    if ($new_id && $product->get_type() !== $type) {
        wp_set_object_terms($new_id, $type, 'product_type');
        $product = wc_get_product_object($type, $new_id);
    }

    $product->set_name($op->post_title);
    $product->set_slug($op->post_name);
    $product->set_status($op->post_status === 'publish' ? 'publish' : 'draft');
    $product->set_menu_order((int) $op->menu_order);
    $product->set_date_created($op->post_date_gmt !== '0000-00-00 00:00:00' ? strtotime($op->post_date_gmt) : null);
    $product->set_reviews_allowed(true);

    $description = ss_clean_html($op->post_content, $op->post_title);
    $tab = trim((string) ($m['_woodmart_product_custom_tab_content'] ?? ''));
    if ($tab !== '') {
        $tab_title = trim((string) ($m['_woodmart_product_custom_tab_title'] ?? '')) ?: 'Дополнительно';
        $description .= "\n\n<h2>" . esc_html($tab_title) . "</h2>\n" . ss_clean_html($tab, $op->post_title);
    }
    $product->set_description($description);
    $product->set_short_description(ss_clean_html($op->post_excerpt, $op->post_title));

    if ($type === 'simple') {
        $set_sku($product, (string) ($m['_sku'] ?? ''), "Товар «{$op->post_title}»");
        $product->set_regular_price($num($m['_regular_price'] ?? ''));
        $product->set_sale_price($num($m['_sale_price'] ?? ''));
    } else {
        $set_sku($product, (string) ($m['_sku'] ?? ''), "Товар «{$op->post_title}»");
    }
    $product->set_manage_stock(($m['_manage_stock'] ?? 'no') === 'yes');
    if ($product->get_manage_stock()) {
        $product->set_stock_quantity((int) ($m['_stock'] ?? 0));
    }
    $product->set_stock_status($m['_stock_status'] ?? 'instock');
    $product->set_backorders($m['_backorders'] ?? 'no');
    foreach (['weight', 'length', 'width', 'height'] as $dim) {
        $product->{"set_$dim"}($num($m["_$dim"] ?? ''));
    }

    // Категории и основная категория (определяет URL).
    $cats = array_values(array_filter(array_map(static fn ($t) => $cat_map[(int) $t->term_id] ?? 0, $terms_of((int) $op->ID, 'product_cat'))));
    $product->set_category_ids($cats);

    // Картинки.
    $hint = $op->post_name;
    $thumb = ss_import_attachment((int) ($m['_thumbnail_id'] ?? 0), $hint, $op->post_title);
    $product->set_image_id($thumb);
    $gallery = [];
    foreach (array_filter(array_map('intval', explode(',', (string) ($m['_product_image_gallery'] ?? '')))) as $i => $gid) {
        if ($g = ss_import_attachment($gid, $hint . '-' . ($i + 2), $op->post_title)) {
            $gallery[] = $g;
        }
    }
    $product->set_gallery_image_ids($gallery);
    $stats['images'] += ($thumb ? 1 : 0) + count($gallery);

    // Атрибуты.
    $old_attrs = maybe_unserialize($m['_product_attributes'] ?? '') ?: [];
    $attributes = [];
    $brand_names = [];
    $local_keys = []; // ключ атрибута в старой базе => ключ в новой (для вариаций)
    $position = 0;
    foreach ($old_attrs as $key => $a) {
        $wc_attr = new WC_Product_Attribute();
        if (!empty($a['is_taxonomy'])) {
            $old_slug = substr($a['name'], 3);
            $values = $terms_of((int) $op->ID, $a['name']);
            if ($old_slug === SS_BRAND_ATTR) {
                $brand_names = wp_list_pluck($values, 'name');
                continue;
            }
            if (!isset($attr_map[$old_slug])) {
                ss_warn("Товар «{$op->post_title}»: неизвестный атрибут {$a['name']}");
                continue;
            }
            $tax = 'pa_' . $attr_map[$old_slug];
            $ids = [];
            foreach ($values as $v) {
                $t = get_term_by('slug', $v->slug, $tax);
                if ($t) {
                    $ids[] = (int) $t->term_id;
                }
            }
            if (!$ids) {
                continue;
            }
            $wc_attr->set_id(wc_attribute_taxonomy_id_by_name($tax));
            $wc_attr->set_name($tax);
            $wc_attr->set_options($ids);
        } else {
            $options = array_values(array_filter(array_map('trim', explode(WC_DELIMITER, (string) $a['value'])), 'strlen'));
            if (!$options) {
                continue;
            }
            $wc_attr->set_name($a['name']);
            $wc_attr->set_options($options);
            $local_keys[(string) $key] = sanitize_title($a['name']);
        }
        $wc_attr->set_position($position++);
        $wc_attr->set_visible(!empty($a['is_visible']));
        $wc_attr->set_variation($type === 'variable' && !empty($a['is_variation']));
        $attributes[] = $wc_attr;
    }
    $product->set_attributes($attributes);

    // Значения вариаций по умолчанию.
    if ($type === 'variable') {
        $defaults = [];
        foreach ((array) maybe_unserialize($m['_default_attributes'] ?? '') as $k => $v) {
            $k = str_starts_with($k, 'pa_') ? 'pa_' . ($attr_map[substr($k, 3)] ?? substr($k, 3)) : $k;
            $defaults[$k] = $v;
        }
        $product->set_default_attributes($defaults);
    }

    $new_id = $product->save();
    update_post_meta($new_id, '_ss_old_id', (int) $op->ID);
    wp_set_object_terms($new_id, $brand_names, 'product_brand');
    $primary_old = (int) ($m['_yoast_wpseo_primary_product_cat'] ?? 0);
    if ($primary_old && !empty($cat_map[$primary_old]) && in_array($cat_map[$primary_old], $cats, true)) {
        update_post_meta($new_id, 'rank_math_primary_product_cat', $cat_map[$primary_old]);
    } else {
        delete_post_meta($new_id, 'rank_math_primary_product_cat');
    }
    $unit = trim((string) ($m['_woo_uom_input'] ?? ''));
    $unit = ['за метр' => 'м', 'метр' => 'м'][$unit] ?? $unit;
    $unit !== '' ? update_post_meta($new_id, '_ss_unit', $unit) : delete_post_meta($new_id, '_ss_unit');

    $product_map[(int) $op->ID] = $new_id;
    $stats[$type]++;

    // --- Вариации ---
    if ($type !== 'variable') {
        continue;
    }
    $seen = [];
    $variations = $old->get_results($old->prepare("SELECT * FROM {$old->posts} WHERE post_type = 'product_variation' AND post_parent = %d AND post_status = 'publish' ORDER BY menu_order, ID", $op->ID));
    foreach ($variations as $ov) {
        $vm = $meta_of((int) $ov->ID);
        $vid = ss_find_post((int) $ov->ID, 'product_variation');
        $var = new WC_Product_Variation($vid);
        $var->set_parent_id($new_id);
        $var->set_menu_order((int) $ov->menu_order);
        $var->set_status('publish');
        $vattrs = [];
        foreach ($vm as $k => $v) {
            if (!str_starts_with($k, 'attribute_')) {
                continue;
            }
            $name = substr($k, 10);
            if (str_starts_with($name, 'pa_')) {
                $name = 'pa_' . ($attr_map[substr($name, 3)] ?? substr($name, 3));
            } elseif (isset($local_keys[$name])) {
                $name = $local_keys[$name];
            } else {
                ss_warn("Вариация {$ov->post_title}: атрибут «{$name}» не найден у товара");
            }
            $vattrs[$name] = $v;
        }
        $var->set_attributes($vattrs);
        $set_sku($var, (string) ($vm['_sku'] ?? ''), "Вариация {$ov->post_title}");
        $var->set_regular_price($num($vm['_regular_price'] ?? ''));
        $var->set_sale_price($num($vm['_sale_price'] ?? ''));
        $var->set_manage_stock(($vm['_manage_stock'] ?? 'no') === 'yes');
        if ($var->get_manage_stock()) {
            $var->set_stock_quantity((int) ($vm['_stock'] ?? 0));
        }
        $var->set_stock_status($vm['_stock_status'] ?? 'instock');
        $var->set_description(ss_clean_html((string) ($vm['_variation_description'] ?? '')));
        foreach (['weight', 'length', 'width', 'height'] as $dim) {
            $var->{"set_$dim"}($num($vm["_$dim"] ?? ''));
        }
        if (!empty($vm['_thumbnail_id'])) {
            $var->set_image_id(ss_import_attachment((int) $vm['_thumbnail_id'], $op->post_name . '-' . sanitize_title(implode('-', $vattrs)), $op->post_title));
        }
        $saved = $var->save();
        update_post_meta($saved, '_ss_old_id', (int) $ov->ID);
        $seen[] = $saved;
        $stats['variations']++;
    }
    // Вариации, исчезнувшие в новом дампе, удаляем.
    foreach (wc_get_product($new_id)->get_children() as $child) {
        if (!in_array($child, $seen, true)) {
            wp_delete_post($child, true);
        }
    }
    WC_Product_Variable::sync($new_id);
}

// ---------------------------------------------------------------------------
// 4. Связанные товары (апсейлы/кросс-сейлы) — после того, как все товары созданы
// ---------------------------------------------------------------------------

foreach ($product_map as $old_id => $new_id) {
    $m = $meta_of($old_id);
    $map_ids = static fn ($raw) => array_values(array_filter(array_map(static fn ($id) => $product_map[(int) $id] ?? 0, (array) maybe_unserialize($raw ?: ''))));
    $p = wc_get_product($new_id);
    $p->set_upsell_ids($map_ids($m['_upsell_ids'] ?? ''));
    $p->set_cross_sell_ids($map_ids($m['_crosssell_ids'] ?? ''));
    $p->save();
}

// Товары, которых больше нет в старой базе, — в корзину.
$imported = get_posts(['post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_ss_old_id']);
foreach (array_diff($imported, $product_map) as $gone) {
    wp_trash_post($gone);
    ss_log("  в корзину (нет в старой базе): #$gone");
}

wp_defer_term_counting(false);
wc_delete_product_transients();
wc_update_product_lookup_tables();
flush_rewrite_rules(false);

// ---------------------------------------------------------------------------
// Отчёт и карта URL
// ---------------------------------------------------------------------------

$csv = fopen('/data/url-map.csv', 'w');
fputcsv($csv, ['type', 'old_id', 'new_id', 'path']);
foreach ($cat_map as $o => $n) {
    if ($n) {
        fputcsv($csv, ['category', $o, $n, wp_make_link_relative(get_term_link($n, 'product_cat'))]);
    }
}
foreach ($product_map as $o => $n) {
    if (get_post_status($n) === 'publish') {
        fputcsv($csv, ['product', $o, $n, wp_make_link_relative(get_permalink($n))]);
    }
}
fclose($csv);

ss_log(sprintf('Итого: простых %d, вариативных %d, вариаций %d, картинок товаров %d, категорий %d, предупреждений %d.',
    $stats['simple'], $stats['variable'], $stats['variations'], $stats['images'], count(array_filter($cat_map)), count($GLOBALS['ss_log']['warnings'])));
ss_log('Карта URL: data/url-map.csv');
