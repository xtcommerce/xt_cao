<?php
/*
 #########################################################################
 #                       xt:Commerce VEYTON 4.0 Shopsoftware
 # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
 #
 # Copyright 2007-2011 xt:Commerce International Ltd. All Rights Reserved.
 # This file may not be redistributed in whole or significant part.
 # Content of this file is Protected By International Copyright Laws.
 #
 # ~~~~~~ xt:Commerce VEYTON 4.0 Shopsoftware IS NOT FREE SOFTWARE ~~~~~~~
 #
 # http://www.xt-commerce.com
 #
 # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
 #
 # @version $Id: class.xt_cao.php 6241 2013-04-15 12:56:27Z mario $
 # @copyright xt:Commerce International Ltd., www.xt-commerce.com
 #
 # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
 #
 # xt:Commerce International Ltd., Kafkasou 9, Aglantzia, CY-2112 Nicosia
 #
 # office@xt-commerce.com
 #
 #########################################################################
 */

defined('_VALID_CALL') or die('Direct Access is not allowed.');


include_once _SRV_WEBROOT.'xtFramework/admin/classes/class.adminDB_DataSave.php';
include_once _SRV_WEBROOT.'xtFramework/classes/class.MediaFileTypes.php';
include_once _SRV_WEBROOT.'xtFramework/classes/class.MediaData.php';
include_once _SRV_WEBROOT.'xtFramework/classes/class.MediaImages.php';
include_once _SRV_WEBROOT.'xtFramework/classes/class.image.php';
include_once _SRV_WEBROOT.'xtFramework/classes/class.upload.php';
include_once _SRV_WEBROOT.'xtFramework/classes/class.FileHandler.php';
include_once _SRV_WEBROOT.'xtFramework/classes/class.MediaGallery.php';

class xt_cao {

	protected $version = '4.430';
	protected $version_data = '2013.03.15';
	var $store_id = '1' ;

	function xt_cao() {
		global $language,$tax,$store_handler;

		$lng = $language->_getLanguageList('store');
		$this->LNG = array();
		foreach ($lng as $key => $val) {
			$this->LNG[$val['code']]=$val;
		}
		$this->store_id =  $store_handler->shop_id;
	}

    function _logData($arr) {
        global $logHandler;
        $logHandler->Log2File('log.txt','plugins/xt_cao/',$arr);
    }


	/**
	 * check login credentials
	 *
	 * @param string $user
	 * @param string $pass
	 * @return boolean
	 */
	public function checkLogin($user='',$pass='') {
		global $filter,$db;
		$sql = "SELECT  user_id FROM ".TABLE_ADMIN_ACL_AREA_USER." where handle='".$filter->_filter($user)."' and user_password='".$filter->_filter($pass)."' and group_id =1";
		$rs =  $db->Execute($sql);
		if ($rs->RecordCount()==0) return false;
		return true;
	}

	public function loginFailed() {
		$status['code']='105';
		$status['action']=$_POST['action'];
		$status['message']='WRONG LOGIN';
		$status['mode']='';
		return $this->statusXMLTag($status);
	}

	public function _GETHandler($action,$data) {

		switch($action) {

			case 'orders_export':
				return $this->_getOrders();
				break;
					
			case 'manufacturers_export':
				return $this->_getManufacturers();
				break;

			case 'categories_export':
				return $this->_getCategories();
				break;
					
			case 'products_export':
				return $this->_getProducts();
				break;
					
			case 'version':
				return $this->_getVersion();
				break;

			case 'customers_export':
				return $this->_getCustomers();
				break;
					
		}

	}

	public function _POSTHandler($action,$data) {

		switch($action) {

			// Customers

			case 'customers_update':
				return $this->_setCustomer();
				break;
					
			case 'customers_erase':
				return $this->_delCustomer();
				break;

			case 'order_update':
				return $this->_setOrder();
				break;
					

				// categories
			case 'categories_update':
				return $this->_setCategories();
				break;

			case 'prod2cat_update':
				return $this->_setProducts2Categories();
				break;
					
			case 'prod2cat_erase':
				return $this->_delProducts2Categories();
				break;
					
			case 'categories_image_upload':
				return $this->_setCategoriesImage();
				break;

				// TODO categories_erase
			case 'categories_erase':
				return 'not supported';
				break;


				// manufacturers
			case 'manufacturers_update':
				return $this->_setManufacturers();
				break;

			case 'manufacturers_erase':
				return $this->_delManufacturers();
				break;
					
			case 'manufacturers_image_upload':
				return $this->_setManufacturersImage();
				break;

					
				// products

			case 'products_image_upload': // image 1
			case 'products_image_upload_med': // image 2
			case 'products_image_upload_large': // image 3
				return $this->_setProductsImage();
				break;

			case 'products_update':
				return $this->_setProducts();
				break;

			case 'products_erase':
				return $this->_delProducts();
				break;

				// TODO products_specialprice_update
				// TODO products_specialprice_erase




			default: // send not suported

				break;
					
		}

	}


	/**
	 * add Image
	 *
	 * @return unknown
	 */
	private function _setProductsImage() {
		return $this->uploadImage('products_image','product');
	}


	/**
	 * delete product
	 *
	 * @return unknown
	 */
	private function _delProducts() {

		$products_id  = (int)$_POST['prodid'];

		if ($products_id>0) {

			$obj = new stdClass;
			$product = new product;
			$product->setPosition('admin');
			$obj = $product->_unset($products_id);

			$status = array();
			$status['code']='0';
			$status['message']='OK';
			return $this->statusXMLTag($status);
		} else {
			$status = array();
			$status['code']='99';
			$status['message']='PARAMETER ERROR';
			return $this->statusXMLTag($status);
		}



	}

