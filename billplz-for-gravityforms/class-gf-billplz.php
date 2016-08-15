<?php

add_action( 'wp', array( 'GFBillplz', 'maybe_thankyou_page' ), 5 );

GFForms::include_payment_addon_framework();

class GFBillplz extends GFPaymentAddOn {

	protected $_version = GF_BILLPLZ_VERSION;
	protected $_min_gravityforms_version = '2.0.3';
	protected $_slug = 'billplzforgravityforms';
	protected $_path = 'billplz-for-gravityforms/billplz.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.facebook.com/billplzplugin';
	protected $_title = 'Billplz for GravityForms';
	protected $_short_title = 'Billplz';
	protected $_supports_callbacks = true;
	private $production_url = 'https://www.billplz.com/api/v3/';
	private $sandbox_url = 'https://billplz-staging.herokuapp.com/api/v3/';
	
	var $billplzSignal;

	// Members plugin integration
	protected $_capabilities = array( 'gravityforms_billplz', 'gravityforms_billplz_uninstall' );

	// Permissions
	protected $_capabilities_settings_page = 'gravityforms_billplz';
	protected $_capabilities_form_settings = 'gravityforms_billplz';
	protected $_capabilities_uninstall = 'gravityforms_billplz_uninstall';

	// Automatic upgrade enabled
	protected $_enable_rg_autoupgrade = false;

