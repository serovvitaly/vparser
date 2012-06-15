<?

#==== ОТКЛЮЧАЕМ ПАРСЕР (при работе - закомментировать) ====
die('Access is forbidden');
#==========================================================

header('Content-Type: text/plain');


#================== ПОДКЛЮЧАЕМ CMS ДВИЖЕК =================
define("VALID_CMS", 1);
define("VALID_CMS_ADMIN", 1);

define('PATH', $_SERVER['DOCUMENT_ROOT']);

require(PATH."/core/cms.php");
                    
require(PATH."/includes/config.inc.php");    
require(PATH."/includes/database.inc.php");
require(PATH."/includes/tools.inc.php");    



$inCore = cmsCore::getInstance();

$inCore->loadClass('user');

$inCore->loadModel('shop');
$model = new cms_model_shop();

#==========================================================


define('VS_PARSER_CONTROLLER', 1);
include_once 'ParserController.php';
$p = new VsParser_Controller();


/**
 * Определяем паттерны (регулярные выражения) для парсинга
 */
$pattern_items = array('items'=>'<div class="good"><div class="good_img"><br \/>(<a href=".*"><div id="watermark_box"><img src="(.*)" alt=".*" \/><\/div><\/a><a href="(.*)" alt=".*" title=".*">.*<\/a>.*){0,1}<\/div><div class="good_text"><p><a href="(.*)" class="good_title">(.*)<\/a><\/p><p>(.*)<\/p><p>.*\: <strong>(.*)<\/strong><\/p><div.*><div.*><span.*>.*<\/span>.*<\/div><div class="left">.*<map.*<\/map><\/div><\/div>.*<p><span.*>(.*)<\/span><a.*><\/a><div class="clear"><\/div><\/p><\/div><\/div>');

$pattern_pages = array('pages'=>'<a href="(([a-z0-9_\-\.\/\#])*)" class="page_link" style=".*">([0-9]*)<\/a>');


/**
 * Устанавливаем правила для парсинга
 */

$p->add_rule('932', 'parse_my_content')
  ->add_pattern($pattern_items)
  ->add_pattern($pattern_pages)
  ->add_address('http://klenmarket.ru/catalog/741/1325/');
  //->add_address('http://www.klenmarket.ru/catalog/607/724/1318/group_1271/');



/**
 * Запускаем парсинг
 */
$p->run();



/**
 * Переконвертирует строку из UTF-8 в WINDOWS-1251.
 */
function viconv($string, $replacement = false)
{
    $string = iconv('utf-8', 'windows-1251//IGNORE', $string);
    if ($replacement) {
        $string = $this->replacement($string);
    }
        
    return  $string;
}


/**
 * Сохраняет картинки товара в нужном месте.
 */
function load_image($im_smal, $im_big, $new_id)
{        
        $new_dir = $_SERVER['DOCUMENT_ROOT']."/images/photos/";
        
        $new_image        = $new_dir . 'shop'.$new_id.'.jpg';
        $new_small_image  = $new_dir . 'small/shop'.$new_id.'.jpg';
        $new_medium_image = $new_dir . 'medium/shop'.$new_id.'.jpg';
                
        $old_image_dir    = 'http://klenmarket.ru';
        $old_image        = $old_image_dir . $im_big;
        $old_small_image  = $old_image_dir . $im_smal;
                
        if ($im1 = @file_get_contents($old_image) AND substr($im1, 0, 5) != '<!DOC') {
            if (!file_exists($new_image)) {
                $handle = fopen($new_image, 'w+');
                fwrite($handle, $im1);
                fclose($handle);
            }            
            if (!file_exists($new_medium_image)) {
                $handle = fopen($new_medium_image, 'w+');
                fwrite($handle, $im1);
                fclose($handle);
            }
        }
                
        if (!file_exists($new_small_image) AND $im2 = @file_get_contents($old_small_image)  AND substr($im2, 0, 5) != '<!DOC') {
            $handle = fopen($new_small_image, 'w+');
            fwrite($handle, $im2);
            fclose($handle);
        }
}



/**
 * Вызывается при итерации правила парсинга, 
 * содержит весь необходимый код для обработки 
 * результата парсинга.
 */
function parse_my_content($result)
{
    global $model;
    
    
    $data = $result->data;
    $rule_name = $result->rule->_name;
    
    $current_rule = $result->rule;
    //$current_rule->_address->clear();
    
    if (isset($data['items']) AND count($data['items']) > 0) {
        foreach ($data['items'] AS $cur_items) {
            foreach ($cur_items['result'] AS $item_mix) {
                //$im_smal  = $item_mix[2];
                $im_smal  = $item_mix[3];
                $im_big   = $item_mix[3];
                $href     = $item_mix[4];
                $title    = $item_mix[5];
                $desc     = $item_mix[6];
                $art_no   = $item_mix[7];
                $price    = $item_mix[8];
                
                preg_match_all('/([\d|\s]+)/', $price, $price_mcs);
                if (isset($price_mcs[1][0])) {
                    $price = str_replace(' ', '', $price_mcs[1][0]);
                    $price = trim($price);
                } else {
                    $price = 0;
                }                
                
                
                $item = array();            
                
                $item['category_id']    = $rule_name;
                $item['art_no']         = viconv($art_no);
                $item['title']          = viconv($title);
                $item['shortdesc']      = viconv($desc);
                $item['description']    = viconv($desc);
                $item['price']          = viconv($price);
                
                $item['filename']       = NULL;
                
                $item['vendor_id']      = NULL;
                $item['url']            = NULL;
                $item['metakeys']       = NULL;
                $item['metadesc']       = NULL;
                $item['metakeys']       = NULL;
                $item['metadesc']       = NULL;
                $item['tags']           = NULL;
                $item['old_price']      = NULL;
                $item['cats']           = array();
                $item['chars']          = array();
                $item['vars_art_no']    = NULL;
                $item['vars_title']     = NULL;
                $item['vars_price']     = NULL;
                $item['vars_qty']       = NULL;
                
                $item['kompl_art_no']   = NULL;
                
                $item['last_update']    = date('Y-m-d H:i:s');
                $item['pubdate']        = date('Y-m-d');
                $item['tpl']            = 'com_inshop_item.tpl';
                $item['is_comments']    = 0;
                $item['is_hit']         = 0;
                $item['is_front']       = 0;
                $item['is_digital']     = 0;
                $item['published']      = 1;
                $item['qty']            = 1;
                $item['auto_thumb']     = 0;
                $item['vendor_brand']   = '---';
                $item['tags']= '';
                
                $item['price'] = rtrim($item['price'], 'руб.');
                $item['price'] = ltrim($item['price'], 'Цена:');
                $item['price'] = str_replace(array("\n","\t"), '', $item['price']);
                $item['price'] = htmlentities($item['price']);
                $item['price'] = (int)trim($item['price'], '&nbsp;');
                
                
                if (!empty($art_no)) {
                    $item_id = $model->addItem($item);
                    load_image($im_smal, $im_big, $item_id);
                }            
            }
        }

    }
    
    if (isset($data['pages']) AND count($data['pages']) > 0) {
        foreach ($data['pages'] AS $pages_mix) {
            foreach ($pages_mix['result'] AS $page) {
                $href = 'http://www.klenmarket.ru' . $page[1];
                $current_rule->add_address($href);
            }
        }
        
        $result->parser->run($rule_name);
        
        
    }
    
    
}