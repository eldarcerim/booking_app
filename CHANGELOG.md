# Changelog

## 1.5.0

- Added Bosnian and English public interfaces through the `lang` shortcode attribute.
- Added the generic `[booking_app]` shortcode while retaining `[most_booking]` for compatibility.
- Added Bosnian and English customer confirmation, status and reminder emails.
- Added a customer-message language selector to admin-created bookings.
- Preserved existing bookings through an automatic database schema upgrade.
- Added production SMTP and real server cron setup guidance.

## 1.4.0

- Added single and recurring weekly bookings created directly by an administrator.
- Added optional same-day reminders for customers and MOST Centre.
- Added reminder scheduling, retry handling and per-booking reminder controls.