	/**
	 * add/set product
	 *
	 * @return unknown
	 */
	private function _setProducts() {
		global $language,$tax,$price,$db,$seo;

		$_data = array();

		// default
		$_data['products_id']=$_POST['pID'];
		$_data['products_model']=$_POST['products_model'];
		$_data['products_ean']=$_POST['products_ean'];
		$_data['products_image']=$_POST['products_image'];
		$_data['products_weight']=$_POST['products_weight'];
		$_data['products_status']=(int)$_POST['products_status'];
		$_data['manufacturers_id']=$_POST['manufacturers_id'];
		$_data['products_quantity']=$_POST['products_quantity'];
		$_data['products_store_id']=$this->store_id;
		

		// price etc
		$_data['products_price']=(float)$_POST['products_price'];
		$_data['products_tax_class_id']=XT_CAO_TAX_ID_1;
		if ($_POST['products_tax_class_id']=='2') $_data['products_tax_class_id']=XT_CAO_TAX_ID_2;

		$taxrate = $tax->_getTaxRates($_data['products_tax_class_id']);

		if ($taxrate>0 && _SYSTEM_USE_PRICE=='true') {

			$_data['products_price']=$price->_AddTax($_data['products_price'],$taxrate);
		}

		// build lang vars
		foreach ($language->_getLanguageList('store') as $key => $val) {
            if ($val['code']=='de') $val['languages_id'] = 2;
			$_data['products_name_'.$val['code']] = $this->utf8helper($_POST['products_name'][$val['languages_id']]);
			$_data['products_description_'.$val['code']] = $this->utf8helper($_POST['products_shop_long_description'][$val['languages_id']]);
			$_data['products_short_description_'.$val['code']] = $this->utf8helper($_POST['products_shop_short_description'][$val['languages_id']]);


			$_data['meta_description_'.$val['code']] = $this->utf8helper($_POST['products_meta_description'][$val['languages_id']]);
			$_data['meta_keywords_'.$val['code']] = $this->utf8helper($_POST['products_meta_keywords'][$val['languages_id']]);
			$_data['meta_title_'.$val['code']] = $this->utf8helper($_POST['products_meta_title'][$val['languages_id']]);

			// update seo urls
			$_seo_data=array();

			//$_seo_data['url_text_'.$val['code']] = str_replace(" ","-",$data['products_name_'.$val['code']]);
			$_seo_data['url_text_store'.$this->store_id.'_'.$val['code']] = str_replace(" ","-",$data['products_name_store'.$this->store_id.'_'.$val['code']]);

			//$_seo_data['url_text_'.$val['code']] = $seo->filterAutoUrlText($data['url_text_'.$val['code']],$val['code'],'product',$data['products_id']);
			$_seo_data['url_text_store'.$this->store_id.'_'.$val['code']] = $seo->filterAutoUrlText($data['url_text_store'.$this->store_id.'_'.$val['code']],$val['code'],'product',$data['products_id']);

			$seo->_UpdateRecord('product',$_data['products_id'], $val['code'], $_seo_data,true,"true",$this->store_id);
			$this->_logData('SEO: product'.$_data['products_id']."-".$val['code']."-" .$this->store_id);

		}

		$obj = new stdClass;
		$product = new product;
		$product->setPosition('admin');
		$obj = $product->_set($_data);
		if ($obj->success) {
			
			// set image
			if ($_data['products_id']>0) {
			//	$product->_setImage($_data['products_id'],$_data['products_image']);
		//		$db->Execute('UPDATE '.TABLE_PRODUCTS." SET products_image='".$_data['products_image']."' WHERE products_id='".$_data['products_id']."'"); 
				
			} else {
			//	$product->_setImage($obj->new_id,$_data['products_image']);
		//		$db->Execute('UPDATE '.TABLE_PRODUCTS." SET products_image='".$_data['products_image']."' WHERE products_id='".$obj->new_id."'"); 
				
			}
			
			$status = array();
			$status['code']='0';
			$status['message']='OK';
			$status['products_id']=$obj->new_id;
			return $this->statusXMLTag($status);
		} else {
			$status = array();
			$status['code']='99';
			$status['message']='PARAMETER ERROR';
			$status['products_id']=$obj->new_id;
			return $this->statusXMLTag($status);
		}


	}

	/**
	 * update order status
	 *
	 * @return unknown
	 */
	private function _setOrder() {
		global $db;

		$orders_id = (int)$_POST['order_id'];
		$new_status = (int)$_POST['status'];

		// check if order exists
		$rs =  $db->Execute("select customers_id from ".TABLE_ORDERS." where orders_id='".$orders_id."'");
		if($rs->RecordCount()==0){
			$status = array();
			$status['ORDER_ID']=$orders_id;
			$status['ACTION']='order_update';
			$status['CODE']='2';
			$status['MESSAGE']='Order not found';
			$arr=array();
			$arr['STATUS']['STATUS_DATA'] = $status;
			return $this->transformXML($arr);
		}

		$order = new order($orders_id,$rs->fields['customers_id']);
		$send_mail = 'false';
		$comments='';
		if ($_POST['notify'] =='on') $send_mail='true';
		$order->_updateOrderStatus($new_status,$comments,$send_mail,'true','CAO','');

		$status = array();
		$status['ORDER_ID']=$orders_id;
		$status['ORDER_STATUS']=$new_status;
		$status['ACTION']='order_update';
		$status['CODE']='0';
		$status['MESSAGE']='OK';
		$arr = array();
		$arr['STATUS']['STATUS_DATA'] = $status;
		return $this->transformXML($arr);
	}

