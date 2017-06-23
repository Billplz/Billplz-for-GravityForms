<?php

if (!defined('ABSPATH')) {
    exit;
}

class BillplzRequiredSettings {

    public static function get_required_settings_billplz_gf() {

        $fields = array(
            array(
                'name' => 'api_key',
                'label' => esc_html__('API Secret Key ', 'gravityformsbillplz'),
                'type' => 'text',
                'class' => 'medium',
                'required' => true,
                'tooltip' => '<h6>' . esc_html__('Billplz API Secret Key', 'gravityformsbillplz') . '</h6>' . esc_html__('It can be from Production or Staging.', 'gravityformsbillplz')
            ),
            array(
                'name' => 'x_signature_key',
                'label' => esc_html__('X Signature Key ', 'gravityformsbillplz'),
                'type' => 'text',
                'class' => 'medium',
                'required' => true,
                'tooltip' => '<h6>' . esc_html__('Billplz X Signature Key', 'gravityformsbillplz') . '</h6>' . esc_html__('Get it from your Billpz Account Settings.', 'gravityformsbillplz')
            ),
            array(
                'name' => 'collection_id',
                'label' => esc_html__('Collection ID ', 'gravityformsbillplz'),
                'type' => 'text',
                'class' => 'medium',
                'required' => false,
                'tooltip' => '<h6>' . esc_html__('Billplz Collection ID', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter your choosen specific Collection ID. Leave blank if unsure', 'gravityformsbillplz')
            ),
            array(
                'label' => esc_html__('Fixed Bill Description', 'gravityformsbillplz'),
                'type' => 'textarea',
                'name' => 'bill_description',
                'tooltip' => '<h6>' . esc_html__('Billplz Bills Description', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter your fixed description here. It will add concatenated with variable description.', 'gravityformsbillplz'),
                'class' => 'medium merge-tag-support mt-position-right',
                'required' => false,
            ),
            array(
                'label' => esc_html__('Reference 1 Label', 'gravityformsbillplz'),
                'type' => 'text',
                'name' => 'reference_1_label',
                'tooltip' => '<h6>' . esc_html__('Billplz Reference 1 Label', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter your desired Reference 1 Label here.', 'gravityformsbillplz'),
                'class' => 'medium',
                'required' => false,
            ),
            array(
                'label' => esc_html__('Reference 2 Label', 'gravityformsbillplz'),
                'type' => 'text',
                'name' => 'reference_2_label',
                'tooltip' => '<h6>' . esc_html__('Billplz Reference 2 Label', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter your desired Reference 2 Label here.', 'gravityformsbillplz'),
                'class' => 'medium',
                'required' => false,
            ),
            array(
                'label' => esc_html__('Reference 1', 'gravityformsbillplz'),
                'type' => 'text',
                'name' => 'reference_1',
                'tooltip' => '<h6>' . esc_html__('Billplz Reference Content', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter your desired Reference 1 content here. It will add concatenated with variable reference 1', 'gravityformsbillplz'),
                'class' => 'medium',
                'required' => false,
            ),
            array(
                'label' => esc_html__('Reference 2', 'gravityformsbillplz'),
                'type' => 'text',
                'name' => 'reference_2',
                'tooltip' => '<h6>' . esc_html__('Billplz Reference Content', 'gravityformsbillplz') . '</h6>' . esc_html__('Enter your desired Reference 2 content here. It will add concatenated with variable reference 2', 'gravityformsbillplz'),
                'class' => 'medium',
                'required' => false,
            ),
            array(
                'label' => esc_html__('Payment Reminder', 'gravityformsbillplz'),
                'type' => 'select',
                'name' => 'payment_reminder',
                'tooltip' => '<h6>' . esc_html__('Billplz Payment Reminder', 'gravityformsbillplz') . '</h6>' . esc_html__('Send Bill payment link to customer on bills creation. Recommended: No Reminder', 'gravityformsbillplz'),
                'choices' => array(
                    array(
                        'label' => 'No Reminder',
                        'value' => '0'
                    ),
                    array(
                        'label' => 'Email Only (FREE)',
                        'value' => '1'
                    ),
                    array(
                        'label' => 'SMS Only (RM0.15)',
                        'value' => '2'
                    ),
                    array(
                        'label' => 'Both (RM0.15)',
                        'value' => '3'
                    )
                )
            ),
        );

        return $fields;
    }

    public static function get_required_billing_info_billplz_gf($billing_info) {
        $billing_fields = $billing_info['field_map'];

        $add_name = true;
        $add_reference_1 = true;
        $add_reference_2 = true;
        $add_bills_desc = true;
        $add_phone = true;
        $add_email = true; //for better arrangement

        $remove_address = false;
        $remove_address2 = false;
        $remove_city = false;
        $remove_state = false;
        $remove_zip = false;
        $remove_country = false;
        $remove_email = false; //for better arrangement

        foreach ($billing_fields as $mapping) {
            //add first/last name if it does not already exist in billing fields
            if ($mapping['name'] == 'name') {
                $add_name = false;
            } else if ($mapping['name'] == 'reference_1') {
                $add_reference_1 = false;
            } else if ($mapping['name'] == 'reference_2') {
                $add_reference_2 = false;
            } else if ($mapping['name'] == 'bill_desc') {
                $add_bills_desc = false;
            } else if ($mapping['name'] == 'bill_mobile') {
                $add_phone = false;
                //remove non-related option
            } else if ($mapping['name'] == 'address') {
                $remove_address = true;
            } else if ($mapping['name'] == 'address2') {
                $remove_address2 = true;
            } else if ($mapping['name'] == 'city') {
                $remove_city = true;
            } else if ($mapping['name'] == 'state') {
                $remove_state = true;
            } else if ($mapping['name'] == 'zip') {
                $remove_zip = true;
            } else if ($mapping['name'] == 'country') {
                $remove_country = true;
            } else if ($mapping['name'] == 'email') {
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
        if ($add_reference_1) {
            array_unshift($billing_info['field_map'], array('name' => 'reference_1', 'label' => esc_html__('Reference 1', 'gravityformsbillplz'), 'required' => false));
        }
        if ($add_bills_desc) {
            array_unshift($billing_info['field_map'], array('name' => 'bill_desc', 'label' => esc_html__('Bill Description', 'gravityformsbillplz'), 'required' => false));
        }
        if ($add_phone) {
            array_unshift($billing_info['field_map'], array('name' => 'bill_mobile', 'label' => esc_html__('Mobile Phone Number', 'gravityformsbillplz'), 'required' => false));
        }
        if ($add_email) {
            array_unshift($billing_info['field_map'], array('name' => 'email', 'label' => esc_html__('Email', 'gravityformsbillplz'), 'required' => false));
        }
        if ($add_name) {
            array_unshift($billing_info['field_map'], array('name' => 'name', 'label' => esc_html__('Name', 'gravityformsbillplz'), 'required' => true));
        }

        return $billing_info;
    }

}
