=== Billplz for GravityForms ===
Contributors: wanzulnet
Tags: billplz,wanzul,paymentgateway,malaysia
Tested up to: 4.5
Stable tag: 3.1
Donate link: https://www.billplz.com/form/sw2co7ig8/
Requires at least: 4.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept Internet Banking Payment by using Billplz. 

== Description ==

Install this plugin to accept payment using Billplz (Maybank2u, CIMB Clicks, Bank Islam, FPX). 

Consider a donation to developer : Maybank 15738-01156-07 (Wan Zulkarnain Bin Wan Hasbullah)
[Make Donation](https://www.billplz.com/form/sw2co7ig8/) to developer now.

== Upgrade Notice == 
* None

== Screenshots ==
* Will available soon

== Changelog ==

= 3.2 =
* Plugin are re-build from scratch

= 3.1 =
* BUG: It is double checked for transaction at Billplz using ID
* Going Open Source

= 3.0 =
* NEW: Billplz API V3 ready
* NEW: User are required to set Bills Description
* NEW: User can define their own Cancel URL
* NEW: Option to delay notification until Customer paid their bills
* NEW: Added Reference 1 Optional Value
* NEW: Staging Mode. Please refer to Facebook Group Billplz Dev Jam
* Improvement: No garbage file will created. No more SQLite

= 1.7 =
* Improvement: Auto-Purge SQLite

= 1.6 =
* Bugfix: Paid status are corrected now

= 1.5 =
* Improved handling Mobile Number
* Garbage file is now stored by using SQLite

= 1.4 =
* Added Cron Jobs instruction to automatically clear Garbage folder

= 1.3 =
* Added Updates Capability

= 1.0 =
1. Initial Release

== Installation ==
1. Make sure you have ionCube Loader version 4.7.5 or higher
2. Set Up API Key & Collection ID
3. Set Cron Jobs that runs once a month >> php -q /home/<yourusername>/public_html/wp-content/gravityformsbillplz/garbage/cron.php
4. All Set!

== Frequently Asked Questions ==

= Where can I get Collection ID? =

You can the Collection ID at your Billplz Billing. Login to http://www.billplz.com


= Troubleshooting =

If you get infinite loop or JSON-like error:
1. Ensure the correct API Key and Collection ID has been set up
2. Contact us at sales@wanzul-hosting.com

== Links ==
[Follow us on Facebook](http://www.facebook.com/billplz) for the latest update and information about this plugin.

== Updates ==
Updates will provided through WordPress Update!

== Thanks ==
Thanks to all donators! Thank You so much!
