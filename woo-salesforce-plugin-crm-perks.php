<?php
/*
* Plugin Name: WooCommerce Salesforce CRM Perks
* Description: Integrates WooCommerce with Salesforce allowing new orders to be automatically sent to your Salesforce account.
* Version: 1.2
* Requires at least: 3.8
* Tested up to: 4.9
* Author: CRM Perks.
* Author URI: https://www.crmperks.com
* Plugin URI: https://www.crmperks.com/plugins/woocommerce-plugins/woocommerce-salesforce-plugin/
*
* Copyright: © 2018 Boohead, Inc.
* 
*/  
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'vxc_sales' ) ):

/**
* Main  class
*
* @since       1.0.0
*/
class vxc_sales{            
 public $url='https://www.crmperks.com';           
  public $id='vxc_sales';
  public $domain='vxc-sales';
  public $crm_name='salesforce';
  public $version = '1.0';
  public $min_wc_version = '2.1.1';
  public $update_id = '50001';
  public $type = 'vxc_sales_pro';

  public $user='';
  public static $path='';
  public static $slug='';
  public static $base_url='';
  public static $tooltips='';

  private $plugin_dir;
  private $filter_condition;
  private static $order;
  private $temp= '';
  public static $note= '';
  public static $title='WooCommerce Salesforce Plugin';  
  public static $save_key='';  
  public static $db_version='';  
  public static $vx_plugins;  
  public static $feeds_res;  
  public static $_order;  
  public static $wc_status;  
  public static $wc_status_msg;  
  public static $plugin;  
  public static $processing_feed=false;  
  public static $api_timeout;  
  
  public function instance(){
 add_action('plugins_loaded', array($this,'setup_main'));
 register_deactivation_hook(__FILE__,array($this,'deactivate'));
register_activation_hook(__FILE__,(array($this,'activate')));



}

  public function init(){ 
   
    /**
  * Check if WooCommerce is active
  **/
  self::$wc_status= $this->wc_status();
    if(self::$wc_status !== 1){
    self::$slug=$this->get_slug();
  add_action( 'admin_notices', array( $this, 'install_wc_notice' ) );
  add_action( 'after_plugin_row_'.self::$slug, array( $this, 'install_wc_notice_plugin_row' ) );    
  return;
  }
  require_once(self::$path . "includes/plugin-pages.php");
  require_once(self::$path . "includes/add-ons.php");
  require_once(self::$path . "includes/crmperks-wc.php");

 
  }

  public function setup_main(){
 
        // hook into woocommerce order status changed hook to handle the desired subscription event trigger
  add_action( 'woocommerce_order_status_changed',array($this,'status_changed'), 10, 3 );
  add_action( 'woocommerce_checkout_update_order_meta',array($this,'order_submit'), 20, 2 ); //order_id, posted
       if(is_admin()){
    self::$path=$this->get_base_path();
  add_action('init', array($this,'init'));
       //loading translations
  //load_plugin_textdomain('woocommerce-salesforce-crm', FALSE, $this->plugin_dir_name() . '/languages/' );

      self::$db_version=get_option($this->type."_version"); 
  if( self::$db_version != $this->version && current_user_can( 'manage_options' )){
       self::$path=$this->get_base_path();
  include_once(self::$path . "includes/install.php");
  $class=$this->id.'_install';
  $install=new $class();
  $install->create_tables();
  $install->create_roles();
  update_option($this->type."_version", $this->version);
  $log_str="Installing WooCommerce Salesforce Plugin version=".$this->version;
  $this->log_msg($log_str);
  }
  
  
       }
  
             
  }


  /**
* Woocommerce status
* 
*/
  public  function wc_status() {
  
  $installed = 0;
  if(!class_exists('WooCommerce')) {
  if(file_exists(WP_PLUGIN_DIR.'/woocommerce/woocommerce.php')) {
  $installed=2;   
  }
  }else{
  $installed=1;
  if(!version_compare(WOOCOMMERCE_VERSION, $this->min_wc_version, ">=")){
  $installed=3;   
  }      
  }
  if($installed !=1){
    if($installed === 0){ // not found
  $message = sprintf(__("%sWooCommerce%s is required. %sDownload latest version!%s", 'woocommerce-salesforce-crm'), "<a href='https://www.woothemes.com/woocommerce/'>", "</a>", "<a href='https://www.woothemes.com/woocommerce/'>", "</a>");   
  }else if($installed === 2){ // not active
  $message = sprintf(__('WooCommerce is installed but not active. %sActivate WooCommerce%s to use the WooCommerce Salesforce Plugin','woocommerce-salesforce-crm'), '<strong><a href="'.wp_nonce_url(admin_url('plugins.php?action=activate&plugin=woocommerce/woocommerce.php'), 'activate-plugin_woocommerce/woocommerce.php').'">', '</a></strong>');  
  } else if($installed === 3){ // not supported
  $message = sprintf(__("A higher version of %sWooCommerce%s is required. %sDownload latest version!%s", 'woocommerce-salesforce-crm'), "<a href='https://www.woothemes.com/woocommerce/'>", "</a>", "<a href='https://www.woothemes.com/woocommerce/'>", "</a>");
  }  
  self::$wc_status_msg=$message;
  }
  return $installed;   
  }
    /**
  * Install Woocommerec Notice
  * 
  */
  public function install_wc_notice(){
        $message=self::$wc_status_msg;
  if(!empty($message)){
  $this->display_msg('admin',$message,'woocommerce'); 
     $this->notice_js=true; 
  
  }
  }
  /**
  * Install Woocommerec Notice (plugin row)
  * 
  */
  public function install_wc_notice_plugin_row(){
  $message=self::$wc_status_msg;
  if(!empty($message)){
   $this->display_msg('',$message,'woocommerce');
  } 
  }
  /**
  * web2lead fields
  *  
  * @param mixed $module
  * @param mixed $map
  */
  public function web_fields($module,$map){
  ////////////////////////////
  $web['Lead']='{"1":{"label":"First Name","max":"40","name":"first_name","type":"text"},"2":{"label":"Last Name","max":"80","name":"last_name","type":"text","req":"true"},"3":{"label":"Email","max":"80","name":"email","type":"text","req":"true"},"4":{"label":"Company","max":"40","name":"company","type":"text"},"5":{"label":"City","max":"40","name":"city","type":"text"},"6":{"label":"State/Province","max":"20","name":"state","type":"text"},"7":{"label":"Salutation","name":"salutation","type":"select"},"8":{"label":"Title","max":"40","name":"title","type":"text"},"9":{"label":"Website","max":"80","name":"URL","type":"text"},"10":{"label":"Phone","max":"40","name":"phone","type":"text"},"11":{"label":"Mobile","max":"40","name":"mobile","type":"text"},"12":{"label":"Fax","max":"40","name":"fax","type":"text"},"13":{"label":"Address","name":"street","type":"select"},"14":{"label":"Zip","max":"20","name":"zip","type":"text"},"15":{"label":"Country","max":"40","name":"country","type":"text"},"16":{"label":"Description","name":"description","type":"select"},"17":{"label":"Lead Source","name":"lead_source","type":"select"},"18":{"label":"Industry","name":"industry","type":"select"},"19":{"label":"Rating","name":"rating","type":"select"},"20":{"label":"Annual Revenue","name":"revenue","type":"text"},"21":{"label":"Employees","name":"employees","type":"text"},"22":{"label":"Email Opt Out","name":"emailOptOut","type":"checkbox"},"23":{"label":"Fax Opt Out","name":"faxOptOut","type":"checkbox"},"24":{"label":"Do Not Call","name":"doNotCall","type":"checkbox"}}';
  $web['Case']='{"1":{"label":"Contact Name","max":"80","name":"name","type":"text"},"2":{"label":"Email","max":"80","name":"email","type":"text"},"3":{"label":"Phone","max":"40","name":"phone","type":"text"},"4":{"label":"Subject","max":"80","name":"subject","type":"text"},"5":{"label":"Description","name":"description","type":"select"},"6":{"label":"Company","max":"80","name":"company","type":"text"},"7":{"label":"Type","name":"type","type":"select"},"8":{"label":"Status","name":"status","type":"select"},"9":{"label":"Case Reason","name":"reason","type":"select"},"10":{"label":"Priority","name":"priority","type":"select"}}'; 
  //////////////////
  if(isset($web[$module])){
  $fields=json_decode($web[$module],true);
  foreach($map as $k=>$v){
  if(isset($v['name_c']))
  $fields[$k]=$v;   
  }
  }
  return $fields;
  }    
 
