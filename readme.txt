=== Billplz for GravityForms ===
Contributors: wanzulnet
Tags: billplz
Tested up to: 5.7
Stable tag: 3.9.1
Requires at least: 4.6
License: GPL-3.0-or-later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 7.0

Accept payment by using Billplz.

== Description ==

Install this plugin to accept payment using Billplz.

== Screenshots ==
* Screenshot 1: Setting for API Secret Key, Collection ID and X Signature Key.
* Screenshot 2: Setting for Payment Amount field and Billplz field.
* Screenshot 3: Sample form integrated with Billplz.
* Screenshot 4: Sample Billplz Payment Page.
* Screenshot 5: Sample return response.
* Screenshot 6: Sample entry on administration side.

== Changelog ==

= 3.9.1 =
* Based on PayPal Standard Addon 3.5

= 3.9.0 =
* Based on PayPal Standard Addon 3.3
* NEW: Support for Enable Extra Payment Completion Information

= 3.8.2 =
* Fix payment status not updated when containing single quotes.

= 3.8.1 =
* Ensure bill id matches with entry before processing.

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

You can get the API Secret Key at your Billplz Account Settings.

= Where can I get Collection ID? =

You can get the Collection ID at your Billplz >> Billing.

= Troubleshooting =

Known issues: Name with (Full) option will return null.

== Links ==
Join our Facebook developer community at [Billplz Dev Jam](https://fb.com/groups/billplzdevjam/).

== Upgrade Notice ==

- None
