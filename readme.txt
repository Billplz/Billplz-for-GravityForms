=== Billplz for GravityForms ===
Contributors: wanzulnet
Tags: billplz,paymentgateway,fpx,malaysia
Tested up to: 4.9.6
Stable tag: 3.7.5
Donate link: http://billplz.com/join/lz7pmrxa45tiihvqdydxqq/
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

= 3.7.5 =
* Still based on PayPal Standard Addon 2.8
* Fix issue when payer doesn't pay the form

= 3.7.4 =
* Still based on PayPal Standard Addon 2.8
* Fix issue for 3.7.3 when other Billplz plugin activated in a time.
* The datetime will strictly depends on Transaction Time

== Installation ==
1. Install & Activate
2. Forms >> Settings >> Billplz >> Add New
3. Insert your API Key, X Signature Key
4. For "Billplz Field" you need to set either Email or Mobile Phone Number or Both.
5. Update Settings

== Frequently Asked Questions ==

= How to include Bill ID on payment notification? =

Set the tag {entry:transaction_id} at the event Payment Completion notification. You may refer to [GravityForms Merge Tag](https://docs.gravityforms.com/merge-tags/#entry-data) for more information.

= Where can I get API Secret Key? =

You can the API Secret Key at your Billplz Account Settings. [Get it here](https://www.billplz.com/enterprise/setting)

= Where can I get X Signature Key? =

You can the X Signature Key at your Billplz Account Settings. [Get it here](https://www.billplz.com/enterprise/setting)

= Troubleshooting =

Please choose parameter name as First and not Full if the page is not redirected to Billplz payment page.

== Links ==
[Sign Up](http://billplz.com/join/lz7pmrxa45tiihvqdydxqq/) for Billplz account to accept payment using Billplz now!

== Upgrade Notice ==
* None