  /**
  * check custom filters
  * 
  * @param mixed $feed
  * @param mixed $order
  */
  public function check_filter($feed){ 
  $filters=$this->post('filters',$feed);
  $final=$this->filter_condition=null;
  if(is_array($filters)){
      $time=current_time('timestamp'); 
  foreach($filters as $filter_s){
  $check=null; $and=null;  $and_c=array();
  if(is_array($filter_s)){
  foreach($filter_s as $filter){
  $field=$filter['field'];
  $fval=$filter['value'];
  $val=$this->get_field_val($filter); 
  if(!$val){
      continue;
  }
  //make case insensitive
  $val=strtolower($val);
  $fval=strtolower($fval);
   //
 $country_fields=array("billing_country","shipping_country","country");
  if(in_array($field,$country_fields)){
  $countries=WC()->countries->countries;
  if(in_array($filter['op'],array("is","is_not"))){
   if(strlen($field)>2){
    $fval=ucwords($fval); 
    if(is_array($countries)){
        foreach($countries as $c_code=>$c_name){
            if(preg_match('/'.$fval.'/i',$c_name)){
           $fval=$c_code;
           break;     
            }
        }   
    }  
   }else{
       $fval=strtoupper($fval);
   }
  }else{ //convert both to lowercase full country names
  $val=isset($countries[$val]) ? strtolower($countries[$val]) : "";     
  }   
  }
  switch($filter['op']){
  case"is": $check=$fval == $val;break;
  case"is_not": $check=$fval != $val;     break;
  case"contains": $check=strpos($val,$fval) !==false;     break;
  case"not_contains": $check=strpos($val,$fval) ===false;     break;
  case"is_in": $check=strpos($fval,$val) !==false;     break;
  case"not_in": $check=strpos($fval,$val) ===false;     break;
  case"starts": $check=strpos($val,$fval) === 0;     break;
  case"not_starts": $check=strpos($val,$fval) !== 0;     break;
  case"ends": $check=(strpos($val,$fval)+strlen($fval)) == strlen($val);  break;
  case"not_ends": $check=(strpos($val,$fval)+strlen($fval)) != strlen($val);  break;
  case"less": $check=(float)$val<(float)$fval; break;
  case"greater": $check=(float)$val>(float)$fval;  break;
  case"less_date": $check=strtotime($val,$time) < strtotime($fval,$time);  break;
  case"greater_date": $check=strtotime($val,$time) > strtotime($fval,$time);  break;
  case"equal_date": $check=strtotime($val,$time) == strtotime($fval,$time);  break;
  case"empty": $check=$val == "";  break;
  case"not_empty": $check=$val != "";  break;
  }   
  $and_c[]=array("check"=>$check,"field_val"=>$fval,"input"=>$val,"field"=>$field,"op"=>$filter['op']);
  if($check !== null){
  if($and !== null){
  $and=$and && $check;    
  }else{
  $and=$check;    
  }   
  }  
  } //end and loop filter
  }
  if($and !== null){
  if($final !== null){
  $final=$final || $and;  
  }else{
  $final=$and;
  }    
  }
  $this->filter_condition[]=$and_c;
  } // end or loop
  }
  return $final === null ? true : $final;
  }    
  /**
  * On status change send order to salesforce
  *     
  * @param mixed $id
  * @param mixed $old_status
  * @param mixed $new_status
  */
  public function status_changed($id,$old_status,$new_status){ 
  if(in_array($new_status,array("processing","completed")) && !self::$processing_feed){
  $this->push($id,$new_status);
  }
  }
  /**
  * Send order to salesforce when user submits the order
  *   
  * @param mixed $id
  * @param mixed $posted
  */
  public function order_submit($id,$posted){
      $status="submit";
      if(isset($posted['account_password'])){
      $status="user_created";    
      }
      if($this->do_actions()){ 
do_action('vx_addons_save_entry',$id,$posted,'wc','');         
      }
  $this->push($id,$status);
  }    
  /**
  * Check settings
  * if settings are not complete then ask user to complete settings first
  *     
  */
  public function verify_settings_msg($info=""){
  $link=$this->link_to_settings();

  return "<div class='alert_danger crm_alert'>".sprintf(__("Please Configure %sSalesforce Settings%s",'woocommerce-salesforce-crm'),"<a href='".$link."'>","</a>")."</div>";

  }

