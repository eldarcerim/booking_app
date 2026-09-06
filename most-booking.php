<?php
/**
 * Plugin Name: Booking App – Community Services
 * Description: Besplatan dvojezični kalendar i sistem ručnog odobravanja termina za centre koji pružaju usluge u zajednici.
 * Version: 1.5.0
 * Author: PONTEM – Sociopedagoški centar MOST
 * Text Domain: most-booking
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

final class MOST_Booking_Plugin {
    const VERSION = '1.5.0';
    const DB_VERSION = '1.3.0';
    const OPTION = 'most_booking_settings';
    const NONCE_ACTION = 'most_booking_public';
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', array($this, 'maybe_upgrade'));
        add_shortcode('most_booking', array($this, 'shortcode'));
        add_shortcode('booking_app', array($this, 'shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('wp_ajax_most_month_availability', array($this, 'ajax_month_availability'));
        add_action('wp_ajax_nopriv_most_month_availability', array($this, 'ajax_month_availability'));
        add_action('wp_ajax_most_submit_booking', array($this, 'ajax_submit_booking'));
        add_action('wp_ajax_nopriv_most_submit_booking', array($this, 'ajax_submit_booking'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_most_booking_action', array($this, 'admin_booking_action'));
        add_action('admin_post_most_booking_settings', array($this, 'save_settings'));
        add_action('admin_post_most_booking_block', array($this, 'admin_block_slot'));
        add_action('admin_post_most_booking_admin_create', array($this, 'admin_create_booking'));
        add_action('admin_post_most_booking_reminders', array($this, 'admin_save_reminders'));
        add_action('most_booking_send_reminder', array($this, 'send_booking_reminder'), 10, 1);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_links'));
    }

    public static function activate() {
        self::install_schema();
        if (!get_option(self::OPTION)) {
            add_option(self::OPTION, self::defaults());
        }
    }

    public function maybe_upgrade() {
        if (get_option('most_booking_db_version') !== self::DB_VERSION) {
            self::install_schema();
        }
    }

    private static function install_schema() {
        global $wpdb;
        $table = $wpdb->prefix . 'most_bookings';
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            entry_type varchar(20) NOT NULL DEFAULT 'booking',
            service varchar(30) NOT NULL DEFAULT 'sensory_room',
            language varchar(5) NOT NULL DEFAULT 'bs',
            first_name varchar(100) NOT NULL DEFAULT '',
            last_name varchar(100) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            phone varchar(60) NOT NULL DEFAULT '',
            reason text NOT NULL,
            admin_note text NOT NULL,
            recurrence_group varchar(64) NOT NULL DEFAULT '',
            reminder_user tinyint(1) unsigned NOT NULL DEFAULT 0,
            reminder_admin tinyint(1) unsigned NOT NULL DEFAULT 0,
            reminder_sent_user tinyint(1) unsigned NOT NULL DEFAULT 0,
            reminder_sent_admin tinyint(1) unsigned NOT NULL DEFAULT 0,
            reminder_attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY slot_lookup (booking_date,start_time,status),
            KEY created_at (created_at)
        ) {$charset};");
        update_option('most_booking_db_version', self::DB_VERSION);
    }

    private static function defaults() {
        $schedule = array();
        for ($day = 1; $day <= 7; $day++) {
            $schedule[$day] = array(
                'enabled' => $day >= 3,
                'start' => '09:00',
                'end' => '20:00',
            );
        }
        return array(
            'notification_email' => 'most@pontem.org.ba',
            'contact_address' => 'Maršala Tita br. 22, Zenica',
            'contact_phone' => '062 283 466',
            'duration' => 60,
            'lead_hours' => 2,
            'days_ahead' => 60,
            'reminder_time' => '08:00',
            'default_admin_reminder' => 1,
            'schedule' => $schedule,
        );
    }

    private function settings() {
        return wp_parse_args(get_option(self::OPTION, array()), self::defaults());
    }

    private function language($requested = 'auto') {
        $requested = strtolower(sanitize_key($requested));
        if (in_array($requested, array('bs', 'en'), true)) {
            return $requested;
        }
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        return strpos(strtolower((string) $locale), 'en') === 0 ? 'en' : 'bs';
    }

    private function front_strings($lang) {
        if ($lang === 'en') {
            return array(
                'months' => array('January','February','March','April','May','June','July','August','September','October','November','December'),
                'weekdays' => array('Mon','Tue','Wed','Thu','Fri','Sat','Sun'),
                'day_names' => array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
                'month_names' => array('January','February','March','April','May','June','July','August','September','October','November','December'),
                'contact_aria' => 'Contact information', 'address' => 'Address', 'phone' => 'Phone',
                'book_appointment' => 'Book an appointment', 'previous_month' => 'Previous month', 'next_month' => 'Next month',
                'calendar_aria' => 'Available appointment calendar', 'available' => 'Available', 'partially_booked' => 'Partly booked', 'unavailable' => 'Unavailable',
                'after_date' => 'Available appointments will appear after you select a date.', 'slots' => 'Appointments',
                'booking_details' => 'Booking details', 'consultation_type' => 'Consultation type', 'choose' => 'Choose',
                'sensory_room' => 'Sensory room', 'counselling' => 'Counselling', 'first_name' => 'First name', 'last_name' => 'Last name',
                'email_optional' => 'Email (optional)', 'mobile' => 'Mobile phone', 'reason' => 'Reason for the appointment',
                'reminder_user' => 'I would like to receive an email reminder on the day of the appointment. Enter an email address above to enable this option.',
                'consent' => 'I agree that the submitted data may be used to process my booking request', 'privacy_intro' => 'in accordance with the', 'privacy_policy' => 'privacy policy',
                'honeypot' => 'Do not fill in this field', 'change_appointment' => 'Change appointment', 'send_request' => 'Send request',
                'success_title' => 'Your request has been sent', 'success_text' => 'The appointment is being held temporarily. If you entered an email address, you will receive the final confirmation by email.',
                'new_request' => 'Send another request', 'loading' => 'Loading appointments…', 'generic_error' => 'Error',
                'calendar_error' => 'The calendar cannot be loaded right now. Please try again.', 'busy' => 'Booked', 'free' => 'available',
                'available_count' => 'available appointments', 'send_error' => 'The request was not sent.', 'sending' => 'Sending…',
                'invalid_month' => 'Invalid month.', 'too_many' => 'Too many requests were sent. Please try again in ten minutes.',
                'rejected' => 'The request was not accepted.', 'required' => 'Please complete all required fields.',
                'slot_gone' => 'The selected appointment is no longer available. Please choose another one.',
                'processing' => 'The appointment is currently being processed. Please try again.',
                'just_booked' => 'The selected appointment has just been booked. Please choose another one.',
                'save_failed' => 'The request could not be saved. Please try again.', 'request_sent' => 'Your request has been sent.',
            );
        }
        return array(
            'months' => array('Januar','Februar','Mart','April','Maj','Juni','Juli','August','Septembar','Oktobar','Novembar','Decembar'),
            'weekdays' => array('Pon','Uto','Sri','Čet','Pet','Sub','Ned'),
            'day_names' => array('nedjelja','ponedjeljak','utorak','srijeda','četvrtak','petak','subota'),
            'month_names' => array('januar','februar','mart','april','maj','juni','juli','august','septembar','oktobar','novembar','decembar'),
            'contact_aria' => 'Kontakt informacije', 'address' => 'Adresa', 'phone' => 'Telefon',
            'book_appointment' => 'Rezerviši termin', 'previous_month' => 'Prethodni mjesec', 'next_month' => 'Naredni mjesec',
            'calendar_aria' => 'Kalendar dostupnih termina', 'available' => 'Dostupno', 'partially_booked' => 'Djelimično zauzeto', 'unavailable' => 'Nije dostupno',
            'after_date' => 'Nakon izbora datuma prikazat će se slobodni termini.', 'slots' => 'Termini',
            'booking_details' => 'Podaci za rezervaciju', 'consultation_type' => 'Vrsta konsultacije', 'choose' => 'Odaberite',
            'sensory_room' => 'Senzorna soba', 'counselling' => 'Savjetovanje', 'first_name' => 'Ime', 'last_name' => 'Prezime',
            'email_optional' => 'E-mail (nije obavezno)', 'mobile' => 'Mobitel', 'reason' => 'Razlog dolaska na tretman',
            'reminder_user' => 'Želim primiti e-mail podsjetnik na dan termina. Za ovu mogućnost unesite e-mail adresu iznad.',
            'consent' => 'Saglasan/na sam da se uneseni podaci koriste radi obrade zahtjeva za rezervaciju', 'privacy_intro' => 'u skladu s', 'privacy_policy' => 'politikom privatnosti',
            'honeypot' => 'Ne popunjavajte ovo polje', 'change_appointment' => 'Promijeni termin', 'send_request' => 'Pošalji zahtjev',
            'success_title' => 'Zahtjev je uspješno poslan', 'success_text' => 'Termin je privremeno rezervisan. Ako ste unijeli e-mail adresu, konačnu potvrdu dobit ćete e-mailom.',
            'new_request' => 'Pošalji novi zahtjev', 'loading' => 'Učitavanje termina…', 'generic_error' => 'Greška',
            'calendar_error' => 'Kalendar se trenutno ne može učitati. Pokušajte ponovo.', 'busy' => 'Zauzeto', 'free' => 'slobodno',
            'available_count' => 'dostupnih termina', 'send_error' => 'Zahtjev nije poslan.', 'sending' => 'Slanje…',
            'invalid_month' => 'Neispravan mjesec.', 'too_many' => 'Poslano je previše zahtjeva. Pokušajte ponovo za deset minuta.',
            'rejected' => 'Zahtjev nije prihvaćen.', 'required' => 'Molimo popunite sva obavezna polja.',
            'slot_gone' => 'Odabrani termin više nije dostupan. Molimo izaberite drugi.',
            'processing' => 'Termin se trenutno obrađuje. Pokušajte ponovo.',
            'just_booked' => 'Odabrani termin je upravo zauzet. Molimo izaberite drugi.',
            'save_failed' => 'Zahtjev nije sačuvan. Pokušajte ponovo.', 'request_sent' => 'Zahtjev je uspješno poslan.',
        );
    }

    private function table() {
        global $wpdb;
        return $wpdb->prefix . 'most_bookings';
    }

    public function plugin_links($links) {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=most-booking-settings')) . '">Postavke</a>');
        return $links;
    }

    public function register_assets() {
        wp_register_style('most-booking', plugins_url('assets/most-booking.css', __FILE__), array(), self::VERSION);
        wp_register_script('most-booking', plugins_url('assets/most-booking.js', __FILE__), array(), self::VERSION, true);
    }

    public function shortcode($atts = array()) {
        wp_enqueue_style('most-booking');
        wp_enqueue_script('most-booking');
        $atts = shortcode_atts(array('lang' => 'auto'), $atts, 'booking_app');
        $lang = $this->language($atts['lang']);
        $strings = $this->front_strings($lang);
        $config = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'today' => current_time('Y-m-d'),
            'language' => $lang,
            'strings' => $strings,
        );

        $privacy_url = get_privacy_policy_url();
        $settings = $this->settings();
        $phone_digits = preg_replace('/\D+/', '', $settings['contact_phone']);
        if (substr($phone_digits, 0, 1) === '0') {
            $phone_digits = '387' . substr($phone_digits, 1);
        }
        ob_start();
        ?>
        <section class="most-booking-app" lang="<?php echo esc_attr($lang); ?>" data-most-booking data-most-config="<?php echo esc_attr(wp_json_encode($config)); ?>">
            <div class="most-booking-layout">
                <div class="most-contact-card" aria-label="<?php echo esc_attr($strings['contact_aria']); ?>">
                    <div class="most-contact-item">
                        <span class="most-contact-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false"><path d="M12 22s7-6.1 7-13A7 7 0 0 0 5 9c0 6.9 7 13 7 13Zm0-9.5A3.5 3.5 0 1 1 12 5a3.5 3.5 0 0 1 0 7.5Z"/></svg>
                        </span>
                        <div><strong><?php echo esc_html($strings['address']); ?></strong><span><?php echo esc_html($settings['contact_address']); ?></span></div>
                    </div>
                    <div class="most-contact-item">
                        <span class="most-contact-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false"><path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.2 1.2.4 2.4.6 3.7.6.5 0 .8.4.8.9V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5c.5 0 .9.4.9.9 0 1.3.2 2.5.6 3.7.1.4 0 .8-.2 1.1l-2.2 2.1Z"/></svg>
                        </span>
                        <div><strong><?php echo esc_html($strings['phone']); ?></strong><a href="tel:+<?php echo esc_attr($phone_digits); ?>"><?php echo esc_html($settings['contact_phone']); ?></a></div>
                    </div>
                </div>

                <div class="most-calendar-card">
                    <div class="most-calendar-heading">
                        <span class="most-step">1</span>
                        <h2><?php echo esc_html($strings['book_appointment']); ?></h2>
                    </div>
                    <div class="most-calendar-nav">
                        <button type="button" class="most-icon-button" data-most-prev aria-label="<?php echo esc_attr($strings['previous_month']); ?>">&#8592;</button>
                        <h3 data-most-month aria-live="polite"></h3>
                        <button type="button" class="most-icon-button" data-most-next aria-label="<?php echo esc_attr($strings['next_month']); ?>">&#8594;</button>
                    </div>
                    <div class="most-weekdays" data-most-weekdays></div>
                    <div class="most-calendar" data-most-calendar aria-label="<?php echo esc_attr($strings['calendar_aria']); ?>"></div>
                    <div class="most-legend">
                        <span><i class="is-free"></i><?php echo esc_html($strings['available']); ?></span>
                        <span><i class="is-partial"></i><?php echo esc_html($strings['partially_booked']); ?></span>
                        <span><i class="is-full"></i><?php echo esc_html($strings['unavailable']); ?></span>
                    </div>
                </div>

                <div class="most-slot-card">
                    <div data-most-slot-empty>
                        <span class="most-step">2</span>
                        <p><?php echo esc_html($strings['after_date']); ?></p>
                    </div>
                    <div data-most-slots-wrap hidden>
                        <p class="most-selected-date" data-most-selected-date></p>
                        <h3><?php echo esc_html($strings['slots']); ?></h3>
                        <div class="most-slots" data-most-slots></div>
                    </div>
                </div>
            </div>

            <form class="most-booking-form" data-most-form hidden novalidate>
                <div class="most-form-heading">
                    <span class="most-step">3</span>
                    <div><h3><?php echo esc_html($strings['booking_details']); ?></h3><p data-most-summary></p></div>
                </div>
                <input type="hidden" name="date">
                <input type="hidden" name="time">
                <input type="hidden" name="language" value="<?php echo esc_attr($lang); ?>">
                <div class="most-form-grid">
                    <label class="most-form-wide"><?php echo esc_html($strings['consultation_type']); ?><span>*</span>
                        <select name="service" required>
                            <option value=""><?php echo esc_html($strings['choose']); ?></option>
                            <option value="sensory_room"><?php echo esc_html($strings['sensory_room']); ?></option>
                            <option value="counselling"><?php echo esc_html($strings['counselling']); ?></option>
                        </select>
                    </label>
                    <label><?php echo esc_html($strings['first_name']); ?><span>*</span><input name="first_name" autocomplete="given-name" required maxlength="100"></label>
                    <label><?php echo esc_html($strings['last_name']); ?><span>*</span><input name="last_name" autocomplete="family-name" required maxlength="100"></label>
                    <label><?php echo esc_html($strings['email_optional']); ?><input name="email" type="email" autocomplete="email" maxlength="190"></label>
                    <label><?php echo esc_html($strings['mobile']); ?><span>*</span><input name="phone" type="tel" autocomplete="tel" required maxlength="60"></label>
                    <label class="most-form-wide"><?php echo esc_html($strings['reason']); ?><span>*</span><textarea name="reason" rows="4" required maxlength="1500"></textarea></label>
                    <label class="most-consent most-reminder-consent most-form-wide"><input type="checkbox" name="reminder_user" value="1" disabled><span><?php echo esc_html($strings['reminder_user']); ?></span></label>
                    <label class="most-consent most-form-wide"><input type="checkbox" name="consent" value="1" required><span><?php echo esc_html($strings['consent']); ?><?php if ($privacy_url) : ?>, <?php echo esc_html($strings['privacy_intro']); ?> <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($strings['privacy_policy']); ?></a><?php endif; ?>.</span></label>
                    <label class="most-hp" aria-hidden="true"><?php echo esc_html($strings['honeypot']); ?><input name="website" tabindex="-1" autocomplete="off"></label>
                </div>
                <div class="most-form-actions">
                    <button type="button" class="most-secondary-button" data-most-change><?php echo esc_html($strings['change_appointment']); ?></button>
                    <button type="submit" class="most-primary-button"><?php echo esc_html($strings['send_request']); ?></button>
                </div>
                <p class="most-form-message" data-most-message role="status" aria-live="polite"></p>
            </form>

            <div class="most-booking-success" data-most-success hidden>
                <div class="most-success-icon">&#10003;</div>
                <h3><?php echo esc_html($strings['success_title']); ?></h3>
                <p><?php echo esc_html($strings['success_text']); ?></p>
                <button type="button" class="most-secondary-button" data-most-new><?php echo esc_html($strings['new_request']); ?></button>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private function available_slots($date, $busy_times = null) {
        $settings = $this->settings();
        $tz = wp_timezone();
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
        if (!$day || $day->format('Y-m-d') !== $date) {
            return array();
        }
        $weekday = (int) $day->format('N');
        $rule = isset($settings['schedule'][$weekday]) ? $settings['schedule'][$weekday] : array('enabled' => false);
        if (empty($rule['enabled'])) {
            return array();
        }

        $now = new DateTimeImmutable('now', $tz);
        $earliest = $now->modify('+' . max(0, (int) $settings['lead_hours']) . ' hours');
        $last_date = $now->setTime(0, 0)->modify('+' . max(1, (int) $settings['days_ahead']) . ' days');
        if ($day < $now->setTime(0, 0) || $day > $last_date) {
            return array();
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $rule['start'], $tz);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $rule['end'], $tz);
        $duration = max(15, (int) $settings['duration']);
        if (!$start || !$end || $end <= $start) {
            return array();
        }

        if ($busy_times === null) {
            global $wpdb;
            $table = $this->table();
            $busy = $wpdb->get_col($wpdb->prepare(
                "SELECT TIME_FORMAT(start_time, '%%H:%%i') FROM {$table} WHERE booking_date = %s AND status IN ('pending','approved','blocked')",
                $date
            ));
        } else {
            $busy = $busy_times;
        }
        $busy = array_flip($busy);
        $slots = array();
        for ($slot = $start; $slot->modify('+' . $duration . ' minutes') <= $end; $slot = $slot->modify('+' . $duration . ' minutes')) {
            $time = $slot->format('H:i');
            if ($slot >= $earliest) {
                $slots[] = array('time' => $time, 'available' => !isset($busy[$time]));
            }
        }
        return $slots;
    }

    public function ajax_month_availability() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        $lang = $this->language(isset($_POST['language']) ? wp_unslash($_POST['language']) : 'bs');
        $strings = $this->front_strings($lang);
        $year = isset($_POST['year']) ? absint($_POST['year']) : 0;
        $month = isset($_POST['month']) ? absint($_POST['month']) : 0;
        if ($year < (int) current_time('Y') || $month < 1 || $month > 12) {
            wp_send_json_error(array('message' => $strings['invalid_month']), 400);
        }
        $tz = wp_timezone();
        $month_start = DateTimeImmutable::createFromFormat('!Y-n-j', $year . '-' . $month . '-1', $tz);
        if (!$month_start) {
            wp_send_json_error(array('message' => $strings['invalid_month']), 400);
        }
        $days = (int) $month_start->format('t');
        $month_end = $month_start->modify('last day of this month')->format('Y-m-d');
        global $wpdb;
        $table = $this->table();
        $busy_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT booking_date, TIME_FORMAT(start_time, '%%H:%%i') AS slot_time FROM {$table} WHERE booking_date BETWEEN %s AND %s AND status IN ('pending','approved','blocked')",
            $month_start->format('Y-m-d'),
            $month_end
        ));
        $busy_by_date = array();
        foreach ($busy_rows as $busy_row) {
            if (!isset($busy_by_date[$busy_row->booking_date])) {
                $busy_by_date[$busy_row->booking_date] = array();
            }
            $busy_by_date[$busy_row->booking_date][] = $busy_row->slot_time;
        }
        $result = array();
        for ($i = 1; $i <= $days; $i++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $i);
            $slots = $this->available_slots($date, isset($busy_by_date[$date]) ? $busy_by_date[$date] : array());
            $available = count(array_filter($slots, function($slot) { return !empty($slot['available']); }));
            $result[$date] = array(
                'slots' => $slots,
                'available' => $available,
                'total' => count($slots),
            );
        }
        wp_send_json_success(array('days' => $result));
    }

    public function ajax_submit_booking() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        $lang = $this->language(isset($_POST['language']) ? wp_unslash($_POST['language']) : 'bs');
        $strings = $this->front_strings($lang);
        $remote = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $rate_key = 'most_booking_rate_' . md5($remote);
        $attempts = (int) get_transient($rate_key);
        if ($attempts >= 8) {
            wp_send_json_error(array('message' => $strings['too_many']), 429);
        }
        set_transient($rate_key, $attempts + 1, 10 * MINUTE_IN_SECONDS);
        if (!empty($_POST['website'])) {
            wp_send_json_error(array('message' => $strings['rejected']), 400);
        }
        $data = array(
            'date' => isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '',
            'time' => isset($_POST['time']) ? sanitize_text_field(wp_unslash($_POST['time'])) : '',
            'service' => isset($_POST['service']) ? sanitize_key(wp_unslash($_POST['service'])) : '',
            'language' => $lang,
            'first_name' => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
            'last_name' => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
            'email' => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
            'phone' => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
            'reason' => isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '',
            'reminder_user' => !empty($_POST['reminder_user']) ? 1 : 0,
        );
        if (!$data['date'] || !preg_match('/^\d{2}:\d{2}$/', $data['time']) || !in_array($data['service'], array('sensory_room', 'counselling'), true) || !$data['first_name'] || !$data['last_name'] || ($data['email'] && !is_email($data['email'])) || !$data['phone'] || !$data['reason'] || empty($_POST['consent'])) {
            wp_send_json_error(array('message' => $strings['required']), 422);
        }
        if (!$data['email']) {
            $data['reminder_user'] = 0;
        }
        $slots = $this->available_slots($data['date']);
        $valid = false;
        foreach ($slots as $slot) {
            if ($slot['time'] === $data['time'] && $slot['available']) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            wp_send_json_error(array('message' => $strings['slot_gone']), 409);
        }

        global $wpdb;
        $table = $this->table();
        $lock_name = 'most_booking_' . md5($data['date'] . '_' . $data['time']);
        $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock_name));
        if ($locked !== 1) {
            wp_send_json_error(array('message' => $strings['processing']), 409);
        }
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE booking_date=%s AND start_time=%s AND status IN ('pending','approved','blocked')",
            $data['date'], $data['time'] . ':00'
        ));
        if ($exists) {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
            wp_send_json_error(array('message' => $strings['just_booked']), 409);
        }
        $duration = max(15, (int) $this->settings()['duration']);
        $end_time = gmdate('H:i:s', strtotime($data['time'] . ':00') + ($duration * MINUTE_IN_SECONDS));
        $now = current_time('mysql');
        $settings = $this->settings();
        $inserted = $wpdb->insert($table, array(
            'booking_date' => $data['date'], 'start_time' => $data['time'] . ':00', 'end_time' => $end_time,
            'status' => 'pending', 'entry_type' => 'booking', 'service' => $data['service'], 'language' => $data['language'], 'first_name' => $data['first_name'],
            'last_name' => $data['last_name'], 'email' => $data['email'], 'phone' => $data['phone'],
            'reason' => $data['reason'], 'admin_note' => '', 'recurrence_group' => '',
            'reminder_user' => $data['reminder_user'], 'reminder_admin' => !empty($settings['default_admin_reminder']) ? 1 : 0,
            'reminder_sent_user' => 0, 'reminder_sent_admin' => 0, 'reminder_attempts' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ), array('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%d','%s','%s'));
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        if (!$inserted) {
            wp_send_json_error(array('message' => $strings['save_failed']), 500);
        }
        $this->send_pending_emails($wpdb->insert_id, $data);
        wp_send_json_success(array('message' => $strings['request_sent']));
    }

    private function send_pending_emails($id, $data) {
        $settings = $this->settings();
        $admin_url = admin_url('admin.php?page=most-booking');
        $service = $this->service_label($data['service'], 'bs');
        $subject_admin = 'Novi zahtjev za konsultacije – ' . $data['date'] . ' u ' . $data['time'];
        $user_email = $data['email'] ? $data['email'] : 'nije unesen';
        $body_admin = "Novi zahtjev za rezervaciju (#{$id})\n\nUsluga: {$service}\nTermin: {$data['date']} u {$data['time']}\nJezik korisnika: " . strtoupper($data['language']) . "\nKorisnik: {$data['first_name']} {$data['last_name']}\nE-mail: {$user_email}\nMobitel: {$data['phone']}\nRazlog dolaska: {$data['reason']}\n\nOdobrite ili odbijte zahtjev: {$admin_url}";
        if (is_email($settings['notification_email'])) {
            wp_mail(sanitize_email($settings['notification_email']), $subject_admin, $body_admin);
        }

        if ($data['email']) {
            if ($data['language'] === 'en') {
                $service_user = $this->service_label($data['service'], 'en');
                $date_user = $this->user_date($data['date'], 'en');
                $subject_user = 'Booking request received – MOST Centre';
                $body_user = "Dear {$data['first_name']},\n\nwe received your request for {$service_user} on {$date_user} at {$data['time']}. The appointment is being held temporarily while we review the request. We will send the final confirmation by email.\n\nMOST Social Pedagogy Centre";
            } else {
                $subject_user = 'Zaprimljen zahtjev za rezervaciju – Centar MOST';
                $body_user = "Poštovani/a {$data['first_name']},\n\nzaprimili smo Vaš zahtjev za uslugu {$service}, za termin {$data['date']} u {$data['time']}. Termin je privremeno zadržan dok ne pregledamo zahtjev. Konačnu potvrdu poslat ćemo Vam e-mailom.\n\nSociopedagoški centar MOST";
            }
            wp_mail($data['email'], $subject_user, $body_user);
        }
    }

    private function service_label($service, $lang = 'bs') {
        if ($lang === 'en') {
            return $service === 'counselling' ? 'Counselling' : 'Sensory room';
        }
        return $service === 'counselling' ? 'Savjetovanje' : 'Senzorna soba';
    }

    private function user_date($date, $lang = 'bs') {
        if ($lang !== 'en') {
            return date_i18n('d.m.Y.', strtotime($date));
        }
        $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
        $months = array(1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December');
        return $value ? $months[(int) $value->format('n')] . ' ' . $value->format('j, Y') : $date;
    }

    public function admin_menu() {
        add_menu_page('Booking App', 'Booking App', 'manage_options', 'most-booking', array($this, 'admin_page'), 'dashicons-calendar-alt', 26);
        add_submenu_page('most-booking', 'Dodaj rezervaciju', 'Dodaj rezervaciju', 'manage_options', 'most-booking-add', array($this, 'admin_create_page'));
        add_submenu_page('most-booking', 'Postavke', 'Postavke', 'manage_options', 'most-booking-settings', array($this, 'settings_page'));
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $table = $this->table();
        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $where = in_array($status, array('pending','approved','rejected','cancelled','blocked'), true) ? $wpdb->prepare(' WHERE status=%s', $status) : '';
        $rows = $wpdb->get_results("SELECT * FROM {$table}{$where} ORDER BY booking_date DESC, start_time DESC LIMIT 300");
        $labels = array('pending'=>'Na čekanju','approved'=>'Potvrđeno','rejected'=>'Odbijeno','cancelled'=>'Otkazano','blocked'=>'Blokirano');
        ?>
        <div class="wrap most-admin"><h1 class="wp-heading-inline">Booking App</h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=most-booking-add')); ?>" class="page-title-action">Dodaj rezervaciju</a>
            <hr class="wp-header-end">
            <?php if (isset($_GET['updated'])) : ?><div class="notice notice-success is-dismissible"><p>Promjena je sačuvana.</p></div><?php endif; ?>
            <?php if (isset($_GET['created'])) : ?>
                <div class="notice notice-success is-dismissible"><p>Kreirano je <?php echo absint($_GET['created']); ?> rezervacija.<?php if (!empty($_GET['skipped'])) : ?> Preskočeno zbog zauzetosti ili nevažećeg termina: <?php echo absint($_GET['skipped']); ?>.<?php endif; ?></p></div>
            <?php endif; ?>
            <div class="most-admin-grid">
                <section class="most-admin-panel"><h2>Blokiraj termin</h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="most_booking_block"><?php wp_nonce_field('most_booking_block'); ?>
                        <label>Datum <input type="date" name="date" required></label>
                        <label>Vrijeme <input type="time" name="time" step="3600" required></label>
                        <label>Napomena <input type="text" name="note" maxlength="500" placeholder="Npr. interni sastanak"></label>
                        <?php submit_button('Blokiraj termin', 'secondary', 'submit', false); ?>
                    </form>
                </section>
                <section class="most-admin-panel"><h2>Ugradnja kalendara</h2><p>Bosanski:</p><code>[booking_app lang="bs"]</code><p>English:</p><code>[booking_app lang="en"]</code><p>Bez atributa se koristi jezik WordPress stranice. Stari <code>[most_booking]</code> shortcode ostaje podržan.</p></section>
            </div>
            <p class="subsubsub"><a href="?page=most-booking">Sve</a> | <a href="?page=most-booking&status=pending">Na čekanju</a> | <a href="?page=most-booking&status=approved">Potvrđene</a> | <a href="?page=most-booking&status=blocked">Blokirane</a></p>
            <table class="widefat striped most-bookings-table"><thead><tr><th>Termin</th><th>Usluga</th><th>Korisnik</th><th>Kontakt</th><th>Razlog / napomena</th><th>Status</th><th>Podsjetnici</th><th>Akcije</th></tr></thead><tbody>
            <?php if (!$rows) : ?><tr><td colspan="8">Nema rezervacija.</td></tr><?php endif; ?>
            <?php foreach ($rows as $row) : ?><tr>
                <td><strong><?php echo esc_html(date_i18n('d.m.Y.', strtotime($row->booking_date))); ?></strong><br><?php echo esc_html(substr($row->start_time,0,5) . '–' . substr($row->end_time,0,5)); ?></td>
                <td><?php echo $row->entry_type === 'block' ? '—' : esc_html($this->service_label($row->service)); ?><?php if ($row->entry_type === 'booking') : ?><br><small><?php echo esc_html(strtoupper(isset($row->language) ? $row->language : 'bs')); ?></small><?php endif; ?></td>
                <td><?php echo $row->entry_type === 'block' ? '—' : esc_html($row->first_name . ' ' . $row->last_name); ?></td>
                <td><?php if ($row->email) : ?><a href="mailto:<?php echo esc_attr($row->email); ?>"><?php echo esc_html($row->email); ?></a><br><?php endif; ?><?php echo esc_html($row->phone); ?></td>
                <td><?php echo nl2br(esc_html($row->entry_type === 'block' ? $row->admin_note : $row->reason)); ?><?php if ($row->entry_type === 'booking' && $row->admin_note) : ?><br><small><strong>Interno:</strong> <?php echo esc_html($row->admin_note); ?></small><?php endif; ?></td>
                <td><span class="most-status most-status-<?php echo esc_attr($row->status); ?>"><?php echo esc_html($labels[$row->status] ?? $row->status); ?></span></td>
                <td><?php $this->admin_reminder_controls($row); ?></td>
                <td><?php $this->admin_actions($row); ?></td>
            </tr><?php endforeach; ?></tbody></table>
        </div><?php $this->admin_styles();
    }

    private function admin_reminder_controls($row) {
        if ($row->entry_type !== 'booking' || in_array($row->status, array('rejected', 'cancelled'), true)) {
            echo '—';
            return;
        }
        ?>
        <form class="most-reminder-admin" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="most_booking_reminders">
            <input type="hidden" name="booking_id" value="<?php echo absint($row->id); ?>">
            <?php wp_nonce_field('most_booking_reminders_' . absint($row->id)); ?>
            <label title="Podsjetnik korisniku"><input type="checkbox" name="reminder_user" value="1" <?php checked(!empty($row->reminder_user)); ?> <?php disabled(empty($row->email)); ?>> Korisnik<?php if (!empty($row->reminder_sent_user)) : ?> ✓<?php endif; ?></label>
            <label title="Podsjetnik Centru MOST"><input type="checkbox" name="reminder_admin" value="1" <?php checked(!empty($row->reminder_admin)); ?>> Centar<?php if (!empty($row->reminder_sent_admin)) : ?> ✓<?php endif; ?></label>
            <button type="submit" class="button button-small">Sačuvaj</button>
        </form>
        <?php
    }

    private function admin_actions($row) {
        $actions = array();
        if ($row->status === 'pending') $actions = array('approve'=>'Potvrdi','reject'=>'Odbij');
        elseif ($row->status === 'approved') $actions = array('cancel'=>'Otkaži');
        elseif ($row->status === 'blocked') $actions = array('cancel'=>'Oslobodi');
        foreach ($actions as $action => $label) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=most_booking_action&booking_id=' . absint($row->id) . '&do=' . $action), 'most_booking_' . $row->id);
            echo '<a class="button button-small" href="' . esc_url($url) . '">' . esc_html($label) . '</a> ';
        }
    }

    public function admin_booking_action() {
        if (!current_user_can('manage_options')) wp_die('Nemate ovlaštenje.');
        $id = isset($_GET['booking_id']) ? absint($_GET['booking_id']) : 0;
        check_admin_referer('most_booking_' . $id);
        $action = isset($_GET['do']) ? sanitize_key($_GET['do']) : '';
        $map = array('approve'=>'approved','reject'=>'rejected','cancel'=>'cancelled');
        if (!$id || !isset($map[$action])) wp_die('Neispravna akcija.');
        global $wpdb;
        $table = $this->table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id));
        if (!$row) wp_die('Rezervacija nije pronađena.');
        $wpdb->update($table, array('status'=>$map[$action], 'updated_at'=>current_time('mysql')), array('id'=>$id), array('%s','%s'), array('%d'));
        if ($row->entry_type === 'booking' && $row->email) $this->send_status_email($row, $map[$action]);
        if ($map[$action] === 'approved') {
            $this->schedule_booking_reminder($id);
        } else {
            $this->clear_booking_reminder($id);
        }
        wp_safe_redirect(admin_url('admin.php?page=most-booking&updated=1')); exit;
    }

    private function send_status_email($row, $status) {
        $date = date_i18n('d.m.Y.', strtotime($row->booking_date));
        $time = substr($row->start_time, 0, 5);
        $lang = isset($row->language) ? $this->language($row->language) : 'bs';
        $service = $this->service_label($row->service, $lang);
        $date_user = $this->user_date($row->booking_date, $lang);
        if ($lang === 'en' && $status === 'approved') {
            $subject = 'Booking confirmed – MOST Centre';
            $body = "Dear {$row->first_name},\n\nyour booking for {$service} on {$date_user} at {$time} has been confirmed.\n\nMOST Social Pedagogy Centre";
        } elseif ($lang === 'en' && $status === 'rejected') {
            $subject = 'Booking request update – MOST Centre';
            $body = "Dear {$row->first_name},\n\nunfortunately, we are unable to confirm the requested appointment on {$date_user} at {$time}. Please choose another available appointment.\n\nMOST Social Pedagogy Centre";
        } elseif ($lang === 'en') {
            $subject = 'Booking cancelled – MOST Centre';
            $body = "Dear {$row->first_name},\n\nyour booking for {$service} on {$date_user} at {$time} has been cancelled.\n\nMOST Social Pedagogy Centre";
        } elseif ($status === 'approved') {
            $subject = 'Potvrđena rezervacija – Centar MOST';
            $body = "Poštovani/a {$row->first_name},\n\nVaša rezervacija za uslugu {$service}, za {$date} u {$time}, je potvrđena.\n\nSociopedagoški centar MOST";
        } elseif ($status === 'rejected') {
            $subject = 'Odgovor na zahtjev za rezervaciju – Centar MOST';
            $body = "Poštovani/a {$row->first_name},\n\nnažalost, nismo u mogućnosti potvrditi traženi termin {$date} u {$time}. Slobodno odaberite drugi raspoloživi termin.\n\nSociopedagoški centar MOST";
        } else {
            $subject = 'Otkazana rezervacija – Centar MOST';
            $body = "Poštovani/a {$row->first_name},\n\nrezervacija za uslugu {$service}, za {$date} u {$time}, je otkazana.\n\nSociopedagoški centar MOST";
        }
        wp_mail($row->email, $subject, $body);
    }

    public function admin_create_page() {
        if (!current_user_can('manage_options')) return;
        $settings = $this->settings();
        ?>
        <div class="wrap most-admin">
            <h1>Dodaj rezervaciju</h1>
            <?php if (!empty($_GET['error'])) : ?><div class="notice notice-error"><p>Nijedan termin nije kreiran. Provjerite raspored, vrijeme i postojeće rezervacije.</p></div><?php endif; ?>
            <p>Rezervaciju možete unijeti jednom ili je ponavljati svake sedmice do odabranog datuma. Zauzeti termini automatski će biti preskočeni.</p>
            <form class="most-admin-booking-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="most_booking_admin_create">
                <?php wp_nonce_field('most_booking_admin_create'); ?>
                <section class="most-admin-panel">
                    <h2>Termin i ponavljanje</h2>
                    <div class="most-admin-fields">
                        <label>Početni datum <input type="date" name="start_date" min="<?php echo esc_attr(current_time('Y-m-d')); ?>" required></label>
                        <label>Vrijeme <input type="time" name="time" step="<?php echo absint(max(15, (int) $settings['duration']) * 60); ?>" required></label>
                        <label>Način rezervacije
                            <select name="recurrence" data-most-recurrence>
                                <option value="once">Samo odabrani datum</option>
                                <option value="weekly">Svake sedmice</option>
                            </select>
                        </label>
                        <label data-most-recurrence-end hidden>Posljednji datum <input type="date" name="end_date"></label>
                        <label>Usluga
                            <select name="service" required>
                                <option value="sensory_room">Senzorna soba</option>
                                <option value="counselling">Savjetovanje</option>
                            </select>
                        </label>
                        <label>Jezik korisničkih poruka
                            <select name="language" required>
                                <option value="bs">Bosanski</option>
                                <option value="en">English</option>
                            </select>
                        </label>
                    </div>
                    <p class="description">Kod sedmičnog ponavljanja dan početnog datuma određuje dan u sedmici. Primjer: početak u srijedu znači svake srijede do posljednjeg datuma.</p>
                </section>
                <section class="most-admin-panel">
                    <h2>Podaci korisnika</h2>
                    <div class="most-admin-fields">
                        <label>Ime <input type="text" name="first_name" maxlength="100" required></label>
                        <label>Prezime <input type="text" name="last_name" maxlength="100" required></label>
                        <label>E-mail <input type="email" name="email" maxlength="190" data-most-admin-email><small>Nije obavezno</small></label>
                        <label>Mobitel <input type="text" name="phone" maxlength="60"></label>
                        <label class="most-admin-wide">Razlog dolaska <textarea name="reason" rows="3" maxlength="1500"></textarea></label>
                        <label class="most-admin-wide">Interna napomena <textarea name="admin_note" rows="2" maxlength="1500"></textarea></label>
                    </div>
                </section>
                <section class="most-admin-panel">
                    <h2>Obavijesti</h2>
                    <label class="most-admin-check"><input type="checkbox" name="reminder_user" value="1" data-most-admin-user-reminder disabled> Pošalji korisniku podsjetnik na dan svakog potvrđenog termina</label>
                    <label class="most-admin-check"><input type="checkbox" name="reminder_admin" value="1" <?php checked(!empty($settings['default_admin_reminder'])); ?>> Pošalji podsjetnik Centru MOST na dan svakog termina</label>
                    <p class="description">Ako je e-mail korisnika unesen, odmah će dobiti potvrdu rezervacije. Dnevni podsjetnik šalje se u vrijeme određeno u Postavkama.</p>
                </section>
                <?php submit_button('Kreiraj rezervaciju'); ?>
            </form>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var recurrence = document.querySelector('[data-most-recurrence]');
            var endWrap = document.querySelector('[data-most-recurrence-end]');
            var endInput = endWrap ? endWrap.querySelector('input') : null;
            var startInput = document.querySelector('[name="start_date"]');
            var email = document.querySelector('[data-most-admin-email]');
            var userReminder = document.querySelector('[data-most-admin-user-reminder]');
            function syncRecurrence() {
                var weekly = recurrence && recurrence.value === 'weekly';
                if (endWrap) endWrap.hidden = !weekly;
                if (endInput) { endInput.required = weekly; endInput.min = startInput ? startInput.value : ''; }
            }
            function syncReminder() {
                if (!email || !userReminder) return;
                userReminder.disabled = !email.value.trim();
                if (userReminder.disabled) userReminder.checked = false;
            }
            if (recurrence) recurrence.addEventListener('change', syncRecurrence);
            if (startInput) startInput.addEventListener('change', syncRecurrence);
            if (email) email.addEventListener('input', syncReminder);
            syncRecurrence(); syncReminder();
        });
        </script>
        <?php $this->admin_styles();
    }

    public function admin_create_booking() {
        if (!current_user_can('manage_options')) wp_die('Nemate ovlaštenje.');
        check_admin_referer('most_booking_admin_create');
        $recurrence = isset($_POST['recurrence']) ? sanitize_key(wp_unslash($_POST['recurrence'])) : 'once';
        if (!in_array($recurrence, array('once', 'weekly'), true)) $recurrence = 'once';
        $data = array(
            'start_date' => sanitize_text_field(wp_unslash($_POST['start_date'] ?? '')),
            'end_date' => sanitize_text_field(wp_unslash($_POST['end_date'] ?? '')),
            'time' => sanitize_text_field(wp_unslash($_POST['time'] ?? '')),
            'service' => sanitize_key(wp_unslash($_POST['service'] ?? '')),
            'language' => $this->language(isset($_POST['language']) ? wp_unslash($_POST['language']) : 'bs'),
            'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
            'last_name' => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
            'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'phone' => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
            'reason' => sanitize_textarea_field(wp_unslash($_POST['reason'] ?? '')),
            'admin_note' => sanitize_textarea_field(wp_unslash($_POST['admin_note'] ?? '')),
            'reminder_user' => !empty($_POST['reminder_user']) ? 1 : 0,
            'reminder_admin' => !empty($_POST['reminder_admin']) ? 1 : 0,
        );
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['start_date']) || !preg_match('/^\d{2}:\d{2}$/', $data['time']) || !in_array($data['service'], array('sensory_room', 'counselling'), true) || !$data['first_name'] || !$data['last_name'] || ($data['email'] && !is_email($data['email']))) {
            wp_die('Molimo provjerite obavezna polja i unesene podatke.');
        }
        if (!$data['email']) $data['reminder_user'] = 0;
        $tz = wp_timezone();
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $data['start_date'], $tz);
        $end_value = $recurrence === 'weekly' ? $data['end_date'] : $data['start_date'];
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $end_value, $tz);
        if (!$start || !$end || $start->format('Y-m-d') !== $data['start_date'] || $end->format('Y-m-d') !== $end_value || $end < $start) {
            wp_die('Raspon datuma nije ispravan.');
        }
        if ($recurrence === 'weekly' && $start->diff($end)->days > 3650) {
            wp_die('Jedna serija može obuhvatiti najviše deset godina.');
        }

        $dates = array();
        for ($date = $start, $guard = 0; $date <= $end && $guard < 523; $date = $date->modify('+7 days'), $guard++) {
            $dates[] = $date->format('Y-m-d');
            if ($recurrence === 'once') break;
        }
        $group = count($dates) > 1 ? wp_generate_uuid4() : '';
        $created_ids = array();
        $created_dates = array();
        $skipped = 0;
        foreach ($dates as $date) {
            $id = $this->insert_admin_booking($date, $data, $group);
            if ($id) {
                $created_ids[] = $id;
                $created_dates[] = $date;
                $this->schedule_booking_reminder($id);
            } else {
                $skipped++;
            }
        }
        if (!$created_ids) {
            wp_safe_redirect(admin_url('admin.php?page=most-booking-add&error=unavailable')); exit;
        }
        if ($data['email']) {
            $this->send_admin_created_confirmation($data, $created_dates, $recurrence);
        }
        wp_safe_redirect(add_query_arg(array('page'=>'most-booking','created'=>count($created_ids),'skipped'=>$skipped), admin_url('admin.php'))); exit;
    }

    private function insert_admin_booking($date, $data, $group) {
        if (!$this->admin_slot_is_valid($date, $data['time'])) return 0;
        global $wpdb;
        $table = $this->table();
        $lock_name = 'most_booking_' . md5($date . '_' . $data['time']);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock_name)) !== 1) return 0;
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE booking_date=%s AND start_time=%s AND status IN ('pending','approved','blocked')",
            $date, $data['time'] . ':00'
        ));
        if ($exists) {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
            return 0;
        }
        $duration = max(15, (int) $this->settings()['duration']);
        $end_time = gmdate('H:i:s', strtotime($data['time'] . ':00') + $duration * MINUTE_IN_SECONDS);
        $now = current_time('mysql');
        $inserted = $wpdb->insert($table, array(
            'booking_date'=>$date, 'start_time'=>$data['time'] . ':00', 'end_time'=>$end_time,
            'status'=>'approved', 'entry_type'=>'booking', 'service'=>$data['service'], 'language'=>$data['language'],
            'first_name'=>$data['first_name'], 'last_name'=>$data['last_name'], 'email'=>$data['email'], 'phone'=>$data['phone'],
            'reason'=>$data['reason'], 'admin_note'=>$data['admin_note'], 'recurrence_group'=>$group,
            'reminder_user'=>$data['reminder_user'], 'reminder_admin'=>$data['reminder_admin'],
            'reminder_sent_user'=>0, 'reminder_sent_admin'=>0, 'reminder_attempts'=>0,
            'created_at'=>$now, 'updated_at'=>$now,
        ), array('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%d','%s','%s'));
        $id = $inserted ? absint($wpdb->insert_id) : 0;
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        return $id;
    }

    private function admin_slot_is_valid($date, $time) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) return false;
        $settings = $this->settings();
        $tz = wp_timezone();
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
        $slot = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $tz);
        if (!$day || !$slot || $day->format('Y-m-d') !== $date || $slot->format('H:i') !== $time || $slot <= new DateTimeImmutable('now', $tz)) return false;
        $weekday = (int) $day->format('N');
        $rule = isset($settings['schedule'][$weekday]) ? $settings['schedule'][$weekday] : array('enabled'=>false);
        if (empty($rule['enabled'])) return false;
        $open = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $rule['start'], $tz);
        $close = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $rule['end'], $tz);
        $duration = max(15, (int) $settings['duration']);
        if (!$open || !$close || $slot < $open || $slot->modify('+' . $duration . ' minutes') > $close) return false;
        return (($slot->getTimestamp() - $open->getTimestamp()) % ($duration * MINUTE_IN_SECONDS)) === 0;
    }

    private function send_admin_created_confirmation($data, $dates, $recurrence) {
        if (!$dates || !is_email($data['email'])) return;
        $lang = $this->language($data['language']);
        $service = $this->service_label($data['service'], $lang);
        $time = $data['time'];
        if ($lang === 'en') {
            if (count($dates) === 1) {
                $subject = 'Booking confirmed – MOST Centre';
                $date_user = $this->user_date($dates[0], 'en');
                $body = "Dear {$data['first_name']},\n\nyour booking for {$service} on {$date_user} at {$time} has been confirmed.\n\nMOST Social Pedagogy Centre";
            } else {
                $date_lines = array();
                foreach ($dates as $confirmed_date) {
                    $date_lines[] = '• ' . $this->user_date($confirmed_date, 'en') . ' at ' . $time;
                }
                $date_list = implode("\n", $date_lines);
                $count = count($dates);
                $subject = 'Recurring bookings confirmed – MOST Centre';
                $body = "Dear {$data['first_name']},\n\n{$count} appointments for {$service} have been confirmed, from " . $this->user_date(reset($dates), 'en') . ' to ' . $this->user_date(end($dates), 'en') . ".\n\nConfirmed dates:\n{$date_list}\n\nIf reminders are enabled, you will receive one on the day of each appointment.\n\nMOST Social Pedagogy Centre";
            }
            wp_mail($data['email'], $subject, $body);
            return;
        }
        if (count($dates) === 1) {
            $date = date_i18n('d.m.Y.', strtotime($dates[0]));
            $subject = 'Potvrđena rezervacija – Centar MOST';
            $body = "Poštovani/a {$data['first_name']},\n\nVaša rezervacija za uslugu {$service}, za {$date} u {$time}, je potvrđena.\n\nSociopedagoški centar MOST";
        } else {
            $days = array(1=>'ponedjeljka',2=>'utorka',3=>'srijede',4=>'četvrtka',5=>'petka',6=>'subote',7=>'nedjelje');
            $weekday = (int) date('N', strtotime($dates[0]));
            $first = date_i18n('d.m.Y.', strtotime(reset($dates)));
            $last = date_i18n('d.m.Y.', strtotime(end($dates)));
            $count = count($dates);
            $date_lines = array();
            foreach ($dates as $confirmed_date) {
                $date_lines[] = '• ' . date_i18n('d.m.Y.', strtotime($confirmed_date)) . ' u ' . $time;
            }
            $date_list = implode("\n", $date_lines);
            $subject = 'Potvrđena serija rezervacija – Centar MOST';
            $body = "Poštovani/a {$data['first_name']},\n\npotvrđeno je {$count} termina za uslugu {$service}, svake {$days[$weekday]} u {$time}, od {$first} do {$last}.\n\nPotvrđeni datumi:\n{$date_list}\n\nNa dan svakog termina dobit ćete podsjetnik ako je ta mogućnost uključena.\n\nSociopedagoški centar MOST";
        }
        wp_mail($data['email'], $subject, $body);
    }

    public function admin_save_reminders() {
        if (!current_user_can('manage_options')) wp_die('Nemate ovlaštenje.');
        $id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        check_admin_referer('most_booking_reminders_' . $id);
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id=%d", $id));
        if (!$row || $row->entry_type !== 'booking') wp_die('Rezervacija nije pronađena.');
        $reminder_user = !empty($_POST['reminder_user']) && is_email($row->email) ? 1 : 0;
        $reminder_admin = !empty($_POST['reminder_admin']) ? 1 : 0;
        $wpdb->update(
            $this->table(),
            array('reminder_user'=>$reminder_user, 'reminder_admin'=>$reminder_admin, 'updated_at'=>current_time('mysql')),
            array('id'=>$id),
            array('%d','%d','%s'),
            array('%d')
        );
        if ($row->status === 'approved' && ($reminder_user || $reminder_admin)) {
            $this->schedule_booking_reminder($id);
        } else {
            $this->clear_booking_reminder($id);
        }
        wp_safe_redirect(admin_url('admin.php?page=most-booking&updated=1')); exit;
    }

    private function clear_booking_reminder($booking_id) {
        wp_clear_scheduled_hook('most_booking_send_reminder', array(absint($booking_id)));
    }

    private function schedule_booking_reminder($booking_id) {
        global $wpdb;
        $booking_id = absint($booking_id);
        $this->clear_booking_reminder($booking_id);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id=%d", $booking_id));
        if (!$row || $row->status !== 'approved' || $row->entry_type !== 'booking') return false;
        $needs_user = !empty($row->reminder_user) && empty($row->reminder_sent_user) && is_email($row->email);
        $needs_admin = !empty($row->reminder_admin) && empty($row->reminder_sent_admin);
        if (!$needs_user && !$needs_admin) return false;

        $settings = $this->settings();
        $reminder_time = !empty($settings['reminder_time']) && preg_match('/^\d{2}:\d{2}$/', $settings['reminder_time']) ? $settings['reminder_time'] : '08:00';
        $tz = wp_timezone();
        $appointment = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $row->booking_date . ' ' . substr($row->start_time, 0, 5), $tz);
        $send_at = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $row->booking_date . ' ' . $reminder_time, $tz);
        $now = new DateTimeImmutable('now', $tz);
        if (!$appointment || !$send_at || $appointment <= $now) return false;
        if ($send_at >= $appointment) $send_at = $appointment->modify('-1 hour');
        if ($send_at <= $now) $send_at = $now->modify('+1 minute');
        if ($send_at >= $appointment) return false;
        return wp_schedule_single_event($send_at->getTimestamp(), 'most_booking_send_reminder', array($booking_id));
    }

    private function reschedule_future_reminders() {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$this->table()} WHERE status='approved' AND entry_type='booking' AND booking_date >= %s AND (reminder_user=1 OR reminder_admin=1)",
            current_time('Y-m-d')
        ));
        foreach ($ids as $id) {
            $this->schedule_booking_reminder(absint($id));
        }
    }

    public function send_booking_reminder($booking_id) {
        global $wpdb;
        $booking_id = absint($booking_id);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id=%d", $booking_id));
        if (!$row || $row->status !== 'approved' || $row->entry_type !== 'booking') return;
        $tz = wp_timezone();
        $appointment = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $row->booking_date . ' ' . substr($row->start_time, 0, 5), $tz);
        $now = new DateTimeImmutable('now', $tz);
        if (!$appointment || $appointment <= $now) return;

        $settings = $this->settings();
        $date = date_i18n('d.m.Y.', strtotime($row->booking_date));
        $time = substr($row->start_time, 0, 5);
        $lang = isset($row->language) ? $this->language($row->language) : 'bs';
        $service = $this->service_label($row->service, $lang);
        $user_sent = false;
        $admin_sent = false;
        $needs_user = !empty($row->reminder_user) && empty($row->reminder_sent_user) && is_email($row->email);
        $needs_admin = !empty($row->reminder_admin) && empty($row->reminder_sent_admin) && is_email($settings['notification_email']);

        if ($needs_user) {
            if ($lang === 'en') {
                $date_user = $this->user_date($row->booking_date, 'en');
                $subject = "Reminder for today's appointment – MOST Centre";
                $body = "Dear {$row->first_name},\n\nthis is a reminder that you have an appointment for {$service} today, {$date_user}, at {$time}.\n\nAddress: {$settings['contact_address']}\nPhone: {$settings['contact_phone']}\n\nMOST Social Pedagogy Centre";
            } else {
                $subject = 'Podsjetnik za današnji termin – Centar MOST';
                $body = "Poštovani/a {$row->first_name},\n\npodsjećamo Vas da danas, {$date}, u {$time} imate termin za uslugu {$service}.\n\nAdresa: {$settings['contact_address']}\nTelefon: {$settings['contact_phone']}\n\nSociopedagoški centar MOST";
            }
            $user_sent = wp_mail($row->email, $subject, $body);
        }
        if ($needs_admin) {
            $email = $row->email ? $row->email : 'nije unesen';
            $subject = 'Današnji termin – ' . $row->first_name . ' ' . $row->last_name . ' u ' . $time;
            $service_admin = $this->service_label($row->service, 'bs');
            $body = "Podsjetnik na današnju rezervaciju (#{$row->id})\n\nTermin: {$date} u {$time}\nUsluga: {$service_admin}\nKorisnik: {$row->first_name} {$row->last_name}\nE-mail: {$email}\nMobitel: {$row->phone}\nRazlog: {$row->reason}\n\nSociopedagoški centar MOST";
            $admin_sent = wp_mail(sanitize_email($settings['notification_email']), $subject, $body);
        }

        $attempts = min(255, ((int) $row->reminder_attempts) + 1);
        $update = array('reminder_attempts'=>$attempts, 'updated_at'=>current_time('mysql'));
        $formats = array('%d','%s');
        if ($user_sent) { $update['reminder_sent_user'] = 1; $formats[] = '%d'; }
        if ($admin_sent) { $update['reminder_sent_admin'] = 1; $formats[] = '%d'; }
        $wpdb->update($this->table(), $update, array('id'=>$booking_id), $formats, array('%d'));

        $retry_user = $needs_user && !$user_sent;
        $retry_admin = $needs_admin && !$admin_sent;
        $retry_at = $now->modify('+15 minutes');
        if (($retry_user || $retry_admin) && $attempts < 3 && $retry_at < $appointment) {
            wp_schedule_single_event($retry_at->getTimestamp(), 'most_booking_send_reminder', array($booking_id));
        }
    }

    public function admin_block_slot() {
        if (!current_user_can('manage_options')) wp_die('Nemate ovlaštenje.');
        check_admin_referer('most_booking_block');
        $date = sanitize_text_field(wp_unslash($_POST['date'] ?? ''));
        $time = sanitize_text_field(wp_unslash($_POST['time'] ?? ''));
        $note = sanitize_text_field(wp_unslash($_POST['note'] ?? ''));
        $valid = false;
        foreach ($this->available_slots($date) as $slot) if ($slot['time'] === $time && $slot['available']) $valid = true;
        if (!$valid) wp_die('Termin nije raspoloživ ili nije dio važećeg rasporeda.');
        $duration = max(15, (int) $this->settings()['duration']);
        $end = gmdate('H:i:s', strtotime($time . ':00') + $duration * MINUTE_IN_SECONDS);
        global $wpdb; $now = current_time('mysql');
        $wpdb->insert($this->table(), array('booking_date'=>$date,'start_time'=>$time . ':00','end_time'=>$end,'status'=>'blocked','entry_type'=>'block','reason'=>'','admin_note'=>$note,'created_at'=>$now,'updated_at'=>$now), array('%s','%s','%s','%s','%s','%s','%s','%s','%s'));
        wp_safe_redirect(admin_url('admin.php?page=most-booking&updated=1')); exit;
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) return;
        $s = $this->settings(); $names = array(1=>'Ponedjeljak',2=>'Utorak',3=>'Srijeda',4=>'Četvrtak',5=>'Petak',6=>'Subota',7=>'Nedjelja');
        ?>
        <div class="wrap most-admin"><h1>Booking App – Postavke</h1><?php if (isset($_GET['updated'])) : ?><div class="notice notice-success"><p>Postavke su sačuvane.</p></div><?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="most_booking_settings"><?php wp_nonce_field('most_booking_settings'); ?>
        <table class="form-table"><tr><th>E-mail za obavijesti</th><td><input type="email" class="regular-text" name="notification_email" value="<?php echo esc_attr($s['notification_email']); ?>" required></td></tr>
        <tr><th>Adresa centra</th><td><input type="text" class="regular-text" name="contact_address" value="<?php echo esc_attr($s['contact_address']); ?>" maxlength="190"></td></tr>
        <tr><th>Kontakt telefon</th><td><input type="text" class="regular-text" name="contact_phone" value="<?php echo esc_attr($s['contact_phone']); ?>" maxlength="60"></td></tr>
        <tr><th>Trajanje termina</th><td><input type="number" name="duration" min="15" step="15" value="<?php echo absint($s['duration']); ?>"> minuta</td></tr>
        <tr><th>Najranija rezervacija</th><td><input type="number" name="lead_hours" min="0" value="<?php echo absint($s['lead_hours']); ?>"> sati unaprijed</td></tr>
        <tr><th>Period za rezervacije</th><td><input type="number" name="days_ahead" min="1" max="365" value="<?php echo absint($s['days_ahead']); ?>"> dana unaprijed</td></tr>
        <tr><th>Vrijeme dnevnog podsjetnika</th><td><input type="time" name="reminder_time" value="<?php echo esc_attr($s['reminder_time']); ?>" required><p class="description">Podsjetnik se šalje na dan potvrđenog termina. Za pouzdan rad WordPress Cron i slanje e-maila moraju biti aktivni.</p></td></tr>
        <tr><th>Podsjetnik Centru MOST</th><td><label><input type="checkbox" name="default_admin_reminder" value="1" <?php checked(!empty($s['default_admin_reminder'])); ?>> Automatski uključi podsjetnik Centru za nove javne zahtjeve</label></td></tr></table>
        <h2>Dostupni termini</h2><table class="widefat striped" style="max-width:720px"><thead><tr><th>Dan</th><th>Dostupno</th><th>Od</th><th>Do</th></tr></thead><tbody>
        <?php foreach ($names as $day=>$name) : $rule=$s['schedule'][$day]; ?><tr><td><strong><?php echo esc_html($name); ?></strong></td><td><input type="checkbox" name="schedule[<?php echo $day; ?>][enabled]" value="1" <?php checked(!empty($rule['enabled'])); ?>></td><td><input type="time" name="schedule[<?php echo $day; ?>][start]" value="<?php echo esc_attr($rule['start']); ?>"></td><td><input type="time" name="schedule[<?php echo $day; ?>][end]" value="<?php echo esc_attr($rule['end']); ?>"></td></tr><?php endforeach; ?>
        </tbody></table><?php submit_button('Sačuvaj postavke'); ?></form></div>
        <?php $this->admin_styles();
    }

    public function save_settings() {
        if (!current_user_can('manage_options')) wp_die('Nemate ovlaštenje.');
        check_admin_referer('most_booking_settings');
        $defaults = self::defaults(); $schedule = array();
        for ($day=1;$day<=7;$day++) {
            $posted = isset($_POST['schedule'][$day]) ? wp_unslash($_POST['schedule'][$day]) : array();
            $start = isset($posted['start']) && preg_match('/^\d{2}:\d{2}$/', $posted['start']) ? $posted['start'] : '09:00';
            $end = isset($posted['end']) && preg_match('/^\d{2}:\d{2}$/', $posted['end']) ? $posted['end'] : '20:00';
            $schedule[$day] = array('enabled'=>!empty($posted['enabled']),'start'=>$start,'end'=>$end);
        }
        update_option(self::OPTION, array(
            'notification_email'=>sanitize_email(wp_unslash($_POST['notification_email'] ?? $defaults['notification_email'])),
            'contact_address'=>sanitize_text_field(wp_unslash($_POST['contact_address'] ?? $defaults['contact_address'])),
            'contact_phone'=>sanitize_text_field(wp_unslash($_POST['contact_phone'] ?? $defaults['contact_phone'])),
            'duration'=>max(15,absint($_POST['duration'] ?? 60)),
            'lead_hours'=>max(0,absint($_POST['lead_hours'] ?? 2)),
            'days_ahead'=>min(365,max(1,absint($_POST['days_ahead'] ?? 60))),
            'reminder_time'=>isset($_POST['reminder_time']) && preg_match('/^\d{2}:\d{2}$/', wp_unslash($_POST['reminder_time'])) ? sanitize_text_field(wp_unslash($_POST['reminder_time'])) : $defaults['reminder_time'],
            'default_admin_reminder'=>!empty($_POST['default_admin_reminder']) ? 1 : 0,
            'schedule'=>$schedule,
        ));
        $this->reschedule_future_reminders();
        wp_safe_redirect(admin_url('admin.php?page=most-booking-settings&updated=1')); exit;
    }

    private function admin_styles() { ?>
        <style>.most-admin-grid{display:grid;grid-template-columns:minmax(300px,1fr) minmax(300px,1fr);gap:18px;margin:18px 0}.most-admin-panel{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;margin:0 0 18px}.most-admin-grid .most-admin-panel form{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}.most-admin-panel label{display:grid;gap:5px}.most-admin-fields{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:16px}.most-admin-fields input,.most-admin-fields select,.most-admin-fields textarea{width:100%;max-width:none}.most-admin-wide{grid-column:1/-1}.most-admin-check{display:flex!important;grid-template-columns:20px 1fr;align-items:start;margin:10px 0;max-width:760px}.most-admin-check input{margin-top:2px}.most-admin-booking-form{max-width:920px}.most-status{display:inline-block;padding:4px 9px;border-radius:999px;font-weight:600}.most-status-pending{background:#fff3cd;color:#6f5200}.most-status-approved{background:#dff2e6;color:#126c36}.most-status-rejected,.most-status-cancelled{background:#f3f4f5;color:#50575e}.most-status-blocked{background:#e8e3f5;color:#563d7c}.most-reminder-admin{display:grid;gap:4px;min-width:105px}.most-reminder-admin label{display:flex;align-items:center;gap:4px}.most-reminder-admin .button{justify-self:start;margin-top:3px}.most-bookings-table td{vertical-align:top}@media(max-width:782px){.most-admin-grid,.most-admin-fields{grid-template-columns:1fr}.most-admin-wide{grid-column:auto}.most-bookings-table{display:block;overflow-x:auto}}</style>
    <?php }
}

register_activation_hook(__FILE__, array('MOST_Booking_Plugin', 'activate'));
MOST_Booking_Plugin::instance();
