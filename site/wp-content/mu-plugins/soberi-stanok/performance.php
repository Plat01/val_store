<?php
/** Ресурсы подключаем только там, где они используются. */
defined('ABSPATH') || exit;
add_filter('wpcf7_load_js',static fn($load)=>is_page('contacts') && $load);
add_filter('wpcf7_load_css',static fn($load)=>is_page('contacts') && $load);
add_action('wp_enqueue_scripts',static function(){
    if(!is_product() && !is_cart() && !is_checkout() && !is_account_page()){
        foreach(['wc-cart-fragments','wc-add-to-cart','woocommerce','sourcebuster-js','wc-order-attribution'] as $handle)wp_dequeue_script($handle);
        if(!is_page('contacts')){ wp_dequeue_script('jquery'); wp_dequeue_script('jquery-migrate'); }
    }
},100);
add_action('init',static function(){remove_action('wp_head','print_emoji_detection_script',7);remove_action('wp_print_styles','print_emoji_styles');});
add_filter('wp_get_attachment_image_attributes',static function($attr,$attachment,$size){
    if($size==='woocommerce_thumbnail'){
        $attr['sizes']='(max-width: 600px) 45vw, (max-width: 960px) 30vw, 280px';
        $attr['loading']='lazy';unset($attr['fetchpriority']);
    }
    if(is_product() && $size==='woocommerce_single'){
        $attr['loading']='eager';$attr['fetchpriority']='high';
        $attr['sizes']='(max-width: 768px) 92vw, 600px';
    }
    return $attr;
},20,3);
