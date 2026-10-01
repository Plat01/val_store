<?php
/**
 * URL товара строится по основной категории (Rank Math → «Основная категория»):
 * /категория/подкатегория/товар/ — как на старом сайте (там основную задавал Yoast).
 * Premmerce Permalink Manager без Yoast берёт категорию из этого фильтра WooCommerce.
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
