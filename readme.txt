=== Billplz for GravityForms ===
Contributors: wanzulnet
Tags: billplz,paymentgateway,fpx,malaysia
Tested up to: 5.0.3
Stable tag: 3.8.0
Requires at least: 4.6
License: GPL-3.0-or-later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 5.6

Accept Internet Banking Payment by using Billplz.

== Description ==

Accept all participating Internet Banking, e-Wallet, Credit Cards and more with Gravity Forms! 

= Why choose Billplz? =

Billplz has no setup and monthly fees. Only pay as per payment received at RM1.50. You can also opt for a Preferred Plan @ RM1,500/month and save up to RM1.00 per payment received.

= How to register? =

[Apply now](https://www.billplz.com/enterprise/signup) and submit the completed form to be registered and verified. The verification will be done automatically in 3 working days.

= Settlement policy =

Total daily collection (minimum RM0.01) shall be deposited automatically into the authorized bank account the next day (UTC+08:00 Kuala Lumpur) excluding Friday, Saturday, Sunday and Federal holidays. 

== Screenshots ==
* Screenshot 1: Setting for API Secret Key, Collection ID and X Signature Key.
* Screenshot 2: Setting for Payment Amount field and Billplz field.
* Screenshot 3: Sample form integrated with Billplz.
* Screenshot 4: Sample Billplz Payment Page.
* Screenshot 5: Sample return response.
* Screenshot 6: Sample entry on administration side.

== Changelog ==

= 3.8.0 =
* Based on PayPal Standard Addon 3.1
* Support for merge tag for Bill Description.
* This update may break your site. Only recommended for new installation.

== Installation ==
1. Install & Activate.
2. Forms >> Settings >> Billplz >> Add New.
3. Insert your API Key, Collection ID and X Signature Key.
4. Update Settings.

== Frequently Asked Questions ==

= How to include Bill ID on payment notification? =

Set the tag {entry:transaction_id} at the event Payment Completion notification. You may refer to [GravityForms Merge Tag](https://docs.gravityforms.com/merge-tags/#entry-data) for more information.

= Where can I get API Secret & X Signature Key ? =

You can the API Secret Key at your Billplz Account Settings. [Get it here](https://www.billplz.com/enterprise/setting)

= Where can I get Collection ID? =

You can the Collection ID at your Billplz >> Billing. [Get it here](https://www.billplz.com/enterprise/billing)

= Troubleshooting =

Known issues: Name with (Full) option will return null.

== Links ==
Join our Facebook developer community at [Billplz Dev Jam](https://fb.com/groups/billplzdevjam/).

Sign up to [Billplz Staging](https://billplz-staging.herokuapp.com) for free unlimited testing.

== Upgrade Notice ==

This update may break existing setup. Make sure you have a proper backup.
