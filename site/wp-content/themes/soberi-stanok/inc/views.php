<?php
defined('ABSPATH') || exit;

function ss_theme_icon(string $name): string {
    $paths = [
        'search' => '<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>',
        'cart' => '<path d="M3 4h3l2 11h11l2-8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>',
        'grid' => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z"/>',
    ];
    return '<svg class="ss-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($paths[$name] ?? $paths['grid']) . '</svg>';
}
function ss_theme_logo(bool $mono = false): string {
    return '<a class="ss-logo" href="' . esc_url(home_url('/')) . '"><img src="' . esc_url(get_theme_file_uri('assets/images/soberi-stanok-logo-' . ($mono ? 'mono-' : '') . 'ss.svg')) . '" width="212" height="40" alt="собери станок — главная"></a>';
}
function ss_theme_categories(): array {
    $terms = get_terms(['taxonomy'=>'product_cat','parent'=>0,'hide_empty'=>true,'exclude'=>[get_option('default_product_cat')]]);
    if(is_wp_error($terms)) return [];
    $order=['dvigateli','shpindeli-i-komplektuyuschie-dlya-nih','komplektuyuschie-dlya-chpu','profil-konstruktsionnyy','elektronika','oborudovanie-s-chpu','rezhuschiy-instrument','kabel-kanaly','programmnoe-obespechenie'];
    usort($terms,static fn($a,$b)=>(array_search($a->slug,$order,true)===false?99:array_search($a->slug,$order,true))<=>(array_search($b->slug,$order,true)===false?99:array_search($b->slug,$order,true)));
    return $terms;
}
function ss_theme_category_label($term): string {
    $names=['dvigateli'=>'Двигатели','shpindeli-i-komplektuyuschie-dlya-nih'=>'Шпиндели','komplektuyuschie-dlya-chpu'=>'Механика','profil-konstruktsionnyy'=>'Профиль','elektronika'=>'Электроника','oborudovanie-s-chpu'=>'Оборудование с ЧПУ','rezhuschiy-instrument'=>'Режущий инструмент','kabel-kanaly'=>'Кабель-каналы','programmnoe-obespechenie'=>'ПО'];
    return $names[$term->slug] ?? $term->name;
}
function ss_theme_messages(): void {
    foreach (['telegram'=>'Telegram','whatsapp'=>'WhatsApp','max'=>'MAX','vk'=>'ВКонтакте'] as $key=>$name) {
        $url = ss_shop($key);
        if ($url && !ss_shop_is_placeholder()) printf('<a href="%s" rel="noopener noreferrer" target="_blank">%s</a>', esc_url($url), esc_html($name));
    }
}
function ss_theme_search_form(): void {
    ?><form class="ss-search" role="search" action="<?php echo esc_url(home_url('/')); ?>" method="get">
        <label class="screen-reader-text" for="ss-search-input">Поиск по каталогу</label>
        <input id="ss-search-input" type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Название или артикул товара" required>
        <input type="hidden" name="post_type" value="product">
        <button type="submit" aria-label="Найти товар"><?php echo ss_theme_icon('search'); ?></button>
    </form><?php
}
function ss_theme_header(): void {
    ?><a class="ss-skip" href="#main">Перейти к содержимому</a>
    <div class="ss-utility"><div class="ss-wrap"><span>Самовывоз · <?php echo esc_html(ss_shop('city', 'Город уточняется')); ?></span><nav aria-label="Информация"><a href="<?php echo esc_url(home_url('/delivery/')); ?>">Доставка и оплата</a><a href="<?php echo esc_url(home_url('/about/')); ?>">О компании</a><a href="<?php echo esc_url(home_url('/contacts/')); ?>">Контакты</a><?php ss_theme_messages(); ?></nav></div></div>
    <header class="ss-header"><div class="ss-wrap">
        <?php echo ss_theme_logo(); ?>
        <details class="ss-catalog-menu"><summary class="ss-button ss-button-dark"><?php echo ss_theme_icon('grid'); ?>Каталог</summary><nav class="ss-mega" aria-label="Каталог товаров"><a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Все товары</a><?php foreach (ss_theme_categories() as $term) { ?><div><a href="<?php echo esc_url(get_term_link($term)); ?>"><strong><?php echo esc_html($term->name); ?></strong></a><?php $children=get_terms(['taxonomy'=>'product_cat','parent'=>$term->term_id,'hide_empty'=>true]); foreach (is_wp_error($children)?[]:$children as $child) printf('<a href="%s">%s</a>',esc_url(get_term_link($child)),esc_html($child->name)); ?></div><?php } ?></nav></details>
        <?php ss_theme_search_form(); ?>
        <div class="ss-header-contact"><?php if (!ss_shop_is_placeholder() && ss_shop_tel()) { ?><a href="tel:<?php echo esc_attr(ss_shop_tel()); ?>"><?php echo esc_html(ss_shop('phone')); ?></a><?php } else { ?><a href="<?php echo esc_url(home_url('/contacts/')); ?>">Контакты магазина</a><?php } ?><small><?php echo esc_html(ss_shop_is_placeholder() ? 'Часы уточняются' : strtok(ss_shop('hours'), "\n")); ?></small></div>
        <a class="ss-cart" href="<?php echo esc_url(wc_get_cart_url()); ?>"><?php echo ss_theme_icon('cart'); ?><span>Корзина</span><b class="ss-cart-count"><?php echo WC()->cart ? esc_html(WC()->cart->get_cart_contents_count()) : '0'; ?></b></a>
    </div></header>
    <nav class="ss-category-nav" aria-label="Разделы каталога"><div class="ss-wrap"><?php foreach (ss_theme_categories() as $term) printf('<a href="%s">%s</a>',esc_url(get_term_link($term)),esc_html(ss_theme_category_label($term))); ?></div></nav><?php
}
function ss_theme_footer(): void {
    ?><footer class="ss-footer"><div class="ss-wrap ss-footer-grid"><div><?php echo ss_theme_logo(true); ?><p>Комплектующие для тех, кто собирает оборудование с ЧПУ сам.</p></div><div><h2>Покупателям</h2><nav aria-label="Покупателям"><a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Каталог</a><a href="<?php echo esc_url(home_url('/delivery/')); ?>">Доставка и оплата</a><a href="<?php echo esc_url(home_url('/about/')); ?>">О компании</a></nav></div><div><h2>Контакты</h2><p><?php echo esc_html(ss_shop_is_placeholder()?'Контакты уточняются':ss_shop('phone')); ?></p><p><?php echo esc_html(ss_shop_is_placeholder()?'Адрес самовывоза уточняется':ss_shop('pickup_address')); ?></p><a href="<?php echo esc_url(home_url('/contacts/')); ?>">Как связаться</a><div class="ss-messages"><?php ss_theme_messages(); ?></div></div><div class="ss-footer-legal"><span><?php echo esc_html(ss_shop_is_placeholder()?'Реквизиты уточняются':ss_shop('legal_name') . ' · ИНН ' . ss_shop('inn')); ?></span><nav aria-label="Документы"><a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">Политика конфиденциальности</a><a href="<?php echo esc_url(home_url('/personal-data-consent/')); ?>">Согласие на обработку ПДн</a><a href="<?php echo esc_url(home_url('/offer/')); ?>">Оферта</a></nav></div></div></footer><?php
}
function ss_theme_category_image($term): string {
    $id=(int)get_term_meta($term->term_id,'thumbnail_id',true);
    if (!$id) {
        $products=wc_get_products(['category'=>[$term->slug],'limit'=>1,'status'=>'publish']);
        if ($products) $id=$products[0]->get_image_id();
    }
    return $id ? wp_get_attachment_image($id,'woocommerce_thumbnail',false,['alt'=>$term->name,'loading'=>'lazy']) : '<img src="' . esc_url(get_theme_file_uri('assets/images/soberi-stanok-mark-ss.svg')) . '" width="96" height="96" alt="' . esc_attr($term->name) . '">';
}
function ss_theme_hero(): void {
    $categories=ss_theme_categories();
    ?><section class="ss-hero ss-wrap"><div><p class="ss-eyebrow">Комплектующие для станков с ЧПУ</p><h1>Соберите станок своими руками</h1><p class="ss-lead">Двигатели, шпиндели, направляющие, профиль и электроника в одном каталоге. Выберите детали под свой проект.</p><div class="ss-actions"><a class="ss-button" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Перейти в каталог</a><a class="ss-button ss-button-outline" href="#assembly">Как собрать станок</a></div><div class="ss-facts"><div><b><?php echo esc_html(wp_count_posts('product')->publish); ?></b><span>товара в каталоге</span></div><div><b><?php echo count($categories); ?></b><span>основных разделов</span></div><div><b>0 ₽</b><span>самовывоз</span></div></div></div><div class="ss-modules"><?php
    // Шпиндель, двигатель и механика: та же композиция, что в выбранном A.
    $ordered=$categories; usort($ordered,static function($a,$b){$rank=static fn($t)=>str_contains($t->slug,'shpind')?0:(str_contains($t->slug,'dvigat')?1:(str_contains($t->slug,'komplektuyuschie-dlya-chpu')?2:3));return $rank($a)<=>$rank($b);});
    foreach(array_slice($ordered,0,3) as $i=>$term) { ?><a class="ss-module <?php echo $i===0?'ss-module-big':($i===1?'ss-module-accent':''); ?>" href="<?php echo esc_url(get_term_link($term)); ?>"><strong><?php echo esc_html($term->name); ?></strong><small><?php echo esc_html($term->count); ?> товаров</small><?php echo ss_theme_category_image($term); ?></a><?php } ?></div></section><?php
}
function ss_theme_category_grid(): void {
    ?><section class="ss-section ss-wrap"><div class="ss-section-heading"><h2>Каталог</h2><a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Все товары</a></div><div class="ss-categories"><?php foreach(ss_theme_categories() as $term) { ?><a class="ss-category" href="<?php echo esc_url(get_term_link($term)); ?>"><div><h3><?php echo esc_html($term->name); ?></h3><p><?php echo esc_html($term->count); ?> товаров</p><?php $children=get_terms(['taxonomy'=>'product_cat','parent'=>$term->term_id,'hide_empty'=>true,'number'=>3]); foreach(is_wp_error($children)?[]:$children as $child) echo '<small>' . esc_html($child->name) . '</small>'; ?></div><?php echo ss_theme_category_image($term); ?></a><?php } ?></div></section><?php
}
function ss_theme_featured(): void {
    ?><section class="ss-section ss-wrap woocommerce"><div class="ss-section-heading"><h2>Из каталога</h2><a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Смотреть все</a></div><?php echo do_shortcode('[products limit="8" columns="4" orderby="date" order="DESC"]'); ?></section><?php
}
function ss_theme_assembly(): void {
    $steps=[['Рама','Алюминиевый профиль, уголки и Т-гайки.'],['Механика','Направляющие, подшипники и винтовая или ременная передача.'],['Привод и шпиндель','Двигатели, шпиндель и совместимый частотный преобразователь.'],['Электроника','Драйверы, контроллер, блок питания и CAM-программа.']];
    ?><section class="ss-assembly" id="assembly"><div class="ss-section ss-wrap"><h2>Из чего собрать станок</h2><ol class="ss-steps"><?php foreach($steps as $i=>[$title,$text]) printf('<li><span>Шаг %d</span><h3>%s</h3><p>%s</p></li>',$i+1,esc_html($title),esc_html($text)); ?></ol><a href="<?php echo esc_url(home_url('/contacts/')); ?>">Обсудить подбор комплектующих</a></div></section><?php
}
function ss_theme_benefits(): void {
    ?><section class="ss-section ss-wrap"><h2>Покупка без лишних шагов</h2><div class="ss-benefits"><div><h3>Характеристики рядом</h3><p>Сравнивайте параметры и выбирайте подходящую модель.</p></div><div><h3>Оплата при получении</h3><p>Оформите заказ и оплатите его при самовывозе.</p></div><div><h3>Самовывоз бесплатно</h3><p>Дождитесь подтверждения готовности перед поездкой.</p></div></div></section><?php
}
function ss_theme_faq_items(): array {
    return [
        'Как подобрать совместимые комплектующие?' => 'Сверьте размеры и электрические параметры в характеристиках. Если нужна помощь, напишите через форму на странице контактов.',
        'Когда можно забрать заказ?' => 'После подтверждения готовности менеджером. Адрес и часы указаны на странице контактов.',
        'Какие способы оплаты доступны?' => 'На старте — оплата при получении. Доступный способ показан при оформлении заказа.',
    ];
}
function ss_theme_faq(): void {
    echo '<section id="faq" class="ss-section ss-wrap ss-faq"><h2>Частые вопросы</h2>';
    foreach(ss_theme_faq_items() as $question=>$answer)echo '<details><summary>'.esc_html($question).'</summary><p>'.esc_html($answer).'</p></details>';
    echo '</section>';
}

