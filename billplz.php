<?php
/**
Plugin Name: Billplz for GravityForms
Plugin URI: https://wordpress.org/plugins/billplz-for-gravityforms/
Description: Billplz. Fair payment platform.
Version: 3.9.1
Author: Billplz Sdn Bhd
Author URI: https://www.billplz.com
License: GPL-2.0+
Text Domain: gravityformsbillplz
Domain Path: /languages
*/

defined( 'ABSPATH' ) || die();

define('GF_BILLPLZ_VERSION', '3.9.1');

add_action('gform_loaded', array( 'GF_Billplz_Bootstrap', 'load' ), 5);

class GF_Billplz_Bootstrap
{

  public static function load()
  {

    if ( ! method_exists('GFForms', 'include_payment_addon_framework')) {
      return;
    }

    require_once 'helpers/billplz_api.php';
    require_once 'helpers/billplz_wpconnect.php';
    require_once 'class-gf-billplz.php';

    GFAddOn::register('GFBillplz');
  }
}

function gf_billplz()
{
  return GFBillplz::get_instance();
}