  /**
  * Get CRM info
  * 
  */
  public function get_info($id){
      global $wpdb;
 $table= $this->get_table_name('accounts');
$info = $wpdb->get_row( 'SELECT * FROM '.$table.' where id="'.$id.'" limit 1',ARRAY_A );
$info_arr=array(); $data=array();  $meta=array(); 
if(is_array($info)){
if(!empty($info['data'])){ 
  $info_arr=json_decode($this->de_crypt($info['data']),true);   
if(!is_array($info_arr)){
    $info_arr=array();
}
}

$info_arr['time']=$info['time']; 
$info_arr['id']=$info['id']; 
$info['data']=$info_arr; 
if(!empty($info['meta'])){ 
  $meta=json_decode($info['meta'],true); 
}
$info['meta']=is_array($meta) ? $meta : array();   
 
}
  return $info;    
  }


  /**
  * display plugins row or admin notice
  * 
  * @param mixed $type
  * @param mixed $message
  * @param mixed $id
  */
  public function display_msg($type,$message,$id=""){
  //exp 
  global $wp_version;
  $ver=floatval($wp_version);
  if($type == "admin"){
     if($ver<4.2){
  ?>
    <div class="error vx_notice notice" data-id="<?php echo $id ?>"><p style="display: table"><span style="display: table-cell; width: 98%"><span class="dashicons dashicons-megaphone"></span> <b><?php echo self::$title ?>. </b><?php echo wp_kses_post($message);?> </span>
<span style="display: table-cell; padding-left: 10px; vertical-align: middle;"><a href="#" class="notice-dismiss" title="<?php _e('Dismiss Notice','woocommerce-salesforce-crm') ?>">dismiss</a></span> </p></div>
  <?php
     }else{
  ?>
  <div class="error vx_notice fade notice is-dismissible" data-id="<?php echo $id ?>"><p><span class="dashicons dashicons-megaphone"></span> <b><?php echo self::$title ?>. </b> <?php echo wp_kses_post($message);?> </p>
  </div>    
  <?php
     }
  }else{
  ?>
  <tr class="plugin-update-tr"><td colspan="5" class="plugin-update">
  <style type="text/css"> .vx_msg a{color: #fff; text-decoration: underline;} .vx_msg a:hover{color: #eee} </style>
  <div style="background-color: rgba(224, 224, 224, 0.5);  padding: 9px; margin: 0px 10px 10px 28px "><div style="background-color: #d54d21; padding: 5px 10px; color: #fff" class="vx_msg"> <span class="dashicons dashicons-info"></span> <?php echo wp_kses_post($message) ?>
</div></div></td></tr>
  <?php
  }   
  }
    /**
  * display screen notices
  * 
  * @param mixed $type
  * @param mixed $message
  */
  public function screen_msg($type,$message){
      $type=$type == "" ? "updated" : $type;
  ?>
  <div class="<?php echo $type ?> fade notice is-dismissible"><p><?php echo $message;?></p></div>    
  <?php   
  }
    /**
  * Formates User Informations and submitted form to string
  * This string is sent to email and salesforce
  * @param  array $info User informations 
  * @param  bool $is_html If HTML needed or not 
  * @return string formated string
  */
  public  function format_user_info($info,$is_html=false){
  $str=""; $file=""; 
  self::$path=$this->get_base_path();
  if($is_html){
  if(file_exists(self::$path."templates/email.php")){    
  ob_start();
  include_once(self::$path."templates/email.php");
  $file= ob_get_contents(); // data is now in here
  ob_end_clean();
  }
  if(trim($file) == "")
  $is_html=false;
  }
  if(isset($info['info']) && is_array($info['info'])){
  if($is_html){
  if(isset($info['info_title'])){
  $str.='<tr><td style="font-family: Helvetica, Arial, sans-serif;background-color: #C35050; height: 36px; color: #fff; font-size: 24px; padding: 0px 10px">'.$info['info_title'].'</td></tr>'."\n";
  }
  if(is_array($info['info']) && count($info['info'])>0){
  $str.='<tr><td style="padding: 10px;"><table border="0" cellpadding="0" cellspacing="0" width="100%;"><tbody>';      
  foreach($info['info'] as $f_k=>$f_val){
  $str.='<tr><td style="padding-top: 10px;color: #303030;font-family: Helvetica;font-size: 13px;line-height: 150%;text-align: right; font-weight: bold; width: 28%; padding-right: 10px;">'.$f_k.'</td><td style="padding-top: 10px;color: #303030;font-family: Helvetica;font-size: 13px;line-height: 150%;text-align: left; word-break:break-all;">'.$f_val.'</td></tr>'."\n";      
  }
  $str.="</table></td></tr>";             
  }
  }else{
  if(isset($info['title']))
  $str.="\n".$info['title']."\n";    
  foreach($info['info'] as $f_k=>$f_val){
  $str.=$f_k." : ".$f_val."\n";      
  }
  }
  }
  if($is_html){
  $str=str_replace(array("{title}","{msg}","{sf_contents}"),array($info['title'],$info['msg'],$str),$file);
  }
  return $str;   
  }
  /**
  * deactivate
  * 
  * @param mixed $action
  */
  public function deactivate($action="deactivate"){

  do_action('plugin_status_'.$this->type,$action);
  }
  /**
  * activate plugin
  * 
  */
  public function activate(){

      self::$path=$this->get_base_path();
      if(file_exists(self::$path.'includes/plugin-api.php')){
      include_once(self::$path.'includes/plugin-api.php');  
      }

do_action('plugin_status_'.$this->type,'activate'); 
  }
 public function do_actions(){
    // if(!is_object(self::$plugin) ){ $this->plugin_api(); }
      if(method_exists(self::$plugin,'valid_addons')){
       return self::$plugin->valid_addons();  
      }
    
   return false;   
  }
  
  /**
  * Insert Log
  * 
  * @param mixed $arr
  */
  public function __log($arr,$log_id=""){ 
  global $wpdb;
  if(!is_array($arr) || count($arr) == 0)
  return;
  ///$wpdb->show_errors();
  $table_name = $this->get_table_name();
  $sql_arr=array();
  foreach($arr as $k=>$v){
   $sql_arr[$k]=is_array($v) ? json_encode($v) : $v;   
  }
  $log_id=(int)$log_id;
  $res=false;
  if(!empty($log_id)){
       // update
   $res=$wpdb->update($table_name,$sql_arr,array("id"=>$log_id));   
  }else{ 
   $res=$wpdb->insert($table_name,$sql_arr);
   $log_id=$wpdb->insert_id;   
  }
  return $log_id; 
  }
  /**
  * Tooltip image
  * 
  * @param mixed $str
  */
  public function tooltip($str){
  if($str == ""){return;}
  ?>
  <i class="vx_icons vxc_tips fa fa-question-circle" data-tip="<?php echo $str ?>"></i> 
  <?php  
  }
  /**
  * modify query parameters
  * 
  * @param mixed $location
  */
  public function add_notice_query_var($location){
 
  remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
  return add_query_arg( array( $this->id.'_msg' => 'true' ), $location );   
  }
  /**
  * Returns true if the current page is an Feed pages. Returns false if not
  * 
  * @param mixed $page
  */
  public function is_crm_page($plugin_page=""){
      $page='';
      if(isset($_GET['tab'])){
      $page=$_GET['tab'];    
      }else if(isset($_GET['page'])){
      $page=$_GET['page'];    
      }
   if(!empty($plugin_page)){
      if($page == $plugin_page){
          return true;
      }else{
          return false;
      } 
   }   
$pages=array($this->id,$this->id.'_log');
if(in_array($page,$pages)){
    return true;
}

  global $post; 
  if(isset($post->post_type) && $post->post_type == $this->id){ 
  return true;
  }    
  return false;
  }
  /**
  * complete address
  * 
  * @param mixed $value
  * @param mixed $f_key
  * @param mixed $order
  */
  public function verify_address($value,$f_key,$order){
  if( $f_key=='_billing_address_1' && isset($order['_billing_address_2'][0]) && $order['_billing_address_2'][0]!=""){
  $value.=" ".$order['_billing_address_2'][0];    
  }
  if( $f_key=='_shipping_address_1' && isset($order['_shipping_address_2'][0]) && $order['_shipping_address_2'][0]!=""){
  $value.=" ".$order['_shipping_address_2'][0];    
  }
  return $value;       
  }

  /**
  * Send Request
  * 
  * @param mixed $body
  * @param mixed $path
  * @param mixed $method
  */
  public function request($path="",$method='POST',$body="",$head=array()) { 
  
  
  if($path=="")
  $path = $this->url;
  
  $args = array(
  'body' => $body,
  'headers'=> $head,
  'method' => strtoupper($method), // GET, POST, PUT, DELETE, etc.
  'sslverify' => false,
  'timeout' => 30,
  );
  
  $response = wp_remote_request($path, $args);
  
  if(is_wp_error($response)) { 
  $this->errorMsg = $response->get_error_message();
  return false;
  } else if(isset($response['response']['code']) && $response['response']['code'] != 200 && $response['response']['code'] != 404) {
  $this->errorMsg = strip_tags($response['body']);
  return false;
  } else if(!$response) {
  return false;
  }
  $result=wp_remote_retrieve_body($response);
  return $result;
  }
  /**
  * plugin base url
  * 
  */
  public function get_base_url(){
  return plugin_dir_url(__FILE__);
  } 
  /**
  * plugin slug
  * 
  */
  public function get_slug(){
       if(empty(self::$slug)){
  self::$slug=plugin_basename(__FILE__);
 }
  return self::$slug;
  }
  
  /**
  * plugin root directory
  * 
  */
  public function get_base_path(){
  return plugin_dir_path(__FILE__);
  }
  /**
  * plugin settings link
  * 
  */
  public function link_to_settings($part=""){
  return admin_url( 'admin.php?page=wc-settings&tab='.$this->id.$part);
  }    
  /**
  * get plugin direcotry name
  * 
  */
  public function plugin_dir_name(){
  if(!empty($this->plugin_dir)){
  return $this->plugin_dir;
  }
  self::$path=$this->get_base_path();
  $this->plugin_dir=basename(self::$path);
  return $this->plugin_dir;
  }
    /**
  * email validation
  * 
  * @param mixed $email
  */
  public function is_valid_email($email){
         if(function_exists('filter_var')){
      if(filter_var($email, FILTER_VALIDATE_EMAIL)){
      return true;    
      }
       }else{
       if(strpos($email,"@")>1){
      return true;       
       }    
       }
   return false;    
  }
  /**
  * Get Table names
  * 
  * @param mixed $table
  */
  public function get_table_name($table="log"){
  global $wpdb;
  return $wpdb->prefix . $this->id."_".$table;
  }
  /**
  * Get time Offset 
  * 
  */
  public function time_offset(){
 $offset = (int) get_option('gmt_offset');
  return $offset*3600;
  }  
  /**
  * Get WP Encryption key
  * @return string Encryption key
  */
  public static function get_key(){
  $k='Wezj%+l-x.4fNzx%hJ]FORKT5Ay1w,iczS=DZrp~H+ve2@1YnS;;g?_VTTWX~-|t';
  if(defined('AUTH_KEY')){
  $k=AUTH_KEY;
  }
  return substr($k,0,30);        
  } 
  /**
  * Decrypts Values
  * @param array $info encrypted API info 
  * @return array API settings
  */
  public static function de_crypt($info){
  $info=trim($info);
  if($info == "")
  return '';
  $str=base64_decode($info);
  $key=self::get_key();
      $decrypted_string='';
     if(function_exists("openssl_encrypt") && strpos($str,':')!==false ) {
$method='AES-256-CBC';
$arr = explode(':', $str);
 if(isset($arr[1]) && $arr[1]!=""){
 $decrypted_string=openssl_decrypt($arr[0],$method,$key,false, base64_decode($arr[1]));     
 }
 }else{
     $decrypted_string=$str;
 }
  return $decrypted_string;
  }   
  /**
  * Encrypts Values
  * @param  string $str 
  * @return string Encrypted Value
  */
  public static function en_crypt($str){
  $str=trim($str);
  if($str == "")
  return '';
  $key=self::get_key();
   if(function_exists("openssl_encrypt")) {
$method='AES-256-CBC';
$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
$enc_str=openssl_encrypt($str,$method, $key,false,$iv);
$enc_str.=":".base64_encode($iv);
  }else{
      $enc_str=$str;
  }
  $enc_str=base64_encode($enc_str);
  return $enc_str;
  }
  /**
  * Get variable from array
  *  
  * @param mixed $key
  * @param mixed $arr
  */
  public function post($key, $arr="") {
  if(is_array($arr)){
  return isset($arr[$key])  ? $arr[$key] : "";
  }
  //clean when getting extrenals
  return isset($_REQUEST[$key]) ? $this->clean($_REQUEST[$key]) : "";
  }
public function clean($var){
    if ( is_array( $var ) ) {
        return array_map( array($this,'clean'), $var );
    } else {
        return  sanitize_text_field($var);
    }
}
  /**
  * Get variable from array
  *  
  * @param mixed $key
  * @param mixed $key2
  * @param mixed $arr
  */
  public function post2($key,$key2, $arr="") {
  if(is_array($arr) && isset($arr[$key]) && is_array($arr[$key])){
  return isset($arr[$key][$key2])  ? $arr[$key][$key2] : "";
  }
  return isset($_REQUEST[$key][$key2]) && is_array($_REQUEST[$key]) ? $this->clean($_REQUEST[$key][$key2]) : "";
  }
  /**
  * Get variable from array
  *  
  * @param mixed $key
  * @param mixed $key2
  * @param mixed $arr
  */
  public function post3($key,$key2,$key3, $arr="") {
  if(is_array($arr)){
  return isset($arr[$key][$key2][$key3])  ? $arr[$key][$key2][$key3] : "";
  }
  return isset($_REQUEST[$key][$key2][$key3]) ? $this->clean($_REQUEST[$key][$key2][$key3]) : "";
  }

 
  /**
  * Logs a message 
  * 
  * @param mixed $msg
  */
  public function log_msg($msg){
       if ( class_exists( 'WC_Logger' ) ) {
          $logger = new WC_Logger();
          $slug=$this->plugin_dir_name();
          $logger->add( $slug, $msg);
       }
  }
  /*************************************plugin functions******************************************/                    
  
  
  /**
  * Formates crm response
  * 
  * @param mixed $note
  * @param mixed $show_error
  */
  public function format_note($note,$show_error=false){
       $object=$this->post('object',$note);

  $id=$this->post('id',$note);
  $error=$this->post('error',$note);
  $msg="";    
  if(!empty($note['status'])){
  if($note['status'] == "4"){
    $msg=sprintf(__('Salesforce (%s) Filtered','woocommerce-salesforce-crm'),$object);    
  }else{
      //web2lead do not have link
  $link=sprintf(__("with ID # %s",'woocommerce-salesforce-crm'),$this->post('id',$note));
  if($this->post('link',$note) !=""){
      $id_link='<a href="'.$this->post('link',$note).'" target="_blank" title="'.$this->post('id',$note).'">'.$this->post('id',$note).'</a>';
  $link=sprintf(__('with ID # %s','woocommerce-salesforce-crm'),$id_link);
  }
  if($this->post('status',$note) == 3){
  $link="Web2".$object;   
  }  
  $action=$this->post('action',$note);
if($note['status'] == '1'){
  $msg=sprintf(__('Added to Salesforce (%s) ','woocommerce-salesforce-crm'),$object).$link;
  }else{
  $msg=sprintf(__("Updated to Salesforce (%s) ",'woocommerce-salesforce-crm'),$object).$link;     
  }                                         
  }
  }else if($show_error){
     if(empty($error)){
                      if(!empty($note['meta'])){
              $error=$note['meta'];            
                      }else{
              $error= __("Error While Posting to Nimble",'woocommerce-nimble-crm');           
                      }
      }
  $msg=$error;
  }
  if(isset($note['log_id'])){
  $log_url=admin_url( 'admin.php?page='.$this->id.'_log&id='.$note['log_id']);  
  $msg.=' - <a href="'.$log_url.'" class="vx_log_link" title="'.__('View Detail','woocommerce-salesforce-crm').'" data-id="'.$note['log_id'].'">'.__('View Detail','woocommerce-salesforce-crm')."</a>";
  } 
  return $msg;
  }
  /**
  * order information fields
  * 
  * @param mixed $f_key
  */
 public function order_info_fields($f_key=""){
         $_order=self::$_order;
 
         $val="";
        switch($f_key){
            case"_order_subtotal": $val=$_order->get_subtotal(); break;
            case"_total_refunded": $val=$_order->get_total_refunded(); break;
            case"_total_refunded_tax": $val=$_order->get_total_tax_refunded(); break;
            case"_total_shipping_refunded": $val=$_order->get_total_shipping_refunded(); break;
            case"_total_qty_refunded": $val=$_order->get_total_qty_refunded(); break;
            case"_items_count": $val=$_order->get_item_count(); break;
            case"_order_status": $val=$_order->get_status(); break;
            case"_customer_notes": $val=$_order->customer_note; break;
            case"_used_coupns": 
            $coupons=$_order->get_used_coupons(); 
             if(is_array($coupons)){
                 $val=implode(', ',$coupons);
             }
             break; 
            case"_order_fees": 
              ////get fees
              $fees=$_order->get_fees();
              if(is_array($fees)){
  foreach($fees as $fee){
    $val.=$fee['name'].' : '.$fee['line_subtotal'];  
  }
              }
             break;
            default:
            if(in_array($f_key,array('_order_items_skus','_order_items_titles','_order_items'))){ 
            $items=$_order->get_items();

            $info=array();  
            if(is_array($items) && count($items)>0){
                      foreach($items as $item){
             $p_id=!empty($item['variation_id']) ? $item['variation_id'] : $item['product_id'];
             $product=wc_get_product($p_id);
             $item_info=array(
             __('SKU','woocommerce-salesforce-crm')=>$product->get_sku()
             ,__('Title','woocommerce-salesforce-crm')=>$product->get_title()
             ,__('Quantity','woocommerce-salesforce-crm')=>$item['qty']
             ,__('Line Tax','woocommerce-salesforce-crm')=>$item['line_tax']
             ,__('Line Subtotal Tax','woocommerce-salesforce-crm')=>$item['line_subtotal_tax']
             ,__('Total','woocommerce-salesforce-crm')=>$item['line_total']
             ,__('Line Subtotal','woocommerce-salesforce-crm')=>$item['line_subtotal']
             );
             //get selected attributes of variable product
             if(!empty($item['variation_id'])){
             $attrs=$product->get_attributes();
    
             if(is_array($attrs) && count($attrs)>0){
                 foreach($attrs as $att){
                     if(isset($item[$att['name']])){ //attribute found
                     $att_name=wc_attribute_label($att['name']);
                     $terms=wc_get_product_terms($item['product_id'],$att['name']);    
     
                     if(is_array($terms) && count($terms)>0){
                         foreach($terms as $term){
      if($term->slug == $item[$att['name']]){
        $att_val=$term->name;
        break;
            }                             
                         }
                     }
                     $item_info[$att_name]=$att_val;
                     }
                 }
             }
             }

             $info[]=$item_info;
     }
            }
          if(count($info)>0){
           $skus=array(); $titles=array();
            foreach($info as $meta){
                $skus[]=$meta['SKU'];
                $titles[]=$meta['Title'];
             if(!empty($val)){
              $val.="\n------------\n";   
             }
             foreach($meta as $k=>$v){
              $val.=$k." : ".$v."\n";   
             }   
            }
            self::$order['_order_items_titles']=implode(', ', $titles); 
           self::$order['_order_items_skus']= implode(', ', $skus); 
          if($f_key == '_order_items_titles'){
              $val=$titles;
          }else if($f_key == '_order_items_titles'){
              $val=$skus;
          }    
          }

            }
             break;
        }
       self::$order[$f_key]=$val; 
      return $val;
 }
 /**
 * Get log of order and feed
 * 
 * @param mixed $feed_id
 * @param mixed $order_id
 */
 public function get_feed_log($feed_id,$order_id,$object,$parent_id=""){
          global $wpdb;
 $table= $this->get_table_name('log');
 $sql= $wpdb->prepare('SELECT * FROM '.$table.' where order_id = %d and feed_id = %d and crm_id!="" and object=%s  and parent_id=%d order by id desc limit 1',$order_id,$feed_id,$object,$parent_id);
$results = $wpdb->get_row( $sql ,ARRAY_A );


return $results;
 } 
  /**
  * Push order to salesforce feeds
  *     
  * @param mixed $order_id
  * @param mixed $status
  */
  public function push($order_id,$status="user",$log=array()){ 

  $log_id=''; self::$processing_feed=true; 
  if(is_array($log) && !empty($log)){
      if(isset($log['id'])){
   $log_id=$log['id'];
      }
   $feeds=array($log['feed_id']);  
  }else{   
  //get feeds of a form
  $feeds= get_posts( array(
  'post_type'           => $this->id,
  'ignore_sticky_posts' => true,
  'nopaging'            => true,
  'post_status'         => 'publish',
  'fields'              => 'ids'
  ) ); 
  } 
  //$status="submit";


  if(is_array($feeds) && count($feeds)>0){
     
     if(isset($log['__vx_data'])){
   self::$order=$log['__vx_data'];     
     }else{ 
       self::$order=$order=get_post_meta($order_id); 
  if(!is_array($order) || count($order) == 0){
      $this->log_msg('Order #'.$order_id.' Not Found');
  return;   
  }
  
$post = get_post( $order_id );
  if($post->post_type != "shop_order"){
      $this->log_msg('Order #'.$order_id.' order type is not valid');
    return;  
  }
  self::$_order=$_order = new WC_Order($order_id); 
   $order_status=$_order->get_status();
   if(!$order_status){
       $this->log_msg('Order #'.$order_id.' - order status is not valid');
       return;
   }
  $date= !empty($post->post_date) ? $post->post_date : current_time( 'mysql' ); 
  $order['_order_date']=$date;
  $order['_order_id']=$order_id;
  if(!isset($order['_completed_date'])){
   $order['_completed_date']=$date;   
  }

  self::$order=$order=apply_filters('vx_crm_post_fields', $order ,$order_id,'wc',''); 
     }
  $feeds_meta=array(); 
   $k=1000; $e=2000; $i=1;
  foreach($feeds as $id){
  $feed=get_post_meta($id,$this->id.'_meta',true);
  if(!is_array($feed)){
  continue;  
}
  $feed['id']=$id; 
$object=$this->post('object',$feed); 
 if(!empty($feed['contract_check']) || !empty($feed['account_check'])){
  if($object == 'Order'){
     $feeds_meta[$e++]=$feed; 
  }else{
  $feeds_meta[$k++]=$feed; 
  }
 }else{
     $feeds_meta[$i++]=$feed; 
 }
  }

  ksort($feeds_meta);  
       $data=$res=array(); $msg=""; $notice=""; $class="updated";  $error=""; 
 
       foreach($feeds_meta as $feed){
   
  $id=$this->post('id',$feed);

  self::$feeds_res[$id]=array();
   $no_filter=true;
   
  $account=$this->post('account',$feed);
   $info=$this->get_info($account); 
       $info_data=array();
  if(isset($info['data'])){
$info_data=$info['data'];
  }
   $api_type=$this->post('api',$info_data);
   $meta=$this->post('meta',$info);
if(is_array($feed) && is_array($meta)){
   $feed=array_merge($meta,$feed); 
}

  $object=$this->post('object',$feed);
  $map=$this->post('map',$feed);
   if($api_type =="web"){
  $feed['fields']=$this->web_fields($object,$map);  
  }
  $fields=isset($feed['fields']) ? $feed['fields'] : array(); 
  if(!is_array($fields) || count($fields) == 0 || empty($object) || empty($object)){
    continue;  
  }
  
  $parent_id=0;
  if(isset(self::$order['__vx_parent_id'])){
  $parent_id=self::$order['__vx_parent_id'];    
  }
    $temp=array();
      $force_send=false;
      $post_comment=true;

  //filter optin condition + events
  if($status !="" ){   //it is not submitted by admin
  
 if( in_array($status,array('restore','update','delete','add_note','delete_note'))){

      // web2lead does not supports notes , delete/update object
     if($api_type =="web"){
       continue;  
     }
   if($status == 'delete_note' && !empty(self::$note)){
         $parent_id=self::$note['id'];
   }
   $search_object=$object;
    if(in_array($status,array('delete_note','add_note'))){
        //check feed
    $order_notes=$this->post('order_notes',$feed); //if notes sync not enabled in feed return
    if( empty($order_notes) ){
        continue;
    }
    
         //change main object to Note
         $feed['related_object']=$object;
        $object=$feed['object']='Note';   
 }
 if($status == 'delete_note'){
//when deleting note search note object 
     $search_object='Note';
 }
$feed_log=$this->get_feed_log($id,$order_id,$search_object,$parent_id); 

 if($status == 'restore' && $feed_log['status'] != 5) { // only allow successfully deleted records
     continue;
 }
  if( in_array($status,array('update','delete') ) && !in_array($feed_log['status'],array(1,2) )  ){ // only allow successfully sent records
     continue;
 }

if(empty($feed_log['crm_id']) || empty($feed_log['object']) || $feed_log['object'] != $search_object){ //feed + log entry validation
   continue; 
}

if($status !='restore'){ //restore is like normal send to crm
$feed['crm_id']=$feed_log['crm_id'];
unset($feed['primary_key']);

}
  $feed['event']=$status;   
// add note and save related extra info
 if( $status == 'add_note' && !empty(self::$note)){
    $temp=array('Title'=>array('value'=>self::$note['title']),'Body'=>array('value'=>self::$note['body']),'ParentId'=>array('value'=> $feed['crm_id']));  
$parent_id=self::$note['id']; 
 $feed['note_object_link']='<a href="'.$feed_log['link'].'" target="_blank">'.$feed_log['crm_id'].'</a>';
 } 
 // delete not and save extra info
 if( $status == 'delete_note'){
     
     $feed_log_arr= json_decode($feed_log['extra'],true);
     if(isset($feed_log_arr['note_object_link'])){
         $feed['note_object_link']=$feed_log_arr['note_object_link'];
     }
  $temp=array('ParentId'=>array('value'=> $feed['crm_id']));     
 }
 //delete object
 if( $status == 'delete'){
   $temp=array('Id'=>array('value'=> $feed['crm_id']));      
 }
//
  if(!in_array($status, array('update','restore'))){  //other case settings(add note, delete note, delete)
        //do not apply filters on all other cases(add note, delete note, delete)
      $force_send=true; 
  }

       //do not post comment in al other cases 
     $post_comment=false; 

 } //var_dump(self::$note,$object,$feed['note_object'],$feed['object'],$feed['crm_id'],$feed['event'],$temp,$feed_log); die();

  // filter on basis of events
  if($feed['event'] == "manual" && $status=="user") //on order submission by user , if set to manual , do not post order
  continue; 
  if($feed['event'] != $status) // if event is set , if event not matched , then continue
  continue;
  //if user created account
  $user=$_order->get_user();
  if($status == "user_created" && !$user )
  continue;
  } 

  //if submitted by admin, $status is empty , so always continue on admin submit button
  if(!$force_send && isset($feed['map']) && count($feed['map'])>0){
      //add new fields found in map

      self::$order=$order=apply_filters('vx_crm_add_map_fields', self::$order ,$order_id,$feed,'wc','');

  foreach($feed['map'] as $k=>$v){ 
  if(isset($v['field'])){
    if(!isset($fields[$k])){ 
      continue;
  } 

  $field=$fields[$k]; 
  $value=$this->get_field_val($v);

  if($api_type == "web"){ 
      if(isset($field['name_c'])){
     $k=$field['name_c']; //custom fields
     $field['label']=$k;     
      }else{ //constant web fields
     $k=$field['name'];     
      }
  if(empty($k)){
      $value=false;
  }
  }
if($value !==false){ //custom value
$f=array("value"=>$value,"label"=>$field['label']);
if($value === ''){
  $f['field']=$v['field'];   
  }
  $temp[$k]=$f;          
  }
  }
  }

  //change owner id
  if(isset($feed['owner']) && !empty($feed['user'])){
   $temp['OwnerId']=array('value'=>apply_filters('vx_assigned_user_id',$feed['user'],$this->id,$feed['id'],$order,$_order) ,'label'=>'Owner ID');   
  }

  //add account or contract

    if(!empty($feed['contract_check']) && !empty($feed['object_contract'])){
     $contract_feed=$feed['object_contract']; 
       if( isset(self::$feeds_res[$contract_feed]) ){

   $contract_res=self::$feeds_res[$contract_feed];
  /////
  if(!empty($contract_res['id'])){
   $temp['ContractId']=array('value'=> $contract_res['id'],'label'=>'Contract ID');   
  }else{ //if empty continue
 //     continue;
  }    
   }
    } 
    if(!empty($feed['account_check']) && !empty($feed['object_account'])){
     $account_feed=$feed['object_account']; 
   if( isset(self::$feeds_res[$account_feed]) ){

   $account_res=self::$feeds_res[$account_feed];
  /////
  if(!empty($account_res['id'])){
   $temp['AccountId']=array('value'=> $account_res['id'],'label'=>'Account ID');   
  }else{ //if empty continue
   //   continue;
  }    
   }  

  }
  //
        if(!empty($feed['note_check']) && !empty($feed['note_fields']) && is_array($feed['note_fields'])){
          $entry_note=''; $entry_note_title='';
          foreach($feed['note_fields'] as $e_note){ 
               $value=$this->get_field_val(array('field'=>$e_note));
           if(!empty($value)){ 
               if(!empty($entry_note)){
                   $entry_note.="\n";
               }
           $entry_note.=$value;    
           }   
           if(empty($entry_note_title)){
            $entry_note_title=substr($entry_note,0,100);   
           }
          }
          if(!empty($entry_note)){
     $feed['__vx_entry_note']=array('Title'=>$entry_note_title,'Body'=>$entry_note);      
          }

  }
  
  }   
 
  if(isset($_REQUEST['bulk_action']) && $_REQUEST['bulk_action'] =="send_to_crm_bulk_force" && !empty($log_id)){
      $force_send=true;
  }
  if(!$force_send && $this->post('optin_enabled',$feed) == "1" ){  //check filters if not sending by force and optin condition enabled  //&& $api !="web"
  $no_filter=$this->check_filter($feed);

  $res=array("status"=>"4","extra"=>array("filter"=>$this->filter_condition),"data"=>$temp);
  } 
    /* if($this->post('class',$info) !="updated"){
  return;
  }     */ 
 
  if($no_filter){
  //get feeds    
  $api=$this->get_api($info);  
  $res=$api->push_object($object,$temp,$feed); 
  }   
  $res['time']=current_time('timestamp');
  $res['object']=$feed['object'];
self::$feeds_res[$id]=$res;
  if(empty($res['status'])){
  $class="error"; 
  }
  if(isset($res['error']) && $res['error']!="" && !is_admin()){
$this->send_error_email($order_id,$info,$res);
  } 
  //   $settings=get_option($this->type.'_settings',array());
  //insert log
//  if($this->post('disable_log',$settings) !="yes"){
  $arr=array("object"=>$feed["object"],"order_id"=>$order_id,"crm_id"=>$this->post('id',$res),"meta"=>$this->post('error',$res),"time"=>date('Y-m-d H:i:s'),"status"=>$this->post('status',$res),"link"=>$this->post('link',$res),"data"=>$temp,"response"=>$this->post('response',$res),"extra"=>$this->post('extra',$res),"feed_id"=>$id,'parent_id'=>$parent_id,'event'=>$status); 
  $log_id_i=$this->__log($arr,$log_id);
  if($log_id_i!=""){ //   
  $res['log_id']=$log_id_i;
  } 
 // }
  $note_text=$this->format_note($res,true); 
  if($notice!=""){
  $notice.="\n";
  } 
  $notice.=$note_text;  
  if($notice!=""){
 // $_order->add_order_note($notice, false); 
  } 

  if(count($res)>0){
  $data[]=$res; 
  }   
  }
    //send crm response to other plugins
  do_action('crm_response_'.$this->id,$res);
  if(count($data)>0){
      if($post_comment){
  //update_post_meta($order_id,$this->id.'_post',$data);
      } 
  return array("class"=>$class,"msg"=>nl2br($notice)); // for multiple feed and multiple messages
  }
  
  }

  return false;
  
  }

  /**
  * notify error email
  * 
  * @param mixed $order_id
  * @param mixed $info
  * @param mixed $res
  */
  public function send_error_email($order_id,$info,$res){
         if(!empty($info['data']['error_email'])){
  $subject=__('Error While Posting to Salesforce','woocommerce-salesforce-crm');
$page_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; 

  $order_url='<a href="'.add_query_arg(array('post'=>$order_id,'action'=>'edit'), admin_url('post.php')).'" target="_blank">'.$order_id.'</a>';
  $email_info=array("msg"=>$res['error'],"title"=>__("Salesforce Error",'woocommerce-salesforce-crm'),"info_title"=>"More Detail","info"=>array("Order Id"=>$order_url,"Time"=>date('d/M/y H:i:s',current_time('timestamp')),"Page URL"=>'<a href="'.$page_url.'" style="word-break:break-all;">'.$page_url.'</a>'));
  $email_body=$this->format_user_info($email_info,true); 
  $error_emails=explode(",",$info['data']['error_email']); 
  $headers = array('Content-Type: text/html; charset=UTF-8');
  foreach($error_emails as $email)   
  wp_mail(trim($email),$subject, $email_body,$headers);
  } 
  }
    /**
  * check if other version of this plugin exists
  * 
  */
  public function other_plugin_version(){ 
  $status=0;
  if(class_exists('vxc_saless_wp')){
      $status=1;
  }else if( file_exists(WP_PLUGIN_DIR.'/woocommerce-salesforce-crm/woocommerce-sales-crm.php')) {
  $status=2;
  } 
  return $status;
  }
  /**
  * get field value from woocommerce order
  * 
  * @param mixed $map_field
  */
  public function get_field_val($map_field){
      $order=self::$order;

        if($this->post('type',$map_field) == ""){
  $type="field";    
  $f_key=$map_field[$type];
  }else{
  $type=$map_field['type'];
  $f_key=$map_field[$type];
  } $value='';
if($this->post($type,$map_field) == ""){
return false;
}  
if(in_array($type,array("field","custom"))){ 
  //if value stored in order
  if(strpos($f_key,"__vx_wp-") ===0){ //if user field
  $f_key=substr($f_key,8); 
  if($this->user == "" && isset($order['_customer_user'][0])){ //get user if not set
  $this->user=get_userdata($order['_customer_user'][0]);
  } 
  if($f_key !="" && isset($this->user->$f_key)){ //user fields
  $value=$this->user->$f_key;      
  }   
  }else{ // general fields 
  if(isset($order[$f_key])){
  if( is_array($order[$f_key])){ 
  $value=maybe_unserialize($order[$f_key][0]);
  }else{
    $value=$order[$f_key];  
  }
  //// verify address fieods
  $value=$this->verify_address($value,$f_key,$order);
  }else{ //get order info fields
   $value=$this->order_info_fields($f_key);   
  }
  } 
  if(is_array($value)){  
  $value=implode("; ",$value);    
  }    
  }else{ //custom value
  $value=$map_field['value'];
        
  }
return $value;
  }
  /**
  * Initialize salesforce api
  *  
  * @param mixed $crm
  * @return VXSalesforceAPI
  */
  public function get_api($crm){
  $api = false;
  $api_class=$this->id."_api";
  if(!class_exists($api_class))
  require_once(self::$path."api/api.php");
  
  $api = new $api_class($crm);
  return $api;
  }
  /**
  * update account
  * 
  * @param mixed $data
  * @param mixed $id
  */
  public function update_info($data,$id) {
global $wpdb;
if(empty($id)){
    return;
}
 $table= $this->get_table_name('accounts');
 $time = current_time( 'mysql' ,1);

  $sql=array('updated'=>$time);
  if(is_array($data)){
 
  
    if(isset($data['meta'])){
  $sql['meta']= json_encode($data['meta']);    
  }
  if( isset($data['data']) && is_array($data['data'])){
      
       if(array_key_exists('time' , $data['data']) && empty($data['data']['time'])){
  $sql['time']= $time;    
  $sql['status']= '2';    
  } 
  if(isset($data['data']['class'])){
  $sql['status']= $data['data']['class'] == 'updated' ? '1' : '2'; 
  }
  if(isset($data['data']['meta'])){
      unset($data['data']['meta']);
  }
  if(isset($data['data']['status'])){
      unset($data['data']['status']);
  }
  if(isset($data['data']['name'])){
     $sql['name']=$data['data']['name']; 

  }else if(isset($_GET['id'])){
       $sql['name']="Account #".$_GET['id']; 
  }

    $str=json_encode($data['data']);
  $enc_str=$this->en_crypt($str);
  $sql['data']=$enc_str;
  }
  } 
     
$result = $wpdb->update( $table,$sql,array('id'=>$id) );

return $result;
}
  /**
  * Get Objects from local options or from salesforce
  *     
  * @param mixed $check_option
  * @return array
  */
  public function get_objects($api_type="",$info="",$refresh=false){
 
      $web_objects=array("Lead"=>"Lead","Case"=>"Case");
  if($api_type == "web"){
  return $web_objects;
  }
  if(empty($info)){
     $option=get_option($this->id.'_meta',array());
     return !empty($option['objects']) ? $option['objects'] : '';  
  }
   $objects=array();      
   $meta=$this->post('meta',$info);  

   if(! isset($meta['objects'])){
    $refresh=true;   
   }else{
     $objects=$meta['objects'];  
   } 
  //get objects from salesforce
 if($refresh){
  $api=$this->get_api($info); 
  $objects=$api->get_crm_objects(); 

  if(is_array($objects)){
  $option=get_option($this->id.'_meta',array());
  $option_objects=array_merge($objects,$web_objects);
  if(!empty($option['objects']) && is_array($option['objects'])){
   $option_objects=array_merge($option_objects,$option['objects']);   
  }
  $option['objects']=$option_objects;
  
  update_option($this->id.'_meta',$option); //save objects for logs search option
  $meta["objects"]=$objects;
  $this->update_info(array("meta"=>$meta),$info['id']);
  }
 }  
  return $objects;    
 }
 

  


}  

endif;
$vxc_sales=new vxc_sales(); 
$vxc_sales->instance();
$vx_wc['vxc_sales']='vxc_sales';

