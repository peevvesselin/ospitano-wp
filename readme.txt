=== OSPITANO Booking ===
Contributors: ospitano
Tags: hotel, booking, reservations, booking engine, accommodation
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Online booking form for hotels on the OSPITANO platform: real-time availability, prices, extras and secure card payments.

== Description ==

Add your hotel's booking form to any page with the "OSPITANO Booking" block or the `[ospitano_booking]` shortcode.

* Real-time availability and prices from your OSPITANO account
* Extras (breakfast, transfer, parking), promo codes, tourist tax
* Secure card payments with Stripe, paid straight to your hotel
* Synchronised with Booking.com and Airbnb calendars
* Bulgarian, English, Romanian, German and Russian; follows WPML and Polylang

This plugin is a connector. Availability, bookings and payments are processed by the OSPITANO platform
(https://ospitano.eu), which requires an active subscription. The form loads its script from the platform
and sends the guest's booking details to it.

== Installation ==

1. Install and activate the plugin.
2. Go to Settings → OSPITANO Booking and enter your hotel code.
3. Add the "OSPITANO Booking" block, or the shortcode `[ospitano_booking]`, to your booking page.

Shortcode options: `[ospitano_booking hotel="your-code" lang="en"]`.

== Changelog ==

= 1.4.1 =
* Room pages get translations: once the platform has a room's name in another language, a translated room page is created and linked in Polylang.

= 1.0.0 =
* First release.
