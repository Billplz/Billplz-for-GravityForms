<?php

defined( 'ABSPATH' ) || die();

add_action('wp', array( 'GFBillplz', 'maybe_thankyou_page' ), 5);

GFForms::include_payment_addon_framework();

class GFBillplz extends GFPaymentAddOn
{
    protected $_version = GF_BILLPLZ_VERSION;
    protected $_min_gravityforms_version = '1.9.3';
    protected $_slug = 'gravityformsbillplz';
    protected $_full_path = __FILE__;
    protected $_url = 'https://www.billplz.com';
    protected $_title = 'Billplz for GravityForms';
    protected $_short_title = 'Billplz';
    protected $_supports_callbacks = true;

    // Members plugin integration
    protected $_capabilities = array( 'gravityforms_billplz', 'gravityforms_billplz_uninstall' );

    // Permissions
    protected $_capabilities_settings_page = 'gravityforms_billplz';
    protected $_capabilities_form_settings = 'gravityforms_billplz';
    protected $_capabilities_uninstall = 'gravityforms_billplz_uninstall';

    // Automatic upgrade enabled
    protected $_enable_rg_autoupgrade = false;

    private static $_instance = null;

    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new GFBillplz();
        }

        return self::$_instance;
    }

    private function __clone()
    {
    } /* do nothing */

    public function get_path() {
        return basename(dirname(__FILE__)) . '/billplz.php';
    }

    public function init_frontend()
    {
        parent::init_frontend();

        add_filter('gform_disable_post_creation', array( $this, 'delay_post' ), 10, 3);
        add_filter('gform_disable_notification', array( $this, 'delay_notification' ), 10, 4);
    }

    public function get_payment_field( $feed ) {
        return rgars( $feed, 'meta/paymentAmount', 'form_total' );
    }

    //----- SETTINGS PAGES ----------//
    
    public function feed_settings_fields()
    {
        $default_settings = parent::feed_settings_fields();

        $fields = array(
            array(
                'name'          => 'mode',
                'label'         => esc_html__( 'Mode', 'gravityformsbillplz' ),
                'type'          => 'radio',
                'required'      => true,
                'choices'       => array(
                    array( 'id' => 'gf_billplz_mode_production', 'label' => esc_html__( 'Production', 'gravityformsbillplz' ), 'value' => 'production' ),
                    array( 'id' => 'gf_billplz_mode_test', 'label' => esc_html__( 'Sandbox', 'gravityformsbillplz' ), 'value' => 'sandbox' ),

                ),

                'horizontal'    => true,
                'default_value' => 'production',
                'tooltip'       => '<h6>' . esc_html__( 'Mode', 'gravityformsbillplz' ) . '</h6>' . esc_html__( 'Select Production to receive real payments. Select Sandbox for testing purposes when using the Billplz sandbox.', 'gravityformsbillplz' )
            ),
            array(
                'name' => 'api_key',
                'label' => esc_html__('API Secret Key ', 'gravityformsbillplz'),
                'type' => 'text',
                'class' => 'medium',
                'required' => true,
                'tooltip' => '<h6>' . esc_html__('Billplz API Secret Key', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter the API Secret Key where payment should be received.', 'gravityformsbillplz')
            ),
            array(
                'name' => 'collection_id',
                'label' => esc_html__('Collection ID ', 'gravityformsbillplz'),
                'type' => 'text',
                'class' => 'medium',
                'required' => true,
                'tooltip' => '<h6>' . esc_html__('Billplz Collection ID', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter your chosen specific Billing Collection ID. It can be retrieved from Billplz Billing page.', 'gravityformsbillplz')
            ),
            array(
                'name' => 'x_signature_key',
                'label' => esc_html__('X Signature Key ', 'gravityformsbillplz'),
                'type' => 'text',
                'class' => 'medium',
                'required' => true,
                'tooltip' => '<h6>' . esc_html__('Billplz X Signature Key', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter the X Signature Key where payment should be received.', 'gravityformsbillplz')
            ),
            array(
                'label' => esc_html__('Bill Description', 'gravityformsbillplz'),
                'type' => 'textarea',
                'name' => 'bill_description',
                'tooltip' => '<h6>' . esc_html__('Billplz Bills Description', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter your description here. It will displayed on Bill page.', 'gravityformsbillplz'),
                'class' => 'medium merge-tag-support mt-position-right',
                'required' => false,
            )
        );

        $default_settings = parent::add_field_after('feedName', $fields, $default_settings);

        //--------------------------------------------------------------------------------------

        //--remove subscription from transaction type drop down
        $transaction_type = parent::get_field('transactionType', $default_settings);
        unset($transaction_type['choices'][2]);
        $default_settings = $this->replace_field('transactionType', $transaction_type, $default_settings);
        //--------------------------------------------------------------------------------------
        
        $fields = array(
            array(
                'name'     => 'cancel_url',
                'label'    => esc_html__('Cancel URL', 'gravityformsbillplz'),
                'type'     => 'text',
                'class'    => 'medium',
                'required' => false,
                'tooltip'  => '<h6>' . esc_html__('Cancel URL', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter the URL the user should be sent to should they cancel before completing their payment.', 'gravityformsbillplz')
            ),
        );

        if ($this->get_setting('delayNotification') || ! $this->is_gravityforms_supported('1.9.12')) {
            $fields[] = array(
                'name'    => 'notifications',
                'label'   => esc_html__('Notifications', 'gravityformsbillplz'),
                'type'    => 'notifications',
                'tooltip' => '<h6>' . esc_html__('Notifications', 'gravityformsbillplz') . '</h6>' . esc_html__("Enable this option if you would like to only send out this form's notifications for the 'Form is submitted' event after payment has been received. Leaving this option disabled will send these notifications immediately after the form is submitted. Notifications which are configured for other events will not be affected by this option.", 'gravityformsbillplz')
            );
        }

        //Add post fields if form has a post
        $form = $this->get_current_form();

        if (GFCommon::has_post_field($form['fields'])) {
            $post_settings = array(
                'name'    => 'post_checkboxes',
                'label'   => esc_html__('Posts', 'gravityformsbillplz'),
                'type'    => 'checkbox',
                'tooltip' => '<h6>' . esc_html__('Posts', 'gravityformsbillplz') . '</h6>' . esc_html__('Enable this option if you would like to only create the post after payment has been received.', 'gravityformsbillplz'),
                'choices' => array(
                    array( 'label' => esc_html__('Create post only when payment is received.', 'gravityformsbillplz'), 'name' => 'delayPost' ),
                ),
            );

            $fields[] = $post_settings;
        }

        //Adding custom settings for backwards compatibility with hook 'gform_billplz_add_option_group'
        $fields[] = array(
            'name'  => 'custom_options',
            'label' => '',
            'type'  => 'custom',
        );

        $default_settings = $this->add_field_after('billingInformation', $fields, $default_settings);
        //-----------------------------------------------------------------------------------------
        
        //--get billing info section and add customer first/last name
        $billing_info = parent::get_field('billingInformation', $default_settings);

        $add_name = true;
        $add_reference_1_label = true;
        $add_reference_2_label = true;
        $add_reference_1 = true;
        $add_reference_2 = true;
        $add_mobile = true;
        $add_email = true; //for better arrangement

        $remove_address = false;
        $remove_address2 = false;
        $remove_city = false;
        $remove_state = false;
        $remove_zip = false;
        $remove_country = false;
        $remove_email = false; //for better arrangement

        foreach ($billing_info['field_map'] as $mapping) {
            //add first/last name if it does not already exist in billing fields
            if ($mapping['name'] == 'name') {
                $add_name = false;
            } elseif ($mapping['name'] == 'reference_1_label') {
                $add_reference_1_label = false;
            } elseif ($mapping['name'] == 'reference_2_label') {
                $add_reference_2_label = false;
            } elseif ($mapping['name'] == 'reference_1') {
                $add_reference_1 = false;
            } elseif ($mapping['name'] == 'reference_2') {
                $add_reference_2 = false;
            } elseif ($mapping['name'] == 'mobile') {
                $add_mobile = false;
            } elseif ($mapping['name'] == 'address') {
                $remove_address = true;
            } elseif ($mapping['name'] == 'address2') {
                $remove_address2 = true;
            } elseif ($mapping['name'] == 'city') {
                $remove_city = true;
            } elseif ($mapping['name'] == 'state') {
                $remove_state = true;
            } elseif ($mapping['name'] == 'zip') {
                $remove_zip = true;
            } elseif ($mapping['name'] == 'country') {
                $remove_country = true;
            } elseif ($mapping['name'] == 'email') {
                $remove_email = true;
            }
        }

        /*
         * Removing unrelated variable
         */

        if ($remove_address) {
            unset($billing_info['field_map'][1]);
        }
        if ($remove_address2) {
            unset($billing_info['field_map'][2]);
        }
        if ($remove_city) {
            unset($billing_info['field_map'][3]);
        }
        if ($remove_state) {
            unset($billing_info['field_map'][4]);
        }
        if ($remove_zip) {
            unset($billing_info['field_map'][5]);
        }
        if ($remove_country) {
            unset($billing_info['field_map'][6]);
        }
        if ($remove_email) {
            unset($billing_info['field_map'][0]);
        }

        /*
         * Adding Billplz required variable. The last will be the first
         */

        if ($add_reference_2) {
            array_unshift($billing_info['field_map'], array('name' => 'reference_2', 'label' => esc_html__('Reference 2', 'gravityformsbillplz'), 'required' => false));
        }
        if ($add_reference_2_label) {
            array_unshift($billing_info['field_map'], array('name' => 'reference_2_label', 'label' => esc_html__('Reference 2 Label', 'gravityformsbillplz'), 'required' => false));
        }
        if ($add_reference_1) {
            array_unshift($billing_info['field_map'], array('name' => 'reference_1', 'label' => esc_html__('Reference 1', 'gravityformsbillplz'), 'required' => false));
        }
        if ($add_reference_1_label) {
            array_unshift($billing_info['field_map'], array('name' => 'reference_1_label', 'label' => esc_html__('Reference 1 Label', 'gravityformsbillplz'), 'required' => false));
        }
        if ($add_mobile) {
            array_unshift($billing_info['field_map'], array('name' => 'mobile', 'label' => esc_html__('Mobile Phone Number', 'gravityformsbillplz'), 'required' => false));
        }
        if ($add_email) {
            array_unshift($billing_info['field_map'], array('name' => 'email', 'label' => esc_html__('Email', 'gravityformsbillplz'), 'required' => false));
        }
        if ($add_name) {
            array_unshift($billing_info['field_map'], array('name' => 'name', 'label' => esc_html__('Name', 'gravityformsbillplz'), 'required' => false));
        }

        $default_settings = parent::replace_field('billingInformation', $billing_info, $default_settings);

        //hide default display of setup fee, not used by Billplz
        $default_settings = parent::remove_field('setupFee', $default_settings);

        /**
         * Filter through the feed settings fields for the Billplz feed
         *
         * @param array $default_settings The Default feed settings
         * @param array $form The Form object to filter through
         */
        return apply_filters('gform_billplz_feed_settings_fields', $default_settings, $form);
    }

    public function field_map_title()
    {
        return esc_html__('Billplz Field', 'gravityformsbillplz');
    }

    public function settings_options($field, $echo = true)
    {
        $html = $this->settings_checkbox($field, false);

        //--------------------------------------------------------
        //For backwards compatibility.
        ob_start();
        do_action('gform_billplz_action_fields', $this->get_current_feed(), $this->get_current_form());
        $html .= ob_get_clean();
        //--------------------------------------------------------

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public function settings_custom($field, $echo = true)
    {

        ob_start();
        ?>
        <div id='gf_billplz_custom_settings'>
            <?php
            do_action('gform_billplz_add_option_group', $this->get_current_feed(), $this->get_current_form());
            ?>
        </div>

        <script type='text/javascript'>
            jQuery(document).ready(function () {
                jQuery('#gf_billplz_custom_settings label.left_header').css('margin-left', '-200px');
            });
        </script>

        <?php

        $html = ob_get_clean();

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public function settings_notifications($field, $echo = true)
    {
        $checkboxes = array(
            'name'    => 'delay_notification',
            'type'    => 'checkboxes',
            'onclick' => 'ToggleNotifications();',
            'choices' => array(
                array(
                    'label' => esc_html__("Send notifications for the 'Form is submitted' event only when payment is received.", 'gravityformsbillplz'),
                    'name'  => 'delayNotification',
                ),
            )
        );

        $html = $this->settings_checkbox($checkboxes, false);

        $html .= $this->settings_hidden(array( 'name' => 'selectedNotifications', 'id' => 'selectedNotifications' ), false);

        $form                      = $this->get_current_form();
        $has_delayed_notifications = $this->get_setting('delayNotification');
        ob_start();
        ?>
        <ul id="gf_billplz_notification_container" style="padding-left:20px; margin-top:10px; <?php echo $has_delayed_notifications ? '' : 'display:none;' ?>">
            <?php
            if (! empty($form) && is_array($form['notifications'])) {
                $selected_notifications = $this->get_setting('selectedNotifications');
                if (! is_array($selected_notifications)) {
                    $selected_notifications = array();
                }

                $notifications = GFCommon::get_notifications('form_submission', $form);

                foreach ($notifications as $notification) {
                    ?>
                    <li class="gf_billplz_notification">
                        <input type="checkbox" class="notification_checkbox" value="<?php echo $notification['id'] ?>" onclick="SaveNotifications();" <?php checked(true, in_array($notification['id'], $selected_notifications)) ?> />
                        <label class="inline" for="gf_billplz_selected_notifications"><?php echo $notification['name']; ?></label>
                    </li>
                    <?php
                }
            }
            ?>
        </ul>
        <script type='text/javascript'>
            function SaveNotifications() {
                var notifications = [];
                jQuery('.notification_checkbox').each(function () {
                    if (jQuery(this).is(':checked')) {
                        notifications.push(jQuery(this).val());
                    }
                });
                jQuery('#selectedNotifications').val(jQuery.toJSON(notifications));
            }

            function ToggleNotifications() {

                var container = jQuery('#gf_billplz_notification_container');
                var isChecked = jQuery('#delaynotification').is(':checked');

                if (isChecked) {
                    container.slideDown();
                    jQuery('.gf_billplz_notification input').prop('checked', true);
                }
                else {
                    container.slideUp();
                    jQuery('.gf_billplz_notification input').prop('checked', false);
                }

                SaveNotifications();
            }
        </script>
        <?php

        $html .= ob_get_clean();

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public function checkbox_input_change_post_status($choice, $attributes, $value, $tooltip)
    {
        $markup = $this->checkbox_input($choice, $attributes, $value, $tooltip);

        $dropdown_field = array(
            'name'     => 'update_post_action',
            'choices'  => array(
                array( 'label' => '' ),
                array( 'label' => esc_html__('Mark Post as Draft', 'gravityformsbillplz'), 'value' => 'draft' ),
                array( 'label' => esc_html__('Delete Post', 'gravityformsbillplz'), 'value' => 'delete' ),

            ),
            'onChange' => "var checked = jQuery(this).val() ? 'checked' : false; jQuery('#change_post_status').attr('checked', checked);",
        );
        $markup .= '&nbsp;&nbsp;' . $this->settings_select($dropdown_field, false);

        return $markup;
    }

    /**
     * Prevent the GFPaymentAddOn version of the options field being added to the feed settings.
     *
     * @return bool
     */
    public function option_choices()
    {
        
        return false;
    }

    public function save_feed_settings($feed_id, $form_id, $settings)
    {

        //--------------------------------------------------------
        //For backwards compatibility
        $feed = $this->get_feed($feed_id);

        //Saving new fields into old field names to maintain backwards compatibility for delayed payments
        $settings['type'] = $settings['transactionType'];

        $feed['meta'] = $settings;
        $feed         = apply_filters('gform_billplz_save_config', $feed);
        
        //call hook to validate custom settings/meta added using gform_billplz_action_fields or gform_billplz_add_option_group action hooks
        $is_validation_error = apply_filters('gform_billplz_config_validation', false, $feed);
        if ($is_validation_error) {
            //fail save
            return false;
        }

        $settings = $feed['meta'];
        
        //--------------------------------------------------------

        return parent::save_feed_settings($feed_id, $form_id, $settings);
    }

    //------ SENDING TO BILLPLZ -----------//
    
    public function redirect_url($feed, $submission_data, $form, $entry)
    {
        // Don't process redirect url if request is a Billplz redirect
        if (!rgempty('billplz', $_GET)) {
            return false;
        }

        // Don't process redirect url if request is a Billplz callback
        if (!rgempty('url', $_POST)) {
            return false;
        }

        // Update lead's payment_status to Processing
        GFAPI::update_entry_property($entry['id'], 'payment_status', 'Processing');

        $feed_meta = $feed['meta'];

        //get array key for required parameter
        $b = 'billingInformation_';

        $int_name = isset($feed_meta[$b.'name']) ? $feed_meta[$b.'name'] : '';
        $int_email = isset($feed_meta[$b.'email']) ? $feed_meta[$b.'email'] : '';
        $int_mobile = isset($feed_meta[$b.'mobile']) ? $feed_meta[$b.'mobile'] : '';
        $int_reference_1_label = isset($feed_meta[$b.'reference_1_label']) ? $feed_meta[$b.'reference_1_label'] : '';
        $int_reference_2_label = isset($feed_meta[$b.'reference_2_label']) ? $feed_meta[$b.'reference_2_label'] : '';
        $int_reference_1 = isset($feed_meta[$b.'reference_1']) ? $feed_meta[$b.'reference_1'] : '';
        $int_reference_2 = isset($feed_meta[$b.'reference_2']) ? $feed_meta[$b.'reference_2'] : '';

        $email = isset($entry[$int_email]) ? $entry[$int_email] : '';
        $mobile = isset($entry[$int_mobile]) ? $entry[$int_mobile] : '';
        $name = isset($entry[$int_name]) ? $entry[$int_name] : '';

        $parameter = array(
            'collection_id' => trim($feed_meta['collection_id']),
            'email' => trim($email),
            'mobile'=> trim($mobile),
            'name' => trim($name),
            'amount' => strval(rgar($submission_data, 'payment_amount') * 100),
            'callback_url' => site_url("/?page=gf_billplz&entry_id={$entry['id']}"),
            'description' => mb_substr(GFCommon::replace_variables($feed_meta['bill_description'], $form, $entry), 0, 200)
        );

        if (empty($parameter['mobile']) && empty($parameter['email'])) {
            $parameter['email'] = 'noreply@billplz.com';
        }

        if (empty($parameter['name'])) {
            $blog_name = get_bloginfo('name');
            $parameter['name'] =  !empty($blog_name) ? $blog_name : 'Set your payer name';
        }

        if (empty($parameter['description'])) {
            $blog_description = get_bloginfo('description');
            $parameter['description'] = !empty($blog_description) ? $blog_description : 'Set your payment description';
        }

        $reference_1_label = isset($entry[$int_reference_1_label]) ? $entry[$int_reference_1_label] : '';
        $reference_1 = isset($entry[$int_reference_1]) ? $entry[$int_reference_1] : '';
        $reference_2_label = isset($entry[$int_reference_2_label]) ? $entry[$int_reference_2_label] : '';
        $reference_2 = isset($entry[$int_reference_2]) ? $entry[$int_reference_2] : '';
        
        $optional = array(
            'redirect_url' => $parameter['callback_url'],
            'reference_1_label' => mb_substr($reference_1_label, 0, 20),
            'reference_1' => mb_substr($reference_1, 0, 120),
            'reference_2_label' => mb_substr($reference_2_label, 0, 20),
            'reference_2' => mb_substr($reference_2, 0, 120)
        );

        if (isset($feed_meta['mode'])){
          $is_sandbox = $feed_meta['mode'] == 'sandbox';
        } else {
          $is_sandbox = false;
        }

        $connect = BillplzGravityFormsWPConnect::get_instance();
        $connect->set_api_key(trim($feed_meta['api_key']), $is_sandbox);

        $billplz = BillplzGravityFormsAPI::get_instance();
        $billplz->set_connect($connect);

        list($rheader, $rbody) = $billplz->toArray($billplz->createBill($parameter, $optional));

        if ($rheader !== 200) {
            $this->log_debug(__METHOD__ . "(): Failed to connect to Billplz");
            return '';
        }

        $return_url = $this->return_url($form['id'], $entry['id']);
        gform_update_meta($entry['id'], 'return_url', $return_url);
        gform_update_meta($entry['id'], 'bill_id', $rbody['id']);

        return $rbody['url'];
    }

    public function return_url($form_id, $lead_id)
    {
        $pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

        $server_port = apply_filters('gform_billplz_return_url_port', $_SERVER['SERVER_PORT']);

        if ($server_port != '80') {
            $pageURL .= $_SERVER['SERVER_NAME'] . ':' . $server_port . $_SERVER['REQUEST_URI'];
        } else {
            $pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        }

        $ids_query = "ids={$form_id}|{$lead_id}";
        $ids_query .= '&hash=' . wp_hash($ids_query);

        $url = add_query_arg('gf_billplz_return', base64_encode($ids_query), $pageURL);

        $query = 'gf_billplz_return=' . base64_encode($ids_query);

        return apply_filters('gform_billplz_return_url', $url, $form_id, $lead_id, $query);
    }

    public static function maybe_thankyou_page()
    {
        $instance = self::get_instance();

        if (! $instance->is_gravityforms_supported()) {
            return;
        }

        if ($str = rgget('gf_billplz_return')) {
            $str = base64_decode($str);

            parse_str($str, $query);
            if (wp_hash('ids=' . $query['ids']) == $query['hash']) {
                list( $form_id, $lead_id ) = explode('|', $query['ids']);

                $form = GFAPI::get_form($form_id);
                $lead = GFAPI::get_entry($lead_id);

                if (! class_exists('GFFormDisplay')) {
                    require_once(GFCommon::get_base_path() . '/form_display.php');
                }

                $confirmation = GFFormDisplay::handle_confirmation($form, $lead, false);

                if (is_array($confirmation) && isset($confirmation['redirect'])) {
                    header("Location: {$confirmation['redirect']}");
                    exit;
                }

                GFFormDisplay::$submission[ $form_id ] = array( 'is_confirmation' => true, 'confirmation_message' => $confirmation, 'form' => $form, 'lead' => $lead );
            }
        }
    }

    public function delay_post($is_disabled, $form, $entry)
    {
        $feed            = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);

        if (! $feed || empty($submission_data['payment_amount'])) {
            return $is_disabled;
        }

        return ! rgempty('delayPost', $feed['meta']);
    }

    public function delay_notification($is_disabled, $notification, $form, $entry)
    {
        if (rgar($notification, 'event') != 'form_submission') {
            return $is_disabled;
        }

        $feed            = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);

        if (! $feed || empty($submission_data['payment_amount'])) {
            return $is_disabled;
        }

        $selected_notifications = is_array(rgar($feed['meta'], 'selectedNotifications')) ? rgar($feed['meta'], 'selectedNotifications') : array();

        return isset($feed['meta']['delayNotification']) && in_array($notification['id'], $selected_notifications) ? true : $is_disabled;
    }

    //------- AFTER PAYMENT -----------//
    
    public function callback()
    {
        if (! $this->is_gravityforms_supported()) {
            return false;
        }

        $entry = GFAPI::get_entry(rgget('entry_id'));

        if (is_wp_error($entry)) {
            $this->log_error(__METHOD__ . '(): Entry could not be found. Aborting.');
            return false;
        }
        
        $this->log_debug(__METHOD__ . '(): Entry has been found => ' . print_r($entry, true));
        
        $bill_id = gform_get_meta($entry['id'], 'bill_id');
        
        if (!$bill_id) {
            $this->log_debug(__METHOD__ . '(): Bill ID not found => ' . print_r($entry, true));
            return false;
        }
      
        if ($entry['status'] == 'spam') {
            $this->log_error(__METHOD__ . '(): Entry is marked as spam. Aborting.');
            return false;
        }

        $feed = $this->get_payment_feed($entry);
        $x_signature = trim($feed['meta']['x_signature_key']);

        try {
            $data = BillplzGravityFormsWPConnect::getXSignature($x_signature);
        } catch (Exception $e) {
            status_header(403);
            $this->log_debug(__METHOD__ . '(): Failed X Signature Validation.');
            exit('Failed X Signature Validation');
        }
        
        if ($bill_id !== $data['id']) {
            $this->log_debug(__METHOD__ . '(): Bill ID not match with entry => ' . print_r($entry, true));
            return false;
        }

        if ($data['type'] === 'redirect') {
            $return_url = gform_get_meta($entry['id'], 'return_url');
            if (!empty($feed['meta']['cancel_url']) && !$data['paid']) {
                $return_url = $feed['meta']['cancel_url'];
            }
            header("Location: $return_url");
            exit;
        }

        //Ignore IPN messages from forms that are no longer configured with the Billplz
        if (! $feed || ! rgar($feed, 'is_active')) {
            $this->log_error(__METHOD__ . "(): Form no longer is configured with Billplz. Form ID: {$entry['form_id']}. Aborting.");
            return false;
        }

        if ($data['type'] === 'callback' && $data['paid']) {
            return array(
                'id' => $data['id'],
                'transaction_id' => $data['id'],
                'amount' => strval($data['amount'] / 100),
                'entry_id' => $entry['id'],
                'payment_date' => get_the_date('y-m-d H:i:s'),
                'type' => 'complete_payment',
                'payment_method' => 'Billplz',
                'ready_to_fulfill' => !$entry['is_fulfilled'] ? true : false,
            );
        }
        return false;
    }

    public function get_payment_feed($entry, $form = false)
    {

        $feed = parent::get_payment_feed($entry, $form);

        if (empty($feed) && ! empty($entry['id'])) {
            //looking for feed created by legacy versions
            $feed = $this->get_billplz_feed_by_entry($entry['id']);
        }

        $feed = apply_filters('gform_billplz_get_payment_feed', $feed, $entry, $form ? $form : GFAPI::get_form($entry['form_id']));

        return $feed;
    }

    private function get_billplz_feed_by_entry($entry_id)
    {

        $feed_id = gform_get_meta($entry_id, 'billplz_feed_id');
        $feed    = $this->get_feed($feed_id);

        return ! empty($feed) ? $feed : false;
    }

    public function post_callback($callback_action, $callback_result)
    {
        if (is_wp_error($callback_action) || ! $callback_action) {
            return false;
        }

        //run the necessary hooks
        $entry          = GFAPI::get_entry($callback_action['entry_id']);
        $feed           = $this->get_payment_feed($entry);
        $transaction_id = rgar($callback_action, 'transaction_id');
        $amount         = rgar($callback_action, 'amount');
        
        $this->fulfill_order($entry, $transaction_id, $amount, $feed);

        do_action('gform_billplz_post_payment_status', $feed, $entry, $transaction_id, $amount);

        if (has_filter('gform_billplz_post_payment_status')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_billplz_post_payment_status.');
        }
    }

    public function is_callback_valid()
    {
        if (rgget('page') != 'gf_billplz') {
            return false;
        }

        return true;
    }

    //------- AJAX FUNCTIONS ------------------//

    public function init_ajax()
    {
        parent::init_ajax();
    }

    //------- ADMIN FUNCTIONS/HOOKS -----------//

    public function init_admin()
    {

        parent::init_admin();

        //add actions to allow the payment status to be modified
        add_action('gform_payment_status', array( $this, 'admin_edit_payment_status' ), 3, 3);
        add_action('gform_payment_date', array( $this, 'admin_edit_payment_date' ), 3, 3);
        add_action('gform_payment_transaction_id', array( $this, 'admin_edit_payment_transaction_id' ), 3, 3);
        add_action('gform_payment_amount', array( $this, 'admin_edit_payment_amount' ), 3, 3);
        add_action('gform_after_update_entry', array( $this, 'admin_update_payment' ), 4, 2);
    }

    public function supported_notification_events($form)
    {
        if (! $this->has_feed($form['id'])) {
            return false;
        }

        return array(
                'complete_payment'          => esc_html__('Payment Completed', 'gravityformsbillplz'),
                'fail_payment'              => esc_html__('Payment Failed', 'gravityformsbillplz'),
        );
    }

    public function admin_edit_payment_status($payment_status, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_status;
        }

        //create drop down for payment status
        $payment_string = gform_tooltip('billplz_edit_payment_status', '', true);
        $payment_string .= '<select id="payment_status" name="payment_status">';
        $payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status . '</option>';
        $payment_string .= '<option value="Paid">Paid</option>';
        $payment_string .= '</select>';

        return $payment_string;
    }

    public function admin_edit_payment_date($payment_date, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_date;
        }

        $payment_date = $entry['payment_date'];
        if (empty($payment_date)) {
            $payment_date = get_the_date('y-m-d H:i:s');
        }

        $input = '<input type="text" id="payment_date" name="payment_date" value="' . $payment_date . '">';

        return $input;
    }

    public function admin_edit_payment_transaction_id($transaction_id, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $transaction_id;
        }

        $input = '<input type="text" id="billplz_transaction_id" name="billplz_transaction_id" value="' . $transaction_id . '">';

        return $input;
    }

    public function admin_edit_payment_amount($payment_amount, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_amount;
        }

        if (empty($payment_amount)) {
            $payment_amount = GFCommon::get_order_total($form, $entry);
        }

        $input = '<input type="text" id="payment_amount" name="payment_amount" class="gform_currency" value="' . $payment_amount . '">';

        return $input;
    }

    public function admin_update_payment($form, $entry_id)
    {
        check_admin_referer('gforms_save_entry', 'gforms_save_entry');

        //update payment information in admin, need to use this function so the lead data is updated before displayed in the sidebar info section
        $entry = GFFormsModel::get_lead($entry_id);

        if ($this->payment_details_editing_disabled($entry, 'update')) {
            return;
        }
        
        //get payment fields to update
        $payment_status = rgpost('payment_status');
        //when updating, payment status may not be editable, if no value in post, set to lead payment status
        if (empty($payment_status)) {
            $payment_status = $entry['payment_status'];
        }

        $payment_amount      = GFCommon::to_number(rgpost('payment_amount'));
        $payment_transaction = rgpost('billplz_transaction_id');
        $payment_date        = rgpost('payment_date');

        $status_unchanged = $entry['payment_status'] == $payment_status;
        $amount_unchanged = $entry['payment_amount'] == $payment_amount;
        $id_unchanged     = $entry['transaction_id'] == $payment_transaction;
        $date_unchanged   = $entry['payment_date'] == $payment_date;

        if ($status_unchanged && $amount_unchanged && $id_unchanged && $date_unchanged) {
            return;
        }

        if (empty($payment_date)) {
            $payment_date = get_the_date('y-m-d H:i:s');
        } else {
            //format date entered by user
            $payment_date = date('Y-m-d H:i:s', strtotime($payment_date));
        }

        global $current_user;
        $user_id   = 0;
        $user_name = 'System';
        if ($current_user && $user_data = get_userdata($current_user->ID)) {
            $user_id   = $current_user->ID;
            $user_name = $user_data->display_name;
        }

        $entry['payment_status'] = $payment_status;
        $entry['payment_amount'] = $payment_amount;
        $entry['payment_date']   = $payment_date;
        $entry['transaction_id'] = $payment_transaction;

        // if payment status does not equal approved/paid or the lead has already been fulfilled, do not continue with fulfillment
        if (( $payment_status == 'Approved' || $payment_status == 'Paid' ) && ! $entry['is_fulfilled']) {
            $action['id']             = $payment_transaction;
            $action['type']           = 'complete_payment';
            $action['transaction_id'] = $payment_transaction;
            $action['amount']         = $payment_amount;
            $action['entry_id']       = $entry['id'];

            $this->complete_payment($entry, $action);
            $this->fulfill_order($entry, $payment_transaction, $payment_amount);
        }
        //update lead, add a note
        GFAPI::update_entry($entry);
        // translators: %1$s is replaced with payment status
        // translators: %2$s is replaced with payment amount
        // translators: %3$s is replaced with currency
        // translators: %4$s is replaced with payment date
        GFFormsModel::add_note($entry['id'], $user_id, $user_name, sprintf(esc_html__('Payment information was manually updated. Status: %1$s. Amount: %2$s. Transaction ID: %3$s. Date: %4$s', 'gravityformsbillplz'), $entry['payment_status'], GFCommon::to_money($entry['payment_amount'], $entry['currency']), $payment_transaction, $entry['payment_date']));
    }

    public function fulfill_order(&$entry, $transaction_id, $amount, $feed = null)
    {

        if (! $feed) {
            $feed = $this->get_payment_feed($entry);
        }

        $form = GFFormsModel::get_form_meta($entry['form_id']);
        if (rgars($feed, 'meta/delayPost')) {
            $this->log_debug(__METHOD__ . '(): Creating post.');
            $entry['post_id'] = GFFormsModel::create_post($form, $entry);
            $this->log_debug(__METHOD__ . '(): Post created.');
        }

        if (rgars($feed, 'meta/delayNotification')) {
            //sending delayed notifications
            $notifications = $this->get_notifications_to_send($form, $feed);
            GFCommon::send_notifications($notifications, $form, $entry, true, 'form_submission');
        }

        do_action('gform_billplz_fulfillment', $entry, $feed, $transaction_id, $amount);
        if (has_filter('gform_billplz_fulfillment')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_billplz_fulfillment.');
        }
    }

    public function get_notifications_to_send($form, $feed)
    {
        $notifications_to_send  = array();
        $selected_notifications = rgars($feed, 'meta/selectedNotifications');

        if (is_array($selected_notifications)) {
            // Make sure that the notifications being sent belong to the form submission event, just in case the notification event was changed after the feed was configured.
            foreach ($form['notifications'] as $notification) {
                if (rgar($notification, 'event') != 'form_submission' || ! in_array($notification['id'], $selected_notifications)) {
                    continue;
                }

                $notifications_to_send[] = $notification['id'];
            }
        }

        return $notifications_to_send;
    }

    public function payment_details_editing_disabled($entry, $action = 'edit')
    {
        if (! $this->is_payment_gateway($entry['id'])) {
            // Entry was not processed by this add-on, don't allow editing.
            return true;
        }

        $payment_status = rgar($entry, 'payment_status');
        if ($payment_status == 'Approved' || $payment_status == 'Paid' || rgar($entry, 'transaction_type') == 2) {
            // Editing not allowed for this entries transaction type or payment status.
            return true;
        }

        if ($action == 'edit' && rgpost('screen_mode') == 'edit') {
            // Editing is allowed for this entry.
            return false;
        }

        if ($action == 'update' && rgpost('screen_mode') == 'view' && rgpost('action') == 'update') {
            // Updating the payment details for this entry is allowed.
            return false;
        }

        // In all other cases editing is not allowed.

        return true;
    }

    public function uninstall()
    {
        parent::uninstall();
    }
}
