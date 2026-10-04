<?php
/** Метрика: только после согласия, без персональных данных в e-commerce. */
defined('ABSPATH') || exit;
function ss_metrika_product($product,int $quantity=1): array {
    return ['id'=>(string)$product->get_id(),'name'=>$product->get_name(),'price'=>(float)$product->get_price(),'quantity'=>$quantity];
}
add_action('woocommerce_add_to_cart',static function($key,$id,$quantity,$variation_id){
    $product=wc_get_product($variation_id?:$id);
    if($product && WC()->session)WC()->session->set('ss_ecommerce_add',ss_metrika_product($product,(int)$quantity));
},10,4);
add_action('wp_enqueue_scripts',static function(){
    $id=ss_shop('metrika_id');if(!preg_match('/^[1-9][0-9]{0,11}$/',$id))return;
    $events=[];
    if(is_product()){$product=wc_get_product(get_queried_object_id());if($product)$events[]=['ecommerce'=>['currencyCode'=>'RUB','detail'=>['products'=>[ss_metrika_product($product)]]]];}
    if(WC()->session && ($added=WC()->session->get('ss_ecommerce_add'))){$events[]=['ecommerce'=>['currencyCode'=>'RUB','add'=>['products'=>[$added]]]];WC()->session->__unset('ss_ecommerce_add');}
    if(is_order_received_page()){
        $order=wc_get_order(absint(get_query_var('order-received')));
        $key=sanitize_text_field(wp_unslash($_GET['key']??''));
        if($order && $key && hash_equals($order->get_order_key(),$key) && !$order->has_status(['failed','cancelled'])){
            $products=[];foreach($order->get_items() as $item){$p=$item->get_product();if(!$p)continue;$row=ss_metrika_product($p,$item->get_quantity());$row['price']=(float)$item->get_total()/max(1,$item->get_quantity());$products[]=$row;}
            $events[]=['ecommerce'=>['currencyCode'=>$order->get_currency(),'purchase'=>['actionField'=>['id'=>(string)$order->get_id(),'revenue'=>(float)$order->get_total()],'products'=>$products]]];
        }
    }
    wp_enqueue_script('ss-analytics',plugins_url('assets/analytics.js',__FILE__),[],filemtime(__DIR__.'/assets/analytics.js'),['in_footer'=>true,'strategy'=>'defer']);
    wp_add_inline_script('ss-analytics','window.ssAnalytics='.wp_json_encode(['id'=>(int)$id,'events'=>$events,'privacy'=>home_url('/privacy-policy/')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
});
