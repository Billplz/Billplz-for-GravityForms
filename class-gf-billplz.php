<?php
add_action('wp', array('GFBillplz', 'maybe_thankyou_page'), 5);

GFForms::include_payment_addon_framework();

class GFBillplz extends GFPaymentAddOn
{

    protected $_version = GF_BILLPLZ_VERSION;
    protected $_min_gravityforms_version = '1.9.3';
    protected $_slug = 'gravityformsbillplz';
    protected $_path = 'gravityformsbillplz/billplz.php';
    protected $_full_path = __FILE__;
    protected $_url = 'http://www.gravityforms.com';
    protected $_title = 'Gravity Forms Billplz Add-On';
    protected $_short_title = 'Billplz';
    protected $_supports_callbacks = true;
    // Members plugin integration
    protected $_capabilities = array('gravityforms_billplz', 'gravityforms_billplz_uninstall');
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

    }
    /* do nothing */

    public function init_frontend()
    {
        parent::init_frontend();

        add_filter('gform_disable_post_creation', array($this, 'delay_post'), 10, 3);
        add_filter('gform_disable_notification', array($this, 'delay_notification'), 10, 4);
    }

    public function feed_list_no_item_message()
    {
        return parent::feed_list_no_item_message();
    }

    public function feed_settings_fields()
    {
        $default_settings = parent::feed_settings_fields();

        /*
         * Include settings file and import
         */
        require_once(__DIR__ . '/includes/billplz-required-settings.php');
        $fields = BillplzRequiredSettings::get_required_settings_billplz_gf();

        $default_settings = parent::add_field_after('feedName', $fields, $default_settings);

        /*
         * Remove Subscription Option on Transaction Type
         */

        $transaction_type = parent::get_field('transactionType', $default_settings);
        unset($transaction_type['choices'][2]);
        $transaction_type['required'] = true;
        $transaction_type['choices'][1]['label'] = 'Billplz (FPX)';

        $default_settings = $this->replace_field('transactionType', $transaction_type, $default_settings);

        /*
         * Add Cancel URL & Delay Notification
         */

        $fields = array(
            array(
                'name' => 'cancelUrl',
                'label' => esc_html__('Cancel URL', 'gravityformsbillplz'),
                'type' => 'text',
                'class' => 'medium',
                'required' => false,
                'tooltip' => '<h6>' . esc_html__('Cancel URL', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter the URL the user should be sent to should they cancel before completing their Billplz payment.', 'gravityformsbillplz')
            ),
        );

        if ($this->get_setting('delayNotification') || !$this->is_gravityforms_supported('1.9.12')) {
            $fields[] = array(
                'name' => 'notifications',
                'label' => esc_html__('Notifications', 'gravityformsbillplz'),
                'type' => 'notifications',
                'tooltip' => '<h6>' . esc_html__('Notifications', 'gravityformsbillplz') . '</h6>' . esc_html__("Enable this option if you would like to only send out this form's notifications for the 'Form is submitted' event after payment has been received. Leaving this option disabled will send these notifications immediately after the form is submitted. Notifications which are configured for other events will not be affected by this option.", 'gravityformsbillplz')
            );
        }

        //Add post fields if form has a post
        $form = $this->get_current_form();
        if (GFCommon::has_post_field($form['fields'])) {
            $post_settings = array(
                'name' => 'post_checkboxes',
                'label' => esc_html__('Posts', 'gravityformsbillplz'),
                'type' => 'checkbox',
                'tooltip' => '<h6>' . esc_html__('Posts', 'gravityformsbillplz') . '</h6>' . esc_html__('Enable this option if you would like to only create the post after payment has been received.', 'gravityformsbillplz'),
                'choices' => array(
                    array('label' => esc_html__('Create post only when payment is received.', 'gravityformsbillplz'), 'name' => 'delayPost'),
                ),
            );
            $fields[] = $post_settings;
        }

        /*
         * Personal Note: To get current setting use this:
         * $this->get_setting('transactionType');
         *
         * It will return e.g.: 'subscription'
         */

        //Adding custom settings for backwards compatibility with hook 'gform_billplz_add_option_group'
        $fields[] = array(
            'name' => 'custom_options',
            'label' => '',
            'type' => 'custom',
        );

        $default_settings = $this->add_field_after('billingInformation', $fields, $default_settings);

        /*
         * Add required Billplz variable to create bills
         * Removed non-related Billplz required variable
         */

        $billing_info = parent::get_field('billingInformation', $default_settings);
        $billing_info = BillplzRequiredSettings::get_required_billing_info_billplz_gf($billing_info);

        $default_settings = parent::replace_field('billingInformation', $billing_info, $default_settings);


        //----------------------------------------------------------------------------------------------------
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
            'name' => 'delay_notification',
            'type' => 'checkboxes',
            'onclick' => 'ToggleNotifications();',
            'choices' => array(
                array(
                    'label' => esc_html__("Send notifications for the 'Form is submitted' event only when payment is received.", 'gravityformsbillplz'),
                    'name' => 'delayNotification',
                ),
            )
        );

        $html = $this->settings_checkbox($checkboxes, false);

        $html .= $this->settings_hidden(array('name' => 'selectedNotifications', 'id' => 'selectedNotifications'), false);

        $form = $this->get_current_form();
        $has_delayed_notifications = $this->get_setting('delayNotification');
        ob_start();

        ?>
        <ul id="gf_billplz_notification_container" style="padding-left:20px; margin-top:10px; <?php echo $has_delayed_notifications ? '' : 'display:none;' ?>">
            <?php
            if (!empty($form) && is_array($form['notifications'])) {
                $selected_notifications = $this->get_setting('selectedNotifications');
                if (!is_array($selected_notifications)) {
                    $selected_notifications = array();
                }

                //$selected_notifications = empty($selected_notifications) ? array() : json_decode($selected_notifications);

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
                } else {
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
            'name' => 'update_post_action',
            'choices' => array(
                array('label' => ''),
                array('label' => esc_html__('Mark Post as Draft', 'gravityformsbillplz'), 'value' => 'draft'),
                array('label' => esc_html__('Delete Post', 'gravityformsbillplz'), 'value' => 'delete'),
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

        if (isset($settings['recurringAmount'])) {
            $settings['recurring_amount_field'] = $settings['recurringAmount'];
        }

        $feed['meta'] = $settings;
        $feed = apply_filters('gform_billplz_save_config', $feed);

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

        //Don't process redirect url if request is a Billplz return
        if (isset($_GET['billplz']['id'])) {
            return false;
        }

        $entry_id = $entry['id'];

        //updating lead's payment_status to Pending
        GFAPI::update_entry_property($entry_id, 'payment_status', 'Pending');

        /*
         * Save after url to redirect if success
         */

        if (!session_id()) {
            session_start();
        }

        $_SESSION['success_redirect' . $entry_id] = $this->return_url($form['id'], $entry['id']);

        /*
         * Get to know where is variable inside $entry
         */

        $int_name = $feed['meta']['billingInformation_name'];
        $int_email = $feed['meta']['billingInformation_email'];
        $int_mobile = $feed['meta']['billingInformation_bill_mobile'];
        $int_reference_1 = $feed['meta']['billingInformation_reference_1'];
        $int_reference_2 = $feed['meta']['billingInformation_reference_2'];
        $int_bill_desc = $feed['meta']['billingInformation_bill_desc'];

        /*
         * Current Currency
         * $currency = rgar($entry, 'currency');
         */

        //URL that will listen to notifications from Billplz
        $ipn_url = get_bloginfo('url') . '/?page=gf_billplz_ipn';

        $api_key = trim($feed['meta']['api_key']);
        $collection_id = trim($feed['meta']['collection_id']);
        $deliver = trim($feed['meta']['payment_reminder']);
        $reference_1_label = trim($feed['meta']['reference_1_label']);
        $reference_2_label = trim($feed['meta']['reference_2_label']);
        $description = $feed['meta']['bill_description'] . $entry[$int_bill_desc];
        $reference_1 = trim($feed['meta']['reference_1'] . $entry[$int_reference_1]);
        $reference_2 = trim($feed['meta']['reference_2'] . $entry[$int_reference_2]);
        $name = trim($entry[$int_name]);
        $amount = rgar($submission_data, 'payment_amount');
        $mobile = trim($entry[$int_mobile]);
        $email = trim($entry[$int_email]);

		/*
         * Save to db for future matching
         */
        update_option('billplz_gf_amount_' . $entry_id, $amount, false);

        /*
         * Import billplz.php file for Create A Bill
         */

        require_once __DIR__ . '/includes/billplz.php';

        $billplz = new Billplz($api_key);
        $billplz
            ->setAmount($amount)
            ->setCollection($collection_id)
            ->setDeliver($deliver)
            ->setDescription($description)
            ->setEmail($email)
            ->setMobile($mobile)
            ->setName($name)
            ->setPassbackURL($ipn_url, $ipn_url)
            ->setReference_1($reference_1)
            ->setReference_1_Label($reference_1_label)
            ->setReference_2($reference_2)
            ->setReference_2_Label($reference_2_label)
            ->create_bill(true);

        $url = $billplz->getURL();
        $id = $billplz->getID();

        /*
         * Save to db for callback & redirect use
         */
        update_option('billplz_gf_' . $id, $entry_id, false);

        $this->log_debug(__METHOD__ . "(): Sending to Billplz: {$url}");

        return $url;
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
        /**
         * Filters Billplz's return URL, which is the URL that users will be sent to after completing the payment on Billplz's site.
         * Useful when URL isn't created correctly (could happen on some server configurations using PROXY servers).
         *
         * @since 2.4.5
         *
         * @param string  $url 	The URL to be filtered.
         * @param int $form_id	The ID of the form being submitted.
         * @param int $entry_id	The ID of the entry that was just created.
         * @param string $query	The query string portion of the URL.
         */
        return apply_filters('gform_billplz_return_url', $url, $form_id, $lead_id, $query);
    }
    /*
     * This method needs to be provided to give an ability for the user to uninstall the plugin
     */

    public function plugin_settings_fields()
    {

    }

    public static function maybe_thankyou_page()
    {
        $instance = self::get_instance();

        if (!$instance->is_gravityforms_supported()) {
            return;
        }

        if ($str = rgget('gf_billplz_return')) {
            $str = base64_decode($str);

            parse_str($str, $query);
            if (wp_hash('ids=' . $query['ids']) == $query['hash']) {
                list( $form_id, $lead_id ) = explode('|', $query['ids']);

                $form = GFAPI::get_form($form_id);
                $lead = GFAPI::get_entry($lead_id);

                if (!class_exists('GFFormDisplay')) {
                    require_once( GFCommon::get_base_path() . '/form_display.php' );
                }

                $confirmation = GFFormDisplay::handle_confirmation($form, $lead, false);

                if (is_array($confirmation) && isset($confirmation['redirect'])) {
                    header("Location: {$confirmation['redirect']}");
                    exit;
                }

                GFFormDisplay::$submission[$form_id] = array('is_confirmation' => true, 'confirmation_message' => $confirmation, 'form' => $form, 'lead' => $lead);
            }
        }
    }

    public function delay_post($is_disabled, $form, $entry)
    {

        $feed = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);

        if (!$feed || empty($submission_data['payment_amount'])) {
            return $is_disabled;
        }

        return !rgempty('delayPost', $feed['meta']);
    }

    public function delay_notification($is_disabled, $notification, $form, $entry)
    {
        if (rgar($notification, 'event') != 'form_submission') {
            return $is_disabled;
        }

        $feed = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);

        if (!$feed || empty($submission_data['payment_amount'])) {
            return $is_disabled;
        }

        $selected_notifications = is_array(rgar($feed['meta'], 'selectedNotifications')) ? rgar($feed['meta'], 'selectedNotifications') : array();

        return isset($feed['meta']['delayNotification']) && in_array($notification['id'], $selected_notifications) ? true : $is_disabled;
    }

    //------- PROCESSING BILLPLZ IPN (Callback) -----------//

    public function callback()
    {

        if (!$this->is_gravityforms_supported()) {
            return false;
        }

        $this->log_debug(__METHOD__ . '(): IPN request received. Starting to process => ' . print_r($_REQUEST, true));

        require_once __DIR__ . '/includes/billplz.php';

        if (!session_id()) {
            session_start();
        }

        $action = $this->process_bill();

        if (isset($_GET['billplz']['x_signature'])) {
            if ($action['type'] === 'complete_payment') {
                $url = $_SESSION['success_redirect' . $action['entry_id']];
                unset($_SESSION['success_redirect' . $action['entry_id']]);

                $stroutput = "Success! Redirecting to Back... If you are not redirected, please click <a href=" . '"' . $url . '"' . " target='_self'>Here</a><br />"
                    . "<script>location.href = '" . $url . "'</script>";
            } else {
                $stroutput = "Cancelled.. Redirecting to Back... If you are not redirected, please click <a href=" . '"' . $action['cancel_url'] . '"' . " target='_self'>Here</a><br />"
                    . "<script>location.href = '" . $action['cancel_url'] . "'</script>";
            }
            echo $stroutput;
        }
        if (rgempty('entry_id', $action)) {
            return false;
        }

        /* Do not process unpaid bills to prevent error */
        if ($action['type'] === 'fail_payment'){
          return false;
        }


        return $action;
    }

    private function process_bill()
    {
        $bill_id = htmlspecialchars(isset($_GET['billplz']['id']) ? $_GET['billplz']['id'] : $_POST['id']);

        $entry_id = get_option('billplz_gf_' . $bill_id, false);

        if (!$entry_id) {
	    $this->log_debug(__METHOD__ . "(): Response from Bill: {$bill_id} but the bills is not related to any entry id.");
            exit;
        }

        $entry = GFAPI::get_entry($entry_id);

        $this->log_debug(__METHOD__ . '(): Entry has been found => ' . print_r($entry, true));

        if ($entry['status'] == 'spam') {
            $this->log_error(__METHOD__ . '(): Entry is marked as spam. Aborting.');
            return false;
        }

        //------ Getting feed related to this IPN ------------------------------------------//
        $feed = $this->get_payment_feed($entry);

        $api_key = $feed['meta']['api_key'];
        $x_sign = $feed['meta']['x_signature_key'];

        if (isset($_GET['billplz']['x_signature'])) {
            $data = Billplz::getRedirectData($x_sign);
        } else if (isset($_POST['x_signature'])) {
            /*
             * Prevent Asynchronous run with redirect
             */
            sleep(10);
            $data = Billplz::getCallbackData($x_sign);
        } else {
            $this->log_error(__METHOD__ . '(): IPN request does not have a custom field, so it was not created by Gravity Forms. Aborting.');
            return false;
        }

        $bill_id = $data['id'];

        $billplz = new Billplz($api_key);
        $moreData = $billplz->check_bill($bill_id);
        $paid_time = $billplz->get_bill_paid_time($bill_id);

        $amount = number_format($moreData['amount'] / 100, 2);

        //Ignore IPN messages from forms that are no longer configured with the Billplz add-on
        if (!$feed || !rgar($feed, 'is_active')) {
            $this->log_error(__METHOD__ . "(): Form no longer is configured with Billplz Addon. Form ID: {$entry['form_id']}. Aborting.");

            return false;
        }
        $this->log_debug(__METHOD__ . "(): Form {$entry['form_id']} is properly configured.");

        //----- Processing IPN ------------------------------------------------------------//
        $this->log_debug(__METHOD__ . '(): Processing IPN...');

        $action = [
            'id' => $bill_id,
            'transaction_id' => $bill_id,
            'amount' => $amount,
            'entry_id' => $entry_id,
            'cancel_url' => $moreData['url']
        ];

        /*
         * If cancel url is preset by user
         */

        if (!empty($feed['meta']['cancelUrl'])) {
            $action['cancel_url'] = $feed['meta']['cancelUrl'];
        }

        if ($data['paid']) {
            $action['type'] = 'complete_payment';
            $action['payment_date'] = gmdate('d-m-Y H:i:s', $paid_time);
            $action['payment_method'] = 'Billplz';
            $action['ready_to_fulfill'] = !$entry['is_fulfilled'] ? true : false;
        } else {
            $action['type'] = 'fail_payment';
        }

        if (!$this->is_valid_initial_payment_amount($entry_id, $amount)) {
            $action['abort_callback'] = true;
        }

        $this->log_debug(__METHOD__ . '(): IPN processing complete.');
        return $action;
    }

    private function is_valid_initial_payment_amount($entry_id, $amount_paid)
    {

        $amount = get_option('billplz_gf_amount_' . $entry_id, false);

        if (!$amount) {
            exit;
        }

        $raw_amount_sent = floatval($amount);
        $amount_sent = number_format($raw_amount_sent, 2);

        if ($amount_sent !== $amount_paid) {
            return false;
        }
        return true;
    }

    public function get_payment_feed($entry, $form = false)
    {

        $feed = parent::get_payment_feed($entry, $form);

        if (empty($feed) && !empty($entry['id'])) {
            //looking for feed created by legacy versions
            $feed = $this->get_billplz_feed_by_entry($entry['id']);
        }

        $feed = apply_filters('gform_billplz_get_payment_feed', $feed, $entry, $form ? $form : GFAPI::get_form($entry['form_id']));

        return $feed;
    }

    private function get_billplz_feed_by_entry($entry_id)
    {

        $feed_id = gform_get_meta($entry_id, 'billplz_feed_id');
        $feed = $this->get_feed($feed_id);

        return !empty($feed) ? $feed : false;
    }

    public function post_callback($callback_action, $callback_result)
    {
        if (is_wp_error($callback_action) || !$callback_action) {
            return false;
        }

        //run the necessary hooks
        $entry = GFAPI::get_entry($callback_action['entry_id']);
        $feed = $this->get_payment_feed($entry);
        $transaction_id = rgar($callback_action, 'transaction_id');
        $amount = rgar($callback_action, 'amount');
        $subscriber_id = rgar($callback_action, 'subscriber_id');
        $pending_reason = rgpost('pending_reason');
        $reason = rgpost('reason_code');
        $status = rgpost('payment_status');
        $txn_type = rgpost('txn_type');
        $parent_txn_id = rgpost('parent_txn_id');

        //run gform_billplz_fulfillment only in certain conditions
        if (rgar($callback_action, 'ready_to_fulfill') && !rgar($callback_action, 'abort_callback')) {
            $this->fulfill_order($entry, $transaction_id, $amount, $feed);
        } else {
            if (rgar($callback_action, 'abort_callback')) {
                $this->log_debug(__METHOD__ . '(): Callback processing was aborted. Not fulfilling entry.');
            } else {
                $this->log_debug(__METHOD__ . '(): Entry is already fulfilled or not ready to be fulfilled, not running gform_billplz_fulfillment hook.');
            }
        }

        do_action('gform_post_payment_status', $feed, $entry, $status, $transaction_id, $subscriber_id, $amount, $pending_reason, $reason);
        if (has_filter('gform_post_payment_status')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_post_payment_status.');
        }

        do_action('gform_billplz_ipn_' . $txn_type, $entry, $feed, $status, $txn_type, $transaction_id, $parent_txn_id, $subscriber_id, $amount, $pending_reason, $reason);
        if (has_filter('gform_billplz_ipn_' . $txn_type)) {
            $this->log_debug(__METHOD__ . "(): Executing functions hooked to gform_billplz_ipn_{$txn_type}.");
        }

        do_action('gform_billplz_post_ipn', $_POST, $entry, $feed, false);
        if (has_filter('gform_billplz_post_ipn')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_billplz_post_ipn.');
        }
    }

    public function modify_post($post_id, $action)
    {

        $result = false;

        if (!$post_id) {
            return $result;
        }

        switch ($action) {
            case 'draft':
                $post = get_post($post_id);
                $post->post_status = 'draft';
                $result = wp_update_post($post);
                $this->log_debug(__METHOD__ . "(): Set post (#{$post_id}) status to \"draft\".");
                break;
            case 'delete':
                $result = wp_delete_post($post_id);
                $this->log_debug(__METHOD__ . "(): Deleted post (#{$post_id}).");
                break;
        }

        return $result;
    }

    public function is_callback_valid()
    {
        if (rgget('page') != 'gf_billplz_ipn') {
            return false;
        }

        return true;
    }

    //------- AJAX FUNCTIONS ------------------//

    public function init_ajax()
    {

        parent::init_ajax();

        add_action('wp_ajax_gf_dismiss_billplz_menu', array($this, 'ajax_dismiss_menu'));
    }

    //------- ADMIN FUNCTIONS/HOOKS -----------//

    public function init_admin()
    {

        parent::init_admin();

        //add actions to allow the payment status to be modified
        add_action('gform_payment_status', array($this, 'admin_edit_payment_status'), 3, 3);
        add_action('gform_payment_date', array($this, 'admin_edit_payment_date'), 3, 3);
        add_action('gform_payment_transaction_id', array($this, 'admin_edit_payment_transaction_id'), 3, 3);
        add_action('gform_payment_amount', array($this, 'admin_edit_payment_amount'), 3, 3);
        add_action('gform_after_update_entry', array($this, 'admin_update_payment'), 4, 2);
    }

    /**
     * Add supported notification events.
     *
     * @param array $form The form currently being processed.
     *
     * @return array
     */
    public function supported_notification_events($form)
    {
        if (!$this->has_feed($form['id'])) {
            return false;
        }

        return array(
            'complete_payment' => esc_html__('Payment Completed', 'gravityformsbillplz'),
            'refund_payment' => esc_html__('Payment Refunded', 'gravityformsbillplz'),
            'fail_payment' => esc_html__('Payment Failed', 'gravityformsbillplz'),
            'add_pending_payment' => esc_html__('Payment Pending', 'gravityformsbillplz'),
            'void_authorization' => esc_html__('Authorization Voided', 'gravityformsbillplz')
        );
    }

    public function ajax_dismiss_menu()
    {

        $current_user = wp_get_current_user();
        update_metadata('user', $current_user->ID, 'dismiss_billplz_menu', '1');
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
            $payment_date = gmdate('d-m-Y H:i:s');
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

        $payment_amount = GFCommon::to_number(rgpost('payment_amount'));
        $payment_transaction = rgpost('billplz_transaction_id');
        $payment_date = rgpost('payment_date');

        $status_unchanged = $entry['payment_status'] == $payment_status;
        $amount_unchanged = $entry['payment_amount'] == $payment_amount;
        $id_unchanged = $entry['transaction_id'] == $payment_transaction;
        $date_unchanged = $entry['payment_date'] == $payment_date;

        if ($status_unchanged && $amount_unchanged && $id_unchanged && $date_unchanged) {
            return;
        }

        if (empty($payment_date)) {
            $payment_date = gmdate('y-m-d H:i:s');
        } else {
            //format date entered by user
            $payment_date = date('Y-m-d H:i:s', strtotime($payment_date));
        }

        global $current_user;
        $user_id = 0;
        $user_name = 'System';
        if ($current_user && $user_data = get_userdata($current_user->ID)) {
            $user_id = $current_user->ID;
            $user_name = $user_data->display_name;
        }

        $entry['payment_status'] = $payment_status;
        $entry['payment_amount'] = $payment_amount;
        $entry['payment_date'] = $payment_date;
        $entry['transaction_id'] = $payment_transaction;

        // if payment status does not equal approved/paid or the lead has already been fulfilled, do not continue with fulfillment
        if (( $payment_status == 'Approved' || $payment_status == 'Paid' ) && !$entry['is_fulfilled']) {
            $action['id'] = $payment_transaction;
            $action['type'] = 'complete_payment';
            $action['transaction_id'] = $payment_transaction;
            $action['amount'] = $payment_amount;
            $action['entry_id'] = $entry['id'];

            $this->complete_payment($entry, $action);
            $this->fulfill_order($entry, $payment_transaction, $payment_amount);
        }
        //update lead, add a note
        GFAPI::update_entry($entry);
        GFFormsModel::add_note($entry['id'], $user_id, $user_name, sprintf(esc_html__('Payment information was manually updated. Status: %s. Amount: %s. Transaction ID: %s. Date: %s', 'gravityformsbillplz'), $entry['payment_status'], GFCommon::to_money($entry['payment_amount'], $entry['currency']), $payment_transaction, $entry['payment_date']));
    }

    public function fulfill_order(&$entry, $transaction_id, $amount, $feed = null)
    {

        if (!$feed) {
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

    /**
     * Retrieve the IDs of the notifications to be sent.
     *
     * @param array $form The form which created the entry being processed.
     * @param array $feed The feed which processed the entry.
     *
     * @return array
     */
    public function get_notifications_to_send($form, $feed)
    {
        $notifications_to_send = array();
        $selected_notifications = rgars($feed, 'meta/selectedNotifications');

        if (is_array($selected_notifications)) {
            // Make sure that the notifications being sent belong to the form submission event, just in case the notification event was changed after the feed was configured.
            foreach ($form['notifications'] as $notification) {
                if (rgar($notification, 'event') != 'form_submission' || !in_array($notification['id'], $selected_notifications)) {
                    continue;
                }

                $notifications_to_send[] = $notification['id'];
            }
        }

        return $notifications_to_send;
    }

    public function billplz_fulfillment($entry, $billplz_config, $transaction_id, $amount)
    {
        //no need to do anything for billplz when it runs this function, ignore
        return false;
    }

    /**
     * Editing of the payment details should only be possible if the entry was processed by Billplz, if the payment status is Pending or Processing, and the transaction was not a subscription.
     *
     * @param array $entry The current entry
     * @param string $action The entry detail page action, edit or update.
     *
     * @return bool
     */
    public function payment_details_editing_disabled($entry, $action = 'edit')
    {
        if (!$this->is_payment_gateway($entry['id'])) {
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
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'billplz_gf_%'");
    }

    //------ FOR BACKWARDS COMPATIBILITY ----------------------//

    public function update_feed_id($old_feed_id, $new_feed_id)
    {
        global $wpdb;
        $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}rg_lead_meta SET meta_value=%s WHERE meta_key='billplz_feed_id' AND meta_value=%s", $new_feed_id, $old_feed_id);
        $wpdb->query($sql);
    }

    public function add_legacy_meta($new_meta, $old_feed)
    {

        $known_meta_keys = array(
            'email', 'mode', 'type', 'style', 'continue_text', 'cancel_url', 'disable_note', 'disable_shipping', 'recurring_amount_field', 'recurring_times',
            'recurring_retry', 'billing_cycle_number', 'billing_cycle_type', 'trial_period_enabled', 'trial_amount', 'trial_period_number', 'trial_period_type', 'delay_post',
            'update_post_action', 'delay_notifications', 'selected_notifications', 'billplz_conditional_enabled', 'billplz_conditional_field_id',
            'billplz_conditional_operator', 'billplz_conditional_value', 'customer_fields',
        );

        foreach ($old_feed['meta'] as $key => $value) {
            if (!in_array($key, $known_meta_keys)) {
                $new_meta[$key] = $value;
            }
        }

        return $new_meta;
    }
    /*
     * This function kept static for backwards compatibility
     */

    public static function get_config_by_entry($entry)
    {

        $billplz = GFBillplz::get_instance();

        $feed = $billplz->get_payment_feed($entry);

        if (empty($feed)) {
            return false;
        }

        return $feed['addon_slug'] == $billplz->_slug ? $feed : false;
    }

    //This function kept static for backwards compatibility
    //This needs to be here until all add-ons are on the framework, otherwise they look for this function
    public static function get_config($form_id)
    {

        $billplz = GFBillplz::get_instance();
        $feed = $billplz->get_feeds($form_id);

        //Ignore IPN messages from forms that are no longer configured with the Billplz add-on
        if (!$feed) {
            return false;
        }

        return $feed[0]; //only one feed per form is supported (left for backwards compatibility)
    }
    //------------------------------------------------------
}
