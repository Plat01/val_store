<?php
/**
 * URL товара строится по основной категории (Rank Math → «Основная категория»):
 * /catalog/категория/подкатегория/товар/ (старые пути перенаправляет Rank Math).
 */

defined('ABSPATH') || exit;

add_filter('wc_product_post_type_link_product_cat', static function ($term, $terms, $post) {
    $primary = (int) get_post_meta($post->ID, 'rank_math_primary_product_cat', true);
    if ($primary) {
        foreach ($terms as $candidate) {
            if ((int) $candidate->term_id === $primary) {
                return $candidate;
            }
        }
    }
    return $term;
}, 20, 3);

// Категории и товары имеют общую базу catalog. Точные правила категорий
// ставим перед общим правилом товаров, включая пагинацию и вложенные ветки.
add_filter('rewrite_rules_array', static function (array $rules): array {
    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    if (is_wp_error($categories)) {
        return $rules;
    }
    $category_rules = [];
    foreach ($categories as $category) {
        $link = get_term_link($category);
        if (is_wp_error($link)) { continue; }
        $path = preg_quote(trim(wp_make_link_relative($link), '/'), '#');
        $query = 'index.php?product_cat=' . $category->slug;
        $category_rules[$path . '/?$'] = $query;
        $category_rules[$path . '/page/([0-9]+)/?$'] = $query . '&paged=$matches[1]';
        $category_rules[$path . '/feed/(feed|rdf|rss|rss2|atom)/?$'] = $query . '&feed=$matches[1]';
    }
    return $category_rules + $rules;
});

foreach (['created_product_cat', 'edited_product_cat', 'delete_product_cat'] as $hook) {
    add_action($hook, static function () { delete_option('rewrite_rules'); });
}

// Импортированные вложения могут иметь слаг старой категории. Явная карта
// переезда должна иметь приоритет над автоматическим редиректом вложения.
add_filter('rank_math/frontend/attachment/redirect_url', static function ($url) {
    $uri = trim(explode('?', \RankMath\Redirections\Redirection::get_full_uri())[0], '/');
    $mapped = \RankMath\Redirections\DB::match_redirections($uri);
    return $mapped ? $mapped['url_to'] : $url;
});
