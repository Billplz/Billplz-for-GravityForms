<?php
/*
Plugin Name: Billplz for GravityForms
Plugin URI: http://www.facebook.com/billplzplugin
Description: Integrates Gravity Forms with Billplz Payments, enabling end users to purchase goods and services through Gravity Forms.
Version: 3.2
Author: Wan Zulkarnain
Author URI: http://www.wanzul-hosting.com
Text Domain: billplzforgravityforms
Domain Path: /languages
*/


define( 'GF_BILLPLZ_VERSION', '3.2' );

add_action( 'gform_loaded', array( 'GF_Billplz_Bootstrap', 'load' ), 5 );

class GF_Billplz_Bootstrap {

	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_payment_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-billplz.php' );

		GFAddOn::register( 'GFBillplz' );
	}
}

function gf_billplz() {
	return GFBillplz::get_instance();
}