	private static $_instance = null;

	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFBillplz();
		}

		return self::$_instance;
	}

	private function __clone() {
	} /* do nothing */

	public function init_frontend() {
		parent::init_frontend();

		add_filter( 'gform_disable_post_creation', array( $this, 'delay_post' ), 10, 3 );
		add_filter( 'gform_disable_notification', array( $this, 'delay_notification' ), 10, 4 );
	}

	//----- SETTINGS PAGES ----------//

	public function plugin_settings_fields() {
		$description = '
			<p style="text-align: left;">' .
			esc_html__( 'Billplz for GravityForms are automatically configured to use IPN. Just confirm by clicking the box below.', 'billplzforgravityforms' ) .
			'</p>
			<br/>';

		return array(
			array(
				'title'       => '',
				'description' => $description,
				'fields'      => array(
					array(
						'name'    => 'gf_billplz_configured',
						'label'   => esc_html__( 'Billplz IPN Setting', 'billplzforgravityforms' ),
						'type'    => 'checkbox',
						'choices' => array( array( 'label' => esc_html__( 'Confirm Now', 'billplzforgravityforms' ), 'name' => 'gf_billplz_configured' ) )
					),
					array(
						'type' => 'save',
						'messages' => array(
											'success' => esc_html__( 'Settings have been updated.', 'billplzforgravityforms' )
											),
					),
				),
			),
		);
	}

	public function feed_list_no_item_message() {
		$settings = $this->get_plugin_settings();
		if ( ! rgar( $settings, 'gf_billplz_configured' ) ) {
			return sprintf( esc_html__( 'To get started, let\'s go configure your %sBillplz Settings%s!', 'billplzforgravityforms' ), '<a href="' . admin_url( 'admin.php?page=gf_settings&subview=' . $this->_slug ) . '">', '</a>' );
		} else {
			return parent::feed_list_no_item_message();
		}
	}

	public function feed_settings_fields() {
		$default_settings = parent::feed_settings_fields();

		$fields = array(
			array(
				'name'     => 'billplzAPIKey',
				'label'    => esc_html__( 'API Key ', 'billplzforgravityforms' ),
				'type'     => 'text',
				'class'    => 'medium',
				'required' => true,
				'tooltip'  => '<h6>' . esc_html__( 'Billplz API Key', 'billplzforgravityforms' ) . '</h6>' . esc_html__( 'Enter the API Key that provided on Billplz >> Settings.', 'billplzforgravityforms' )
			),
			array(
				'name'     => 'billplzCollectionID',
				'label'    => esc_html__( 'Collection ID ', 'billplzforgravityforms' ),
				'type'     => 'text',
				'class'    => 'medium',
				'required' => true,
				'tooltip'  => '<h6>' . esc_html__( 'Billplz Collection ID', 'billplzforgravityforms' ) . '</h6>' . esc_html__( 'Enter the Collection ID that provided on Billplz >> Billing.', 'billplzforgravityforms' )
			),
			array(
				'name'     => 'billplzDescription',
				'label'    => esc_html__( 'Bills Description ', 'billplzforgravityforms' ),
				'type'     => 'text',
				'class'    => 'medium',
				'required' => false,
				'tooltip'  => '<h6>' . esc_html__( 'Billplz Bills Description', 'billplzforgravityforms' ) . '</h6>' . esc_html__( 'Enter the Description that will appear on Bills.', 'billplzforgravityforms' )
			),
			array(
				'name'     => 'billplzSMS',
				'label'    => esc_html__( 'SMS Notification ', 'billplzforgravityforms' ),
				'type'     => 'checkbox',
				'choices' => array(
					array(
						'label' => esc_html__('Notify Customer', 'billplzforgravityforms'),
						'value' => 'smsnotify',
						'name' => 'billplzSMS'
					)
				),
				'tooltip'  => '<h6>' . esc_html__( 'Billplz SMS Notification', 'billplzforgravityforms' ) . '</h6>' . esc_html__( 'Your customer will get SMS Notification immediately after filling the form and you will be charged RM0.15. You are not recommended to use this feature.', 'billplzforgravityforms' )
			),
			array(
				'name'     => 'billplzEmail',
				'label'    => esc_html__( 'Email Notification ', 'billplzforgravityforms' ),
				'type'     => 'checkbox',
				'choices' => array(
					array(
						'label' => esc_html__('Notify Customer', 'billplzforgravityforms'),
						'value' => 'emailnotify',
						'name' => 'billplzEmail'
					)
				),
				'tooltip'  => '<h6>' . esc_html__( 'Billplz Email Notification', 'billplzforgravityforms' ) . '</h6>' . esc_html__( 'Your customer will get Email Notification immediately after filling the form. You are not recommended to use this feature.', 'billplzforgravityforms' )
			),
			array(
				'name'          => 'mode',
				'label'         => esc_html__( 'Mode', 'billplzforgravityforms' ),
				'type'          => 'radio',
				'choices'       => array(
					array( 'id' => 'gf_billplz_mode_production', 'label' => esc_html__( 'Production', 'billplzforgravityforms' ), 'value' => 'production' ),
					array( 'id' => 'gf_billplz_mode_test', 'label' => esc_html__( 'Test', 'billplzforgravityforms' ), 'value' => 'test' ),

				),

				'horizontal'    => true,
				'default_value' => 'production',
				'tooltip'       => '<h6>' . esc_html__( 'Mode', 'billplzforgravityforms' ) . '</h6>' . esc_html__( 'Select Production for www.billplz.com API Key. Select Test for billplz-staging.herokuapp.com.', 'billplzforgravityforms' )
			),
			array(
				'name'          => 'autoSubmit',
				'label'         => esc_html__( 'Skip Billplz', 'billplzforgravityforms' ),
				'type'          => 'radio',
				'choices'       => array(
					array( 'id' => 'auto_submit_none', 'label' => esc_html__( 'None', 'billplzforgravityforms' ), 'value' => 'billplz' ),
					array( 'id' => 'auto_submit_fpx', 'label' => esc_html__( 'FPX', 'billplzforgravityforms' ), 'value' => 'fpx' ),
					array( 'id' => 'auto_submit_paypal', 'label' => esc_html__( 'PayPal', 'billplzforgravityforms' ), 'value' => 'paypal' ),
				),

				'horizontal'    => true,
				'default_value' => 'billplz',
				'tooltip'       => '<h6>' . esc_html__( 'Skip Billplz', 'billplzforgravityforms' ) . '</h6>' . esc_html__( 'Choose where to auto submit to payment and skipping the Billplz Page.', 'billplzforgravityforms' )
			),
		);

		$default_settings = parent::add_field_after( 'feedName', $fields, $default_settings );
		//--------------------------------------------------------------------------------------

		//--changing transaction type drop down
		$transaction_type = parent::get_field( 'transactionType', $default_settings );
		unset($transaction_type['choices'][1]);
		unset($transaction_type['choices'][2]);
		$choices          = $transaction_type['choices'];
		$add_product     = true;
		foreach ( $choices as $choice ) {
			if ( $choice['value'] == 'product' ) {
				$add_product = false;
			}
		}
		if ( $add_product ) {
			$choices[] = array( 'label' => __( 'Billplz Payment Form', 'billplzforgravityforms' ), 'value' => 'product' );
		}
		$transaction_type['choices'] = $choices;
		$default_settings            = $this->replace_field( 'transactionType', $transaction_type, $default_settings );
		//-------------------------------------------------------------------------------------------------

		//--add Page Style, Continue Button Label, Cancel URL
		$fields = array(
			array(
				'name'     => 'reference_1_label',
				'label'    => esc_html__( 'Reference 1 Label', 'billplzforgravityforms' ),
				'type'     => 'text',
				'class'    => 'medium',
				'required' => false,
				'tooltip'  => '<h6>' . esc_html__( 'Reference 1', 'billplzforgravityforms' ) . '</h6>' . esc_html__( 'Enter the text that will replace Reference 1 label at Bills.', 'billplzforgravityforms' )
			),
			array(
				'name'     => 'cancelUrl',
				'label'    => esc_html__( 'Cancel URL', 'billplzforgravityforms' ),
				'type'     => 'text',
				'class'    => 'medium',
				'required' => false,
				'tooltip'  => '<h6>' . esc_html__( 'Cancel URL', 'billplzforgravityforms' ) . '</h6>' . esc_html__( 'Enter the URL the user should be sent to should they cancel before completing their Billplz payment.', 'billplzforgravityforms' )
			),
			array(
				'name'    => 'options',
				'label'   => esc_html__( 'Options', 'billplzforgravityforms' ),
				'type'    => 'options',
				'tooltip' => '<h6>' . esc_html__( 'Options', 'billplzforgravityforms' ) . '</h6>' . esc_html__( 'Turn on or off the available Billplz checkout options.', 'billplzforgravityforms' )
			),
			array(
				'name'    => 'notifications',
				'label'   => esc_html__( 'Notifications', 'billplzforgravityforms' ),
				'type'    => 'notifications',
				'tooltip' => '<h6>' . esc_html__( 'Notifications', 'billplzforgravityforms' ) . '</h6>' . esc_html__( "Enable this option if you would like to only send out this form's notifications for the 'Form is submitted' event after payment has been received. Leaving this option disabled will send these notifications immediately after the form is submitted. Notifications which are configured for other events will not be affected by this option.", 'billplzforgravityforms' )
			),
		);

		//Add post fields if form has a post
		$form = $this->get_current_form();
		if ( GFCommon::has_post_field( $form['fields'] ) ) {
			$post_settings = array(
				'name'    => 'post_checkboxes',
				'label'   => esc_html__( 'Posts', 'billplzforgravityforms' ),
				'type'    => 'checkbox',
				'tooltip' => '<h6>' . esc_html__( 'Posts', 'billplzforgravityforms' ) . '</h6>' . esc_html__( 'Enable this option if you would like to only create the post after payment has been received.', 'billplzforgravityforms' ),
				'choices' => array(
					array( 'label' => esc_html__( 'Create post only when payment is received.', 'billplzforgravityforms' ), 'name' => 'delayPost' ),
				),
			);

			//if ( $this->get_setting( 'transactionType' ) == 'subscription' ) {
			//	$post_settings['choices'][] = array(
			//		'label'    => esc_html__( 'Change post status when subscription is canceled.', 'billplzforgravityforms' ),
			//		'name'     => 'change_post_status',
			//		'onChange' => 'var action = this.checked ? "draft" : ""; jQuery("#update_post_action").val(action);',
			//	);
			//}

			$fields[] = $post_settings;
		}

		//Adding custom settings for backwards compatibility with hook 'gform_billplz_add_option_group'
		$fields[] = array(
			'name'  => 'custom_options',
			'label' => '',
			'type'  => 'custom',
		);

		$default_settings = $this->add_field_after( 'billingInformation', $fields, $default_settings );
		//-----------------------------------------------------------------------------------------

		//--get billing info section and add customer first/last name
		$billing_info   = parent::get_field( 'billingInformation', $default_settings );
		$billing_fields = $billing_info['field_map'];

		$add_first_name = true;
		$add_last_name  = true;
		$add_ref_1		= true;
		$add_bills_desc = true;
		$add_phone		= true;
		$add_email		= true; //for better arrangement
		
		//added for easy to understand option
		$remove_address = false;
		$remove_address2= false;
		$remove_city	= false;
		$remove_state	= false;
		$remove_zip		= false;
		$remove_country = false;
		$remove_email	= false; //for better arrangement
		
		foreach ( $billing_fields as $mapping ) {
			//add first/last name if it does not already exist in billing fields
			if ( $mapping['name'] == 'firstName' ) {
				$add_first_name = false;
			} else if ( $mapping['name'] == 'lastName' ) {
				$add_last_name = false;
			//add additional option for Billplz API V3
			} else if ( $mapping['name'] == 'ref_1_label' ) {
				$add_ref_1	   = false;
			} else if ( $mapping['name'] == 'bill_desc' ) {
				$add_bills_desc = false;
			} else if ( $mapping['name'] == 'bill_mobile' ) {
				$add_phone = false;
			//remove non-related option
			} else if ( $mapping['name'] == 'address' ) {
				$remove_address = true;
			} else if ( $mapping['name'] == 'address2' ) {
				$remove_address2= true;
			} else if ( $mapping['name'] == 'city' ) {
				$remove_city = true;
			} else if ( $mapping['name'] == 'state' ) {
				$remove_state = true;
			} else if ( $mapping['name'] == 'zip' ) {
				$remove_zip = true;
			} else if ( $mapping['name'] == 'country' ) {
				$remove_country = true;
			} else if ( $mapping['name'] == 'email' ) {
				$remove_email = true;
			}
		}
		
		//It must be removed first because the array index will "lari" if do it later
		
		if ( $remove_address ){
			unset($billing_info['field_map'][1]);
		}
		if ( $remove_address2 ){
			unset($billing_info['field_map'][2]);
		}
		if ( $remove_city ){
			unset($billing_info['field_map'][3]);
		}
		if ( $remove_state){
			unset($billing_info['field_map'][4]);
		}
		if ( $remove_zip ){
			unset($billing_info['field_map'][5]);
		}
		if ( $remove_country ){
			unset($billing_info['field_map'][6]);
		}
		if ( $remove_email ){
			unset($billing_info['field_map'][0]);
		}

		if ( $add_ref_1 ) {
			array_unshift( $billing_info['field_map'], array( 'name' => 'reference_1', 'label' => esc_html__( 'Reference 1', 'billplzforgravityforms' ), 'required' => false ) );
		}
		if ( $add_bills_desc ) {
			array_unshift( $billing_info['field_map'], array( 'name' => 'bill_desc', 'label' => esc_html__( 'Bill Description', 'billplzforgravityforms' ), 'required' => false ) );
		}
		if ( $add_phone ) {
			array_unshift( $billing_info['field_map'], array( 'name' => 'bill_mobile', 'label' => esc_html__( 'Mobile Phone Number', 'billplzforgravityforms' ), 'required' => false ) );
		}
		if ( $add_email ) {
			array_unshift( $billing_info['field_map'], array( 'name' => 'email', 'label' => esc_html__( 'Email', 'billplzforgravityforms' ), 'required' => false ) );
		}
		if ( $add_last_name ) {
			array_unshift( $billing_info['field_map'], array( 'name' => 'lastName', 'label' => esc_html__( 'Last Name', 'billplzforgravityforms' ), 'required' => false ) );
		}
		if ( $add_first_name ) {
			array_unshift( $billing_info['field_map'], array( 'name' => 'firstName', 'label' => esc_html__( 'First Name', 'billplzforgravityforms' ), 'required' => false ) );
		}
		
		$default_settings = parent::replace_field( 'billingInformation', $billing_info, $default_settings );
		//----------------------------------------------------------------------------------------------------

		//hide default display of setup fee, not used by Billplz
		$default_settings = parent::remove_field( 'setupFee', $default_settings );

		//--add trial period
		/*
		$trial_period     = array(
			'name'    => 'trialPeriod',
			'label'   => esc_html__( 'Trial Period', 'billplzforgravityforms' ),
			'type'    => 'trial_period',
			'hidden'  => ! $this->get_setting( 'trial_enabled' ),
			'tooltip' => '<h6>' . esc_html__( 'Trial Period', 'billplzforgravityforms' ) . '</h6>' . esc_html__( 'Select the trial period length.', 'billplzforgravityforms' )
		);
		$default_settings = parent::add_field_after( 'trial', $trial_period, $default_settings );
		*/
		//-----------------------------------------------------------------------------------------

		//--Add Try to bill again after failed attempt.
		/*
		$recurring_retry  = array(
			'name'       => 'recurringRetry',
			'label'      => esc_html__( 'Recurring Retry', 'billplzforgravityforms' ),
			'type'       => 'checkbox',
			'horizontal' => true,
			'choices'    => array( array( 'label' => esc_html__( 'Try to bill again after failed attempt.', 'billplzforgravityforms' ), 'name' => 'recurringRetry', 'value' => '1' ) ),
			'tooltip'    => '<h6>' . esc_html__( 'Recurring Retry', 'billplzforgravityforms' ) . '</h6>' . esc_html__( 'Turn on or off whether to try to bill again after failed attempt.', 'billplzforgravityforms' )
		);
		$default_settings = parent::add_field_after( 'recurringTimes', $recurring_retry, $default_settings );
		*/
		
		//-----------------------------------------------------------------------------------------------------

		/**
		 * Filter through the feed settings fields for the Billplz feed
		 *
		 * @param array $default_settings The Default feed settings
		 * @param array $form The Form object to filter through
		 */
		return apply_filters( 'gform_billplz_feed_settings_fields', $default_settings, $form );
	}
	
	/*
	public function supported_billing_intervals() {

		$billing_cycles = array(
			'day'   => array( 'label' => esc_html__( 'day(s)', 'billplzforgravityforms' ), 'min' => 1, 'max' => 90 ),
			'week'  => array( 'label' => esc_html__( 'week(s)', 'billplzforgravityforms' ), 'min' => 1, 'max' => 52 ),
			'month' => array( 'label' => esc_html__( 'month(s)', 'billplzforgravityforms' ), 'min' => 1, 'max' => 24 ),
			'year'  => array( 'label' => esc_html__( 'year(s)', 'billplzforgravityforms' ), 'min' => 1, 'max' => 5 )
		);

		return $billing_cycles;
	}
	*/

	public function field_map_title() {
		return esc_html__( 'Billplz Variable Field', 'billplzforgravityforms' );
	}

	/*
	public function settings_trial_period( $field, $echo = true ) {
		//use the parent billing cycle function to make the drop down for the number and type
		$html = parent::settings_billing_cycle( $field );

		return $html;
	}
	*/

	/*
	public function set_trial_onchange( $field ) {
		//return the javascript for the onchange event
		return "
		if(jQuery(this).prop('checked')){
			jQuery('#{$field['name']}_product').show('slow');
			jQuery('#gaddon-setting-row-trialPeriod').show('slow');
			if (jQuery('#{$field['name']}_product').val() == 'enter_amount'){
				jQuery('#{$field['name']}_amount').show('slow');
			}
			else{
				jQuery('#{$field['name']}_amount').hide();
			}
		}
		else {
			jQuery('#{$field['name']}_product').hide('slow');
			jQuery('#{$field['name']}_amount').hide();
			jQuery('#gaddon-setting-row-trialPeriod').hide('slow');
		}";
	}
	*/

	public function settings_options( $field, $echo = true ) {
		/*
		$checkboxes = array(
			'name'    => 'options_checkboxes',
			'type'    => 'checkboxes',
			'choices' => array(
				array( 'label' => esc_html__( 'Do not prompt buyer to include a shipping address.', 'billplzforgravityforms' ), 'name' => 'disableShipping' ),
				array( 'label' => esc_html__( 'Do not prompt buyer to include a note with payment.', 'billplzforgravityforms' ), 'name' => 'disableNote' ),
			)
		);

		$html = $this->settings_checkbox( $checkboxes, false );

		//--------------------------------------------------------
		//For backwards compatibility.
		ob_start();
		do_action( 'gform_billplz_action_fields', $this->get_current_feed(), $this->get_current_form() );
		$html .= ob_get_clean();
		//--------------------------------------------------------

		if ( $echo ) {
			echo $html;
		}

		return $html;
		*/
	}

	public function settings_custom( $field, $echo = true ) {

		ob_start();
		?>
		<div id='gf_billplz_custom_settings'>
			<?php
			do_action( 'gform_billplz_add_option_group', $this->get_current_feed(), $this->get_current_form() );
			?>
		</div>

		<script type='text/javascript'>
			jQuery(document).ready(function () {
				jQuery('#gf_billplz_custom_settings label.left_header').css('margin-left', '-200px');
			});
		</script>

		<?php

		$html = ob_get_clean();

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function settings_notifications( $field, $echo = true ) {
		$checkboxes = array(
			'name'    => 'delay_notification',
			'type'    => 'checkboxes',
			'onclick' => 'ToggleNotifications();',
			'choices' => array(
				array(
					'label' => esc_html__( "Send notifications for the 'Form is submitted' event only when payment is received.", 'billplzforgravityforms' ),
					'name'  => 'delayNotification',
				),
			)
		);

		$html = $this->settings_checkbox( $checkboxes, false );

		$html .= $this->settings_hidden( array( 'name' => 'selectedNotifications', 'id' => 'selectedNotifications' ), false );

		$form                      = $this->get_current_form();
		$has_delayed_notifications = $this->get_setting( 'delayNotification' );
		ob_start();
		?>
		<ul id="gf_billplz_notification_container" style="padding-left:20px; margin-top:10px; <?php echo $has_delayed_notifications ? '' : 'display:none;' ?>">
			<?php
			if ( ! empty( $form ) && is_array( $form['notifications'] ) ) {
				$selected_notifications = $this->get_setting( 'selectedNotifications' );
				if ( ! is_array( $selected_notifications ) ) {
					$selected_notifications = array();
				}

				//$selected_notifications = empty($selected_notifications) ? array() : json_decode($selected_notifications);

				$notifications = GFCommon::get_notifications( 'form_submission', $form );

				foreach ( $notifications as $notification ) {
					?>
					<li class="gf_billplz_notification">
						<input type="checkbox" class="notification_checkbox" value="<?php echo $notification['id'] ?>" onclick="SaveNotifications();" <?php checked( true, in_array( $notification['id'], $selected_notifications ) ) ?> />
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

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function checkbox_input_change_post_status( $choice, $attributes, $value, $tooltip ) {
		$markup = $this->checkbox_input( $choice, $attributes, $value, $tooltip );

		$dropdown_field = array(
			'name'     => 'update_post_action',
			'choices'  => array(
				array( 'label' => '' ),
				array( 'label' => esc_html__( 'Mark Post as Draft', 'billplzforgravityforms' ), 'value' => 'draft' ),
				array( 'label' => esc_html__( 'Delete Post', 'billplzforgravityforms' ), 'value' => 'delete' ),

			),
			'onChange' => "var checked = jQuery(this).val() ? 'checked' : false; jQuery('#change_post_status').attr('checked', checked);",
		);
		$markup .= '&nbsp;&nbsp;' . $this->settings_select( $dropdown_field, false );

		return $markup;
	}

	public function option_choices() {
		return false;
	}

	public function save_feed_settings( $feed_id, $form_id, $settings ) {

		//--------------------------------------------------------
		//For backwards compatibility
		$feed = $this->get_feed( $feed_id );

		//Saving new fields into old field names to maintain backwards compatibility for delayed payments
		$settings['type'] = $settings['transactionType'];

		if ( isset( $settings['recurringAmount'] ) ) {
			$settings['recurring_amount_field'] = $settings['recurringAmount'];
		}

		$feed['meta'] = $settings;
		$feed         = apply_filters( 'gform_billplz_save_config', $feed );
		
		//call hook to validate custom settings/meta added using gform_billplz_action_fields or gform_billplz_add_option_group action hooks
		$is_validation_error = apply_filters( 'gform_billplz_config_validation', false, $feed );
		if ( $is_validation_error ) {
			//fail save
			return false;
		}

		$settings = $feed['meta'];
		
		//--------------------------------------------------------

		return parent::save_feed_settings( $feed_id, $form_id, $settings );
	}
	
	/*
	public function check_ipn_request() {

		$dismiss = isset( $_GET['dismiss_ipn_check'] );
		if ( $dismiss ) {
			add_option( 'dismiss_ipn_check', 1 );
		}

		if ( get_option( 'dismiss_ipn_check' ) ) {
			return;
		}

		$can_verify_ipn = get_option( 'can_verify_ipn' );
		if ( $can_verify_ipn == 'yes' ) {
			return;
		}

		if ( empty( $can_verify_ipn ) ) {

			$url = 'https://www.sandbox.billplz.com/cgi-bin/webscr/';
			$request  = new WP_Http();
			$response = $request->post( $url, array( 'httpversion' => '1.1', 'sslverify' => false, 'ssl' => true, 'body' => 'cmd=_notify-validate', 'timeout' => 20 ) );

			if ( ! is_wp_error( $response ) && rgar( $response, 'body' ) == 'INVALID' ) {

				$can_verify_ipn = 'yes';
			} else {

				wp_mail( get_bloginfo( 'admin_email' ), 'Immediate Action Required: SSL certificate is outdated', 'WARNING: Your web server does not currently support the SHA-2 SSL Certificate standard required by Billplz.  <a href="https://devblog.billplz.com/billplz-ssl-certificate-changes/">For details see Billplz\'s changeover announcement</a>. Please contact your web host to resolve this issue as soon as possible.' );
				$can_verify_ipn = 'no';
			}

			update_option( 'can_verify_ipn', $can_verify_ipn );
		}

		if ( $can_verify_ipn == 'no' ) {

			//display message
			echo '<div class="error"> <p><strong>WARNING:</strong> Your web server does not currently support the SHA-2 SSL Certificate standard required by Billplz.  <a href="https://devblog.billplz.com/billplz-ssl-certificate-changes/">For details see Billplz\'s changeover announcement</a>. Please contact your web host to resolve this issue as soon as possible. <a href="' . add_query_arg( array( 'dismiss_ipn_check' => 1 ) ) . '">Dismiss</a></p></div>';

		}
	}
	*/

	//------ SENDING TO BILLPLZ -----------//

	public function redirect_url( $feed, $submission_data, $form, $entry ) {

		//Don't process redirect url if request is a Billplz return
		if ( ! rgempty( 'gf_billplz_return', $_GET ) ) {
			return false;
		}

		//updating lead's payment_status to Processing
		GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Processing' );

		//Getting Url (Production or Sandbox)
		$url = $feed['meta']['mode'] == 'production' ? $this->production_url : $this->sandbox_url;
		$url.= 'bills/';
		$autoSubmit = $feed['meta']['autoSubmit'];
		//$invoice_id = apply_filters( 'gform_billplz_invoice', '', $form, $entry );

		//$invoice = empty( $invoice_id ) ? '' : "&invoice={$invoice_id}";

		//Current Currency
		$currency = rgar( $entry, 'currency' );
		if ( $currency != 'MYR' ) {
			$this->log_debug( __METHOD__ . '(): NOT sending to Billplz: The currency is not supported.' );
			return '';
		}
		//'MYR' is supported

		//Customer fields
		$customer_fields = $this->customer_query_string( $feed, $entry );

		//Page style
		$page_style = ! empty( $feed['meta']['pageStyle'] ) ? '&page_style=' . urlencode( $feed['meta']['pageStyle'] ) : '';

		//Continue link text
		$continue_text = ! empty( $feed['meta']['continueText'] ) ? '&cbt=' . urlencode( $feed['meta']['continueText'] ) : '&cbt=' . __( 'Click here to continue', 'billplzforgravityforms' );

		//Set return mode to 2 (Billplz will post info back to page). rm=1 seems to create lots of problems with the redirect back to the site. Defaulting it to 2.
		//Will use this URL Later
		//$return_mode = '2';
		//return mode compulsory. tapi takleh bawa sebab dia &. so, make sure nombor rm=2 dibawa pada masa akan datang

		$return_url = '&returnSuccess=' . $this->return_url( $form['id'], $entry['id'] ); //. "&rm={$return_mode}";

		//Cancel URL
		//Will use this URL Later.
		//$cancel_url = ! empty( $feed['meta']['cancelUrl'] ) ? '&cancel_return=' . urlencode( $feed['meta']['cancelUrl'] ) : '';

		//Don't display note section
		//$disable_note = ! empty( $feed['meta']['disableNote'] ) ? '&no_note=1' : '';

		//Don't display shipping section
		//$disable_shipping = ! empty( $feed['meta']['disableShipping'] ) ? '&no_shipping=1' : '';

		//URL that will listen to notifications from Billplz
		//$ipn_url = get_bloginfo( 'url' ) . '/?page=gf_billplz_ipn'  . '&feedid='.$feed['id'].'&formid='.$form['id'].$return_url;
		$ipn_url = home_url('/?page=gf_billplz_ipn')  . '&feedid='.$feed['id'].'&formid='.$form['id'].'$entryid='.$entry['id'].$return_url;

		$api_key = urlencode( trim( $feed['meta']['billplzAPIKey'] ) );
		$collection_id = urlencode( trim( $feed['meta']['billplzCollectionID'] ) );
		$custom_field   = $entry['id'] . '|' . wp_hash( $entry['id'] );

		//$url .= "?notify_url={$ipn_url}&charset=UTF-8&currency_code={$currency}&business={$api_key}&custom={$custom_field}{$invoice}{$customer_fields}{$page_style}{$continue_text}{$cancel_url}{$disable_note}{$disable_shipping}{$return_url}";
		$query_string = '';

		switch ( $feed['meta']['transactionType'] ) {
			case 'product' :
				//build query string using $submission_data
				$query_string = $this->get_product_query_string( $submission_data, $entry['id'] );
				break;

			//case 'donation' :
			//	$query_string = $this->get_donation_query_string( $submission_data, $entry['id'] );
			//	break;

			//case 'subscription' :
			//	$query_string = $this->get_subscription_query_string( $feed, $submission_data, $entry['id'] );
			//	break;
		}

		$query_string = gf_apply_filters( 'gform_billplz_query', $form['id'], $query_string, $form, $entry, $feed, $submission_data );

		if ( ! $query_string ) {
			$this->log_debug( __METHOD__ . '(): NOT sending to Billplz: The price is either zero or the gform_billplz_query filter was used to remove the querystring that is sent to Billplz.' );

			return '';
		}

		//$url .= $query_string;

		//$url = gf_apply_filters( 'gform_billplz_request', $form['id'], $url, $form, $entry, $feed, $submission_data );
		
		//add the bn code (build notation code)
		//$url .= '&bn=Wanzul-Hosting.com_SP';
		
		$fName   = $feed['meta']['billingInformation_firstName'];//Integer: Where the Array Index of the $entry
		$sName   = $feed['meta']['billingInformation_lastName'];//Integer: Where the Array Index of the $entry
		$bDesc	 = $feed['meta']['billingInformation_bill_desc']; //Integer: Where the Array Index of the $entry
		$billdesc= $feed['meta']['billplzDescription'] . $entry[$bDesc];
		$billdesc= substr($billdesc, 0, 199); //Limit to 200 characters only
		$billdesc= $billdesc=='' ? 'Billplz Payment' : $billdesc;
		$bEmail	 = $feed['meta']['billingInformation_email'];//Integer: Where the Array Index of the $entry
		$email	 = $entry[$bEmail];
		
		$ref_1_label = $feed['meta']['reference_1_label'];
		$ref_1_label = $ref_1_label!='' ? substr($ref_1_label, 0, 119) : ''; // Limit to 120 Character
		$bref_1	 = $feed['meta']['billingInformation_reference_1']; //Integer: Where the Array Index of the $entry
		$ref_1	 = $entry[$bref_1];
		$ref_1	 = $ref_1!='' ? substr($ref_1, 0, 19): '';
		$sPhone	 = $feed['meta']['billingInformation_bill_mobile'];//Integer: Where the Array Index of the $entry
		$phone	 = $entry[$sPhone];
		
		//number intelligence
		$custTel2     = substr($phone, 0, 1);
		if ($custTel2 == '+') {
			$custTel3 = substr($phone, 1, 1);
			if ($custTel3 != '6'){
				$phone = "+6" . $phone;
			}
		} else if ($custTel2 == '6') {
		} else {
			if ($phone != ''){
				$phone = "+6" . $phone;
			}
		}
		//number intelligence
		
		$sSms   = $feed['meta']['billplzSMS'];
		$sEmailNotify = $feed['meta']['billplzEmail'];
		$deliver = $sSms==1 OR $sEmailNotify==1 ? true : false ; 
		
		$email	= (($sSms==0 AND $sEmailNotify==1) OR ($deliver AND $sEmailNotify==1)) ? $email : !$deliver ? $email : '';
		$phone	= (($sSms==1 AND $sEmailNotify==0) OR ($deliver AND $sSms==1)) ? preg_replace("/[^0-9]/", "", $phone) : !$deliver ? preg_replace("/[^0-9]/", "", $phone) : '';
		$billplz_data = array(
			'amount' => rgar($submission_data, 'payment_amount') * 100,
			'name' => $entry[$fName] . " " . $entry[$sName],
			'description' => $billdesc,
			'email' => $email,
			'collection_id' => $collection_id,
			'reference_1_label' => $ref_1_label,
			'reference_1' => $ref_1,
			'reference_2_label' => "ID",
			'reference_2' => $entry['id'],
			'deliver' => $deliver,
			'mobile' => $phone,
			'redirect_url' => $ipn_url,
			'callback_url' => $ipn_url
		);
		
		$process = curl_init($url );
		curl_setopt($process, CURLOPT_HEADER, 0);
		curl_setopt($process, CURLOPT_USERPWD, $api_key . ":");
		curl_setopt($process, CURLOPT_TIMEOUT, 30);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, http_build_query($billplz_data));
		$return = curl_exec($process);
		curl_close($process);
		$arr = json_decode($return, true);
		if (isset($arr['error'])){
			unset($billplz_data['mobile']);
			$process = curl_init($url );
			curl_setopt($process, CURLOPT_HEADER, 0);
			curl_setopt($process, CURLOPT_USERPWD, $api_key . ":");
			curl_setopt($process, CURLOPT_TIMEOUT, 30);
			curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($process, CURLOPT_POSTFIELDS, http_build_query($billplz_data));
			$return = curl_exec($process);
			curl_close($process);
			$arr            = json_decode($return, true);
			$url = isset($arr['url']) ? $arr['url'] : "http://facebook.com/billplzplugin";
		}
		else {
			$url = $arr['url'];
		}
		$url.='?auto_submit='.$autoSubmit;
		$this->log_debug( __METHOD__ . "(): Sending to Billplz: {$url}" );
		//troubleshooting purpose
		//$result = var_export($billplz_data, true); 
		//mail("wanzulkarnain69@gmail.com","huhu",$result);
		return $url;
	}

	public function get_product_query_string( $submission_data, $entry_id ) {

		if ( empty( $submission_data ) ) {
			return false;
		}

		$query_string   = '';
		$payment_amount = rgar( $submission_data, 'payment_amount' );
		$setup_fee      = rgar( $submission_data, 'setup_fee' );
		$trial_amount   = rgar( $submission_data, 'trial' );
		$line_items     = rgar( $submission_data, 'line_items' );
		$discounts      = rgar( $submission_data, 'discounts' );

		$product_index = 1;
		$shipping      = '';
		$discount_amt  = 0;
		$cmd           = '_cart';
		$extra_qs      = '&upload=1';

		//work on products
		if ( is_array( $line_items ) ) {
			foreach ( $line_items as $item ) {
				$product_name = urlencode( $item['name'] );
				$quantity     = $item['quantity'];
				$unit_price   = $item['unit_price'];
				$options      = rgar( $item, 'options' );
				$product_id   = $item['id'];
				$is_shipping  = rgar( $item, 'is_shipping' );

				if ( $is_shipping ) {
					//populate shipping info
					$shipping .= ! empty( $unit_price ) ? "&shipping_1={$unit_price}" : '';
				} else {
					//add product info to querystring
					$query_string .= "&item_name_{$product_index}={$product_name}&amount_{$product_index}={$unit_price}&quantity_{$product_index}={$quantity}";
				}
				//add options
				if ( ! empty( $options ) ) {
					if ( is_array( $options ) ) {
						$option_index = 1;
						foreach ( $options as $option ) {
							$option_label = urlencode( $option['field_label'] );
							$option_name  = urlencode( $option['option_name'] );
							$query_string .= "&on{$option_index}_{$product_index}={$option_label}&os{$option_index}_{$product_index}={$option_name}";
							$option_index ++;
						}
					}
				}
				$product_index ++;
			}
		}

		//look for discounts
		if ( is_array( $discounts ) ) {
			foreach ( $discounts as $discount ) {
				$discount_full = abs( $discount['unit_price'] ) * $discount['quantity'];
				$discount_amt += $discount_full;
			}
			if ( $discount_amt > 0 ) {
				$query_string .= "&discount_amount_cart={$discount_amt}";
			}
		}

		$query_string .= "{$shipping}&cmd={$cmd}{$extra_qs}";
		
		//save payment amount to lead meta
		gform_update_meta( $entry_id, 'payment_amount', $payment_amount );

		return $payment_amount > 0 ? $query_string : false;

	}
	/*
	public function get_donation_query_string( $submission_data, $entry_id ) {
		if ( empty( $submission_data ) ) {
			return false;
		}

		$query_string   = '';
		$payment_amount = rgar( $submission_data, 'payment_amount' );
		$line_items     = rgar( $submission_data, 'line_items' );
		$purpose        = '';
		$cmd            = '_donations';

		//work on products
		if ( is_array( $line_items ) ) {
			foreach ( $line_items as $item ) {
				$product_name    = $item['name'];
				$quantity        = $item['quantity'];
				$quantity_label  = $quantity > 1 ? $quantity . ' ' : '';
				$options         = rgar( $item, 'options' );
				$is_shipping     = rgar( $item, 'is_shipping' );
				$product_options = '';

				if ( ! $is_shipping ) {
					//add options
					if ( ! empty( $options ) ) {
						if ( is_array( $options ) ) {
							$product_options = ' (';
							foreach ( $options as $option ) {
								$product_options .= $option['option_name'] . ', ';
							}
							$product_options = substr( $product_options, 0, strlen( $product_options ) - 2 ) . ')';
						}
					}
					$purpose .= $quantity_label . $product_name . $product_options . ', ';
				}
			}
		}

		if ( ! empty( $purpose ) ) {
			$purpose = substr( $purpose, 0, strlen( $purpose ) - 2 );
		}

		$purpose = urlencode( $purpose );

		//truncating to maximum length allowed by Billplz
		if ( strlen( $purpose ) > 127 ) {
			$purpose = substr( $purpose, 0, 124 ) . '...';
		}

		$query_string = "&amount={$payment_amount}&item_name={$purpose}&cmd={$cmd}";
		
		//save payment amount to lead meta
		gform_update_meta( $entry_id, 'payment_amount', $payment_amount );

		return $payment_amount > 0 ? $query_string : false;

	}
	*/

	/*
	public function get_subscription_query_string( $feed, $submission_data, $entry_id ) {

		if ( empty( $submission_data ) ) {
			return false;
		}

		$query_string         = '';
		$payment_amount       = rgar( $submission_data, 'payment_amount' );
		$setup_fee            = rgar( $submission_data, 'setup_fee' );
		$trial_enabled        = rgar( $feed['meta'], 'trial_enabled' );
		$line_items           = rgar( $submission_data, 'line_items' );
		$discounts            = rgar( $submission_data, 'discounts' );
		$recurring_field      = rgar( $submission_data, 'payment_amount' ); //will be field id or the text 'form_total'
		$product_index        = 1;
		$shipping             = '';
		$discount_amt         = 0;
		$cmd                  = '_xclick-subscriptions';
		$extra_qs             = '';
		$name_without_options = '';
		$item_name            = '';

		//work on products
		if ( is_array( $line_items ) ) {
			foreach ( $line_items as $item ) {
				$product_id     = $item['id'];
				$product_name   = $item['name'];
				$quantity       = $item['quantity'];
				$quantity_label = $quantity > 1 ? $quantity . ' ' : '';

				$unit_price  = $item['unit_price'];
				$options     = rgar( $item, 'options' );
				$product_id  = $item['id'];
				$is_shipping = rgar( $item, 'is_shipping' );

				$product_options = '';
				if ( ! $is_shipping ) {
					//add options

					if ( ! empty( $options ) && is_array( $options ) ) {
						$product_options = ' (';
						foreach ( $options as $option ) {
							$product_options .= $option['option_name'] . ', ';
						}
						$product_options = substr( $product_options, 0, strlen( $product_options ) - 2 ) . ')';
					}

					$item_name .= $quantity_label . $product_name . $product_options . ', ';
					$name_without_options .= $product_name . ', ';
				}
			}

			//look for discounts to pass in the item_name
			if ( is_array( $discounts ) ) {
				foreach ( $discounts as $discount ) {
					$product_name   = $discount['name'];
					$quantity       = $discount['quantity'];
					$quantity_label = $quantity > 1 ? $quantity . ' ' : '';
					$item_name .= $quantity_label . $product_name . ' (), ';
					$name_without_options .= $product_name . ', ';
				}
			}

			if ( ! empty( $item_name ) ) {
				$item_name = substr( $item_name, 0, strlen( $item_name ) - 2 );
			}

			//if name is larger than max, remove options from it.
			if ( strlen( $item_name ) > 127 ) {
				$item_name = substr( $name_without_options, 0, strlen( $name_without_options ) - 2 );

				//truncating name to maximum allowed size
				if ( strlen( $item_name ) > 127 ) {
					$item_name = substr( $item_name, 0, 124 ) . '...';
				}
			}
			$item_name = urlencode( $item_name );

		}

		$trial = '';
		//see if a trial exists
		if ( $trial_enabled ) {
			$trial_amount        = rgar( $submission_data, 'trial' ) ? rgar( $submission_data, 'trial' ) : 0;
			$trial_period_number = rgar( $feed['meta'], 'trialPeriod_length' );
			$trial_period_type   = $this->convert_interval( rgar( $feed['meta'], 'trialPeriod_unit' ), 'char' );
			$trial               = "&a1={$trial_amount}&p1={$trial_period_number}&t1={$trial_period_type}";
		}

		//check for recurring times
		$recurring_times = rgar( $feed['meta'], 'recurringTimes' ) ? '&srt=' . rgar( $feed['meta'], 'recurringTimes' ) : '';
		$recurring_retry = rgar( $feed['meta'], 'recurringRetry' ) ? '1' : '0';

		$billing_cycle_number = rgar( $feed['meta'], 'billingCycle_length' );
		$billing_cycle_type   = $this->convert_interval( rgar( $feed['meta'], 'billingCycle_unit' ), 'char' );

		$query_string = "&cmd={$cmd}&item_name={$item_name}{$trial}&a3={$payment_amount}&p3={$billing_cycle_number}&t3={$billing_cycle_type}&src=1&sra={$recurring_retry}{$recurring_times}";

		//save payment amount to lead meta
		gform_update_meta( $entry_id, 'payment_amount', $payment_amount );
		
		return $payment_amount > 0 ? $query_string : false;

	}
	*/

	public function customer_query_string( $feed, $entry ) {
		$fields = '';
		foreach ( $this->get_customer_fields() as $field ) {
			$field_id = $feed['meta'][ $field['meta_name'] ];
			$value    = rgar( $entry, $field_id );

			if ( $field['name'] == 'country' ) {
				$value = class_exists( 'GF_Field_Address' ) ? GF_Fields::get( 'address' )->get_country_code( $value ) : GFCommon::get_country_code( $value );
			} elseif ( $field['name'] == 'state' ) {
				$value = class_exists( 'GF_Field_Address' ) ? GF_Fields::get( 'address' )->get_us_state_code( $value ) : GFCommon::get_us_state_code( $value );
			}

			if ( ! empty( $value ) ) {
				$fields .= "&{$field['name']}=" . urlencode( $value );
			}
		}

		return $fields;
	}

	public function return_url( $form_id, $lead_id ) {
		$pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

		$server_port = apply_filters( 'gform_billplz_return_url_port', $_SERVER['SERVER_PORT'] );

		if ( $server_port != '80' ) {
			$pageURL .= $_SERVER['SERVER_NAME'] . ':' . $server_port . $_SERVER['REQUEST_URI'];
		} else {
			$pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		}

		$ids_query = "ids={$form_id}|{$lead_id}";
		$ids_query .= '&hash=' . wp_hash( $ids_query );

		$url = add_query_arg( 'gf_billplz_return', base64_encode( $ids_query ), $pageURL );

		$query = 'gf_billplz_return=' . base64_encode( $ids_query );
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
		return apply_filters( 'gform_billplz_return_url', $url, $form_id, $lead_id, $query  );

	}

	public static function maybe_thankyou_page() {
		$instance = self::get_instance();

		if ( ! $instance->is_gravityforms_supported() ) {
			return;
		}

		if ( $str = rgget( 'gf_billplz_return' ) ) {
			$str = base64_decode( $str );

			parse_str( $str, $query );
			if ( wp_hash( 'ids=' . $query['ids'] ) == $query['hash'] ) {
				list( $form_id, $lead_id ) = explode( '|', $query['ids'] );

				$form = GFAPI::get_form( $form_id );
				$lead = GFAPI::get_entry( $lead_id );

				if ( ! class_exists( 'GFFormDisplay' ) ) {
					require_once( GFCommon::get_base_path() . '/form_display.php' );
				}

				$confirmation = GFFormDisplay::handle_confirmation( $form, $lead, false );

				if ( is_array( $confirmation ) && isset( $confirmation['redirect'] ) ) {
					header( "Location: {$confirmation['redirect']}" );
					exit;
				}

				GFFormDisplay::$submission[ $form_id ] = array( 'is_confirmation' => true, 'confirmation_message' => $confirmation, 'form' => $form, 'lead' => $lead );
			}
		}
	}

	public function get_customer_fields() {
		return array(
			array( 'name' => 'first_name', 'label' => 'First Name', 'meta_name' => 'billingInformation_firstName' ),
			array( 'name' => 'last_name', 'label' => 'Last Name', 'meta_name' => 'billingInformation_lastName' ),
			array( 'name' => 'email', 'label' => 'Email', 'meta_name' => 'billingInformation_email' ),
			array( 'name' => 'bill_mobile', 'label' => 'Phone', 'meta_name' => 'billingInformation_bill_mobile' ),
			array( 'name' => 'bill_desc', 'label' => 'Description', 'meta_name' => 'billingInformation_bill_desc' ),
			array( 'name' => 'bill_reference_1', 'label' => 'Reference 1', 'meta_name' => 'billingInformation_reference_1' ),
		);
	}

	/*
	public function convert_interval( $interval, $to_type ) {
		//convert single character into long text for new feed settings or convert long text into single character for sending to billplz
		//$to_type: text (change character to long text), OR char (change long text to character)
		if ( empty( $interval ) ) {
			return '';
		}

		$new_interval = '';
		if ( $to_type == 'text' ) {
			//convert single char to text
			switch ( strtoupper( $interval ) ) {
				case 'D' :
					$new_interval = 'day';
					break;
				case 'W' :
					$new_interval = 'week';
					break;
				case 'M' :
					$new_interval = 'month';
					break;
				case 'Y' :
					$new_interval = 'year';
					break;
				default :
					$new_interval = $interval;
					break;
			}
		} else {
			//convert text to single char
			switch ( strtolower( $interval ) ) {
				case 'day' :
					$new_interval = 'D';
					break;
				case 'week' :
					$new_interval = 'W';
					break;
				case 'month' :
					$new_interval = 'M';
					break;
				case 'year' :
					$new_interval = 'Y';
					break;
				default :
					$new_interval = $interval;
					break;
			}
		}

		return $new_interval;
	}
	*/

	public function delay_post( $is_disabled, $form, $entry ) {

		$feed            = $this->get_payment_feed( $entry );
		$submission_data = $this->get_submission_data( $feed, $form, $entry );

		if ( ! $feed || empty( $submission_data['payment_amount'] ) ) {
			return $is_disabled;
		}

		return ! rgempty( 'delayPost', $feed['meta'] );
	}

	public function delay_notification( $is_disabled, $notification, $form, $entry ) {

		$feed            = $this->get_payment_feed( $entry );
		$submission_data = $this->get_submission_data( $feed, $form, $entry );

		if ( ! $feed || empty( $submission_data['payment_amount'] ) ) {
			return $is_disabled;
		}

		$selected_notifications = is_array( rgar( $feed['meta'], 'selectedNotifications' ) ) ? rgar( $feed['meta'], 'selectedNotifications' ) : array();

		return isset( $feed['meta']['delayNotification'] ) && in_array( $notification['id'], $selected_notifications ) ? true : $is_disabled;
	}


	//------- PROCESSING BILLPLZ IPN (Callback) -----------//

	public function callback() {

		if ( ! $this->is_gravityforms_supported() ) {
			return false;
		}
		
		if ( ! isset($_POST['id'])){
			$this->log_debug( __METHOD__ . '(): IPN request received. Starting to process => ' . print_r( $_POST, true ) );
		}

		//------- Send request to billplz and verify it has not been spoofed ---------------------//
		$is_verified = $this->verify_billplz_ipn();	
		
		if ( is_wp_error( $is_verified ) ) {
			$this->log_error( __METHOD__ . '(): IPN verification failed with an error. Aborting with a 500 error so that IPN is resent.' );
			
			return new WP_Error( 'IPNVerificationError', 'There was an error when verifying the IPN message with Billplz', array( 'status_header' => 500 ) );
		} elseif ( ! $is_verified ) {
			$this->log_error( __METHOD__ . '(): IPN request could not be verified by Billplz. Aborting.' );

			return false;
		}
		//mail("wanzulkarnain69@gmail.com","yayyy","dah ok wuhu!!");
		$this->log_debug( __METHOD__ . '(): IPN message successfully verified by Billplz' );		

		//------ Getting entry related to this IPN ----------------------------------------------//
		$entry = $this->get_entry( $this->billplzSignal['reference_2'] );
		//$entry = GFAPI::get_entry($this->billplzSignal['reference_2']);

		//Ignore orphan IPN messages (ones without an entry)
		if ( ! $entry ) {
			$this->log_error( __METHOD__ . '(): Entry could not be found. Aborting.' );

			return false;
		}
		$this->log_debug( __METHOD__ . '(): Entry has been found => ' . print_r( $entry, true ) );

		if ( $entry['status'] == 'spam' ) {
			$this->log_error( __METHOD__ . '(): Entry is marked as spam. Aborting.' );

			return false;
		}

		//------ Getting feed related to this IPN ------------------------------------------//
		$feed = $this->get_payment_feed( $entry );
		
		//------- Redirect for Redirect ---------------------------------------------------------//
		
		if (isset($_GET['billplz']['id'])){
			if ($this->billplzSignal['paid']){
				$return_url = $_GET['returnSuccess'].'&rm=2';
				header("Location: " . $return_url);
			}
			elseif (!$this->billplzSignal['paid']){
				if ($feed['meta']['cancelUrl'] == ''){
					$cancelURL = $entry['source_url'];
				}
				else {
					$cancelURL = $feed['meta']['cancelUrl'];
				}
				//mail("wanzulkarnain69@gmail.com","feed meta", var_export($feed,true));
				header("Location: " . $cancelURL);
			}
			return false;
		}
		
		//Ignore IPN messages from forms that are no longer configured with the Billplz add-on
		if ( ! $feed || ! rgar( $feed, 'is_active' ) ) {
			$this->log_error( __METHOD__ . "(): Form no longer is configured with Billplz Addon. Form ID: {$entry['form_id']}. Aborting." );

			return false;
		}
		$this->log_debug( __METHOD__ . "(): Form {$entry['form_id']} is properly configured." );

		//----- Making sure this IPN can be processed -------------------------------------//
		if ( ! $this->can_process_ipn( $feed, $entry ) ) {
			$this->log_debug( __METHOD__ . '(): IPN cannot be processed.' );

			return false;
		}
		
		//----- Processing IPN ------------------------------------------------------------//
		$this->log_debug( __METHOD__ . '(): Processing IPN...' );
		//$result = var_export($feed, true); //sambung sini
		
		//$action = $this->process_ipn( $feed, $entry, $this->billplzSignal['paid'], 'product', $this->billplzSignal['id'], rgpost( 'parent_txn_id' ), rgpost( 'subscr_id' ), rgpost( 'mc_gross' ), rgpost( 'pending_reason' ), rgpost( 'reason_code' ), rgpost( 'mc_amount3' ) );
		
		$action = array();
		
		if ($this->billplzSignal['paid']){
			$action['id']               = $this->billplzSignal['id'] . '_' . $this->billplzSignal['state'];
			$action['type']             = 'complete_payment';
			$action['transaction_id']   = $this->billplzSignal['id'];
			$action['amount']           = $this->billplzSignal['amount']/100;
			$action['entry_id']         = $entry['id'];
			$action['payment_date']     = gmdate( 'y-m-d H:i:s' );
			$action['payment_method']	= 'Billplz';
			$action['ready_to_fulfill'] = ! $entry['is_fulfilled'] ? true : false;
			GFPaymentAddOn::add_note( $entry['id'], sprintf( __( 'Bills URL: %s', 'billplzforgravityforms' ), $this->billplzSignal['url'] ) );
			GFPaymentAddOn::add_note( $entry['id'], sprintf( __( 'CONSIDER A DONATION TO DEVELOPER. PLEASE MAKE A DONATION http://www.wanzul.net/donate', 'billplzforgravityforms' )) );
		}
		elseif (!$this->billplzSignal['paid']){
			$action['id']             = $this->billplzSignal['id'] . '_' . $this->billplzSignal['state'];
			$action['type']           = 'fail_payment';
			$action['transaction_id'] = $this->billplzSignal['id'];
			$action['entry_id']       = $entry['id'];
			$action['amount']         = $this->billplzSignal['amount']/100;
		}
		
		$this->log_debug( __METHOD__ . '(): IPN processing complete.' );
		
		if ( rgempty( 'entry_id', $action ) ) {
			return false;
		}
		//mail("wanzulkarnain69@gmail.com","ok dah","kerja setel");
		return $action;

	}

	public function get_payment_feed( $entry, $form = false ) {

		$feed = parent::get_payment_feed( $entry, $form );

		if ( empty( $feed ) && ! empty( $entry['id'] ) ) {
			//looking for feed created by legacy versions
			$feed = $this->get_billplz_feed_by_entry( $entry['id'] );
		}

		$feed = apply_filters( 'gform_billplz_get_payment_feed', $feed, $entry, $form ? $form : GFAPI::get_form( $entry['form_id'] ) );

		return $feed;
	}

	private function get_billplz_feed_by_entry( $entry_id ) {

		$feed_id = gform_get_meta( $entry_id, 'billplz_feed_id' );
		$feed    = $this->get_feed( $feed_id );

		return ! empty( $feed ) ? $feed : false;
	}

	public function post_callback( $callback_action, $callback_result ) {
		if ( is_wp_error( $callback_action ) || ! $callback_action ) {
			return false;
		}

		//run the necessary hooks
		$entry          = GFAPI::get_entry( $callback_action['entry_id'] );
		$feed           = $this->get_payment_feed( $entry );
		$transaction_id = rgar( $callback_action, 'transaction_id' );
		$amount         = rgar( $callback_action, 'amount' );
		$subscriber_id  = rgar( $callback_action, 'subscriber_id' );
		$pending_reason = $this->billplzSignal['state'];
		$reason         = 'no reason';
		$status         = $this->billplzSignal['paid'];
		$txn_type       = 'product';
		$parent_txn_id  = $this->billplzSignal['id'];

		//run gform_billplz_ only in certain conditions
		if ( rgar( $callback_action, 'ready_to_fulfill' ) && ! rgar( $callback_action, 'abort_callback' ) ) {
			$this->fulfill_order( $entry, $transaction_id, $amount, $feed );
		} else {
			if ( rgar( $callback_action, 'abort_callback' ) ) {
				$this->log_debug( __METHOD__ . '(): Callback processing was aborted. Not fulfilling entry.' );
			} else {
				$this->log_debug( __METHOD__ . '(): Entry is already fulfilled or not ready to be fulfilled, not running gform_billplz_fulfillment hook.' );
			}
		}

		do_action( 'gform_post_payment_status', $feed, $entry, $status, $transaction_id, $subscriber_id, $amount, $pending_reason, $reason );
		if ( has_filter( 'gform_post_payment_status' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_post_payment_status.' );
		}

		do_action( 'gform_billplz_ipn_' . $txn_type, $entry, $feed, $status, $txn_type, $transaction_id, $parent_txn_id, $subscriber_id, $amount, $pending_reason, $reason );
		if ( has_filter( 'gform_billplz_ipn_' . $txn_type ) ) {
			$this->log_debug( __METHOD__ . "(): Executing functions hooked to gform_billplz_ipn_{$txn_type}." );
		}

		do_action( 'gform_billplz_post_ipn', $_POST, $entry, $feed, false );
		if ( has_filter( 'gform_billplz_post_ipn' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_billplz_post_ipn.' );
		}
	}

	private function verify_billplz_ipn() {
		
		$id = isset($_POST['id']) ? $_POST['id'] : $_GET['billplz']['id'];
		
		//$FormID = GFAPI::get_feeds($_GET['feedid'], $_GET['formid']);
		$entryid = htmlspecialchars($_GET['entryid']);
		$entry = $this->get_entry( $entryid );
		$feed = $this->get_payment_feed( $entry );
		
		$host = $feed['meta']['mode'] == 'production' ? $this->production_url : $this->sandbox_url;
		$api_key = $feed['meta']['billplzAPIKey'];
		$collection_id = $feed['meta']['billplzCollectionID'];
		
		$host    = $host .'bills/'. $id;
		$process = curl_init($host);
		curl_setopt($process, CURLOPT_HEADER, 0);
		curl_setopt($process, CURLOPT_USERPWD, $api_key . ":");
		curl_setopt($process, CURLOPT_TIMEOUT, 30);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		$return = curl_exec($process);
		curl_close($process);
		$arra = json_decode($return, true);
		
		$this->billplzSignal = $arra;
		
		$this->log_debug( __METHOD__ . "(): Sending IPN request to Billplz for validation" );
		
		return $arra['collection_id']==$collection_id;
	}

	private function process_ipn( $config, $entry, $status, $transaction_type, $transaction_id, $parent_transaction_id, $subscriber_id, $amount, $pending_reason, $reason, $recurring_amount ) {
		/*
		$this->log_debug( __METHOD__ . "(): Payment status: {$status} - Transaction Type: {$transaction_type} - Transaction ID: {$transaction_id} - Parent Transaction: {$parent_transaction_id} - Subscriber ID: {$subscriber_id} - Amount: {$amount} - Pending reason: {$pending_reason} - Reason: {$reason}" );

		$action = array();
		switch ( strtolower( $transaction_type ) ) {
			case 'subscr_payment' :
				//transaction created
				$action['id']               = $transaction_id;
				$action['transaction_id']   = $transaction_id;
				$action['type']             = 'add_subscription_payment';
				$action['subscription_id']  = $subscriber_id;
				$action['amount']           = $amount;
				$action['entry_id']         = $entry['id'];
				$action['payment_method']	= 'Billplz';
				return $action;
				break;

			case 'subscr_signup' :
				//no transaction created
				$action['id']               = $subscriber_id . '_' . $transaction_type;
				$action['type']             = 'create_subscription';
				$action['subscription_id']  = $subscriber_id;
				$action['amount']           = $recurring_amount;
				$action['entry_id']         = $entry['id'];
				$action['ready_to_fulfill'] = ! $entry['is_fulfilled'] ? true : false;
				
				if ( ! $this->is_valid_initial_payment_amount( $entry['id'], $recurring_amount ) ){
					//create note and transaction
					$this->log_debug( __METHOD__ . '(): Payment amount does not match subscription amount. Subscription will not be activated.' );
					GFPaymentAddOn::add_note( $entry['id'], sprintf( __( 'Payment amount (%s) does not match subscription amount. Subscription will not be activated. Transaction Id: %s', 'billplzforgravityforms' ), GFCommon::to_money( $recurring_amount, $entry['currency'] ), $subscriber_id ) );
					GFPaymentAddOn::insert_transaction( $entry['id'], 'payment', $subscriber_id, $recurring_amount );

					$action['abort_callback'] = true;
				}

				return $action;
				break;

			case 'subscr_cancel' :
				//no transaction created

				$action['id'] = $subscriber_id . '_' . $transaction_type;
				$action['type']            = 'cancel_subscription';
				$action['subscription_id'] = $subscriber_id;
				$action['entry_id']        = $entry['id'];

				return $action;
				break;

			case 'subscr_eot' :
				//no transaction created
				if ( empty( $transaction_id ) ) {
					$action['id'] = $subscriber_id . '_' . $transaction_type;
				} else {
					$action['id'] = $transaction_id;
				}
				$action['type']            = 'expire_subscription';
				$action['subscription_id'] = $subscriber_id;
				$action['entry_id']        = $entry['id'];

				return $action;
				break;

			case 'subscr_failed' :
				//no transaction created
				if ( empty( $transaction_id ) ) {
					$action['id'] = $subscriber_id . '_' . $transaction_type;
				} else {
					$action['id'] = $transaction_id;
				}
				$action['type']            = 'fail_subscription_payment';
				$action['subscription_id'] = $subscriber_id;
				$action['entry_id']        = $entry['id'];
				$action['amount']          = $amount;

				return $action;
				break;

			default:
				//handles products and donation
				switch ( strtolower( $status ) ) {
					case 'completed' :
						//creates transaction
						$action['id']               = $transaction_id . '_' . $status;
						$action['type']             = 'complete_payment';
						$action['transaction_id']   = $transaction_id;
						$action['amount']           = $amount;
						$action['entry_id']         = $entry['id'];
						$action['payment_date']     = gmdate( 'y-m-d H:i:s' );
						$action['payment_method']	= 'Billplz';
						$action['ready_to_fulfill'] = ! $entry['is_fulfilled'] ? true : false;
						
						if ( ! $this->is_valid_initial_payment_amount( $entry['id'], $amount ) ){
							//create note and transaction
							$this->log_debug( __METHOD__ . '(): Payment amount does not match product price. Entry will not be marked as Approved.' );
							GFPaymentAddOn::add_note( $entry['id'], sprintf( __( 'Payment amount (%s) does not match product price. Entry will not be marked as Approved. Transaction Id: %s', 'billplzforgravityforms' ), GFCommon::to_money( $amount, $entry['currency'] ), $transaction_id ) );
							GFPaymentAddOn::insert_transaction( $entry['id'], 'payment', $transaction_id, $amount );

							$action['abort_callback'] = true;
						}

						return $action;
						break;

					case 'reversed' :
						//creates transaction
						$this->log_debug( __METHOD__ . '(): Processing reversal.' );
						GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Refunded' );
						GFPaymentAddOn::add_note( $entry['id'], sprintf( __( 'Payment has been reversed. Transaction Id: %s. Reason: %s', 'billplzforgravityforms' ), $transaction_id, $this->get_reason( $reason ) ) );
						GFPaymentAddOn::insert_transaction( $entry['id'], 'refund', $action['transaction_id'], $action['amount'] );
						break;

					case 'canceled_reversal' :
						//creates transaction
						$this->log_debug( __METHOD__ . '(): Processing a reversal cancellation' );
						GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Paid' );
						GFPaymentAddOn::add_note( $entry['id'], sprintf( __( 'Payment reversal has been canceled and the funds have been transferred to your account. Transaction Id: %s', 'billplzforgravityforms' ), $entry['transaction_id'] ) );
						GFPaymentAddOn::insert_transaction( $entry['id'], 'payment', $action['transaction_id'], $action['amount'] );
						break;

					case 'processed' :
					case 'pending' :
						$action['id']             = $transaction_id . '_' . $status;
						$action['type']           = 'add_pending_payment';
						$action['transaction_id'] = $transaction_id;
						$action['entry_id']       = $entry['id'];
						$action['amount']         = $amount;
						$action['entry_id']       = $entry['id'];
						$amount_formatted         = GFCommon::to_money( $action['amount'], $entry['currency'] );
						$action['note']           = sprintf( __( 'Payment is pending. Amount: %s. Transaction Id: %s. Reason: %s', 'billplzforgravityforms' ), $amount_formatted, $action['transaction_id'], $this->get_pending_reason( $pending_reason ) );

						return $action;
						break;

					case 'refunded' :
						$action['id']             = $transaction_id . '_' . $status;
						$action['type']           = 'refund_payment';
						$action['transaction_id'] = $transaction_id;
						$action['entry_id']       = $entry['id'];
						$action['amount']         = $amount;

						return $action;
						break;

					case 'voided' :
						$action['id']             = $transaction_id . '_' . $status;
						$action['type']           = 'void_authorization';
						$action['transaction_id'] = $transaction_id;
						$action['entry_id']       = $entry['id'];
						$action['amount']         = $amount;

						return $action;
						break;

					case 'denied' :
					case 'failed' :
						$action['id']             = $transaction_id . '_' . $status;
						$action['type']           = 'fail_payment';
						$action['transaction_id'] = $transaction_id;
						$action['entry_id']       = $entry['id'];
						$action['amount']         = $amount;

						return $action;
						break;
				}

				break;
		}
		*/
	}

	public function get_entry( $custom_field ) {

		//Valid IPN requests must have a custom field
		if ( empty( $custom_field ) ) {
			$this->log_error( __METHOD__ . '(): IPN request does not have a custom field, so it was not created by Gravity Forms. Aborting.' );

			return false;
		}

		//Getting entry associated with this IPN message (entry id is sent in the 'custom' field)
		//list( $entry_id, $hash ) = explode( '|', $custom_field );
		//$hash_matches = wp_hash( $entry_id ) == $hash;

		//allow the user to do some other kind of validation of the hash
		//$hash_matches = apply_filters( 'gform_billplz_hash_matches', $hash_matches, $entry_id, $hash, $custom_field );

		//Validates that Entry Id wasn't tampered with
		//if ( ! rgpost( 'test_ipn' ) && ! $hash_matches ) {
		//	$this->log_error( __METHOD__ . "(): Entry Id verification failed. Hash does not match. Custom field: {$custom_field}. Aborting." );

		//	return false;
		//}

		//$this->log_debug( __METHOD__ . "(): IPN message has a valid custom field: {$custom_field}" );
		
		$entry_id = $custom_field;
		$entry = GFAPI::get_entry( $entry_id );

		if ( is_wp_error( $entry ) ) {
			$this->log_error( __METHOD__ . '(): ' . $entry->get_error_message() );

			return false;
		}

		return $entry;
	}

	public function can_process_ipn( $feed, $entry ) {
		/*
		$this->log_debug( __METHOD__ . '(): Checking that IPN can be processed.' );
		//Only process test messages coming fron SandBox and only process production messages coming from production Billplz
		if ( ( $feed['meta']['mode'] == 'test' && ! rgpost( 'test_ipn' ) ) || ( $feed['meta']['mode'] == 'production' && rgpost( 'test_ipn' ) ) ) {
			$this->log_error( __METHOD__ . "(): Invalid test/production mode. IPN message mode (test/production) does not match mode configured in the Billplz feed. Configured Mode: {$feed['meta']['mode']}. IPN test mode: " . rgpost( 'test_ipn' ) );

			return false;
		}
		*/
		
		/**
		 * Filter through your Billplz business email (Checks to make sure it matches)
		 *
		 * @param string $feed['meta']['billplzAPIKey'] The Billplz Email to filter through (Taken from the feed object under feed meta)
		 * @param array $feed The Feed object to filter through and use for modifications
		 * @param array $entry The Entry Object to filter through and use for modifications
		 */
		 //$business_email is Billplz API Key
		 /*
		$business_email = apply_filters( 'gform_billplz_business_email', $feed['meta']['billplzAPIKey'], $feed, $entry );

		$recipient_email = rgempty( 'business' ) ? rgpost( 'receiver_email' ) : rgpost( 'business' );
		if ( strtolower( trim( $recipient_email ) ) != strtolower( trim( $business_email ) ) ) {
			$this->log_error( __METHOD__ . '(): Billplz email does not match. Email entered on Billplz feed:' . strtolower( trim( $business_email ) ) . ' - Email from IPN message: ' . $recipient_email );

			return false;
		}

		//Pre IPN processing filter. Allows users to cancel IPN processing
		$cancel = apply_filters( 'gform_billplz_pre_ipn', false, $_POST, $entry, $feed );

		if ( $cancel ) {
			$this->log_debug( __METHOD__ . '(): IPN processing cancelled by the gform_billplz_pre_ipn filter. Aborting.' );
			do_action( 'gform_billplz_post_ipn', $_POST, $entry, $feed, true );
			if ( has_filter( 'gform_billplz_post_ipn' ) ) {
				$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_billplz_post_ipn.' );
			}

			return false;
		}
		*/
		
		return true;
	}

	public function cancel_subscription( $entry, $feed, $note = null ) {

		/*
		parent::cancel_subscription( $entry, $feed, $note );

		$this->modify_post( rgar( $entry, 'post_id' ), rgars( $feed, 'meta/update_post_action' ) );

		return true;
		*/
	}

	public function modify_post( $post_id, $action ) {

		$result = false;

		if ( ! $post_id ) {
			return $result;
		}

		switch ( $action ) {
			case 'draft':
				$post = get_post( $post_id );
				$post->post_status = 'draft';
				$result = wp_update_post( $post );
				$this->log_debug( __METHOD__ . "(): Set post (#{$post_id}) status to \"draft\"." );
				break;
			case 'delete':
				$result = wp_delete_post( $post_id );
				$this->log_debug( __METHOD__ . "(): Deleted post (#{$post_id})." );
				break;
		}

		return $result;
	}

	private function get_reason( $code ) {

		switch ( strtolower( $code ) ) {
			case 'due':
				return esc_html__( 'Buyer did not complete the payment', 'billplzforgravityforms' );
			case 'overdue':
				return esc_html__( 'Bills are not paid for a very long time.', 'billplzforgravityforms' );

			case 'paid':
				return esc_html__( 'Payment completed', 'billplzforgravityforms' );

			case 'hidden':
				return esc_html__( 'Bills have been deleted.', 'billplzforgravityforms' );

			default:
				return empty( $code ) ? esc_html__( 'Reason has not been specified. For more information, contact Billplz Customer Service.', 'billplzforgravityforms' ) : $code;
		}
	}

	public function is_callback_valid() {
		if ( rgget( 'page' ) != 'gf_billplz_ipn' ) {
			return false;
		}

		return true;
	}

	private function get_pending_reason( $code ) {
		/*
		switch ( strtolower( $code ) ) {
			case 'address':
				return esc_html__( 'The payment is pending because your customer did not include a confirmed shipping address and your Payment Receiving Preferences is set to allow you to manually accept or deny each of these payments. To change your preference, go to the Preferences section of your Profile.', 'billplzforgravityforms' );

			case 'authorization':
				return esc_html__( 'You set the payment action to Authorization and have not yet captured funds.', 'billplzforgravityforms' );

			case 'echeck':
				return esc_html__( 'The payment is pending because it was made by an eCheck that has not yet cleared.', 'billplzforgravityforms' );

			case 'intl':
				return esc_html__( 'The payment is pending because you hold a non-U.S. account and do not have a withdrawal mechanism. You must manually accept or deny this payment from your Account Overview.', 'billplzforgravityforms' );

			case 'multi-currency':
				return esc_html__( 'You do not have a balance in the currency sent, and you do not have your Payment Receiving Preferences set to automatically convert and accept this payment. You must manually accept or deny this payment.', 'billplzforgravityforms' );

			case 'order':
				return esc_html__( 'You set the payment action to Order and have not yet captured funds.', 'billplzforgravityforms' );

			case 'paymentreview':
				return esc_html__( 'The payment is pending while it is being reviewed by Billplz for risk.', 'billplzforgravityforms' );

			case 'unilateral':
				return esc_html__( 'The payment is pending because it was made to an email address that is not yet registered or confirmed.', 'billplzforgravityforms' );

			case 'upgrade':
				return esc_html__( 'The payment is pending because it was made via credit card and you must upgrade your account to Business or Premier status in order to receive the funds. upgrade can also mean that you have reached the monthly limit for transactions on your account.', 'billplzforgravityforms' );

			case 'verify':
				return esc_html__( 'The payment is pending because you are not yet verified. You must verify your account before you can accept this payment.', 'billplzforgravityforms' );

			case 'other':
				return esc_html__( 'Reason has not been specified. For more information, contact Billplz Customer Service.', 'billplzforgravityforms' );

			default:
				return empty( $code ) ? esc_html__( 'Reason has not been specified. For more information, contact Billplz Customer Service.', 'billplzforgravityforms' ) : $code;
		}
		*/
	}

	//------- AJAX FUNCTIONS ------------------//

	public function init_ajax() {

		parent::init_ajax();

		add_action( 'wp_ajax_gf_dismiss_billplz_menu', array( $this, 'ajax_dismiss_menu' ) );

	}

	//------- ADMIN FUNCTIONS/HOOKS -----------//

	public function init_admin() {

		parent::init_admin();

		//add actions to allow the payment status to be modified
		add_action( 'gform_payment_status', array( $this, 'admin_edit_payment_status' ), 3, 3 );
		add_action( 'gform_payment_date', array( $this, 'admin_edit_payment_date' ), 3, 3 );
		add_action( 'gform_payment_transaction_id', array( $this, 'admin_edit_payment_transaction_id' ), 3, 3 );
		add_action( 'gform_payment_amount', array( $this, 'admin_edit_payment_amount' ), 3, 3 );
		add_action( 'gform_after_update_entry', array( $this, 'admin_update_payment' ), 4, 2 );

		add_filter( 'gform_addon_navigation', array( $this, 'maybe_create_menu' ) );

		//checking if webserver is compatible with Billplz SSL certificate
		//add_action( 'admin_notices', array( $this, 'check_ipn_request' ) );
	}

	/**
	 * Add supported notification events.
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return array
	 */
	public function supported_notification_events( $form ) {
		if ( ! $this->has_feed( $form['id'] ) ) {
			return false;
		}

		return array(
				'complete_payment'          => esc_html__( 'Payment Completed', 'billplzforgravityforms' ),
				'refund_payment'            => esc_html__( 'Payment Refunded', 'billplzforgravityforms' ),
				'fail_payment'              => esc_html__( 'Payment Failed', 'billplzforgravityforms' ),
				'add_pending_payment'       => esc_html__( 'Payment Pending', 'billplzforgravityforms' ),
				'void_authorization'        => esc_html__( 'Authorization Voided', 'billplzforgravityforms' ),
				//'create_subscription'       => esc_html__( 'Subscription Created', 'billplzforgravityforms' ),
				//'cancel_subscription'       => esc_html__( 'Subscription Canceled', 'billplzforgravityforms' ),
				//'expire_subscription'       => esc_html__( 'Subscription Expired', 'billplzforgravityforms' ),
				//'add_subscription_payment'  => esc_html__( 'Subscription Payment Added', 'billplzforgravityforms' ),
				//'fail_subscription_payment' => esc_html__( 'Subscription Payment Failed', 'billplzforgravityforms' ),
		);
	}

	public function maybe_create_menu( $menus ) {
		$current_user = wp_get_current_user();
		$dismiss_billplz_menu = get_metadata( 'user', $current_user->ID, 'dismiss_billplz_menu', true );
		if ( $dismiss_billplz_menu != '1' ) {
			$menus[] = array( 'name' => $this->_slug, 'label' => $this->get_short_title(), 'callback' => array( $this, 'temporary_plugin_page' ), 'permission' => $this->_capabilities_form_settings );
		}

		return $menus;
	}

	public function ajax_dismiss_menu() {

		$current_user = wp_get_current_user();
		update_metadata( 'user', $current_user->ID, 'dismiss_billplz_menu', '1' );
	}

	public function temporary_plugin_page() {
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
			<h1><?php _e( 'Billplz for GravityForms', 'billplzforgravityforms' ) ?></h1>
			<div class="about-text"><?php esc_html_e( 'Thank you for updating! The new version of the Billplz for GravityForms makes changes to how you manage your Billplz integration.', 'billplzforgravityforms' ) ?></div>
			<div class="changelog">
				<hr/>
				<div class="feature-section col two-col">
					<div class="col-1">
						<h3><?php esc_html_e( 'Manage Billplz Contextually', 'billplzforgravityforms' ) ?></h3>
						<p><?php esc_html_e( 'Billplz Feeds are now accessed via the Billplz sub-menu within the Form Settings for the Form you would like to integrate Billplz with.', 'billplzforgravityforms' ) ?></p>
					</div>
					<div class="col-2 last-feature">
						<img src="/wp-content/plugins/billplz-for-gravityforms/images/billplzdonate.png">
					</div>
				</div>

				<hr/>

				<form method="post" id="dismiss_menu_form" style="margin-top: 20px;">
					<input type="checkbox" name="dismiss_billplz_menu" value="1" onclick="dismissMenu();"> <label><?php _e( 'I understand this change, dismiss this message!', 'billplzforgravityforms' ) ?></label>
					<img id="gf_spinner" src="<?php echo GFCommon::get_base_url() . '/images/spinner.gif'?>" alt="<?php _e( 'Please wait...', 'billplzforgravityforms' ) ?>" style="display:none;"/>
				</form>

			</div>
		</div>
		<?php
	}

	public function admin_edit_payment_status( $payment_status, $form, $entry ) {
		if ( $this->payment_details_editing_disabled( $entry ) ) {
			return $payment_status;
		}

		//create drop down for payment status
		$payment_string = gform_tooltip( 'billplz_edit_payment_status', '', true );
		$payment_string .= '<select id="payment_status" name="payment_status">';
		$payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status . '</option>';
		$payment_string .= '<option value="Paid">Paid</option>';
		$payment_string .= '</select>';

		return $payment_string;
	}

	public function admin_edit_payment_date( $payment_date, $form, $entry ) {
		if ( $this->payment_details_editing_disabled( $entry ) ) {
			return $payment_date;
		}

		$payment_date = $entry['payment_date'];
		if ( empty( $payment_date ) ) {
			$payment_date = gmdate( 'Y-m-d H:i:s' );
		}

		$input = '<input type="text" id="payment_date" name="payment_date" value="' . $payment_date . '">';

		return $input;
	}

	public function admin_edit_payment_transaction_id( $transaction_id, $form, $entry ) {
		if ( $this->payment_details_editing_disabled( $entry ) ) {
			return $transaction_id;
		}

		$input = '<input type="text" id="billplz_transaction_id" name="billplz_transaction_id" value="' . $transaction_id . '">';

		return $input;
	}

	public function admin_edit_payment_amount( $payment_amount, $form, $entry ) {
		if ( $this->payment_details_editing_disabled( $entry ) ) {
			return $payment_amount;
		}

		if ( empty( $payment_amount ) ) {
			$payment_amount = GFCommon::get_order_total( $form, $entry );
		}

		$input = '<input type="text" id="payment_amount" name="payment_amount" class="gform_currency" value="' . $payment_amount . '">';

		return $input;
	}

	public function admin_update_payment( $form, $entry_id ) {
		check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );

		//update payment information in admin, need to use this function so the lead data is updated before displayed in the sidebar info section
		$entry = GFFormsModel::get_lead( $entry_id );

		if ( $this->payment_details_editing_disabled( $entry, 'update' ) ) {
			return;
		}
        
		//get payment fields to update
		$payment_status = rgpost( 'payment_status' );
		//when updating, payment status may not be editable, if no value in post, set to lead payment status
		if ( empty( $payment_status ) ) {
			$payment_status = $entry['payment_status'];
		}

		$payment_amount      = GFCommon::to_number( rgpost( 'payment_amount' ) );
		$payment_transaction = rgpost( 'billplz_transaction_id' );
		$payment_date        = rgpost( 'payment_date' );
		if ( empty( $payment_date ) ) {
			$payment_date = gmdate( 'y-m-d H:i:s' );
		} else {
			//format date entered by user
			$payment_date = date( 'Y-m-d H:i:s', strtotime( $payment_date ) );
		}

		global $current_user;
		$user_id   = 0;
		$user_name = 'System';
		if ( $current_user && $user_data = get_userdata( $current_user->ID ) ) {
			$user_id   = $current_user->ID;
			$user_name = $user_data->display_name;
		}

		$entry['payment_status'] = $payment_status;
		$entry['payment_amount'] = $payment_amount;
		$entry['payment_date']   = $payment_date;
		$entry['transaction_id'] = $payment_transaction;

		// if payment status does not equal approved/paid or the lead has already been fulfilled, do not continue with fulfillment
		if ( ( $payment_status == 'Approved' || $payment_status == 'Paid' ) && ! $entry['is_fulfilled'] ) {
			$action['id']             = $payment_transaction;
			$action['type']           = 'complete_payment';
			$action['transaction_id'] = $payment_transaction;
			$action['amount']         = $payment_amount;
			$action['entry_id']       = $entry['id'];

			$this->complete_payment( $entry, $action );
			$this->fulfill_order( $entry, $payment_transaction, $payment_amount );
		}
		//update lead, add a note
		GFAPI::update_entry( $entry );
		GFFormsModel::add_note( $entry['id'], $user_id, $user_name, sprintf( esc_html__( 'Payment information was manually updated. Status: %s. Amount: %s. Transaction Id: %s. Date: %s', 'billplzforgravityforms' ), $entry['payment_status'], GFCommon::to_money( $entry['payment_amount'], $entry['currency'] ), $payment_transaction, $entry['payment_date'] ) );
	}

	public function fulfill_order( &$entry, $transaction_id, $amount, $feed = null ) {

		if ( ! $feed ) {
			$feed = $this->get_payment_feed( $entry );
		}

		$form = GFFormsModel::get_form_meta( $entry['form_id'] );
		if ( rgars( $feed, 'meta/delayPost' ) ) {
			$this->log_debug( __METHOD__ . '(): Creating post.' );
			$entry['post_id'] = GFFormsModel::create_post( $form, $entry );
			$this->log_debug( __METHOD__ . '(): Post created.' );
		}

		if ( rgars( $feed, 'meta/delayNotification' ) ) {
			//sending delayed notifications
			$notifications = $this->get_notifications_to_send( $form, $feed );
			GFCommon::send_notifications( $notifications, $form, $entry, true, 'form_submission' );
		}

		do_action( 'gform_billplz_fulfillment', $entry, $feed, $transaction_id, $amount );
		if ( has_filter( 'gform_billplz_fulfillment' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_billplz_fulfillment.' );
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
	public function get_notifications_to_send( $form, $feed ) {
		$notifications_to_send  = array();
		$selected_notifications = rgars( $feed, 'meta/selectedNotifications' );
		if ( is_array( $selected_notifications ) ) {
			// Make sure that the notifications being sent belong to the form submission event, just in case the notification event was changed after the feed was configured.
			foreach ( $form['notifications'] as $notification ) {
				if ( rgar( $notification, 'event' ) != 'form_submission' || ! in_array( $notification['id'], $selected_notifications ) ) {
					continue;
				}
				$notifications_to_send[] = $notification['id'];
			}
		}
		return $notifications_to_send;
	}

	private function is_valid_initial_payment_amount( $entry_id, $amount_paid ) {

		//get amount initially sent to billplz
		$amount_sent = gform_get_meta( $entry_id, 'payment_amount' );
		if ( empty( $amount_sent ) ) {
			return true;
		}

		$epsilon    = 0.00001;
		$is_equal   = abs( floatval( $amount_paid ) - floatval( $amount_sent ) ) < $epsilon;
		$is_greater = floatval( $amount_paid ) > floatval( $amount_sent );

		//initial payment is valid if it is equal to or greater than product/subscription amount
		if ( $is_equal || $is_greater ) {
			return true;
		}

		return false;

	}

	public function billplz_fulfillment( $entry, $billplz_config, $transaction_id, $amount ) {
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
	public function payment_details_editing_disabled( $entry, $action = 'edit' ) {
		if ( ! $this->is_payment_gateway( $entry['id'] ) ) {
			// Entry was not processed by this add-on, don't allow editing.
			return true;
		}
		
		$payment_status = rgar( $entry, 'payment_status' );
		if ( $payment_status == 'Approved' || $payment_status == 'Paid' || rgar( $entry, 'transaction_type' ) == 2 ) {
			// Editing not allowed for this entries transaction type or payment status.
			return true;
		}
		if ( $action == 'edit' && rgpost( 'screen_mode' ) == 'edit' ) {
			// Editing is allowed for this entry.
			return false;
		}
		if ( $action == 'update' && rgpost( 'screen_mode' ) == 'view' && rgpost( 'action' ) == 'update' ) {
			// Updating the payment details for this entry is allowed.
			return false;
		}
		// In all other cases editing is not allowed.
		return true;
	}

	/**
	 * Activate sslverify by default for new installations.
	 *
	 * Transform data when upgrading from legacy billplz.
	 *
	 * @param $previous_version
	 */
	public function upgrade( $previous_version ) {

		if ( empty( $previous_version ) ) {
			$previous_version = get_option( 'gf_billplz_version' );
		}

		if ( empty( $previous_version ) ) {
			update_option( 'gform_billplz_sslverify', true );
		}

		$previous_is_pre_addon_framework = ! empty( $previous_version ) && version_compare( $previous_version, '2.0.dev1', '<' );

		if ( $previous_is_pre_addon_framework ) {

			//copy plugin settings
			//$this->copy_settings(); Not needed because it's not upgradable from too old version

			//copy existing feeds to new table
			//$this->copy_feeds(); Not needed because it's not upgradable from too old version

			//copy existing billplz transactions to new table
			//$this->copy_transactions(); Not needed because it's not upgradable from too old version

			//updating payment_gateway entry meta to 'billplzforgravityforms' from 'billplz'
			$this->update_payment_gateway();

			//updating entry status from 'Approved' to 'Paid'
			$this->update_lead();			
			
		}
	}

	public function uninstall(){
		parent::uninstall();
		delete_option( 'gform_billplz_sslverify' );
	}

	//------ FOR BACKWARDS COMPATIBILITY ----------------------//

	public function update_feed_id( $old_feed_id, $new_feed_id ) {
		global $wpdb;
		$sql = $wpdb->prepare( "UPDATE {$wpdb->prefix}rg_lead_meta SET meta_value=%s WHERE meta_key='billplz_feed_id' AND meta_value=%s", $new_feed_id, $old_feed_id );
		$wpdb->query( $sql );
	}

	public function add_legacy_meta( $new_meta, $old_feed ) {

		$known_meta_keys = array(
								'email', 'mode', 'type', 'style', 'continue_text', 'cancel_url', 'disable_note', 'disable_shipping', 'recurring_amount_field', 'recurring_times',
								'recurring_retry', 'billing_cycle_number', 'billing_cycle_type', 'trial_period_enabled', 'trial_amount', 'trial_period_number', 'trial_period_type', 'delay_post',
								'update_post_action', 'delay_notifications', 'selected_notifications', 'billplz_conditional_enabled', 'billplz_conditional_field_id',
								'billplz_conditional_operator', 'billplz_conditional_value', 'customer_fields',
								);

		foreach ( $old_feed['meta'] as $key => $value ) {
			if ( ! in_array( $key, $known_meta_keys ) ) {
				$new_meta[ $key ] = $value;
			}
		}

		return $new_meta;
	}

	public function update_payment_gateway() {
		global $wpdb;
		$sql = $wpdb->prepare( "UPDATE {$wpdb->prefix}rg_lead_meta SET meta_value=%s WHERE meta_key='payment_gateway' AND meta_value='billplz'", $this->_slug );
		$wpdb->query( $sql );
	}

	public function update_lead() {
		global $wpdb;
		$sql = $wpdb->prepare(
			"UPDATE {$wpdb->prefix}rg_lead
			 SET payment_status='Paid', payment_method='Billplz'
		     WHERE payment_status='Approved'
		     		AND ID IN (
					  	SELECT lead_id FROM {$wpdb->prefix}rg_lead_meta WHERE meta_key='payment_gateway' AND meta_value=%s
				   	)",
			$this->_slug);

		$wpdb->query( $sql );
	}

	public function copy_settings() {
		/*
		//copy plugin settings
		$old_settings = get_option( 'gf_billplz_configured' );
		$new_settings = array( 'gf_billplz_configured' => $old_settings );
		$this->update_plugin_settings( $new_settings );
		*/
	}

	public function copy_feeds() {
		/*
		//get feeds
		$old_feeds = $this->get_old_feeds();

		if ( $old_feeds ) {

			$counter = 1;
			foreach ( $old_feeds as $old_feed ) {
				$feed_name       = 'Feed ' . $counter;
				$form_id         = $old_feed['form_id'];
				$is_active       = $old_feed['is_active'];
				$customer_fields = $old_feed['meta']['customer_fields'];

				$new_meta = array(
					'feedName'                     => $feed_name,
					'billplzAPIKey'                => rgar( $old_feed['meta'], 'email' ),
					'mode'                         => rgar( $old_feed['meta'], 'mode' ),
					'transactionType'              => rgar( $old_feed['meta'], 'type' ),
					'type'                         => rgar( $old_feed['meta'], 'type' ), //For backwards compatibility of the delayed payment feature
					'pageStyle'                    => rgar( $old_feed['meta'], 'style' ),
					'continueText'                 => rgar( $old_feed['meta'], 'continue_text' ),
					'cancelUrl'                    => rgar( $old_feed['meta'], 'cancel_url' ),
					'disableNote'                  => rgar( $old_feed['meta'], 'disable_note' ),
					//'disableShipping'              => rgar( $old_feed['meta'], 'disable_shipping' ),

					'recurringAmount'              => rgar( $old_feed['meta'], 'recurring_amount_field' ) == 'all' ? 'form_total' : rgar( $old_feed['meta'], 'recurring_amount_field' ),
					'recurring_amount_field'       => rgar( $old_feed['meta'], 'recurring_amount_field' ), //For backwards compatibility of the delayed payment feature
					'recurringTimes'               => rgar( $old_feed['meta'], 'recurring_times' ),
					'recurringRetry'               => rgar( $old_feed['meta'], 'recurring_retry' ),
					'paymentAmount'                => 'form_total',
					'billingCycle_length'          => rgar( $old_feed['meta'], 'billing_cycle_number' ),
					'billingCycle_unit'            => $this->convert_interval( rgar( $old_feed['meta'], 'billing_cycle_type' ), 'text' ),

					'trial_enabled'                => rgar( $old_feed['meta'], 'trial_period_enabled' ),
					'trial_product'                => 'enter_amount',
					'trial_amount'                 => rgar( $old_feed['meta'], 'trial_amount' ),
					'trialPeriod_length'           => rgar( $old_feed['meta'], 'trial_period_number' ),
					'trialPeriod_unit'             => $this->convert_interval( rgar( $old_feed['meta'], 'trial_period_type' ), 'text' ),

					'delayPost'                    => rgar( $old_feed['meta'], 'delay_post' ),
					'change_post_status'           => rgar( $old_feed['meta'], 'update_post_action' ) ? '1' : '0',
					'update_post_action'           => rgar( $old_feed['meta'], 'update_post_action' ),

					'delayNotification'            => rgar( $old_feed['meta'], 'delay_notifications' ),
					'selectedNotifications'        => rgar( $old_feed['meta'], 'selected_notifications' ),

					'billingInformation_firstName' => rgar( $customer_fields, 'first_name' ),
					'billingInformation_lastName'  => rgar( $customer_fields, 'last_name' ),
					'billingInformation_email'     => rgar( $customer_fields, 'email' ),
					'billingInformation_address'   => rgar( $customer_fields, 'address1' ),
					'billingInformation_address2'  => rgar( $customer_fields, 'address2' ),
					'billingInformation_city'      => rgar( $customer_fields, 'city' ),
					'billingInformation_state'     => rgar( $customer_fields, 'state' ),
					'billingInformation_zip'       => rgar( $customer_fields, 'zip' ),
					'billingInformation_country'   => rgar( $customer_fields, 'country' ),

				);

				$new_meta = $this->add_legacy_meta( $new_meta, $old_feed );

				//add conditional logic
				$conditional_enabled = rgar( $old_feed['meta'], 'billplz_conditional_enabled' );
				if ( $conditional_enabled ) {
					$new_meta['feed_condition_conditional_logic']        = 1;
					$new_meta['feed_condition_conditional_logic_object'] = array(
						'conditionalLogic' =>
							array(
								'actionType' => 'show',
								'logicType'  => 'all',
								'rules'      => array(
									array(
										'fieldId'  => rgar( $old_feed['meta'], 'billplz_conditional_field_id' ),
										'operator' => rgar( $old_feed['meta'], 'billplz_conditional_operator' ),
										'value'    => rgar( $old_feed['meta'], 'billplz_conditional_value' )
									),
								)
							)
					);
				} else {
					$new_meta['feed_condition_conditional_logic'] = 0;
				}


				$new_feed_id = $this->insert_feed( $form_id, $is_active, $new_meta );
				$this->update_feed_id( $old_feed['id'], $new_feed_id );

				$counter ++;
			}
		}
		*/
	}

	public function copy_transactions() {
		/*
		//copy transactions from the billplz transaction table to the add payment transaction table
		global $wpdb;
		$old_table_name = $this->get_old_transaction_table_name();
		if ( ! $this->table_exists( $old_table_name ) ) {
			return false;
		}
		$this->log_debug( __METHOD__ . '(): Copying old Billplz transactions into new table structure.' );

		$new_table_name = $this->get_new_transaction_table_name();

		$sql = "INSERT INTO {$new_table_name} (lead_id, transaction_type, transaction_id, is_recurring, amount, date_created)
					SELECT entry_id, transaction_type, transaction_id, is_renewal, amount, date_created FROM {$old_table_name}";

		$wpdb->query( $sql );

		$this->log_debug( __METHOD__ . "(): transactions: {$wpdb->rows_affected} rows were added." );
		*/
	}
	
	public function get_old_transaction_table_name() {
		/*
		global $wpdb;
		return $wpdb->prefix . 'rg_billplz_transaction';
		*/
	}

	public function get_new_transaction_table_name() {
		/*
		global $wpdb;
		return $wpdb->prefix . 'gf_addon_payment_transaction';
		*/
	}

	public function get_old_feeds() {
		/*
		global $wpdb;
		$table_name = $wpdb->prefix . 'rg_billplz';

		if ( ! $this->table_exists( $table_name ) ) {
			return false;
		}

		$form_table_name = GFFormsModel::get_form_table_name();
		$sql             = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
					FROM {$table_name} s
					INNER JOIN {$form_table_name} f ON s.form_id = f.id";

		$this->log_debug( __METHOD__ . "(): getting old feeds: {$sql}" );

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$this->log_debug( __METHOD__ . "(): error?: {$wpdb->last_error}" );

		$count = sizeof( $results );

		$this->log_debug( __METHOD__ . "(): count: {$count}" );

		for ( $i = 0; $i < $count; $i ++ ) {
			$results[ $i ]['meta'] = maybe_unserialize( $results[ $i ]['meta'] );
		}

		return $results;
		*/
	}

	//This function kept static for backwards compatibility
	public static function get_config_by_entry( $entry ) {

		$billplz = GFBillplz::get_instance();

		$feed = $billplz->get_payment_feed( $entry );

		if ( empty( $feed ) ) {
			return false;
		}

		return $feed['addon_slug'] == $billplz->_slug ? $feed : false;
	}

	//This function kept static for backwards compatibility
	//This needs to be here until all add-ons are on the framework, otherwise they look for this function
	public static function get_config( $form_id ) {

		$billplz = GFBillplz::get_instance();
		$feed   = $billplz->get_feeds( $form_id );

		//Ignore IPN messages from forms that are no longer configured with the Billplz add-on
		if ( ! $feed ) {
			return false;
		}

		return $feed[0]; //only one feed per form is supported (left for backwards compatibility)
	}

	//------------------------------------------------------


}
