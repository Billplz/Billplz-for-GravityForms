<?php
/**
 * Plugin Name: Billplz for GravityForms
 * Plugin URI: https://wordpress.org/plugins-wp/billplz-for-gravityforms/
 * Description: Billplz Payment Gateway | Accept Payment using all participating FPX Banking Channels. <a href="https://www.billplz.com/join/8ant7x743awpuaqcxtqufg" target="_blank">Sign up Now</a>.
 * Author: Wanzul Hosting Enterprise
 * Author URI: http://www.wanzul-hosting.com/
 * Version: 3.4
 * License: GPLv3
 * Text Domain: wcbillplz
 * Domain Path: /languages/
 */
// Add settings link on plugin page

define( 'GF_BILLPLZ_VERSION', '3.4' );

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