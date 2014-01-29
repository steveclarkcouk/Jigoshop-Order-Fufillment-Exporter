<?php
/*
Plugin Name: JigoShop - Order Exporter
Description: Jigoshop Customer Order Plugin
Version: 1
Author: Steve Clark
*/

include('classes/jigoshop_customer_base.php');
include('classes/jigoshop_customer_admin.php');

if(is_admin()) {
	$jci_plugin = new Jigoshop_Fufillment_Order_Admin();
}



?>