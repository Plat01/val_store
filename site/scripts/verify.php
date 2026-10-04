<?php
// Проверка каталога против источника, включая варианты, слаги, цены и файлы.
defined('ABSPATH') || exit;
$old = new wpdb(DB_USER, DB_PASSWORD, getenv('OLD_DB') ?: 'old', DB_HOST);
$old->set_prefix('wp_');
$errors = [];
$check = static function ($ok, $message) use (&$errors) { if (!$ok) { $errors[] = $message; } };
global $wpdb;
$check(get_option('blog_public') == 0, 'Индексация должна быть закрыта');
foreach (['timezone_string'=>'Europe/Moscow','woocommerce_currency'=>'RUB','woocommerce_default_country'=>'RU','woocommerce_weight_unit'=>'kg','woocommerce_dimension_unit'=>'cm'] as $key=>$expected) {
 $check(get_option($key) === $expected, $key);
}
$ids = [];
foreach ($old->get_results("SELECT ID, post_type, post_status, post_name FROM {$old->posts} WHERE (post_type='product' AND post_status IN ('publish','pending')) OR (post_type='product_variation' AND post_status='publish')") as $source) {
 $rows = $wpdb->get_col($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON p.ID=m.post_id WHERE m.meta_key='_ss_old_id' AND m.meta_value=%d AND p.post_type=%s", $source->ID, $source->post_type));
 $check(count($rows) === 1, "Сопоставление {$source->post_type} #{$source->ID}");
 if (count($rows)!==1) { continue; }
 $id=(int)$rows[0]; $ids[]=$id; $p=wc_get_product($id);
 $check($p && $p->get_status() === ($source->post_status==='pending'?'draft':'publish'), "Статус #$id");
 if ($source->post_type==='product') {
  $check($p->get_slug() === $source->post_name, "Слаг #$id");
  $check(!preg_match('~\[/?(?:vc_|woodmart_|rev_slider)~', $p->get_description()), "Шорткоды #$id");
 }
 if (!$p->is_type('variable')) {
  foreach (['regular_price','sale_price','sku','weight','length','width','height'] as $key) {
   $value = $old->get_var($old->prepare("SELECT meta_value FROM {$old->postmeta} WHERE post_id=%d AND meta_key=%s LIMIT 1",$source->ID,'_'.$key));
   $current=$p->{'get_'.$key}();
   $check($key==='sku' ? (string)$value===$current : (string)$value==='' && $current==='' || (float)$value===(float)$current, "$key #$id");
  }
 }
 $source_meta = [];
 foreach ($old->get_results($old->prepare("SELECT meta_key,meta_value FROM {$old->postmeta} WHERE post_id=%d",$source->ID)) as $meta) {$source_meta[$meta->meta_key]=$meta->meta_value;}
 $managed=($source_meta['_manage_stock']??'no')==='yes';
 $check($p->get_manage_stock()===$managed,"Учёт остатков #$id");
 if ($managed) {$check((int)$p->get_stock_quantity()===(int)($source_meta['_stock']??0),"Остаток #$id");}
 $check($p->get_backorders()===($source_meta['_backorders']??'no'),"Предзаказ #$id");
}
$expected=(int)$old->get_var("SELECT COUNT(*) FROM {$old->posts} WHERE (post_type='product' AND post_status IN ('publish','pending')) OR (post_type='product_variation' AND post_status='publish')");
$check(count($ids) === $expected, 'Число сущностей отличается от источника');
$cats=$old->get_results("SELECT t.term_id,t.slug,tt.parent FROM {$old->terms} t JOIN {$old->term_taxonomy} tt USING(term_id) WHERE tt.taxonomy='product_cat'");
foreach ($cats as $cat) {
 $terms=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'meta_query'=>[['key'=>'_ss_old_id','value'=>$cat->term_id]]]);
 $check(count($terms)===1, "Категория #{$cat->term_id}");
 if (count($terms)!==1) {continue;}
 $check($terms[0]->slug===$cat->slug, "Слаг категории #{$cat->term_id}");
 $parent=$terms[0]->parent ? (int)get_term_meta($terms[0]->parent,'_ss_old_id',true):0;
 $check($parent===(int)$cat->parent,"Родитель категории #{$cat->term_id}");
}
$attachments=get_posts(['post_type'=>'attachment','post_status'=>'inherit','numberposts'=>-1,'fields'=>'ids','meta_key'=>'_ss_old_file']);
foreach ($attachments as $id) { $check(is_readable(get_attached_file($id)),"Файл вложения #$id"); }
$duplicates=$wpdb->get_var("SELECT count(*) FROM (SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_ss_old_id' GROUP BY meta_value HAVING count(*)>1) d");
$check(!$duplicates,'Дубли старых ID');
if ($errors) { foreach($errors as $error) {WP_CLI::warning($error);} WP_CLI::error('Проверка не пройдена'); }
WP_CLI::success(sprintf('Каталог сверён: %d товаров/вариаций, %d категорий, %d файлов. Слаги, цены и иерархия совпадают.',count($ids),count($cats),count($attachments)));
