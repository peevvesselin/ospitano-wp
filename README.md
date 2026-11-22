# OSPITANO Booking for WordPress

GPL-2.0-or-later. Thin connector that embeds the OSPITANO booking form (`<ospitano-booking>`) on a WordPress site.
All booking logic lives in the OSPITANO platform; this plugin holds no prices, availability or guest data.

- Block "OSPITANO Booking" and shortcode `[ospitano_booking hotel="code" lang="bg"]`
- Settings → OSPITANO Booking: hotel code, language (auto follows WPML/Polylang/site locale), button colour, platform URL
- Text domain `ospitano-booking`, WordPress.org-style `readme.txt`

Build a release zip: `git archive --prefix=ospitano-booking/ -o ospitano-booking.zip HEAD`.
