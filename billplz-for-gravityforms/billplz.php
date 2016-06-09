<?php
/*
Plugin Name: Billplz for GravityForms
Plugin URI: https://www.wanzul-hosting.com
Description: Integrates Gravity Forms with Billplz, enabling end users to purchase goods and services through Gravity Forms. Please consider a donation to developer. Sumbangan boleh dilakukan disini. <a href="https://www.billplz.com/form/sw2co7ig8" target="_blank">Donate Now</a>
Version: 3.1
Author: Wanzul Hosting Enterprise
Author URI: http://www.wanzul.net
Text Domain: billplzforgravityformsaddon
Domain Path: /languages
*/

define( 'GF_BILLPLZ_VERSION', '1.2' );

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