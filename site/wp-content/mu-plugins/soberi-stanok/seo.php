<?php
/** SEO-фундамент; единственный источник meta и JSON-LD — Rank Math. */
defined('ABSPATH') || exit;
function ss_seo_private(): bool {
    if(is_search() || is_404() || is_cart() || is_checkout() || is_account_page())return true;
    foreach(array_keys($_GET) as $key) if(preg_match('/^(filter_|query_type_|min_price$|max_price$|orderby$|add-to-cart$)/',(string)$key))return true;
    return false;
}
add_filter('rank_math/frontend/robots', static function($robots){
    if(!get_option('blog_public'))return ['index'=>'noindex','follow'=>'nofollow'];
    if(ss_seo_private())return ['index'=>'noindex','follow'=>'follow'];
    return $robots;
},99);
add_filter('rank_math/frontend/canonical',static function($url){
    if(is_search() || is_404())return $url;
    return strtok($url,'?');
},99);
add_filter('robots_txt', static function($text,$public){
    if(!$public)return "User-agent: *\nDisallow: /\n";
    $text.="\nDisallow: /cart/\nDisallow: /checkout/\nDisallow: /my-account/\nDisallow: /*?s=\nDisallow: /*?*s=\n";
    $params=['utm_source','utm_medium','utm_campaign','utm_content','utm_term','yclid','orderby','min_price','max_price'];
    foreach(wc_get_attribute_taxonomies() as $attr){$params[]='filter_'.$attr->attribute_name;$params[]='query_type_'.$attr->attribute_name;}
    $text.="\nUser-agent: Yandex\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\nDisallow: /cart/\nDisallow: /checkout/\nDisallow: /my-account/\nDisallow: /*?s=\nDisallow: /*?*s=\nDisallow: /*add-to-cart=\nClean-param: ".implode('&',$params)." /\nSitemap: ".home_url('/sitemap_index.xml')."\n";
    return $text;
},99,2);
// shop — архив страницы, поэтому описание не всегда приходит из post meta.
add_filter('rank_math/frontend/description',static function($value){
    if(is_shop())return 'Каталог комплектующих для станков с ЧПУ: двигатели, электроника, механика, шпиндели и профиль. Сравните характеристики и закажите с оплатой при получении.';
    if(!$value && is_singular('page'))return wp_trim_words(wp_strip_all_tags(get_the_excerpt()),25,'');
    return $value;
});
function ss_seo_breadcrumbs(): array {
    $crumbs=(new WC_Breadcrumb())->generate();
    array_unshift($crumbs,['Главная',home_url('/')]);
    if(!$crumbs || is_front_page())return [];
    $items=[];
    foreach($crumbs as $i=>$crumb)$items[]=['@type'=>'ListItem','position'=>$i+1,'name'=>wp_strip_all_tags($crumb[0]),'item'=>$crumb[1] ?: strtok(home_url(wp_unslash($_SERVER['REQUEST_URI'])),'?')];
    return ['@type'=>'BreadcrumbList','@id'=>strtok(home_url(wp_unslash($_SERVER['REQUEST_URI'])),'?').'#breadcrumb','itemListElement'=>$items];
}
add_filter('rank_math/json_ld',static function($data){
    $org_id=home_url('/#organization');
    $org=['@type'=>'Organization','@id'=>$org_id,'name'=>ss_shop('brand','собери станок'),'url'=>home_url('/'),'logo'=>['@type'=>'ImageObject','url'=>get_theme_file_uri('assets/images/apple-touch-icon.png'),'width'=>180,'height'=>180]];
    if(!ss_shop_is_placeholder()){
        if(ss_shop('legal_name'))$org['legalName']=ss_shop('legal_name');
        if(ss_shop('phone'))$org['contactPoint']=['@type'=>'ContactPoint','telephone'=>ss_shop('phone'),'contactType'=>'customer service','availableLanguage'=>'Russian'];
        $org['sameAs']=array_values(array_filter(array_map('ss_shop',['telegram','whatsapp','max','vk'])));
        if(ss_shop('pickup_address'))$data['ss-store']=['@type'=>'Store','@id'=>home_url('/#pickup'),'name'=>ss_shop('brand','собери станок'),'url'=>home_url('/contacts/'),'parentOrganization'=>['@id'=>$org_id],'image'=>get_theme_file_uri('assets/images/apple-touch-icon.png'),'telephone'=>ss_shop('phone'),'address'=>['@type'=>'PostalAddress','addressCountry'=>'RU','addressLocality'=>ss_shop('city'),'streetAddress'=>ss_shop('pickup_address')],'description'=>trim(ss_shop('pickup_details').' '.ss_shop('hours'))];
    }
    // Заменяем стандартную Organization и BreadcrumbList, чтобы не дублировать сущности.
    foreach($data as $key=>&$node){
        if(($node['@type']??'')==='Organization')unset($data[$key]);
        if(($node['@type']??'')==='BreadcrumbList')unset($data[$key]);
        if(($node['@type']??'')==='Product'){
            $product=wc_get_product(get_queried_object_id());
            if($product)$node['name']=$product->get_name();
            if(isset($node['offers']['seller']))$node['offers']['seller']=['@id'=>$org_id];
            foreach($node['additionalProperty']??[] as $i=>$prop)$node['additionalProperty'][$i]['name']=wc_attribute_label($prop['name']);
        }
    }unset($node);
    $data['ss-organization']=$org;
    $crumb=ss_seo_breadcrumbs();if($crumb)$data['ss-breadcrumb']=$crumb;
    if(is_front_page())$data['ss-faq']=['@type'=>'FAQPage','@id'=>home_url('/#faq'),'mainEntity'=>array_map(static fn($q,$a)=>['@type'=>'Question','name'=>$q,'acceptedAnswer'=>['@type'=>'Answer','text'=>$a]],array_keys(ss_theme_faq_items()),array_values(ss_theme_faq_items()))];
    return $data;
},99);
add_action('wp_head',static function(){
    $code=ss_shop('webmaster_meta');if(preg_match('/^[a-f0-9]{16,64}$/i',$code))echo '<meta name="yandex-verification" content="'.esc_attr($code).'">';
});
// Новые загрузки/импорт: fallback alt берётся из названия изображения.
add_filter('wp_get_attachment_image_attributes',static function($attr,$attachment){
    if(empty($attr['alt']))$attr['alt']=get_the_title($attachment);
    return $attr;
},10,2);
