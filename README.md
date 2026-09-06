# Booking App – Community Services

A free, open-source bilingual WordPress booking plugin created for the MOST Social Pedagogy Centre and shared with centres and organisations that provide community-based services. It provides a public availability calendar, manual approval, recurring admin bookings and optional same-day email reminders.

Besplatan dvojezični WordPress dodatak otvorenog koda izrađen za Sociopedagoški centar MOST i podijeljen sa centrima i organizacijama koje pružaju usluge u zajednici. Sadrži javni kalendar dostupnosti, ručno odobravanje, ponavljajuće administratorske rezervacije i opcionalne e-mail podsjetnike na dan termina.

**[Preuzmi instalacijski ZIP / Download the installable ZIP](release/booking-app-1.5.0.zip)**

## Bosanski

### Besplatno korištenje u zajednici

Ovaj projekat je javno dostupan pod licencom GPL-2.0-or-later. Centri, udruženja, ustanove i druge organizacije koje pružaju savjetodavne, obrazovne, socijalne, terapeutske ili srodne usluge u zajednici mogu ga besplatno koristiti, kopirati, prilagođavati i dalje dijeliti u skladu s licencom.

Početne usluge, naziv i e-mail poruke prilagođeni su Centru MOST. Drugi korisnici mogu promijeniti kontaktne podatke i raspored u administraciji, a naziv organizacije, ponuđene usluge i tekstove po potrebi prilagoditi u izvornom kodu. SMTP podaci, lozinke i drugi tajni podaci nisu dio projekta i moraju se zasebno podesiti na svakoj instalaciji.

### Glavne mogućnosti

- javni kalendar slobodnih i zauzetih termina;
- zahtjev odmah privremeno blokira odabrani termin;
- administrator potvrđuje, odbija ili otkazuje rezervaciju;
- usluge „Senzorna soba“ i „Savjetovanje“;
- pojedinačna rezervacija ili sedmično ponavljanje do odabranog datuma;
- zauzeti termini u seriji automatski se preskaču;
- opcionalni e-mail korisnika;
- potvrde i podsjetnici na bosanskom ili engleskom;
- zaseban podsjetnik korisniku i Centru MOST;
- javni prikaz ne otkriva podatke rezervisanih korisnika.

### Instalacija

1. Preuzmite instalacijski ZIP putem linka na vrhu ove stranice ili iz foldera `release`.
2. U WordPressu otvorite **Dodaci → Dodaj novi → Prenesi dodatak**.
3. Instalirajte ZIP i aktivirajte dodatak.
4. Otvorite **MOST rezervacije → Postavke** i podesite raspored, trajanje, adresu, telefon i vrijeme podsjetnika.
5. Dodajte shortcode na željenu stranicu:

```text
[booking_app lang="bs"]
```

Za englesku stranicu koristite:

```text
[booking_app lang="en"]
```

Ako izostavite `lang`, dodatak pokušava koristiti jezik trenutne WordPress stranice. Raniji `[most_booking]` shortcode ostaje podržan radi kompatibilnosti.

### Administratorske i sedmične rezervacije

Otvorite **MOST rezervacije → Dodaj rezervaciju**. Odaberite početni datum i vrijeme. Za ponavljanje izaberite **Svake sedmice** i unesite posljednji datum. Dan početnog datuma određuje dan ponavljanja; početak u srijedu znači svake srijede do navedenog datuma. Kreirani termini su odmah potvrđeni.

### Obavezno za pouzdane e-mailove

Dodatak šalje poruke standardnom WordPress funkcijom `wp_mail()`. To ne garantuje isporuku. Na produkcijskom sajtu uradite sljedeće:

1. Instalirajte i konfigurirajte pouzdan SMTP dodatak.
2. Koristite SMTP podatke koje je dao hosting ili vaš e-mail pružalac: server, port, enkripciju, korisničko ime i lozinku.
3. Kao adresu pošiljaoca postavite adresu s vaše domene, npr. `most@pontem.org.ba`, ako je ta adresa dozvoljena SMTP nalogom.
4. U SMTP dodatku pošaljite probni e-mail i provjerite Inbox i Spam/Junk.
5. Provjerite SPF, DKIM i po mogućnosti DMARC zapise domene kod hostinga.

Nikada ne unosite SMTP lozinku u ovaj repozitorij, `README`, PHP ili JavaScript datoteku.

### Obavezno za precizne podsjetnike

