<?php
add_action('wp', array(
	'GFBillplz',
	'maybe_thankyou_page'
), 5);
GFForms::include_payment_addon_framework();
//cURL
function DapatkanLink($api_key, $billplz_data, $host)
{
	$process = curl_init($host);
	curl_setopt($process, CURLOPT_HEADER, 0);
	curl_setopt($process, CURLOPT_USERPWD, $api_key . ":");
	curl_setopt($process, CURLOPT_TIMEOUT, 30);
	curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($process, CURLOPT_POSTFIELDS, http_build_query($billplz_data));
	$return = curl_exec($process);
	curl_close($process);
	$arr = json_decode($return, true);
	return $arr;
}
function DapatkanInfo($api_key, $verification2, $host)
{
	$host    = $host . $verification2;
	$process = curl_init($host);
	curl_setopt($process, CURLOPT_HEADER, 0);
	curl_setopt($process, CURLOPT_USERPWD, $api_key . ":");
	curl_setopt($process, CURLOPT_TIMEOUT, 30);
	curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
	$return = curl_exec($process);
	curl_close($process);
	$arra = json_decode($return, true);
	return $arra;
}
//end cURL
class GFBillplz extends GFPaymentAddOn
{
	protected $_version = GF_BILLPLZ_VERSION;
	protected $_min_gravityforms_version = '1.9.3';
	protected $_slug = 'gravityformsbillplz';
	protected $_path = 'billplz-for-gravityforms/billplz.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.wanzul-hosting.com';
	protected $_title = 'Billplz for GravityForms';
	protected $_short_title = 'Billplz';
	protected $_supports_callbacks = true;
	private $production_url = 'https://www.billplz.com/api/v3/bills/';
	private $staging_url = 'https://billplz-staging.herokuapp.com/api/v3/bills/';
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
		add_filter('gform_disable_post_creation', array(
			$this,
			'delay_post'
		), 10, 3);
		add_filter('gform_disable_notification', array(
			$this,
			'delay_notification'
		), 10, 4);
	}
	//----- SETTINGS PAGES ----------//
	public function plugin_settings_fields()
	{
		$description = '
			<p style="text-align: left;">' . esc_html__('Sokong projek Paymnet Gateway percuma dengan memberikan sumbangan kepada kami. Sumbangan boleh dilakukan dengan menekan pautan di bawah', 'gravityformsbillplz') . '</p>
			<ul>
				<li>' . sprintf(esc_html__('%sDonate Now.%s', 'gravityformsbillplz'), '<a href="https://www.billplz.com/form/sw2co7ig8" target="_blank">', '</a>') . '</li>' . '<li>' . sprintf(esc_html__('Saya telah membuat sumbangan kepada developer.: %s', 'gravityformsbillplz'), '<strong>' . esc_url(add_query_arg('page', 'gf_billplz_ipn', get_bloginfo('url') . '/')) . '</strong>') . '</li>' . '</ul>
				<br/>';
		return array(
			array(
				'title' => '',
				'description' => $description,
				'fields' => array(
					array(
						'name' => 'gf_billplz_configured',
						'label' => esc_html__('Billplz Donate Confirmation', 'gravityformsbillplz'),
						'type' => 'checkbox',
						'choices' => array(
							array(
								'label' => esc_html__('Saya telah membuat sumbangnan kepada developer.', 'gravityformsbillplz'),
								'name' => 'gf_billplz_configured'
							)
						)
					),
					array(
						'type' => 'save',
						'messages' => array(
							'success' => esc_html__('Thanks for donating!', 'gravityformsbillplz')
						)
					)
				)
			)
		);
	}
	public function feed_list_no_item_message()
	{
		$settings = $this->get_plugin_settings();
		if (!rgar($settings, 'gf_billplz_configured')) {
			return sprintf(esc_html__('To get started, let\'s go and confirm your donation at %sBillplz Settings%s!', 'gravityformsbillplz'), '<a href="' . admin_url('admin.php?page=gf_settings&subview=' . $this->_slug) . '">', '</a>');
		} else {
			return parent::feed_list_no_item_message();
		}
	}
	public function feed_settings_fields()
	{
		$default_settings = parent::feed_settings_fields();
		//--add Billplz Email Address field
		$fields           = array(
			array(
				'name' => 'billplzEmail', //billplzEmail is for Api Key
				'label' => esc_html__('Billplz Api Key ', 'gravityformsbillplz'),
				'type' => 'text',
				'class' => 'medium',
				'required' => true,
				'tooltip' => '<h6>' . esc_html__('Billplz Api Key', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter the Billplz api key.', 'gravityformsbillplz')
			),
			array(
				'name' => 'billplzColl', //billplzColl is for Collection ID
				'label' => esc_html__('Billplz Collection ID ', 'gravityformsbillplz'),
				'type' => 'text',
				'class' => 'medium',
				'required' => true,
				'tooltip' => '<h6>' . esc_html__('Billplz Collection ID', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter the Billplz collection id where payment should be received.', 'gravityformsbillplz')
			),
			array(
				'name' => 'billplzDescription', //billplzColl is for Collection ID
				'label' => esc_html__('Billplz Bill Description ', 'gravityformsbillplz'),
				'type' => 'text',
				'class' => 'medium',
				'tooltip' => '<h6>' . esc_html__('Billplz Bill Description', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter description here to be display in Billplz bills or you can set it below using variable. It will be here (this value) + variable', 'gravityformsbillplz')
			),
			array(
				'name' => 'smsnotification',
				'label' => esc_html__('SMS Notification ', 'gravityformsbillplz'),
				'type' => 'checkbox',
				'choices' => array(
					array(
						'label' => esc_html__('Notify Customer', 'gravityformsbillplz'),
						'value' => 'smsnotify',
						'name' => 'smsnotification'
					)
				),
				'tooltip' => '<h6>' . esc_html__('SMS Notification', 'gravityformsbillplz') . '</h6>' . esc_html__('Send SMS Notification to Customer. Please note that this SMS Notification is sent by Billplz and will cost you RM0.15 per SMS sent. *SMS & Email Notification must be same to ensure both value are sent to Bills', 'gravityformsbillplz')
			),
			array(
				'name' => 'emailnotification',
				'label' => esc_html__('Email Notification ', 'gravityformsbillplz'),
				'type' => 'checkbox',
				'choices' => array(
					array(
						'label' => esc_html__('Notify Customer', 'gravityformsbillplz'),
						'value' => 'emailnotify',
						'name' => 'emailnotification'
					)
				),
				'tooltip' => '<h6>' . esc_html__('Email Notification', 'gravityformsbillplz') . '</h6>' . esc_html__('Send Email Notification to Customer. Please note that this Email Notificatoin is sent by Billplz. *SMS & Email Notification must be same to ensure both value are sent to Bills', 'gravityformsbillplz')
			),
			array(
				'name' => 'mode',
				'label' => esc_html__('Mode', 'gravityformsbillplz'),
				'type' => 'radio',
				'choices' => array(
					array(
						'id' => 'gf_billplz_mode_production',
						'label' => esc_html__('Production', 'gravityformsbillplz'),
						'value' => 'production'
					),
					array(
						'id' => 'gf_billplz_mode_staging',
						'label' => esc_html__('Staging', 'gravityformsbillplz'),
						'value' => 'staging'
					)
				),
				'horizontal' => true,
				'default_value' => 'production',
				'tooltip' => '<h6>' . esc_html__('Mode', 'gravityformsbillplz') . '</h6>' . esc_html__('Untuk test, sila masukkan API Key "73eb57f0-7d4e-42b9-a544-aeac6e4b0f81" dan collection ID "inbmmepb".', 'gravityformsbillplz')
			)
		);
		$default_settings = parent::add_field_after('feedName', $fields, $default_settings);
		//-----------------------------------------------------------------------------------------
		$fields           = array(
			array(
				'name' => 'ref1label',
				'label' => esc_html__('Reference 1 Label', 'gravityformsbillplz'),
				'type' => 'text',
				'class' => 'medium',
				'required' => false,
				'tooltip' => '<h6>' . esc_html__('Reference 1 Label', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter your label name for Reference 1 label.', 'gravityformsbillplz')
			),
			array(
				'name' => 'cancelUrl',
				'label' => esc_html__('Cancel URL', 'gravityformsbillplz'),
				'type' => 'text',
				'class' => 'medium',
				'required' => false,
				'tooltip' => '<h6>' . esc_html__('Cancel URL', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter the URL the user should be sent to should they cancel before completing their Billplz payment.', 'gravityformsbillplz')
			),
			array(
				'name' => 'notifications',
				'label' => esc_html__('Notifications', 'gravityformsbillplz'),
				'type' => 'notifications',
				'tooltip' => '<h6>' . esc_html__('Notifications', 'gravityformsbillplz') . '</h6>' . esc_html__("Enable this option if you would like to only send out this form's notifications for the 'Form is submitted' event after payment has been received. Leaving this option disabled will send these notifications immediately after the form is submitted. Notifications which are configured for other events will not be affected by this option.", 'gravityformsbillplz')
			)
		);
		$default_settings = $this->add_field_after('billingInformation', $fields, $default_settings);
		//--get billing info section and add customer first/last name
		$billing_info     = parent::get_field('billingInformation', $default_settings);
		$billing_fields   = $billing_info['field_map'];
		$add_first_name   = true;
		$add_last_name    = true;
		$add_phone        = true;
		$add_billdesc     = true;
		$add_ref1         = true;
		foreach ($billing_fields as $mapping) {
			//add first/last name if it does not already exist in billing fields
			if ($mapping['name'] == 'firstName') {
				$add_first_name = false;
			} else if ($mapping['name'] == 'lastName') {
				$add_last_name = false;
			} else if ($mapping['name'] == 'billdesc') {
				$add_billdesc = false;
			} else if ($mapping['name'] == 'ref1') {
				$add_ref1 = false;
			} else if ($mapping['name'] == 'phone') {
				$add_phone = false;
			}
		}
		if ($add_phone) {
			array_unshift($billing_info['field_map'], array(
				'name' => 'phone',
				'label' => esc_html__('Phone', 'gravityformsbillplz'),
				'required' => false
			));
		}
		if ($add_billdesc) {
			array_unshift($billing_info['field_map'], array(
				'name' => 'billdesc',
				'label' => esc_html__('Bill Description', 'gravityformsbillplz'),
				'required' => false
			));
		}
		if ($add_ref1) {
			array_unshift($billing_info['field_map'], array(
				'name' => 'ref1',
				'label' => esc_html__('Reference 1', 'gravityformsbillplz'),
				'required' => false
			));
		}
		if ($add_last_name) {
			//add last name
			array_unshift($billing_info['field_map'], array(
				'name' => 'lastName',
				'label' => esc_html__('Last Name', 'gravityformsbillplz'),
				'required' => false
			));
		}
		if ($add_first_name) {
			array_unshift($billing_info['field_map'], array(
				'name' => 'firstName',
				'label' => esc_html__('First Name', 'gravityformsbillplz'),
				'required' => false
			));
		}
		$default_settings = parent::replace_field('billingInformation', $billing_info, $default_settings);
		//----------------------------------------------------------------------------------------------------
		//hide default display of setup fee, not used by Billplz Standard
		$default_settings = parent::remove_field('setupFee', $default_settings);
		$default_settings = parent::remove_field('recurringTimes', $default_settings);
		$default_settings = parent::remove_field('billingCycle', $default_settings);
		$default_settings = parent::remove_field('recurringAmount', $default_settings);
		$disabledsub      = array(
			'name' => 'disabledsub',
			'label' => esc_html__('NOTE: SUBSCRIPTION FEATURES IS NOT AVAILABLE. PLEASE CHOOSE PRODUCT AND SERVICES AS YOUR TRANSACTION TYPE', 'gravityformsbillplz')
		);
		$default_settings = parent::replace_field('trial', $disabledsub, $default_settings);
		//-----------------------------------------------------------------------------------------------------
		/**
		 * Filter through the feed settings fields for the Billplz feed
		 *
		 * @param array $default_settings The Default feed settings
		 * @param array $form The Form object to filter through
		 */
		return apply_filters('gform_billplz_feed_settings_fields', $default_settings, $form);
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
					'name' => 'delayNotification'
				)
			)
		);
		$html       = $this->settings_checkbox($checkboxes, false);
		$html .= $this->settings_hidden(array(
			'name' => 'selectedNotifications',
			'id' => 'selectedNotifications'
		), false);
		$form                      = $this->get_current_form();
		$has_delayed_notifications = $this->get_setting('delayNotification');
		ob_start();
?>
		<ul id="gf_billplz_notification_container" style="padding-left:20px; margin-top:10px; <?php
		echo $has_delayed_notifications ? '' : 'display:none;';
?>">
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
						<input type="checkbox" class="notification_checkbox" value="<?php
				echo $notification['id'];
?>" onclick="SaveNotifications();" <?php
				checked(true, in_array($notification['id'], $selected_notifications));
?> />
						<label class="inline" for="gf_billplz_selected_notifications"><?php
				echo $notification['name'];
?></label>
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
	public function field_map_title()
	{
		return esc_html__('Billplz Field', 'gravityformsbillplz');
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
	public function checkbox_input_change_post_status($choice, $attributes, $value, $tooltip)
	{
		$markup         = $this->checkbox_input($choice, $attributes, $value, $tooltip);
		$dropdown_field = array(
			'name' => 'update_post_action',
			'choices' => array(
				array(
					'label' => ''
				),
				array(
					'label' => esc_html__('Mark Post as Draft', 'gravityformsbillplz'),
					'value' => 'draft'
				),
				array(
					'label' => esc_html__('Delete Post', 'gravityformsbillplz'),
					'value' => 'delete'
				)
			),
			'onChange' => "var checked = jQuery(this).val() ? 'checked' : false; jQuery('#change_post_status').attr('checked', checked);"
		);
		$markup .= '&nbsp;&nbsp;' . $this->settings_select($dropdown_field, false);
		return $markup;
	}
	public function option_choices()
	{
		return false;
		$option_choices = array(
			array(
				'label' => __('Do not prompt buyer to include a shipping address.', 'gravityformsbillplz'),
				'name' => 'disableShipping',
				'value' => ''
			),
			array(
				'label' => __('Do not prompt buyer to include a note with payment.', 'gravityformsbillplz'),
				'name' => 'disableNote',
				'value' => ''
			)
		);
		return $option_choices;
	}
	public function save_feed_settings($feed_id, $form_id, $settings)
	{
		//--------------------------------------------------------
		//For backwards compatibility
		$feed = $this->get_feed($feed_id);
		//Saving new fields into old field names to maintain backwards compatibility for delayed payments
		//$settings['type'] = $settings['transactionType'];
		if (isset($settings['recurringAmount'])) {
			$settings['recurring_amount_field'] = $settings['recurringAmount'];
		}
		$feed['meta']        = $settings;
		$feed                = apply_filters('gform_billplz_save_config', $feed);
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
	//public function check_ipn_request() {
	//------ SENDING TO BILLPLZ -----------//
	public function redirect_url($feed, $submission_data, $form, $entry)
	{
		//experimen utk api key
		//$grigibes = GFAPI::get_feeds($feed['id'],$form['id']);
		//$grigibes2 = GFAPI::get_entry( $entry['id'] );
		//$grigibes3 = GFAPI::get_form($form['id']);
		//Don't process redirect url if request is a Billplz return
		if (!rgempty('gf_billplz_return', $_GET)) {
			return false;
		}
		$nonamafirst   = $feed['meta']['billingInformation_firstName'];
		$nonamasecond  = $feed['meta']['billingInformation_lastName'];
		$namacu        = $entry[$nonamafirst] . " " . $entry[$nonamasecond];
		$email3        = $feed['meta']['billingInformation_email'];
		$phone2        = $feed['meta']['billingInformation_phone'];
		$billdescFinal = $feed['meta']['billplzDescription'];
		$billdescVar   = $feed['meta']['billingInformation_billdesc'];
		$billdescVar   = $entry[$billdescVar];
		if ($feed['meta']['ref1label'] != '')
			$reference1label = substr($feed['meta']['ref1label'], 0, 120);
		else
			$reference1label = '';
		$reference1 = $feed['meta']['billingInformation_ref1'];
		if ($entry[$reference1] != '')
			$reference1 = substr($entry[$reference1], 0, 20);
		else
			$reference1 = '';
		$billdesc     = substr($billdescFinal . $billdescVar, 0, 200);
		$phone2       = preg_replace("/[^0-9]/", "", $entry[$phone2]);
		$email3       = $entry[$email3];
		$api_key      = $feed['meta']['billplzEmail'];
		$collectionid = $feed['meta']['billplzColl'];
		$smsnotify3   = $feed['meta']['smsnotification'];
		$emailnotify3 = $feed['meta']['emailnotification'];
		//Getting Url (Production or Staging)
		$host         = $feed['meta']['mode'] == 'production' ? $this->production_url : $this->staging_url;
		//number intelligence
		$custTel2     = substr($phone2, 0, 1);
		if ($custTel2 == '+') {
			$custTel3 = substr($phone2, 1, 1);
			if ($custTel3 != '6')
				$phone2 = "+6" . $phone2;
		} else if ($custTel2 == '6') {
		} else {
			if ($phone2 != '')
				$phone2 = "+6" . $phone2;
		}
		//number intelligence
		if ($smsnotify3 == 1 && $emailnotify3 == 1) {
			$deliver = true;
		} else if ($smsnotify3 == 0 && $emailnotify3 == 0) {
			$deliver = false;
		} else if ($smsnotify3 == 1 && $emailnotify3 == 0) {
			$deliver = true;
			$email3  = "";
		} else if ($smsnotify3 == 0 && $emailnotify3 == 1) {
			$deliver = true;
			$phone2  = "";
		}
		$entryIdToDB = $entry['id'];
		$feedid      = $feed['id'];
		$formid      = $form['id'];
		$redirectS   = $this->return_url($formid, $entryIdToDB) . "&rm=2";
		$urlpass      = (get_bloginfo('url') . '/?page=gf_billplz_ipn') . '&entryidtdb=' . $entryIdToDB . '&feedid=' . $feedid . '&formid=' . $formid . '&redirectS=' . $redirectS;
		$billplz_data = array(
			'amount' => rgar($submission_data, 'payment_amount') * 100,
			'name' => $namacu,
			'description' => $billdesc,
			'email' => $email3,
			'collection_id' => $collectionid,
			'reference_1_label' => $reference1label,
			'reference_1' => $reference1,
			'reference_2_label' => "ID",
			'reference_2' => $entryIdToDB,
			'deliver' => $deliver,
			'mobile' => $phone2,
			'redirect_url' => $urlpass,
			'callback_url' => $urlpass
		);
		$arr          = DapatkanLink($api_key, $billplz_data, $host);
		if (isset($arr['error'])) {
			$billplz_data = array(
				'amount' => rgar($submission_data, 'payment_amount') * 100,
				'name' => $namacu,
				'email' => $email3,
				'description' => $billdesc,
				'reference_1_label' => $reference1label,
				'reference_1' => $reference1,
				'reference_2_label' => "ID",
				'reference_2' => $entryIdToDB,
				'collection_id' => $collectionid,
				'deliver' => $deliver,
				'redirect_url' => $urlpass,
				'callback_url' => $urlpass
			);
			$arr          = DapatkanLink($api_key, $billplz_data, $host);
		}
		$url_from_link = $arr['url'];
		$this->log_debug(__METHOD__ . "(): Sending to Billplz: {$url_from_link}");
		GFAPI::update_entry_property($entry['id'], 'payment_status', 'Processing');
		return $url_from_link;
	}
	public function customer_query_string($feed, $entry)
	{
		$fields = '';
		foreach ($this->get_customer_fields() as $field) {
			$field_id = $feed['meta'][$field['meta_name']];
			$value    = rgar($entry, $field_id);
			if ($field['name'] == 'country') {
				$value = class_exists('GF_Field_Address') ? GF_Fields::get('address')->get_country_code($value) : GFCommon::get_country_code($value);
			} elseif ($field['name'] == 'state') {
				$value = class_exists('GF_Field_Address') ? GF_Fields::get('address')->get_us_state_code($value) : GFCommon::get_us_state_code($value);
			}
			if (!empty($value)) {
				$fields .= "&{$field['name']}=" . urlencode($value);
			}
		}
		return $fields;
	}
	public function return_url($form_id, $lead_id)
	{
		$pageURL     = GFCommon::is_ssl() ? 'https://' : 'http://';
		$server_port = apply_filters('gform_billplz_return_url_port', $_SERVER['SERVER_PORT']);
		if ($server_port != '80') {
			$pageURL .= $_SERVER['SERVER_NAME'] . ':' . $server_port . $_SERVER['REQUEST_URI'];
		} else {
			$pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		}
		$ids_query = "ids={$form_id}|{$lead_id}";
		$ids_query .= '&hash=' . wp_hash($ids_query);
		$url   = add_query_arg('gf_billplz_return', base64_encode($ids_query), $pageURL);
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
				list($form_id, $lead_id) = explode('|', $query['ids']);
				$form = GFAPI::get_form($form_id);
				$lead = GFAPI::get_entry($lead_id);
				if (!class_exists('GFFormDisplay')) {
					require_once(GFCommon::get_base_path() . '/form_display.php');
				}
				$confirmation = GFFormDisplay::handle_confirmation($form, $lead, false);
				if (is_array($confirmation) && isset($confirmation['redirect'])) {
					header("Location: {$confirmation['redirect']}");
					exit;
				}
				GFFormDisplay::$submission[$form_id] = array(
					'is_confirmation' => true,
					'confirmation_message' => $confirmation,
					'form' => $form,
					'lead' => $lead
				);
			}
		}
	}
	public function get_customer_fields()
	{
		return array(
			array(
				'name' => 'first_name',
				'label' => 'First Name',
				'meta_name' => 'billingInformation_firstName'
			),
			array(
				'name' => 'last_name',
				'label' => 'Last Name',
				'meta_name' => 'billingInformation_lastName'
			),
			array(
				'name' => 'email',
				'label' => 'Email',
				'meta_name' => 'billingInformation_email'
			),
			array(
				'name' => 'phone',
				'label' => 'Phone',
				'meta_name' => 'billingInformation_phone'
			),
			array(
				'name' => 'ref1',
				'label' => 'Reference 1',
				'meta_name' => 'billingInformation_ref1'
			),
			array(
				'name' => 'billdesc',
				'label' => 'Bill Description',
				'meta_name' => 'billingInformation_billdesc'
			),
			array(
				'name' => 'address1',
				'label' => 'Address',
				'meta_name' => 'billingInformation_address'
			),
			array(
				'name' => 'address2',
				'label' => 'Address 2',
				'meta_name' => 'billingInformation_address2'
			),
			array(
				'name' => 'city',
				'label' => 'City',
				'meta_name' => 'billingInformation_city'
			),
			array(
				'name' => 'state',
				'label' => 'State',
				'meta_name' => 'billingInformation_state'
			),
			array(
				'name' => 'zip',
				'label' => 'Zip',
				'meta_name' => 'billingInformation_zip'
			),
			array(
				'name' => 'country',
				'label' => 'Country',
				'meta_name' => 'billingInformation_country'
			)
		);
	}
	public function delay_post($is_disabled, $form, $entry)
	{
		$feed            = $this->get_payment_feed($entry);
		$submission_data = $this->get_submission_data($feed, $form, $entry);
		if (!$feed || empty($submission_data['payment_amount'])) {
			return $is_disabled;
		}
		return !rgempty('delayPost', $feed['meta']);
	}
	public function delay_notification($is_disabled, $notification, $form, $entry)
	{
		$feed            = $this->get_payment_feed($entry);
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
		$grigibes = GFAPI::get_feeds($_GET['feedid'], $_GET['formid']);
		$api_key  = $grigibes['0']['meta']['billplzEmail'];
		$host     = $grigibes['0']['meta']['mode'] == 'production' ? $this->production_url : $this->staging_url;
		if (isset($_GET['billplz'])) {
			$verification2 = implode($_GET["billplz"]);
			$redURL        = $_GET['redirectS'];
			$arra          = DapatkanInfo($api_key, $verification2, $host); //security
			if ($arra['reference_2'] != $_GET['entryidtdb'])
				exit("Hacking Attempt!");
			$payment_status = $arra['paid'];
			if ($payment_status)
				header("Location: " . $redURL);
			else {
				$grigibes2 = GFAPI::get_entry($_GET['entryidtdb']);
				if ($grigibes['0']['meta']['cancelUrl'] == '')
					$cancelURL = $grigibes2['source_url'];
				else
					$cancelURL = $grigibes['0']['meta']['cancelUrl'];
				header("Location: " . $cancelURL);
			}
			return false;
		}
		if (!$this->is_gravityforms_supported()) {
			return false;
		}
		//----- Processing IPN ------------------------------------------------------------//
		$this->log_debug(__METHOD__ . '(): Processing IPN...');
		$transaction_id = $_POST['id'];
		$entryid        = $_GET['entryidtdb'];
		$amount         = $_POST['amount'] / 100;
		$transaction_id = $_POST['id'];
		//security
		$arra           = DapatkanInfo($api_key, $transaction_id, $host);
		if ($arra['reference_2'] != $_GET['entryidtdb'])
				exit("Hacking Attempt!");
		$paidStatus     = $arra['paid'];
		//security
		if ($paidStatus) {
			$this->log_debug(__METHOD__ . '(): IPN processing complete.');
			$action = array(
				'id' => $entryid,
				'type' => 'complete_payment',
				'transaction_id' => $transaction_id,
				'amount' => $amount,
				'entry_id' => $entryid,
				'payment_date' => gmdate('y-m-d H:i:s'),
				'payment_method' => "Billplz",
				'ready_to_fulfill' => "true"
			);
			return $action;
		} else if (!$paidStatus) {
			$action = array(
				'id' => $entryid,
				'type' => 'fail_payment',
				'transaction_id' => $transaction_id,
				'entry_id' => $entryid,
				'note' => "This payment has failed because the user has cancelled the payment at the payment page.",
				'amount' => $amount
			);
			return $action;
		}
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
		$feed    = $this->get_feed($feed_id);
		return !empty($feed) ? $feed : false;
	}
	public function post_callback($callback_action, $callback_result)
	{
		if (is_wp_error($callback_action) || !$callback_action) {
			return false;
		}
		//run the necessary hooks
		$entry          = GFAPI::get_entry($callback_action['entry_id']);
		$feed           = $this->get_payment_feed($entry);
		$transaction_id = rgar($callback_action, 'transaction_id');
		$amount         = rgar($callback_action, 'amount');
		$subscriber_id  = rgar($callback_action, 'subscriber_id');
		$pending_reason = rgpost('pending_reason');
		$reason         = rgpost('reason_code');
		$status         = rgpost('payment_status');
		$txn_type       = rgpost('txn_type');
		$parent_txn_id  = rgpost('parent_txn_id');
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
	public function get_entry($custom_field)
	{
		//Valid IPN requests must have a custom field
		if (empty($custom_field)) {
			$this->log_error(__METHOD__ . '(): IPN request does not have a custom field, so it was not created by Gravity Forms. Aborting.');
			return false;
		}
		//Getting entry associated with this IPN message (entry id is sent in the 'custom' field)
		list($entry_id, $hash) = explode('|', $custom_field);
		$hash_matches = wp_hash($entry_id) == $hash;
		//allow the user to do some other kind of validation of the hash
		$hash_matches = apply_filters('gform_billplz_hash_matches', $hash_matches, $entry_id, $hash, $custom_field);
		//Validates that Entry Id wasn't tampered with
		if (!rgpost('test_ipn') && !$hash_matches) {
			$this->log_error(__METHOD__ . "(): Entry Id verification failed. Hash does not match. Custom field: {$custom_field}. Aborting.");
			return false;
		}
		$this->log_debug(__METHOD__ . "(): IPN message has a valid custom field: {$custom_field}");
		$entry = GFAPI::get_entry($entry_id);
		if (is_wp_error($entry)) {
			$this->log_error(__METHOD__ . '(): ' . $entry->get_error_message());
			return false;
		}
		return $entry;
	}
	public function modify_post($post_id, $action)
	{
		$result = false;
		if (!$post_id) {
			return $result;
		}
		switch ($action) {
			case 'draft':
				$post              = get_post($post_id);
				$post->post_status = 'draft';
				$result            = wp_update_post($post);
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
		add_action('wp_ajax_gf_dismiss_billplz_menu', array(
			$this,
			'ajax_dismiss_menu'
		));
	}
	//------- ADMIN FUNCTIONS/HOOKS -----------//
	public function init_admin()
	{
		parent::init_admin();
		//add actions to allow the payment status to be modified
		add_action('gform_payment_status', array(
			$this,
			'admin_edit_payment_status'
		), 3, 3);
		add_action('gform_payment_date', array(
			$this,
			'admin_edit_payment_date'
		), 3, 3);
		add_action('gform_payment_transaction_id', array(
			$this,
			'admin_edit_payment_transaction_id'
		), 3, 3);
		add_action('gform_payment_amount', array(
			$this,
			'admin_edit_payment_amount'
		), 3, 3);
		add_action('gform_after_update_entry', array(
			$this,
			'admin_update_payment'
		), 4, 2);
		add_filter('gform_addon_navigation', array(
			$this,
			'maybe_create_menu'
		));
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
			'add_pending_payment' => esc_html__('Payment Pending', 'gravityformsbillplz')
		);
	}
	public function maybe_create_menu($menus)
	{
		$current_user         = wp_get_current_user();
		$dismiss_billplz_menu = get_metadata('user', $current_user->ID, 'dismiss_billplz_menu', true);
		if ($dismiss_billplz_menu != '1') {
			$menus[] = array(
				'name' => $this->_slug,
				'label' => $this->get_short_title(),
				'callback' => array(
					$this,
					'temporary_plugin_page'
				),
				'permission' => $this->_capabilities_form_settings
			);
		}
		return $menus;
	}
	public function ajax_dismiss_menu()
	{
		$current_user = wp_get_current_user();
		update_metadata('user', $current_user->ID, 'dismiss_billplz_menu', '1');
	}
	public function temporary_plugin_page()
	{
		$current_user = wp_get_current_user();
?>
		<script type="text/javascript">
			function dismissMenu(){
				jQuery('#gf_spinner').show();
				jQuery.post(ajaxurl, {
						action : "gf_dismiss_billplz_menu"
					},
					function (response) {
						document.location.href='?page=gf_edit_forms';
						jQuery('#gf_spinner').hide();
					}
				);

			}
		</script>

		<div class="wrap about-wrap">
			<h1><?php
		_e('Billplz Add-On v1.7', 'gravityformsbillplz');
?></h1>
			<div class="about-text"><?php
		esc_html_e('Thank you for updating! The new version of the Gravity Forms Billplz Billplz Add-On makes changes to how you manage your Billplz integration.', 'gravityformsbillplz');
?></div>
			<div class="changelog">
				<hr/>
				<div class="feature-section col two-col">
					<div class="col-1">
						<h3><?php
		esc_html_e('Manage Billplz Contextually', 'gravityformsbillplz');
?></h3>
						<p><?php
		esc_html_e('Billplz Feeds are now accessed via the Billplz sub-menu within the Form Settings for the Form you would like to integrate Billplz with.', 'gravityformsbillplz');
?></p>
					</div>
					<div class="col-2 last-feature">
						<img src="<?php
		echo (get_bloginfo('url') . '/wp-content/plugins/billplz-for-gravityforms/images/billplzdonate.png');
?>">
					</div>
				</div>

				<hr/>

				<form method="post" id="dismiss_menu_form" style="margin-top: 20px;">
					<input type="checkbox" name="dismiss_billplz_menu" value="1" onclick="dismissMenu();"> <label><?php
		_e('I confirmed I have make donation to developer.' . '<br>Dismiss this message!', 'gravityformsbillplz');
?></label>
					<img id="gf_spinner" src="<?php
		echo GFCommon::get_base_url() . '/images/spinner.gif';
?>" alt="<?php
		_e('Please wait...', 'gravityformsbillplz');
?>" style="display:none;"/>
				</form>

			</div>
		</div>
		<?php
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
			$payment_date = gmdate('y-m-d H:i:s');
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
		if (empty($payment_date)) {
			$payment_date = gmdate('y-m-d H:i:s');
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
		if (($payment_status == 'Approved' || $payment_status == 'Paid') && !$entry['is_fulfilled']) {
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
		GFFormsModel::add_note($entry['id'], $user_id, $user_name, sprintf(esc_html__('Payment information was manually updated. Status: %s. Amount: %s. Transaction Id: %s. Date: %s', 'gravityformsbillplz'), $entry['payment_status'], GFCommon::to_money($entry['payment_amount'], $entry['currency']), $payment_transaction, $entry['payment_date']));
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
			$notifications = rgars($feed, 'meta/selectedNotifications');
			GFCommon::send_notifications($notifications, $form, $entry, true, 'form_submission');
		}
		do_action('gform_billplz_fulfillment', $entry, $feed, $transaction_id, $amount);
		if (has_filter('gform_billplz_fulfillment')) {
			$this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_billplz_fulfillment.');
		}
	}
	private function is_valid_initial_payment_amount($entry_id, $amount_paid)
	{
		//get amount initially sent to billplz
		$amount_sent = gform_get_meta($entry_id, 'payment_amount');
		if (empty($amount_sent)) {
			return true;
		}
		$epsilon    = 0.00001;
		$is_equal   = abs(floatval($amount_paid) - floatval($amount_sent)) < $epsilon;
		$is_greater = floatval($amount_paid) > floatval($amount_sent);
		//initial payment is valid if it is equal to or greater than product/subscription amount
		if ($is_equal || $is_greater) {
			return true;
		}
		return false;
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
		$payment_status = rgar($entry, 'payment_status');
		$form_action    = strtolower(rgpost('save'));
		return !$this->is_payment_gateway($entry['id']) || $form_action <> $action || $payment_status == 'Approved' || $payment_status == 'Paid' || rgar($entry, 'transaction_type') == 2;
	}
	public function upgrade($previous_version)
	{
		//dikosongkan
	}
	public function uninstall()
	{
		parent::uninstall();
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
			'email',
			'mode',
			'type',
			'style',
			'continue_text',
			'cancel_url',
			'disable_note',
			'disable_shipping',
			'recurring_amount_field',
			'recurring_times',
			'recurring_retry',
			'billing_cycle_number',
			'billing_cycle_type',
			'trial_period_enabled',
			'trial_amount',
			'trial_period_number',
			'trial_period_type',
			'delay_post',
			'update_post_action',
			'delay_notifications',
			'selected_notifications',
			'billplz_conditional_enabled',
			'billplz_conditional_field_id',
			'billplz_conditional_operator',
			'billplz_conditional_value',
			'customer_fields'
		);
		foreach ($old_feed['meta'] as $key => $value) {
			if (!in_array($key, $known_meta_keys)) {
				$new_meta[$key] = $value;
			}
		}
		return $new_meta;
	}
	public function update_payment_gateway()
	{
		global $wpdb;
		$sql = $wpdb->prepare("UPDATE {$wpdb->prefix}rg_lead_meta SET meta_value=%s WHERE meta_key='payment_gateway' AND meta_value='billplz'", $this->_slug);
		$wpdb->query($sql);
	}
	public function update_lead()
	{
		global $wpdb;
		$sql = $wpdb->prepare("UPDATE {$wpdb->prefix}rg_lead
			 SET payment_status='Paid', payment_method='Billplz'
		     WHERE payment_status='Approved'
		     		AND ID IN (
					  	SELECT lead_id FROM {$wpdb->prefix}rg_lead_meta WHERE meta_key='payment_gateway' AND meta_value=%s
				   	)", $this->_slug);
		$wpdb->query($sql);
	}
	public function copy_settings()
	{
		//copy plugin settings
		$old_settings = get_option('gf_billplz_configured');
		$new_settings = array(
			'gf_billplz_configured' => $old_settings
		);
		$this->update_plugin_settings($new_settings);
	}
	public function copy_feeds()
	{
		//get feeds
		$old_feeds = $this->get_old_feeds();
		if ($old_feeds) {
			$counter = 1;
			foreach ($old_feeds as $old_feed) {
				$feed_name           = 'Feed ' . $counter;
				$form_id             = $old_feed['form_id'];
				$is_active           = $old_feed['is_active'];
				$customer_fields     = $old_feed['meta']['customer_fields'];
				$new_meta            = array(
					'feedName' => $feed_name,
					'billplzEmail' => rgar($old_feed['meta'], 'email'),
					'mode' => rgar($old_feed['meta'], 'mode'),
					'transactionType' => rgar($old_feed['meta'], 'type'),
					'type' => rgar($old_feed['meta'], 'type'), //For backwards compatibility of the delayed payment feature
					'pageStyle' => rgar($old_feed['meta'], 'style'),
					'ref1label' => rgar($old_feed['meta'], 'ref1label'),
					'cancelUrl' => rgar($old_feed['meta'], 'cancel_url'),
					'disableNote' => rgar($old_feed['meta'], 'disable_note'),
					'disableShipping' => rgar($old_feed['meta'], 'disable_shipping'),
					'recurringAmount' => rgar($old_feed['meta'], 'recurring_amount_field') == 'all' ? 'form_total' : rgar($old_feed['meta'], 'recurring_amount_field'),
					'recurring_amount_field' => rgar($old_feed['meta'], 'recurring_amount_field'), //For backwards compatibility of the delayed payment feature
					'recurringTimes' => rgar($old_feed['meta'], 'recurring_times'),
					'recurringRetry' => rgar($old_feed['meta'], 'recurring_retry'),
					'paymentAmount' => 'form_total',
					'delayPost' => rgar($old_feed['meta'], 'delay_post'),
					'change_post_status' => rgar($old_feed['meta'], 'update_post_action') ? '1' : '0',
					'update_post_action' => rgar($old_feed['meta'], 'update_post_action'),
					'delayNotification' => rgar($old_feed['meta'], 'delay_notifications'),
					'selectedNotifications' => rgar($old_feed['meta'], 'selected_notifications'),
					'billingInformation_firstName' => rgar($customer_fields, 'first_name'),
					'billingInformation_lastName' => rgar($customer_fields, 'last_name'),
					'billingInformation_email' => rgar($customer_fields, 'email'),
					'billingInformation_phone' => rgar($customer_fields, 'phone'),
					'billingInformation_billdesc' => rgar($customer_fields, 'billdesc'),
					'billingInformation_ref1' => rgar($customer_fields, 'ref1'),
					'billingInformation_address' => rgar($customer_fields, 'address1'),
					'billingInformation_address2' => rgar($customer_fields, 'address2'),
					'billingInformation_city' => rgar($customer_fields, 'city'),
					'billingInformation_state' => rgar($customer_fields, 'state'),
					'billingInformation_zip' => rgar($customer_fields, 'zip'),
					'billingInformation_country' => rgar($customer_fields, 'country')
				);
				$new_meta            = $this->add_legacy_meta($new_meta, $old_feed);
				//add conditional logic
				$conditional_enabled = rgar($old_feed['meta'], 'billplz_conditional_enabled');
				if ($conditional_enabled) {
					$new_meta['feed_condition_conditional_logic']        = 1;
					$new_meta['feed_condition_conditional_logic_object'] = array(
						'conditionalLogic' => array(
							'actionType' => 'show',
							'logicType' => 'all',
							'rules' => array(
								array(
									'fieldId' => rgar($old_feed['meta'], 'billplz_conditional_field_id'),
									'operator' => rgar($old_feed['meta'], 'billplz_conditional_operator'),
									'value' => rgar($old_feed['meta'], 'billplz_conditional_value')
								)
							)
						)
					);
				} else {
					$new_meta['feed_condition_conditional_logic'] = 0;
				}
				$new_feed_id = $this->insert_feed($form_id, $is_active, $new_meta);
				$this->update_feed_id($old_feed['id'], $new_feed_id);
				$counter++;
			}
		}
	}
	public function copy_transactions()
	{
		//copy transactions from the billplz transaction table to the add payment transaction table
		global $wpdb;
		$old_table_name = $this->get_old_transaction_table_name();
		if (!$this->table_exists($old_table_name)) {
			return false;
		}
		$this->log_debug(__METHOD__ . '(): Copying old Billplz transactions into new table structure.');
		$new_table_name = $this->get_new_transaction_table_name();
		$sql            = "INSERT INTO {$new_table_name} (lead_id, transaction_type, transaction_id, is_recurring, amount, date_created)
					SELECT entry_id, transaction_type, transaction_id, is_renewal, amount, date_created FROM {$old_table_name}";
		$wpdb->query($sql);
		$this->log_debug(__METHOD__ . "(): transactions: {$wpdb->rows_affected} rows were added.");
	}
	public function get_old_transaction_table_name()
	{
		global $wpdb;
		return $wpdb->prefix . 'rg_billplz_transaction';
	}
	public function get_new_transaction_table_name()
	{
		global $wpdb;
		return $wpdb->prefix . 'gf_addon_payment_transaction';
	}
	public function get_old_feeds()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'rg_billplz';
		if (!$this->table_exists($table_name)) {
			return false;
		}
		$form_table_name = GFFormsModel::get_form_table_name();
		$sql             = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
					FROM {$table_name} s
					INNER JOIN {$form_table_name} f ON s.form_id = f.id";
		$this->log_debug(__METHOD__ . "(): getting old feeds: {$sql}");
		$results = $wpdb->get_results($sql, ARRAY_A);
		$this->log_debug(__METHOD__ . "(): error?: {$wpdb->last_error}");
		$count = sizeof($results);
		$this->log_debug(__METHOD__ . "(): count: {$count}");
		for ($i = 0; $i < $count; $i++) {
			$results[$i]['meta'] = maybe_unserialize($results[$i]['meta']);
		}
		return $results;
	}
	//This function kept static for backwards compatibility
	public static function get_config_by_entry($entry)
	{
		$billplz = GFBillplz::get_instance();
		$feed    = $billplz->get_payment_feed($entry);
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
		$feed    = $billplz->get_feeds($form_id);
		//Ignore IPN messages from forms that are no longer configured with the Billplz add-on
		if (!$feed) {
			return false;
		}
		return $feed[0]; //only one feed per form is supported (left for backwards compatibility)
	}
}