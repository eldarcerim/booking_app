=== Booking App – Community Services ===
Contributors: pontem
Tags: booking, reservations, calendar, reminders, bilingual
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later

Besplatan dvojezični WordPress dodatak otvorenog koda za centre koji pružaju usluge u zajednici: javni kalendar, ručno odobravanje i e-mail podsjetnici. Free and open-source bilingual WordPress plugin for community service centres: availability, manual approval and email reminders.

== Instalacija / Installation ==

1. Instalirajte i aktivirajte ZIP kroz Dodaci > Dodaj novi > Prenesi dodatak.
2. Otvorite MOST rezervacije > Postavke i unesite radno vrijeme.
3. Bosanski kalendar: [booking_app lang="bs"]
4. English calendar: [booking_app lang="en"]
5. Bez lang atributa dodatak koristi jezik WordPress stranice. Without lang, the plugin follows the WordPress page locale. The legacy [most_booking] shortcode remains supported.

== Funkcije / Features ==

* Javni pregled dostupnih i zauzetih termina.
* Ručno potvrđivanje i odbijanje zahtjeva.
* Pojedinačne ili sedmične administratorske rezervacije do odabranog datuma.
* Opcionalni e-mail korisnika i podsjetnik na dan potvrđenog termina.
* Bosanski i engleski javni interfejs i korisničke e-mail poruke.
* Public availability calendar with manual approval.
* Single or recurring weekly bookings created by an administrator.
* Optional user email and same-day reminders for confirmed appointments.
* Bosnian and English public UI and user emails.

== E-mail i cron / Email and cron ==

Slanje koristi WordPress wp_mail. Za pouzdanu isporuku konfigurirajte SMTP dodatak. Podsjetnici koriste WP-Cron; za precizno vrijeme preporučuje se pravi serverski cron koji redovno poziva wp-cron.php.

Email is sent through WordPress wp_mail. Configure an SMTP plugin for reliable delivery. Reminders use WP-Cron; for accurate timing, configure a real server cron job that calls wp-cron.php regularly.

Detaljne upute nalaze se u GitHub datoteci README.md.

Deaktivacija dodatka ne briše rezervacije. Deactivation does not delete bookings.
