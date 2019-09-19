<?php
/**
Plugin Name: Billplz for GravityForms
Plugin URI: https://wordpress.org/plugins-wp/billplz-for-gravityforms/
Description: Billplz Payment Gateway | <a href="https://www.billplz.com/enterprise/signup" target="_blank">Sign up Now</a>.
Version: 3.8.2
Author: Billplz Sdn. Bhd.
Author URI: https://www.billplz.com
License: GPL-2.0+
Text Domain: gravityformsbillplz
Domain Path: /languages
*/


define('GF_BILLPLZ_VERSION', '3.8.2');

add_action('gform_loaded', array( 'GF_Billplz_Bootstrap', 'load' ), 5);

class GF_Billplz_Bootstrap
{

    public static function load()
    {

        if (! method_exists('GFForms', 'include_payment_addon_framework')) {
            return;
        }

        include_once 'includes/WPConnect.php';
        include_once 'includes/API.php';
        include_once 'class-gf-billplz.php';

        GFAddOn::register('GFBillplz');
    }
}

function gf_billplz()
{
    return GFBillplz::get_instance();
}
