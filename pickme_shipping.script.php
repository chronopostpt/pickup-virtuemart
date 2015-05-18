<?php

defined('_JEXEC') or die('Restricted access');

class plgVmShipmentPickme_ShippingInstallerScript {

	public function install(JAdapterInstance $adapter) {
		// enabling plugin
		$db =& JFactory::getDBO();
		$db->setQuery('update #__extensions set enabled = 1 where type = "plugin" and element = "pickme_shipping" and folder = "vmshipment"');
		$db->query();

		return true;
	}

	public function uninstall(JAdapterInstance $adapter) {
		// Remove plugin table
		// $db =& JFactory::getDBO();
		// $db->setQuery('DROP TABLE `#__virtuemart_shipment_plg_rules_shipping`;');
		// $db->query();
	}

	public function postflight($route, JAdapterInstance $adapter) {
		if ($route=='install' || $route=='update') {
				
			// create pickme shop table
			$db =& JFactory::getDBO();
			$db->setQuery('CREATE TABLE IF NOT EXISTS `'.$db->getPrefix().'virtuemart_pickme_shops` (
					`id_pickme_shop` int(10) unsigned NOT NULL auto_increment,
					`pickme_id` varchar(30) NULL, `name` varchar(255) NULL,
					`address` varchar(1000) NULL,
					`location` varchar(400) NULL,
					`postal_code` varchar(20) NULL,
					PRIMARY KEY  (`id_pickme_shop`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;');
			$db->query();
				
			// copy logo
			$logo_file = 'chronopost_pickup.jpg';
			$src = JPATH_ROOT.DS.'plugins'.DS.'vmshipment'.DS.'pickme_shipping'.DS.$logo_file;
			$dest_dir = JPATH_ROOT.DS.'images'.DS.'stories'.DS.'virtuemart'.DS.'shipment';
			if (!JFolder::exists($dest_dir)) {
				JFolder::create($dest_dir);
			}
			JFile::copy($src, $dest_dir.DS.$logo_file);
		}
	}
}