Podsjetnici koriste WordPress WP-Cron. Zadani WP-Cron se pokreće tek kada neko posjeti stranicu, zbog čega podsjetnik može kasniti na sajtu sa malo posjeta. Za preciznije slanje postavite pravi serverski cron u cPanelu.

Preporučeni raspored je svakih pet minuta:

```cron
*/5 * * * *
```

Najbolja varijanta je PHP CLI naredba. Tačnu putanju do PHP-a i WordPress instalacije treba uzeti iz cPanela/hostinga:

```sh
/PUTANJA/DO/php -q /PUTANJA/DO/public_html/wp-cron.php >/dev/null 2>&1
```

Ako hosting ne dozvoljava PHP CLI, moguća HTTP varijanta je:

```sh
wget -q -O - "https://centar-most.ba/wp-cron.php?doing_wp_cron" >/dev/null 2>&1
```

Tek nakon što potvrdite da serverski cron radi, u `wp-config.php` možete dodati:

```php
define('DISABLE_WP_CRON', true);
```

Nemojte isključiti ugrađeni WP-Cron prije nego što je serverski cron provjeren. U suprotnom se podsjetnici i drugi WordPress zadaci neće izvršavati.

### Kako podsjetnici rade

- podsjetnik se zakazuje samo za potvrđenu rezervaciju;
- korisnički podsjetnik se ne šalje ako e-mail nije unesen;
- administrator može posebno uključiti ili isključiti podsjetnik korisniku i Centru;
- vrijeme podsjetnika podešava se u **MOST rezervacije → Postavke**;
- ako je podešeno vrijeme poslije samog termina, dodatak pokušava zakazati podsjetnik jedan sat prije termina;
- neuspjelo slanje pokušava se ponovo najviše dva puta, u razmacima od 15 minuta, dok termin nije počeo.

### Test prije produkcije

1. Provjerite da je WordPress vremenska zona postavljena na **Europe/Sarajevo**.
2. Napravite probnu rezervaciju s vlastitom e-mail adresom.
3. Potvrdite zahtjev i uključite korisnički podsjetnik.
4. Provjerite prijem početne poruke, potvrde i podsjetnika.
5. Nakon testa otkažite ili uklonite probni termin kroz administraciju.

## English

### Free community use

This public project is licensed under GPL-2.0-or-later. Community centres, associations, institutions and other organisations providing counselling, education, social, therapeutic or related community services may use, copy, adapt and redistribute it free of charge in accordance with the licence.

The default services, organisation name and email copy are tailored to MOST Centre. Other organisations can change contact details and schedules in WordPress and adapt the organisation name, service options and copy in the source code. SMTP credentials and other secrets are never included and must be configured separately for each installation.

### Features

- public free/busy calendar with private customer data;
- manual approval, rejection and cancellation;
- Sensory Room and Counselling services;
- one-off or weekly admin bookings up to a chosen end date;
- occupied dates in a recurring series are skipped safely;
- optional customer email;
- Bosnian or English customer-facing UI and emails;
- separate same-day reminders for the customer and MOST Centre.

### Installation and shortcodes

Download the installable ZIP using the link at the top of this page, then install it through **Plugins → Add New → Upload Plugin**, activate it and configure the plugin settings.

```text
[booking_app lang="en"]
```

Use `[booking_app lang="bs"]` for Bosnian. Without `lang`, the current WordPress locale is used. The earlier `[most_booking]` shortcode remains supported for compatibility.

### Email delivery and cron requirements

The plugin sends messages with WordPress `wp_mail()`. For production, configure an SMTP plugin with credentials supplied by your hosting provider or email service. Send a test message and verify SPF, DKIM and preferably DMARC. Never commit SMTP passwords or other secrets to GitHub.

Reminders use WP-Cron. Low-traffic sites can execute WP-Cron late, so a real cPanel/server cron running every five minutes is recommended. Prefer PHP CLI with the exact paths supplied by your host:

```cron
*/5 * * * * /PATH/TO/php -q /PATH/TO/public_html/wp-cron.php >/dev/null 2>&1
```

Only set `define('DISABLE_WP_CRON', true);` after the real cron job has been tested successfully.

### Privacy

Bookings contain personal information. Restrict WordPress administrator access, keep backups protected, use HTTPS and maintain an appropriate privacy policy and retention process.

## Requirements

- WordPress 6.2 or newer
- PHP 7.4 or newer

## License

GPL-2.0-or-later.
