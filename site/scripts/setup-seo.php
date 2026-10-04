<?php
/** Этап 6: воспроизводимые настройки, контакты и индексацию не меняем. */
$merge = static function ($key, $values) { update_option($key, array_replace((array) get_option($key, []), $values)); };
$modules = (array) get_option('rank_math_modules', []);
update_option('rank_math_modules', array_values(array_unique(array_merge($modules, ['sitemap','rich-snippet','woocommerce']))));
$merge('rank-math-options-titles', [
    'knowledgegraph_type'=>'company', 'knowledgegraph_name'=>ss_shop('brand','собери станок'),
    'knowledgegraph_logo'=>get_theme_file_uri('assets/images/apple-touch-icon.png'),
    'homepage_title'=>'Комплектующие для станков с ЧПУ — купить | собери станок',
    'homepage_description'=>'Комплектующие для сборки станков с ЧПУ: двигатели, шпиндели, направляющие, профиль и электроника. Характеристики, выбор моделей, оплата при получении и самовывоз.',
    'pt_product_title'=>'%title% — купить за %wc_price% | собери станок',
    'pt_product_description'=>'%title%: характеристики и выбор комплектующих для станков с ЧПУ. Закажите в магазине «собери станок»: оплата при получении, самовывоз.',
    'pt_product_archive_title'=>'Комплектующие для ЧПУ — каталог %page% | собери станок',
    'tax_product_cat_title'=>'%term% — купить в интернет-магазине %page% | собери станок',
    'tax_product_cat_description'=>'%term% для станков с ЧПУ: выбор моделей и характеристики в каталоге «собери станок». Оформите заказ онлайн с оплатой при получении и самовывозом.',
    'tax_product_cat_custom_robots'=>'on', 'tax_product_cat_robots'=>['index'],
    'pt_page_default_rich_snippet'=>'off', 'noindex_search'=>'on', 'noindex_archive_subpages'=>'off',
    'disable_author_archives'=>'on', 'disable_date_archives'=>'on',
    '404_title'=>'Страница не найдена | собери станок',
]);
$merge('rank-math-options-general', ['breadcrumbs'=>'on','breadcrumbs_home_label'=>'Главная','breadcrumbs_ancestor_categories'=>'on']);
$excluded=[];
foreach (['cart','checkout','myaccount'] as $page) {
    $id=wc_get_page_id($page); if($id>0) { $excluded[]=$id; update_post_meta($id,'rank_math_robots',['noindex','follow']); }
}
$sitemap_options=(array)get_option('rank-math-options-sitemap',[]);
$excluded=array_unique(array_merge($excluded,array_filter(array_map('intval',explode(',',$sitemap_options['exclude_posts']??'')))));
$merge('rank-math-options-sitemap', ['items_per_page'=>200,'include_images'=>'on','include_featured_image'=>'on','pt_product_sitemap'=>'on','pt_page_sitemap'=>'on','pt_attachment_sitemap'=>'off','tax_product_cat_sitemap'=>'on','tax_product_cat_include_empty'=>'off','tax_product_tag_sitemap'=>'off','exclude_posts'=>implode(',',$excluded)]);

// Реальные названия изображений вместо названий файлов (также при повторном импорте).
foreach(get_posts(['post_type'=>'product','post_status'=>'any','numberposts'=>-1]) as $post) {
    $p=wc_get_product($post->ID); if(!$p)continue;
    foreach(array_filter(array_merge([$p->get_image_id()],$p->get_gallery_image_ids())) as $id) {
        if(!get_post_meta($id,'_wp_attachment_image_alt',true))update_post_meta($id,'_wp_attachment_image_alt',$p->get_name());
    }
}
if(class_exists('\RankMath\Sitemap\Cache')) \RankMath\Sitemap\Cache::invalidate_storage();
WP_CLI::success('SEO-настройки применены; blog_public сохранён.');
