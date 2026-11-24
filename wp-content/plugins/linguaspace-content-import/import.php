<?php
/*
Plugin Name: LinguaSpace Content Import
Description: Импорт страниц, статей и меню LinguaSpace.
Version: 1.0
*/
if (!defined('ABSPATH')) exit;
add_action('admin_menu', function(){add_management_page('Импорт LinguaSpace','Импорт LinguaSpace','manage_options','ls-import','lsi_screen');});
function lsi_screen(){
if(!current_user_can('manage_options')) return;
echo '<div class="wrap"><h1>Импорт LinguaSpace</h1>';
if(isset($_POST['lsi_run'])){
check_admin_referer('lsi_import');
$data=json_decode(file_get_contents(__DIR__.'/content.json'),true); $ids=[];$errors=[];$count=0;
$save=function($slug,$title,$body,$type,$cat=0) use (&$errors,&$count){
$old=get_page_by_path($slug,OBJECT,$type); $args=['post_type'=>$type,'post_name'=>$slug,'post_title'=>$title,'post_content'=>$body,'post_status'=>'publish']; if($old)$args['ID']=$old->ID; if($cat)$args['post_category']=[$cat];
$id=wp_insert_post(wp_slash($args),true);if(is_wp_error($id)){$errors[]=$title.': '.$id->get_error_message();return 0;} $count++;update_post_meta($id,'ls_description',wp_html_excerpt(trim(preg_replace('/\s+/u',' ',wp_strip_all_tags(str_replace(['</p>','</h2>'],' ',$body)))),150));return $id;};
foreach($data['pages'] as $p){$ids[$p[0]]=$save($p[0],$p[1],$p[2],'page');}
$i=0;foreach($data['articles'] as $name=>$posts){$i++;$term=term_exists($name,'category');if(!$term)$term=wp_insert_term($name,'category');if(is_wp_error($term)){$errors[]=$term->get_error_message();continue;}$cat=(int)(is_array($term)?$term['term_id']:$term);$j=0;foreach($posts as $p){$j++;$save("guide-$i-$j",$p[0],$p[1],'post',$cat);}}
if(!empty($ids['home'])){update_option('show_on_front','page');update_option('page_on_front',$ids['home']);}
update_option('blogname','LinguaSpace');update_option('blogdescription','Центр изучения иностранных языков');global $wp_rewrite;$wp_rewrite->set_permalink_structure('/%postname%/');
$menu=wp_get_nav_menu_object('LinguaSpace');$mid=$menu?$menu->term_id:wp_create_nav_menu('LinguaSpace');if(!is_wp_error($mid)){ $existing=wp_get_nav_menu_items($mid);$seen=[];foreach((array)$existing as $item){if(is_object($item))$seen[]=(int)$item->object_id;}foreach(['courses','adults','children','online','prices','articles','contacts'] as $slug){if(!empty($ids[$slug])&&!in_array($ids[$slug],$seen,true)){ $r=wp_update_nav_menu_item($mid,0,['menu-item-object-id'=>$ids[$slug],'menu-item-object'=>'page','menu-item-type'=>'post_type','menu-item-status'=>'publish']);if(is_wp_error($r))$errors[]=$r->get_error_message();}}$loc=get_theme_mod('nav_menu_locations',[]);$loc['primary']=$mid;set_theme_mod('nav_menu_locations',$loc);}else{$errors[]=$mid->get_error_message();}flush_rewrite_rules();
echo '<p>Обработано страниц и записей: '.intval($count).'</p>';foreach($errors as $err)echo '<p>'.esc_html($err).'</p>';echo '<p><a href="'.esc_url(home_url('/')).'">Открыть сайт</a></p>';}
echo '<p>Создаёт или обновляет 25 страниц и 15 статей по их адресам, настраивает главную страницу, категории, меню и постоянные ссылки. Повторный импорт заменяет содержимое этих страниц.</p><form method="post">';wp_nonce_field('lsi_import');submit_button('Импортировать контент','primary','lsi_run');echo '</form></div>';}
