<?php

defined ('_JEXEC') or die('Restricted access');

/**
 * Shipment plugin for general, rules-based shipments, like regular postal services with complex shipping cost structures
 *
 * @version $Id$
 * @package VirtueMart
 * @subpackage Plugins - shipment
 * @copyright Copyright (C) 2004-2012 VirtueMart Team - All rights reserved.
 * @copyright Copyright (C) 2013 Reinhold Kainhofer, reinhold@kainhofer.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.org
 * @author Reinhold Kainhofer, based on the weight_countries shipping plugin by Valerie Isaksen
 *
*/
if (!class_exists ('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}
if (!class_exists ('plgVmShipmentPickme_Shipping')) {
	// Only declare the class once...

	/** Shipping costs according to general rules.
	 *  Supported Variables: Weight, ZIP, Amount, Products (1 for each product, even if multiple ordered), Articles
	 *  Assignable variables: Shipping, Name
	 */
	class plgVmShipmentPickme_Shipping extends vmPSPlugin {

		/**
		 * @param object $subject
		 * @param array  $config
		 */
		function __construct (& $subject, $config) {

			parent::__construct ($subject, $config);

			$this->_loggable = TRUE;
			$this->_tablepkey = 'id';
			$this->_tableId = 'id';
			$this->tableFields = array_keys ($this->getTableSQLFields ());
			$varsToPush = $this->getVarsToPush ();
			$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);
		}

		/**
		 * Create the table for this plugin if it does not yet exist.
		 *
		 * @author Valérie Isaksen
		 */
		public function getVmPluginCreateTableSQL () {
			JFactory::getApplication()->enqueueMessage('getVmPluginCreateTableSQL', 'message');
			return $this->createTableSQL ('Shipment PickUp Table');
		}

		/**
		 * @return array
		 */
		function getTableSQLFields () {
			JFactory::getApplication()->enqueueMessage('getTableSQLFields', 'message');

			$SQLfields = array(
					'id'                           => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
					'virtuemart_order_id'          => 'int(11) UNSIGNED',
					'order_number'                 => 'char(32)',
					'virtuemart_shipmentmethod_id' => 'mediumint(1) UNSIGNED',
					'shipment_name'                => 'varchar(5000)',
					'pickme_shop_id'               => 'int(10) UNSIGNED',
					'order_weight'                 => 'decimal(10,4)',
					'order_articles'               => 'int(1)',
					'order_products'               => 'int(1)'
			);
			return $SQLfields;
		}

		/**
		 * This method is fired when showing the order details in the frontend.
		 * It displays the shipment-specific data.
		 *
		 * @param integer $virtuemart_order_id The order ID
		 * @param integer $virtuemart_shipmentmethod_id The selected shipment method id
		 * @param string  $shipment_name Shipment Name
		 * @return mixed Null for shipments that aren't active, text (HTML) otherwise
		 * @author Valérie Isaksen
		 * @author Max Milbers
		 */
		public function plgVmOnShowOrderFEShipment ($virtuemart_order_id, $virtuemart_shipmentmethod_id, &$shipment_name) {
			JFactory::getApplication()->enqueueMessage('plgVmOnShowOrderFEShipment', 'message');
			$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_shipmentmethod_id, $shipment_name);
		}

		/**
		 * This event is fired after the order has been stored; it gets the shipment method-
		 * specific data.
		 *
		 * @param int    $order_id The order_id being processed
		 * @param object $cart  the cart
		 * @param array  $order The actual order saved in the DB
		 * @return mixed Null when this method was not selected, otherwise true
		 * @author Valerie Isaksen
		 */
		function plgVmConfirmedOrder (VirtueMartCart $cart, $order) {
			JFactory::getApplication()->enqueueMessage('plgVmConfirmedOrder', 'message');

			// TODO: escrever order na tabela
			
			
			if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_shipmentmethod_id))) {
				return NULL; // Another method was selected, do nothing
			}
			if (!$this->selectedThisElement ($method->shipment_element)) {
				return FALSE;
			}
			$values['virtuemart_order_id'] = $order['details']['BT']->virtuemart_order_id;
			$values['order_number'] = $order['details']['BT']->order_number;
			$values['virtuemart_shipmentmethod_id'] = $order['details']['BT']->virtuemart_shipmentmethod_id;
			$values['shipment_name'] = $this->renderPluginName ($method);
			// TODO:
			$values['pickme_shop_id'] = 999;
			$values['order_weight'] = $this->getOrderWeight ($cart, $method->weight_unit);
			$values['order_articles'] = $this->getOrderArticles ($cart);
			$values['order_products'] = $this->getOrderProducts ($cart);
			$this->storePSPluginInternalData ($values);

			return TRUE;
		}

		/**
		 * This method is fired when showing the order details in the backend.
		 * It displays the shipment-specific data.
		 * NOTE, this plugin should NOT be used to display form fields, since it's called outside
		 * a form! Use plgVmOnUpdateOrderBE() instead!
		 *
		 * @param integer $virtuemart_order_id The order ID
		 * @param integer $virtuemart_shipmentmethod_id The order shipment method ID
		 * @param object  $_shipInfo Object with the properties 'shipment' and 'name'
		 * @return mixed Null for shipments that aren't active, text (HTML) otherwise
		 * @author Valerie Isaksen
		 */
		public function plgVmOnShowOrderBEShipment ($virtuemart_order_id, $virtuemart_shipmentmethod_id) {
			JFactory::getApplication()->enqueueMessage('plgVmOnShowOrderBEShipment', 'message');

			if (!($this->selectedThisByMethodId ($virtuemart_shipmentmethod_id))) {
				return NULL;
			}
			$html = $this->getOrderShipmentHtml ($virtuemart_order_id);
			return $html;
		}

		/**
		 * @param $virtuemart_order_id
		 * @return string
		 */
		function getOrderShipmentHtml ($virtuemart_order_id) {
			JFactory::getApplication()->enqueueMessage('getOrderShipmentHtml', 'message');

			$db = JFactory::getDBO ();
			$q = 'SELECT * FROM `' . $this->_tablename . '` '
					. 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
			$db->setQuery ($q);
			if (!($shipinfo = $db->loadObject ())) {
				vmWarn (500, $q . " " . $db->getErrorMsg ());
				return '';
			}

			if (!class_exists ('CurrencyDisplay')) {
				require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
			}

			$currency = CurrencyDisplay::getInstance ();
			$tax = ShopFunctions::getTaxByID ($shipinfo->tax_id);
			$taxDisplay = is_array ($tax) ? $tax['calc_value'] . ' ' . $tax['calc_value_mathop'] : $shipinfo->tax_id;
			$taxDisplay = ($taxDisplay == -1) ? JText::_ ('COM_VIRTUEMART_PRODUCT_TAX_NONE') : $taxDisplay;

			$html = '<table class="adminlist">' . "\n";
			$html .= $this->getHtmlHeaderBE ();
			$html .= $this->getHtmlRowBE ('RULES_SHIPPING_NAME', $shipinfo->shipment_name);
			$html .= $this->getHtmlRowBE ('RULES_WEIGHT', $shipinfo->order_weight . ' ' . ShopFunctions::renderWeightUnit ($shipinfo->shipment_weight_unit));
			$html .= $this->getHtmlRowBE ('RULES_ARTICLES', $shipinfo->order_articles . '/' . $shipinfo->order_products);
			$html .= $this->getHtmlRowBE ('RULES_COST', $currency->priceDisplay ($shipinfo->shipment_cost));
			$html .= $this->getHtmlRowBE ('RULES_TAX', $taxDisplay);
			$html .= '</table>' . "\n";

			return $html;
		}

		protected function renderPluginName ($plugin) {
			JFactory::getApplication()->enqueueMessage('renderPluginName', 'message');
			$return = '';
			$plugin_name = $this->_psType . '_name';
			$plugin_desc = $this->_psType . '_desc';
			$description = '';
			// 		$params = new JParameter($plugin->$plugin_params);
			// 		$logo = $params->get($this->_psType . '_logos');
			$logosFieldName = $this->_psType . '_logos';
			$logos = $plugin->$logosFieldName;
			if (!empty($logos)) {
				$return = $this->displayLogos ($logos) . ' ';
			}
			if (!empty($plugin->$plugin_desc)) {
				$description = '<span class="' . $this->_type . '_description">' . $plugin->$plugin_desc . '</span>';
			}

			
			$db =& JFactory::getDBO();
			$db->setQuery('select * from `'.$db->getPrefix().'virtuemart_pickme_shops`;');
			$results = $db->loadAssocList();
				
			$list = '<select id="pickme_stores" class="pickme_stores_select">';
			$list .= '<option disabled="disabled">---</option>';
			foreach ($results as $row) {
				$list .= '<option value="'.$row['id_pickme_shop'].'">'.$row['name'].' - '.$row['location'].'</option>';
			}
			$list .= '</select>';
				
			$pluginName = $return . '<span class="' . $this->_type . '_name">' . $plugin->$plugin_name.'</span>' . $description . '&nbsp;' . $list;
			return $pluginName;
		}


		/**
		 * @param VirtueMartCart $cart
		 * @param                $method
		 * @param                $cart_prices
		 * @return int
		 */
		function getCosts (VirtueMartCart $cart, $method, $cart_prices) {
			JFactory::getApplication()->enqueueMessage('getCosts', 'message');

			if (!empty($method->pickme_overcost) && is_numeric($method->pickme_overcost)) {
				return $method->pickme_overcost;
			}

			vmdebug('getCosts '.$method->name.' does not return shipping costs');
			return 0;
		}



		protected function getOrderArticles (VirtueMartCart $cart) {
			JFactory::getApplication()->enqueueMessage('getOrderArticles', 'message');
			/* Cache the value in a static variable and calculate it only once! */
			static $articles = 0;
			if(empty($articles) and count($cart->products)>0){
				foreach ($cart->products as $product) {
					$articles += $product->quantity;
				}
			}
			return $articles;
		}

		protected function getOrderProducts (VirtueMartCart $cart) {
			JFactory::getApplication()->enqueueMessage('getOrderProducts', 'message');
			/* Cache the value in a static variable and calculate it only once! */
			static $products = 0;
			if(empty($products) and count($cart->products)>0){
				$products = count($cart->products);
			}
			return $products;
		}


		/**
		 * @param \VirtueMartCart $cart
		 * @param int             $method
		 * @param array           $cart_prices
		 * @return bool
		 */
		protected function checkConditions ($cart, $method, $cart_prices) {
			JFactory::getApplication()->enqueueMessage('checkConditions', 'message');
				
			return true;
		}

		/**
		 * Create the table for this plugin if it does not yet exist.
		 * This functions checks if the called plugin is active one.
		 * When yes it is calling the standard method to create the tables
		 *
		 * @author Valérie Isaksen
		 *
		 */
		function plgVmOnStoreInstallShipmentPluginTable ($jplugin_id) {
			JFactory::getApplication()->enqueueMessage('plgVmOnStoreInstallShipmentPluginTable', 'message');

			return $this->onStoreInstallPluginTable ($jplugin_id);
		}

		/**
		 * @param VirtueMartCart $cart
		 * @return null
		 */
		public function plgVmOnSelectCheckShipment (VirtueMartCart &$cart) {
			JFactory::getApplication()->enqueueMessage('plgVmOnSelectCheckShipment', 'message');

			return $this->OnSelectCheck ($cart);
		}

		/**
		 * plgVmDisplayListFE
		 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for example
		 *
		 * @param object  $cart Cart object
		 * @param integer $selected ID of the method selected
		 * @return boolean True on success, false on failures, null when this plugin was not selected.
		 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
		 *
		 * @author Valerie Isaksen
		 * @author Max Milbers
		 */
		public function plgVmDisplayListFEShipment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {
			JFactory::getApplication()->enqueueMessage('plgVmDisplayListFEShipment', 'message');
			return $this->displayListFE ($cart, $selected, $htmlIn);
		}

		/**
		 * @param VirtueMartCart $cart
		 * @param array          $cart_prices
		 * @param                $cart_prices_name
		 * @return bool|null
		 */
		public function plgVmOnSelectedCalculatePriceShipment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
			JFactory::getApplication()->enqueueMessage('plgVmOnSelectedCalculatePriceShipment', 'message');
			return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
		}

		/**
		 * plgVmOnCheckAutomaticSelected
		 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
		 * The plugin must check first if it is the correct type
		 *
		 * @author Valerie Isaksen
		 * @param VirtueMartCart cart: the cart object
		 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
		 *
		 */
		function plgVmOnCheckAutomaticSelectedShipment (VirtueMartCart $cart, array $cart_prices = array(), &$shipCounter) {
			JFactory::getApplication()->enqueueMessage('plgVmOnCheckAutomaticSelectedShipment', 'message');
			if ($shipCounter > 1) {
				return 0;
			}
			return $this->onCheckAutomaticSelected ($cart, $cart_prices, $shipCounter);
		}

		/**
		 * This method is fired when showing when priting an Order
		 * It displays the the payment method-specific data.
		 *
		 * @param integer $_virtuemart_order_id The order ID
		 * @param integer $method_id  method used for this order
		 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
		 * @author Valerie Isaksen
		 */
		function plgVmonShowOrderPrint ($order_number, $method_id) {
			JFactory::getApplication()->enqueueMessage('plgVmonShowOrderPrint', 'message');
			return $this->onShowOrderPrint ($order_number, $method_id);
		}

		function plgVmDeclarePluginParamsShipment ($name, $id, &$data) {
			JFactory::getApplication()->enqueueMessage('plgVmDeclarePluginParamsShipment', 'message');
			return $this->declarePluginParams ('shipment', $name, $id, $data);
		}


		/**
		 * @author Max Milbers
		 * @param $data
		 * @param $table
		 * @return bool
		 */
		function plgVmSetOnTablePluginShipment(&$data,&$table){

			$name = $data['shipment_element'];
			$id = $data['shipment_jplugin_id'];

			if (!empty($this->_psType) and !$this->selectedThis ($this->_psType, $name, $id)) {
				return false;
			} else {

				$this->updateDatabase($data['pickme_ws']);

				return $this->setOnTablePluginParams ($name, $id, $table);
			}
		}

		private function updateDatabase($url_ws) {

			try {
				if ($url_ws != "") {
					$client = new SoapClient($url_ws);

					$db =& JFactory::getDBO();
					$db->setQuery('delete from `'.$db->getPrefix().'virtuemart_pickme_shops`;');
					$db->query();

					$result = $client->getPointList_V3();
					foreach ($result->return->lB2CPointsArr as $message) {
						$db->setQuery('INSERT INTO `'.$db->getPrefix().'virtuemart_pickme_shops`
								(pickme_id, name, address, postal_code, location) VALUES
								("'.$message->Number.'", "'.$message->Name.'", "'.$message->Address.'", "'.$message->PostalCode.'", "'.$message->PostalCodeLocation.'")');
						$db->query();
					}

					JFactory::getApplication()->enqueueMessage('The PickUp database successfully updated', 'message');
				}
			} catch (Exception $e) {
				JFactory::getApplication()->enqueueMessage(
				JText::sprintf('Some error occurred: %s', $e->getMessage()), 'error');
			}
		}

	}
}