	/**
	 * get orders
	 *
	 * @return unknown
	 */
	private function _getOrders() {
		global $db;

		$from = (int)$_GET['order_from'];
		$status = (int)$_GET['order_status'];


		$qry ="SELECT * FROM " . TABLE_ORDERS . " WHERE orders_id >= '" . $from . "'";
		if ($status>0)
		$qry .= "AND orders_status = " . $status;

		$rs = $db->Execute($qry);
		//	echo 'xx';
		if ($rs->RecordCount()>0) {
			$o_data = array();
			while (!$rs->EOF) {

				$order = array();

				$order = new order($rs->fields['orders_id'],$rs->fields['customers_id']);

				// header
				$header = array();
				$header['ORDER_ID'] = $rs->fields['orders_id'];
				$header['CUSTOMER_ID'] = $rs->fields['customers_id'];
				$header['CUSTOMER_CID'] = $rs->fields['customers_cid'];
				$header['CUSTOMER_GROUP'] = $rs->fields['customers_status'];
				$header['ORDER_DATE'] = $rs->fields['date_purchased'];
				$header['ORDER_STATUS'] = $rs->fields['orders_status'];
				$header['ORDER_IP'] = '';
				$header['ORDER_CURRENCY'] = $rs->fields['currency_code'];
				$header['ORDER_CURRENCY_VALUE'] = $rs->fields['currency_value'];

				$o_data['ORDER_HEADER'] = $header;
				// header end

				//billing address
				$o_data['BILLING_ADDRESS']=$this->extractAddress($rs->fields,'billing');
				$o_data['BILLING_ADDRESS'] = $this->_filterExport($o_data['BILLING_ADDRESS']);
				// delivery address
				$o_data['DELIVERY_ADDRESS']=$this->extractAddress($rs->fields,'delivery');
				$o_data['DELIVERY_ADDRESS'] = $this->_filterExport($o_data['DELIVERY_ADDRESS']);

				// payment type
				$o_data['PAYMENT']['PAYMENT_METHOD'] = $order->order_data['payment_code'];
				$o_data['PAYMENT']['PAYMENT_CLASS'] = $order->order_data['payment_code'];
				// shipping
				$o_data['SHIPPING']['SHIPPING_METHOD'] = $order->order_data['shipping_code'];
				$o_data['SHIPPING']['SHIPPING_CLASS'] = $order->order_data['shipping_code'];

				//products
				$o_data['ORDER_PRODUCTS'] = $this->extractOrdersProducts($order->order_products);

				// total
				$o_data['ORDER_TOTAL']=$this->extractOrderTotal($order->order_total_data,$order->order_total);

				$orders['ORDER_INFO'][]=$o_data;
				$rs->MoveNext();
			}

			$ret = array();
			$ret['ORDER'] = $orders;
			return $this->transformXML($ret);

		} else { // no orders
			$status = array();
			$status['code']='0';
			$status['message']='PARAMETER ERROR';
			//	return $this->statusXMLTag($status);
		}


	}

	private function _getProducts() {
		global $db,$tax;

		$limit = '';
		if (isset($_GET['products_from'])) {
			$from = (int)$_GET['products_from'];
			$cnt  = (int)$_GET['products_count'];
			if (!isset($cnt)) $cnt = 500;
			$limit = " LIMIT ".$from.",".$cnt;
		}

		$rs = $db->Execute("SELECT * FROM ".TABLE_PRODUCTS.$limit);
		$prod=array();
		while (!$rs->EOF) {

			$ls = $db->Execute("SELECT * FROM ".TABLE_PRODUCTS_DESCRIPTION." pd, ".TABLE_SEO_URL." su WHERE pd.language_code=su.language_code and su.link_type=1 and su.link_id=pd.products_id and pd.products_id='".$rs->fields['products_id']."'");
			$prod_info = array();
			$i=0;
			$tmp= '';
			while (!$ls->EOF) {


				$tmp = array('NAME'=>$ls->fields['products_name'],
							'URL'=>$ls->fields['products_url'],
							'DESCRIPTION'=>$ls->fields['products_description'],
							'SHORT_DESCRIPTION'=>$ls->fields['products_short_description'],
							'META_TITLE'=>$ls->fields['meta_title'],
							'META_DESCRIPTION'=>$ls->fields['meta_description'],
							'META_KEYWORDS'=>$ls->fields['meta_keywords']);

				$tmp = $this->_filterExport($tmp);
				$prod_info[$i] = $tmp;
				$prod_info[$i.' attr'] = array('ID'=>$this->LNG[$ls->fields['language_code']]['languages_id'],'CODE'=>$ls->fields['language_code'],'NAME'=>$this->LNG[$ls->fields['language_code']]['name']);



				$ls->MoveNext();
				$i++;
			}

			$tax_rate = 0;
			$tax_rate = $tax->data[$rs->fields['products_tax_class_id']];
			if (is_array($tmp)) {
				$prod['PRODUCT_INFO'][]['PRODUCT_DATA'] = array(
								'PRODUCT_ID' => $rs->fields['products_id'],
            					'PRODUCT_DEEPLINK'=>'',
            					'PRODUCT_QUANTITY' => $rs->fields['products_quantity'],
            					'PRODUCT_MODEL' => $rs->fields['products_model'],
            					'PRODUCT_FSK18' => $rs->fields['products_fsk18'],
            					'PRODUCT_IMAGE' => $rs->fields['products_image'],
								'PRODUCT_WEIGHT' => $rs->fields['products_weight'],
            					'PRODUCT_STATUS' => $rs->fields['products_status'],
            					'PRODUCT_TAX_CLASS_ID' => $rs->fields['products_tax_class_id'],
            					'PRODUCT_TAX_RATE' => $tax_rate,
            					'MANUFACTURERS_ID'  => $rs->fields['manufacturers_id'],
            					'PRODUCT_DATE_ADDED' => $rs->fields['date_added'],
            					'PRODUCT_LAST_MODIFIED' => $rs->fields['last_modified'],
            					'PRODUCT_DATE_AVAILABLE' => $rs->fields['date_available'],
            					'PRODUCTS_ORDERED'  => $rs->fields['products_ordered'],
								'PRODUCT_PRICE' => $rs->fields['products_price'],
								'PRODUCT_DESCRIPTION'=>$prod_info);
			}
			$rs->MoveNext();
		}




		$ret['PRODUCTS'] = $prod;
		return $this->transformXML($ret);
	}

