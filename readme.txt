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

Install this plugin to accept payment using Billplz.

== Screenshots ==
* Screenshot 1
* Screenshot 2

== Changelog ==

= 3.8.0 =
* Based on PayPal Standard Addon 3.1
* Support for merge tag for Bill Description.
* This update may break your site. Only recommended for new installation.

= 3.7.6 =

* Still based on PayPal Standard Addon 2.8
* Fix syntax error involving reference_1 and reference_2
* Removed dependency on PHP GuzzleHTTP

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
[Sign Up](http://billplz.com/join/lz7pmrxa45tiihvqdydxqq/) for Billplz account to accept payment using Billplz now!

== Upgrade Notice ==

This update may break existing setup. Make sure you have a proper backup.
