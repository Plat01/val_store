<?php
/** Создаёт только страницы проекта, повторный запуск не перезаписывает редакторские правки. */
$pages=[
    'home'=>['Главная',''],
    'delivery'=>['Доставка и оплата','<!-- wp:shortcode -->[ss_document type="delivery"]<!-- /wp:shortcode -->'],
    'about'=>['О компании','<!-- wp:paragraph --><p>«собери станок» — магазин комплектующих для тех, кто собирает оборудование с ЧПУ своими руками. В каталоге — двигатели, шпиндели, механика, профиль и электроника.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Сравнивайте характеристики, выбирайте модель и оформляйте заказ с оплатой при получении. Если нужна помощь с совместимостью, задайте вопрос через форму на странице контактов.</p><!-- /wp:paragraph -->'],
    'contacts'=>['Контакты','<!-- wp:ss/view {"view":"contacts"} /-->'],
    'privacy-policy'=>['Политика конфиденциальности','<!-- wp:shortcode -->[ss_document type="privacy"]<!-- /wp:shortcode -->'],
    'personal-data-consent'=>['Согласие на обработку персональных данных','<!-- wp:shortcode -->[ss_document type="consent"]<!-- /wp:shortcode -->'],
    'offer'=>['Оферта','<!-- wp:shortcode -->[ss_document type="offer"]<!-- /wp:shortcode -->'],
];
$ids=[];
foreach($pages as $slug=>[$title,$content]) {
    $page=get_page_by_path($slug,OBJECT,'page');
    if(!$page) { $id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_name'=>$slug,'post_title'=>$title,'post_content'=>$content],true);if(is_wp_error($id))WP_CLI::error($id->get_error_message()); }
    else $id=$page->ID;
    $ids[$slug]=$id;
}
update_option('show_on_front','page');update_option('page_on_front',$ids['home']);
update_option('wp_page_for_privacy_policy',$ids['privacy-policy']);
update_option('woocommerce_terms_page_id',$ids['offer']);
update_option('woocommerce_checkout_privacy_policy_text','Ваши данные используются для обработки заказа. Подробнее: [privacy_policy].');
update_option('woocommerce_registration_privacy_policy_text','Данные используются для работы личного кабинета. Подробнее: [privacy_policy].');
$form_id=(int)get_option('ss_contact_form_id');
if(!$form_id || !get_post($form_id)) {
    $form=WPCF7_ContactForm::get_template();
    $form->set_title('Вопрос магазину');
    $form->set_properties([
        'form'=>'<p><label>Ваше имя [text* your-name autocomplete:name]</label></p><p><label>Email [email* your-email autocomplete:email]</label></p><p><label>Ваш вопрос [textarea* your-message]</label></p><p>[acceptance personal-data] Даю <a href="' . esc_url(home_url('/personal-data-consent/')) . '">согласие на обработку персональных данных</a> для ответа на обращение. [/acceptance]</p><p><a href="' . esc_url(home_url('/privacy-policy/')) . '">Политика конфиденциальности</a></p><p>[submit "Отправить вопрос"]</p>',
        'mail'=>['active'=>true,'subject'=>'Вопрос в магазин «собери станок»','sender'=>'Собери станок <wordpress@' . substr(strrchr(get_option('admin_email'),'@'),1) . '>','recipient'=>ss_shop('email',get_option('admin_email')),'body'=>"Имя: [your-name]\nEmail: [your-email]\nСообщение: [your-message]\nСогласие: [personal-data]",'additional_headers'=>'Reply-To: [your-email]','use_html'=>false,'exclude_blank'=>false,'attachments'=>''],
        'additional_settings'=>'acceptance_as_validation: on',
    ]);
    $form->save();update_option('ss_contact_form_id',$form->id());
}
// Почтовый адрес отправителя должен иметь допустимый домен и воспроизводиться.
$contact=WPCF7_ContactForm::get_instance((int)get_option('ss_contact_form_id'));
if($contact){$mail=$contact->prop('mail');$mail['sender']='Собери станок <wordpress@' . substr(strrchr(get_option('admin_email'),'@'),1) . '>';$contact->set_properties(['mail'=>$mail]);$contact->save();}
// Нативный checkbox условий оферты + отдельный обязательный PD checkbox API.
$id=(int)get_option('woocommerce_checkout_page_id');
$post=get_post($id); $blocks=parse_blocks($post->post_content);
$walk=static function (&$blocks) use (&$walk) {
    foreach($blocks as &$block) {
        if($block['blockName']==='woocommerce/checkout-terms-block') $block['attrs']['checkbox']=true;
        if(!empty($block['innerBlocks'])) $walk($block['innerBlocks']);
    }
};
$walk($blocks);
$content=serialize_blocks($blocks);
if(!str_contains($content,'ss-legal-links')) $content.='<!-- wp:html --><div class="ss-legal-links"><a href="' . esc_url(home_url('/personal-data-consent/')) . '" target="_blank" rel="noopener">Текст согласия на обработку ПДн</a><a href="' . esc_url(home_url('/privacy-policy/')) . '" target="_blank" rel="noopener">Политика конфиденциальности</a><a href="' . esc_url(home_url('/offer/')) . '" target="_blank" rel="noopener">Оферта</a></div><!-- /wp:html -->';
if($content!==$post->post_content)wp_update_post(['ID'=>$id,'post_content'=>$content]);
WP_CLI::success('Страницы, форма связи и согласия настроены.');