	private function _delCustomer() {
		global $db;

		$rs = $db->Execute("SELECT * FROM ".TABLE_CUSTOMERS ." WHERE customers_id ='" . (int)$_POST['cID'] . "' LIMIT 1");


		if ($rs->RecordCount() > 0){
			 
			$getDefaultAdress = $db->Execute("DELETE FROM ".TABLE_CUSTOMERS_ADDRESSES ."
															 WHERE customers_id='" . (int)$_POST['cID'] . "' ");
				
			$rs = $db->Execute("DELETE FROM ".TABLE_CUSTOMERS ." WHERE customers_id ='" . (int)$_POST['cID'] . "' ");

			$status = array();
			$status['code']='0';
			$status['action']='customers_erase';
			$status['message']='OK';
			$status['mode']='SQL_RES1';
			return $this->statusXMLTag($status);

		}else{
			 
			$status = array();
			$status['code']='0';
			$status['action']='customers_erase';
			$status['message']='OK';
			$status['mode']='SQL_RES1';
			return $this->statusXMLTag($status);

			 
			 
		}

	}

	// Get adress_book_id from table customer_adresses
	private function getAdressBookId($customers_id){

		global $db;

		$getDefaultAdress = $db->Execute("SELECT address_book_id
											 FROM ".TABLE_CUSTOMERS_ADDRESSES ." 
											 WHERE customers_id='" . (int)$customers_id . "' 
											 and address_class ='default'");
			
		if($getDefaultAdress->RecordCount() > 0){
				
			$getadress = $getDefaultAdress->FetchRow();

			return $getadress['address_book_id'];
		}

	}

	// Insert new Customer
	private function _setCustomer()
	{

		global $db;
		 
		$customers_id = -1;

		if (isset($_POST['cID'])) $customers_id =  $_POST['cID'] ;

		 
		$data  = array();
		if (isset($_POST['customers_cid'])) $data['customers_cid'] = $_POST['customers_cid'];
		if (isset($_POST['customers_email'])) $data['customers_email_address'] = $this->utf8helper($_POST['customers_email']);
		if (isset($_POST['customers_password']))
		{
			$data['customers_password'] = md5($_POST['customers_password']); // Generate Password
		}
		$address_data =array();
		if (isset($_POST['customers_firstname'])) $address_data['customers_firstname'] = $this->utf8helper($_POST['customers_firstname']);
		if (isset($_POST['customers_lastname'])) $address_data['customers_lastname'] = $this->utf8helper($_POST['customers_lastname']);
		if (isset($_POST['customers_company'])) $address_data['customers_company'] = $_POST['customers_company'];
		if (isset($_POST['customers_street'])) $address_data['customers_street_address'] = $_POST['customers_street'];
		if (isset($_POST['customers_city'])) $address_data['customers_city'] = $_POST['customers_city'];
		if (isset($_POST['customers_postcode'])) $address_data['customers_postcode'] = $_POST['customers_postcode'];
		if (isset($_POST['customers_gender'])) $address_data['customers_gender'] = $_POST['customers_gender'];
		if (isset($_POST['customers_country_id'])) $address_data['customers_country_code'] = $_POST['customers_country_id'];


		$rs = $db->Execute("SELECT * FROM ".TABLE_CUSTOMERS ." WHERE customers_cid ='" . (int)$customers_id . "' LIMIT 1");

	 // Customer exists & Update Tables
		if ($rs->RecordCount() > 0){
			 
			$mode = 'UPDATE';

			$user = $rs->FetchRow();

			$update_record = array('customers_last_modified'=>$db->BindDate(time()));
			$customer_record = array_merge($update_record, $data);
			$db->AutoExecute(TABLE_CUSTOMERS, $customer_record, 'UPDATE', "customers_cid=".(int)$customers_id."");
				
			$_getAdressBookId = $this->getAdressBookId($user['customers_id']);

			if($_getAdressBookId){

				$adress_update_record = array('address_last_modified'=>$db->BindDate(time()));
				$adress_record = array_merge($adress_update_record, $address_data);
				$db->AutoExecute(TABLE_CUSTOMERS_ADDRESSES, $adress_record, 'UPDATE', "address_book_id=".$_getAdressBookId."");
					

			}else{
				$address_insert_record = array('date_added'=>$db->BindDate(time()));
				$address_data['customers_id'] = $customers_id;
				$address_data['address_class'] = "default";
				$adress_record = array_merge($address_insert_record, $address_data);
				$db->AutoExecute(TABLE_CUSTOMERS_ADDRESSES, $adress_record , 'INSERT');

			}
				
			 

		}
		else  // Customer not exists & Insert Data
		{
			$mode= 'APPEND';


			if (strlen($_POST['customers_password'])==0)
			{
					
				$sql_customers_data_array['customers_password']= $this->create_password();
			}


			$insert_record = array('date_added'=>$db->BindDate(time()));
				
			$customer_record = array_merge($insert_record, $data );
			$db->AutoExecute(TABLE_CUSTOMERS, $customer_record, 'INSERT');
			$new_customer_id = $db->Insert_ID();
				
			$address_data['customers_id'] = $new_customer_id;
			$address_data['address_class'] = "default";
				
				
			$address_record = array_merge($insert_record, $address_data);
			$db->AutoExecute(TABLE_CUSTOMERS_ADDRESSES, $address_record, 'INSERT');

		}

		 
		$status = array();
		$status['code']='0';
		$status['message']='OK';
		$status['mode']= $mode;
		return $this->statusXMLTag($status);


		 
	}


	private function _getCustomers() {
		global $db, $xtPlugin;

		$limit = '';
		if (isset($_GET['customers_from'])) {
			$from = (int)$_GET['customers_from'];
			$cnt = (int)$_GET['customers_count'];
			if (!isset($cnt)) $cnt = 500;
			$limit = " LIMIT ".$from.",".$cnt;
		}
		$cust = array();



		$rs = $db->Execute("SELECT * FROM ".TABLE_CUSTOMERS." c, ".TABLE_CUSTOMERS_ADDRESSES." ca WHERE c.customers_id=ca.customers_id AND ca.address_class='default' ".$limit);

		while (!$rs->EOF) {


			$tmp = array('CUSTOMERS_ID' => $rs->fields['customers_id'],
              			'CUSTOMERS_CID' => $rs->fields['customers_cid'],
			 			'EXTERNAL_ID' => $rs->fields['external_id'],
              			'GENDER' => $rs->fields['customers_gender'],
              			'COMPANY'  => $rs->fields['customers_company'],
              			'FIRSTNAME' =>  $rs->fields['customers_firstname'] ,
              			'LASTNAME' =>   $rs->fields['customers_lastname'] ,
              			'STREET' => $rs->fields['customers_street_address'],
              			'POSTCODE' => $rs->fields['customers_postcode'],
              			'CITY' => $rs->fields['customers_city'],
              			'SUBURB' => $rs->fields['customers_suburb'],
              			'STATE' => $rs->fields['customers_state'],
              			'COUNTRY' => $rs->fields['customers_country_code'],
              			'TELEPHONE' => $rs->fields['customers_phone'],
              			'FAX' => $rs->fields['customers_fax'],
              			'EMAIL'  => $rs->fields['customers_email_address'],
              			'BIRTHDAY' => $rs->fields['customers_dob'],
              			'DATE_ACCOUNT_CREATED' => $rs->fields['date_added']);
								
				
			($plugin_code = $xtPlugin->PluginCode('class.xt_cao.php:getCustomers_bottom')) ? eval($plugin_code) : false; 
				


			$tmp = $this->_filterExport($tmp);
			$cust['CUSTOMERS_DATA'][]=$tmp;
			$rs->MoveNext();
		}

		$ret['CUSTOMERS'] = $cust;
		return $this->transformXML($ret);
	}

	/**
	 * get script version
	 *
	 * @return unknown
	 */
	private function _getVersion() {

		$xml = array();
		$xml['ACTION']='version';
		$xml['CODE']='111';
		$xml['SCRIPT_VER']=$this->version;
		$xml['SCRIPT_DATE']=$this->version_data;

		$ret['STATUS']['STATUS_DATA'] = $xml;
		return $this->transformXML($ret);

	}

	private function _delManufacturers() {

		$manufacturers_id  = (int)$_POST['mID'];

		if ($manufacturers_id>0) {

			$obj = new stdClass;
			$manufacturer = new manufacturer;
			$obj = $manufacturer->_unset($manufacturers_id);

			$status = array();
			$status['code']='0';
			$status['message']='OK';
			return $this->statusXMLTag($status);
		} else {
			$status = array();
			$status['code']='99';
			$status['message']='PARAMETER ERROR';
			return $this->statusXMLTag($status);
		}

	}

	/**
	 * upload manufacturers image
	 *
	 * @return unknown
	 */
	private function _setManufacturersImage() {
		return $this->uploadImage('manufacturers_image','manufacturers');
	}

	/**
	 * add/update manufacturer
	 *
	 * @return xml
	 */
	private function _setManufacturers() {
		global $db,$language;

		$manufacturers_id = (int)$_POST['mID'];

		$data = array();

		$manufacturers_id = (int)$_POST['mID'];

		// default
		$data['manufacturers_id']=$manufacturers_id;
		$data['manufacturers_name']=$this->utf8helper($_POST['manufacturers_name']);
		$data['manufacturers_image']=$_POST['manufacturers_image'];
		$data['manufacturers_status']='1';
		$data['manufacturers_store_id']=$this->store_id;

		// build lang vars
		foreach ($language->_getLanguageList() as $key => $val) {
            if ($val['code']=='de') $val['languages_id'] = 2;
			//$data['manufacturers_description_'.$val['code']] = $_POST['descr'];
			$data['manufacturers_url_'.$val['code']] = $_POST['manufacturers_url'][$val['languages_id']];
			//	$data['meta_description_'.$val['code']] = $_POST['categories_meta_description'];
			//	$data['meta_keywords_'.$val['code']] = $_POST['categories_meta_keywords'];
			//	$data['meta_title_'.$val['code']] = $_POST['categories_meta_title'];
		}


		$obj = new stdClass;
		$manufacturer = new manufacturer;
		$obj = $manufacturer->_set($data);
		if ($obj->success) {
			$status = array();
			$status['code']='0';
			$status['message']='OK';
			return $this->statusXMLTag($status);
		} else {
			$status = array();
			$status['code']='99';
			$status['message']='PARAMETER ERROR';
			return $this->statusXMLTag($status);
		}


	}

	/**
	 * get Manufacturers
	 *
	 * @return xml
	 */
	private function _getManufacturers() {
		global $db;

		$rs = $db->Execute("SELECT * FROM ".TABLE_MANUFACTURERS);
		$man = array();
		if ($rs->RecordCount()>0) {

			while (!$rs->EOF) {

				$ls = $db->Execute("SELECT * FROM ".TABLE_MANUFACTURERS_DESCRIPTION." WHERE manufacturers_id='".$rs->fields['manufacturers_id']."'");
				$man_info = array();
				$i=0;
				while (!$ls->EOF) {

					$tmp = array('URL'=> $ls->fields['manufacturers_url'],
							'URL_CLICK'=>0,
							'DATE_LAST_CLICK'=>'');

					$man_info[$i] = $tmp;
					$man_info[$i.' attr'] = array('ID'=>$this->LNG[$ls->fields['language_code']]['languages_id'],'CODE'=>$ls->fields['language_code'],'NAME'=>$this->LNG[$ls->fields['language_code']]['name']);
					$ls->MoveNext();
					$i++;
				}



				$man['MANUFACTURERS_DATA'][] = array('ID'=>$rs->fields['manufacturers_id'],
												'EXTERNAL_ID'=>$rs->fields['external_id'],
												'NAME'=> $this->_filterExport($rs->fields['manufacturers_name']),
												'IMAGE'=> htmlspecialchars($rs->fields['manufacturers_image']), // CHANGE
												'DATE_ADDED'=> $rs->fields['date_added'],
												'LAST_MODIFIED'=>$rs->fields['last_modified'],
												'MANUFACTURERS_DESCRIPTION'=>$man_info);	

				$rs->MoveNext();
			}
		}

		$ret['MANUFACTURERS'] = $man;
		return $this->transformXML($ret);
	}

	/**
	 * upload categories image
	 *
	 * @return unknown
	 */
	private function _setCategoriesImage() {
		return $this->uploadImage('categories_image','default');
	}


	/**
	 * delete products to categories entry
	 *
	 * @return unknown
	 */
	private function _delProducts2Categories() {
		global $db;

		$products_id = (int)$_POST['prodid'];
		$categories_id  = (int)$_POST['catid'];

		if (!isset($_POST['prodid'])) {
			$status = array();
			$status['code']='99';
			$status['message']='PARAMETER ERROR';
			return $this->statusXMLTag($status);
		}
		if (!isset($_POST['catid'])) {
			$status = array();
			$status['code']='99';
			$status['message']='PARAMETER ERROR';
			return $this->statusXMLTag($status);
		}

		$sql="DELETE FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id='" . $products_id ."' and categories_id='" . $categories_id . "'";
		$rs = $db->Execute($sql);

		$status = array();
		$status['code']='0';
		$status['message']='OK';
		return $this->statusXMLTag($status);

		// TODO check master link

	}

	/**
	 * add/update products to categories connection
	 *
	 * @return unknown
	 */
	private function _setProducts2Categories() {
		global $db;

		$products_id = (int)$_POST['prodid'];
		$categories_id  = (int)$_POST['catid'];

		if (!isset($_POST['prodid'])) {
			$status = array();
			$status['code']='99';
			$status['message']='PARAMETER ERROR';
			return $this->statusXMLTag($status);
		}
		if (!isset($_POST['catid'])) {
			$status = array();
			$status['code']='99';
			$status['message']='PARAMETER ERROR';
			return $this->statusXMLTag($status);
		}

		$sql="REPLACE INTO " . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id,store_id) Values ('" . $products_id ."', '" . $categories_id . "', '" . $this->store_id . "')";
		$rs = $db->Execute($sql);

		$status = array();
		$status['code']='0';
		$status['message']='OK';
		return $this->statusXMLTag($status);

		// TODO set master ID
	}

	/**
	 * add/update categories
	 *
	 * @return xml
	 */
	private function _setCategories() {
		global $db,$language;

		$data = array();

		$cat_id = (int)$_POST['catid'];
		$parent_id = (int)$_POST['parentid'];

		// check if categorie exists
		$rs = $db->Execute("SELECT * FROM ".TABLE_CATEGORIES." WHERE categories_id='".$cat_id."'");

		$mode = 'UPDATE';

		$type='edit';
		if ($rs->RecordCount()==0){
			$type='new';
			$mode= 'APPEND';
		}


		// default
		$data['parent_id']=$parent_id;
		$data['categories_id']=$cat_id;
		$data['categories_status']='1';
		$data['sort_order']=$_POST['sort'];
		$data['categories_image']=$this->utf8helper($_POST['image']);

		// build lang vars
		foreach ($language->_getLanguageList() as $key => $val) {
            if ($val['code']=='de') $val['languages_id'] = 2;
			$data['categories_description_'.$val['code']] = $this->utf8helper($_POST['descr']);
			$data['categories_name_'.$val['code']] = $this->utf8helper($_POST['name']);
			$data['meta_description_'.$val['code']] = $this->utf8helper($_POST['categories_meta_description']);
			$data['meta_keywords_'.$val['code']] = $this->utf8helper($_POST['categories_meta_keywords']);
			$data['meta_title_'.$val['code']] = $this->utf8helper($_POST['categories_meta_title']);
			$data['categories_store_id_'.$val['code']] = $this->store_id;
		}

		$obj = new stdClass;
		$category = new category;

		$obj = $category->_set($data, $type);
		// We need to update it again. If we do it not in TABLE_CATEGORIES_DESCRIPTION the categories_id will be 0
		$obj = $category->_set($data, "edit");
		if ($obj->success) {
			$status = array();
			$status['code']='0';
			$status['message']='OK';
			$status['mode']= $mode;
			return $this->statusXMLTag($status);
		} else {
			$status = array();
			$status['code']='99';
			$status['message']='PARAMETER ERROR';
			return $this->statusXMLTag($status);
		}
	}

	/**
	 * get Categories and subcategories with listed products
	 *
	 * @return unknown
	 */
	function _getCategories() {
		global $db;


		$rs = $db->Execute("SELECT * FROM ".TABLE_CATEGORIES." order by parent_id, categories_id");
		$cat = array();
		while (!$rs->EOF) {


			$ls = $db->Execute("SELECT * FROM ".TABLE_CATEGORIES_DESCRIPTION." cd, ".TABLE_SEO_URL." su WHERE cd.language_code=su.language_code and su.link_type=2 and su.link_id=cd.categories_id and cd.categories_id='".$rs->fields['categories_id']."'");
			$cat_info = array();
			$i=0;
			while (!$ls->EOF) {

				$tmp = array('NAME'=>$ls->fields['categories_name'],
							'HEADING_TITLE'=>$ls->fields['categories_heading_title'],
							'DESCRIPTION'=>$ls->fields['categories_description'],
							'META_TITLE'=>$ls->fields['meta_title'],
							'META_DESCRIPTION'=>$ls->fields['meta_description'],
							'META_KEYWORDS'=>$ls->fields['meta_keywords'],
							'URL_TEXT'=>$ls->fields['url_text']);

				$tmp = $this->_filterExport($tmp);

				$cat_info[$i] = $tmp;
				$cat_info[$i.' attr'] = array('ID'=>$this->LNG[$ls->fields['language_code']]['languages_id'],'CODE'=>$ls->fields['language_code'],'NAME'=>$this->LNG[$ls->fields['language_code']]['name']);

				$ls->MoveNext();
				$i++;
			}

			// products in categorie
			$ps = $db->Execute("SELECT * FROM " . TABLE_PRODUCTS_TO_CATEGORIES ." WHERE categories_id='" . $rs->fields['categories_id'] . "'");
			$products = array();
			$i=0;
			while (!$ps->EOF) {

				$products[$i]='';
				$products[$i.' attr']=array('ID'=>$ps->fields['products_id']);
				$ps->MoveNext();
				$i++;
			}
			$tmp = array('ID'=>$rs->fields['categories_id'],
												'EXTERNAL_ID'=>$rs->fields['external_id'],
												'STATUS'=>$rs->fields['categories_status'],
												'PARENT_ID'=>$rs->fields['parent_id'],
												'IMAGE_URL'=>$rs->fields['categories_image'],
												'SORT_ORDER'=>$rs->fields['sort_order'],
												'DATE_ADDED'=>$rs->fields['date_added'],
												'LAST_MODIFIED'=>$rs->fields['last_modified']);
			$tmp = $this->_filterExport($tmp);
			$tmp['CATEGORIES_DESCRIPTION'] = $cat_info;
			$tmp['PRODUCTS'] = $products;
				
			$cat['CATEGORIES_DATA'][] = $tmp;


			$rs->MoveNext();
		}

		$ret['CATEGORIES'] = $cat;
		return $this->transformXML($ret);
	}

	// HELPER //

	/**
	 * format orders total positions
	 *
	 * @param unknown_type $data
	 * @param unknown_type $data2
	 * @return unknown
	 */
	private function extractOrderTotal($data,$data2){

		$total = array();

		// subtotal
		$total['TOTAL'][]=array('TOTAL_TITLE'=>TEXT_SUB_TOTAL,
							'TOTAL_VALUE'=>$data2['product_total']['plain'],
							'TOTAL_CLASS'=>'ot_subtotal',
							'TOTAL_SORT_ORDER'=>'10',
							'TOTAL_PREFIX'=>'',
							'TOTAL_TAX'=>'');
		// total stuff
		foreach ($data as $key => $val) {
			if ($val['orders_total_key']=='shipping') {
				$val['orders_total_key'] = 'ot_shipping';
			}
			$total['TOTAL'][]=array('TOTAL_TITLE'=>$val['orders_total_name'],
							'TOTAL_VALUE'=>$val['orders_total_price']['plain'],
							'TOTAL_CLASS'=>$val['orders_total_key'],
							'TOTAL_SORT_ORDER'=>50,
							'TOTAL_PREFIX'=>'+',
							'TOTAL_TAX'=>$val['orders_total_tax_rate']);	
		}
		// tax
		foreach ($data2['total_tax'] as $key => $val) {
			$total['TOTAL'][]=array('TOTAL_TITLE'=>$val['tax_key'].' %',
							'TOTAL_VALUE'=>$val['tax_value']['plain'],
							'TOTAL_CLASS'=>'ot_tax',
							'TOTAL_SORT_ORDER'=>60,
							'TOTAL_PREFIX'=>'',
							'TOTAL_TAX'=>'');	
		}
		// total
		$total['TOTAL'][]=array('TOTAL_TITLE'=>TEXT_TOTAL,
							'TOTAL_VALUE'=>$data2['total']['plain'],
							'TOTAL_CLASS'=>'ot_total',
							'TOTAL_SORT_ORDER'=>'10',
							'TOTAL_PREFIX'=>'',
							'TOTAL_TAX'=>'');
		return $total;
	}



	/**
	 * format products data
	 *
	 * @param unknown_type $data
	 */
	private function extractOrdersProducts($data) {

		global $xtPlugin,  $db,$language, $icao;

		$products = array();

		$icao = 0;

		foreach ($data as $key => $val) {

			$products['PRODUCT'][$icao]['PRODUCTS_ID'] =  $this->_filterExport($val['products_id']);
			$products['PRODUCT'][$icao]['PRODUCTS_QUANTITY']  =  $this->_filterExport($val['products_quantity']);
			$products['PRODUCT'][$icao]['PRODUCTS_MODEL'] =  $this->_filterExport($val['products_model']);
			$products['PRODUCT'][$icao]['PRODUCTS_NAME'] =  $this->_filterExport($val['products_name']);
			$products['PRODUCT'][$icao]['PRODUCTS_PRICE'] =  $this->_filterExport($val['products_price']['plain_otax']);
			$products['PRODUCT'][$icao]['PRODUCTS_TAX'] =  $this->_filterExport($val['products_tax_rate']);
			$products['PRODUCT'][$icao]['PRODUCTS_TAX_FLAG'] =  $this->_filterExport($val['allow_tax']);

			($plugin_code = $xtPlugin->PluginCode('class.xt_cao.php:_OrdersProducts_bottom')) ? eval($plugin_code) : false;
				
				
			$icao++;
		}
		return $products;

	}

	/**
	 * format delivery/billing address
	 *
	 * @param unknown_type $data
	 * @param unknown_type $type
	 * @return unknown
	 */
	private function extractAddress($data,$type='delivery') {

		$address = array();
		$address['VAT_ID']=$data['customers_vat_id'];
		$address['COMPANY']=$data[$type.'_company'];
		//$address['NAME']=$data[$type.'_'];
		$address['FIRSTNAME']=  $data[$type.'_firstname'] ;
		$address['LASTNAME']=  $data[$type.'_lastname'] ;
		$address['STREET']=$data[$type.'_street_address'];
		$address['POSTCODE']=$data[$type.'_postcode'];
		$address['CITY']=$data[$type.'_city'];
		$address['SUBURB']=$data[$type.'_suburb'];
		//$address['STATE']=$data[$type.'_'];
		$address['COUNTRY']=$data[$type.'_country_code'];
		$address['TELEPHONE']=$data[$type.'_phone'];
		$address['EMAIL']=$data['customers_email_address'];
		//$address['BIRTHDAY']=$data[$type.'_'];
		$address['GENDER']=$data[$type.'_gender'];
		return $address;

	}

	#########################################################################
	#  Filter
	#########################################################################

	private function _filterExport($var) {

		if (is_array($var)) {

			foreach ($var as $key => $val) {
				$var[$key]=iconv("UTF-8", "ISO-8859-1", htmlspecialchars( $val));
					
			}

			return $var;
		} else {

			$val = iconv("UTF-8", "ISO-8859-1", htmlspecialchars($var));

			return $val;
		}

	}


	#########################################################################
	#  UTF8 Multibyte to ISO
	#########################################################################

	private function _utf8toISO($var) {

		$search = array('â‚¬' ,'Â¤' ,'Â¦' ,'Â§' ,'Â¨','Â©' ,'Â«' ,'Â¬' ,'Â®' ,'Â°' ,'Â±' ,'Â´' ,'Âµ' ,'Â¶' ,'Â·' ,'Â¸' ,'Â»' , 'Ã„' , 'Ã‰', 'Ã”' ,'Ã–' , 'Ãœ' ,'Ã›' ,'ÃƒÂ¼', 'ÃŸ' ,'Ã¡' ,'Ã¢' ,'Ã¤' ,'Ã§' ,'Ã©' ,'Ã«' , 'Ã®' ,'Ã³' ,'Ã´' ,'Ã¶' ,'Ã·' ,'Ãº' ,'Ã¼' ,'Ã½' , 'Ã†' , 'Ä¹' ,'Ã…' ,'Äº' ,'Ã¥' ,'Ä½','Ä¾' , 'Ã€' , 'Ã˜' , 'Å¢' ,'Å£' , 'Å®' ,'Å¯' ,'Å°' ,'Å±' , 'Å»' ,'Å¼' , 'Â²' ,'Â½','â€ž' ,'â€œ' ,'â€¦' ,'â€' ,'â€˜' ,'â€™' ,'â€š' ,'â€“' );

		$replace = array('€','¤','¦','§','¨','©','«','¬','®','°','±','´','µ','¶','·','¸','»', 'Ä', 'É', 'Ô','Ö', 'Ü','Ü','Ü', 'ß','á','â','ä','ç','é','ë', 'î','ó','ô','ö','÷','ú','ü','ý', 'Æ', 'Å','Å','å','å','¼','¾', 'À', 'Ø', 'Þ','þ', 'Ù','ù','Û','û', '¯','¿', '²','½','„','“','…','”','’','’','‚','–' );

		$str  = str_replace($search, $replace, $var);
		return $str;

	}

	#########################################################################
	#  Remove HTML Tags
	#########################################################################

	private function _removeHtml($var) {

		if (is_array($var)) {

			foreach ($var as $key => $val) {
				$var[$key]= iconv("utf-8", "ISO-8859-1", strip_tags($val));
					
			}

			return $var;
		} else {

			$val = iconv("utf-8", "ISO-8859-1",  strip_tags($var));

			return $val;
		}

	}


	function transformXML ($array) {
		include_once _SRV_WEBROOT.'xtFramework/library/phpxml/xml.php';

		return XML_serialize($array);
	}

	function uploadImage($name,$class='default') {

		$obj = new stdClass;

		$_FILES["Filedata"] = $_FILES[$name];

		$filename = $_FILES["Filedata"]["name"];
		
/*
		$md = new MediaData();
		$md->setClass($class);
		$md->url_data = $_REQUEST;
*/
		
		$data_array = array();
		$data_array['currentType']=$class;
		
		$md = new MediaData();
		$md->setClass($class);
		$md->url_data = $data_array;
		//		$this->url_data['currentType']='product';
		$class = "Media".ucfirst($md->_getFileTypesByExtension($filename));

		$md = new $class;
		$md->url_data = $data_array;
		$obj = $md->Upload($filename);

		
		if($obj->success==1){
			$status = array();
			$status['code']='0';
			$status['message']='OK';
			$status['file_name']=$_FILES["Filedata"]["name"];
			return $this->statusXMLTag($status);
		}else{
			$status = array();
			$status['code']='-1';
			$status['message']='UPLOAD FAILED';
			$status['file_name']=$_FILES["Filedata"]["name"];
			return $this->statusXMLTag($status);
		}
	}

	function statusXMLTag($data) {


		$xml = array();
		foreach ($data as $key=>$val) {
			$xml[strtoupper($key)]=$val;
		}

		if (!isset($xml['action'])) {
			$xml['ACTION']=$_POST['action'];
		}

		$ret['STATUS']['STATUS_DATA'] = $xml;
		return $this->transformXML($ret);
	}

	static function utf8helper($string) {
		if ($string=='') return '';

		return utf8_encode($string);


	}


	function removeTags($data){


			
		$data = str_replace('<br>', ' ', $data);
		$data = str_replace('<b>', '', $data);
		$data = str_replace('</br>', ' ', $data);
		$data = str_replace('</b>', ' ', $data);
		$data = str_replace('&ouml;', 'ö', $data);
		$data = str_replace('&auml;', 'ä', $data);
		$data = str_replace('&uuml;', 'ü', $data);
		$data = str_replace('&Ouml;', 'Ö', $data);
		$data = str_replace('&Auml;', 'Ä', $data);
		$data = str_replace('&Uuml;', 'Ü', $data);
		$data = str_replace('&szlig;', 'ß', $data);
		$data = str_replace('Ã¶', 'ö', $data);
		$data = str_replace('Ã¤', 'ä', $data);
		$data = strip_tags($data);
			
			


			
		return $data;
			
			
	}




	#########################################################################
	#  CREATE PASSWORD
	#########################################################################

	function  create_password( ) {

		$random = md5(time());
		$password = substr($random,0,5);
		return md5($password);
	}



}
?>