function ss_theme_filters(): void {
    // Нативные filter_* WooCommerce сохраняют совместимость с WC_Query.
    $term=is_product_category()?get_queried_object():null;
    $args=['post_type'=>'product','post_status'=>'publish','fields'=>'ids','posts_per_page'=>-1];
    if($term) $args['tax_query']=[['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$term->term_id,'include_children'=>true]];
    $ids=get_posts($args);
    ?><details class="ss-filters" <?php echo wp_is_mobile()?'':'open'; ?>><summary>Фильтры по характеристикам</summary><form method="get" action="<?php echo esc_url($term?get_term_link($term):wc_get_page_permalink('shop')); ?>"><div class="ss-filter-fields"><?php
    $count=0;
    $attributes=wc_get_attribute_taxonomies();
    $priority=['tiporazmer','tiporazmer-dvigatelya','diameter','power','moshhnost-kvt','material','voltage','tok-a','section','length','thread'];
    usort($attributes,static function($a,$b) use($priority){$rank=static fn($x)=>array_search($x->attribute_name,$priority,true);$ra=$rank($a);$rb=$rank($b);return ($ra===false?99:$ra)<=>($rb===false?99:$rb);});
    foreach($attributes as $attribute) {
        $taxonomy=wc_attribute_taxonomy_name($attribute->attribute_name);
        $values=$ids?wp_get_object_terms($ids,$taxonomy,['orderby'=>'name']):[];
        if(is_wp_error($values)||count($values)<2) continue;
        $key='filter_' . $attribute->attribute_name;
        $selected=isset($_GET[$key])?sanitize_text_field(wp_unslash($_GET[$key])):'';
        printf('<label>%s<select name="%s"><option value="">Все</option>',esc_html($attribute->attribute_label),esc_attr($key));
        foreach($values as $value) printf('<option value="%s" %s>%s</option>',esc_attr($value->slug),selected($selected,$value->slug,false),esc_html($value->name));
        echo '</select></label>';
        if(++$count>=6) break;
    }
    ?><label>Цена от, ₽<input type="number" min="0" name="min_price" value="<?php echo isset($_GET['min_price'])?esc_attr(wc_clean(wp_unslash($_GET['min_price']))):''; ?>"></label><label>Цена до, ₽<input type="number" min="0" name="max_price" value="<?php echo isset($_GET['max_price'])?esc_attr(wc_clean(wp_unslash($_GET['max_price']))):''; ?>"></label></div><?php if(isset($_GET['orderby'])) printf('<input type="hidden" name="orderby" value="%s">',esc_attr(wc_clean(wp_unslash($_GET['orderby'])))); ?><div class="ss-actions"><button class="ss-button" type="submit">Применить</button><a href="<?php echo esc_url($term?get_term_link($term):wc_get_page_permalink('shop')); ?>">Сбросить фильтры</a></div></form></details><?php
}
function ss_theme_catalog(): void {
    ?><main id="main" class="ss-section ss-wrap woocommerce ss-catalog"><?php woocommerce_breadcrumb(); echo '<h1 class="page-title">' . esc_html(woocommerce_page_title(false)) . '</h1>'; ss_theme_filters(); add_filter('woocommerce_show_page_title','__return_false'); woocommerce_content(); remove_filter('woocommerce_show_page_title','__return_false');
    if(is_product_category()&&!is_paged()) { $description=term_description(get_queried_object_id(),'product_cat'); if($description) echo '<section class="ss-category-description"><h2>О разделе</h2>' . wp_kses_post($description) . '</section>'; }
    ?></main><?php
}
function ss_theme_product(): void {
    ?><main id="main" class="ss-section ss-wrap woocommerce ss-product"><?php woocommerce_breadcrumb(); global $post, $product; $post=get_post(get_queried_object_id()); if($post && $post->post_type==='product') { WC_Frontend_Scripts::load_scripts(); setup_postdata($post); $product=wc_get_product($post->ID); wc_get_template_part('content','single-product'); wp_reset_postdata(); } ?></main><?php
}
function ss_theme_contacts(): void {
    ?><section class="ss-contacts"><div><h2>Связаться с магазином</h2><?php if(ss_shop_is_placeholder()) { ?><p>Контакты и адрес самовывоза уточняются перед запуском.</p><?php } else { ?><p><a href="tel:<?php echo esc_attr(ss_shop_tel()); ?>"><?php echo esc_html(ss_shop('phone')); ?></a></p><p><a href="mailto:<?php echo esc_attr(ss_shop('email')); ?>"><?php echo esc_html(ss_shop('email')); ?></a></p><p><?php echo esc_html(ss_shop('city') . ', ' . ss_shop('pickup_address')); ?></p><p><?php echo nl2br(esc_html(ss_shop('hours'))); ?></p><p><?php echo esc_html(ss_shop('pickup_details')); ?></p><?php } ?><div class="ss-messages"><?php ss_theme_messages(); ?></div><?php
    $url=ss_shop('map_embed'); $host=wp_parse_url($url,PHP_URL_HOST);
    if($url && in_array($host,['yandex.ru','yandex.com'],true) && str_starts_with((string)wp_parse_url($url,PHP_URL_PATH),'/map-widget/')) printf('<iframe src="%s" title="Карта самовывоза" width="600" height="360" loading="lazy" referrerpolicy="no-referrer"></iframe>',esc_url($url));
    else echo '<div class="ss-map-placeholder">Карта появится после уточнения адреса самовывоза.</div>';
    ?></div><div><h2>Задать вопрос</h2><?php $id=get_option('ss_contact_form_id'); if($id) echo do_shortcode('[contact-form-7 id="' . absint($id) . '"]'); ?></div></section><?php
}
function ss_theme_search(): void {
    ?><main id="main" class="ss-section ss-wrap woocommerce"><h1>Результаты поиска: <?php echo esc_html(get_search_query()); ?></h1><?php
    if(have_posts()) { woocommerce_product_loop_start(); while(have_posts()) {the_post(); if(get_post_type()==='product') wc_get_template_part('content','product');} woocommerce_product_loop_end(); the_posts_pagination(['prev_text'=>'Назад','next_text'=>'Далее']); }
    else { echo '<p>Товары не найдены. Попробуйте другое название или артикул.</p>'; ss_theme_search_form(); }
    ?></main><?php
}
function ss_theme_not_found(): void {
    ?><main id="main" class="ss-section ss-wrap"><h1>Страница не найдена</h1><p>Проверьте адрес или найдите нужную деталь в каталоге.</p><?php ss_theme_search_form(); ?><p><a class="ss-button" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Перейти в каталог</a></p></main><?php
}
function ss_theme_view(string $view): string {
    $views=['breadcrumbs','header','footer','hero','category_grid','featured','assembly','benefits','faq','catalog','product','contacts','search','not_found'];
    if(!in_array($view,$views,true)) return '';
    ob_start(); call_user_func('ss_theme_' . $view); return ob_get_clean();
}
// Данные и блоки в коротком описании товара.
add_action('woocommerce_single_product_summary', static function () {
    global $product;
    if(!$product) return;
    echo '<div class="ss-pickup"><strong>Самовывоз — бесплатно</strong><p>' . esc_html(ss_shop_is_placeholder()?'Адрес уточняется перед запуском.':ss_shop('pickup_address')) . '</p><p>Оплата при получении. Забирайте после подтверждения готовности заказа.</p></div><dl class="ss-key-specs">';
    $n=0; foreach($product->get_attributes() as $attribute) {if(!$attribute->get_visible())continue;$value=$product->get_attribute($attribute->get_name());if(!$value)continue;printf('<div><dt>%s</dt><dd>%s</dd></div>',esc_html(wc_attribute_label($attribute->get_name())),esc_html($value));if(++$n>=5)break;} echo '</dl>';
},35);

function ss_theme_breadcrumbs(): void { if(!is_front_page())woocommerce_breadcrumb(); }
