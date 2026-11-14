<?php
if (!defined('ABSPATH')) exit;

final class ABHSP_Platform {
    private static $instance = null;
    private $manufacturer_data = array(
        'Ackermann GmbH' => array(
            'description' => 'Maßgeschneiderte Fahrzeugaufbauten für Abschlepp-, Transport- und Logistiklösungen. Innovative Leichtbaukonzepte und intelligente Nutzlastlösungen stehen für professionellen Fahrzeugeinsatz mit Ackermann.',
            'specialties' => 'Abschleppfahrzeuge • Autotransporter • Kofferaufbauten • Zusatzachsen'
        ),
        'Flexmo (Kögl GmbH)' => array(
            'description' => 'Modulare Fahrzeugeinrichtungen „Made in Germany“ für nahezu jede Branche. Flexible Stauraumlösungen sorgen für Ordnung, Sicherheit und maximale Effizienz im Arbeitsalltag.',
            'specialties' => 'Fahrzeugeinrichtungen • Regalsysteme • Individualausbau'
        ),
        'Hahlbrock Fahrzeugausbau' => array(
            'description' => 'Professionelle Innenausbauten und Kühlfahrzeuge für höchste Hygienestandards. Individuelle Lösungen ermöglichen den sicheren Transport von Lebensmitteln, Pharmazeutika und Tiefkühlware.',
            'specialties' => 'GFK-Ausbau • Kühlfahrzeuge • Pharmalogistik • Mehrkammerlösungen'
        ),
        'Junge Fahrzeugbau' => array(
            'description' => 'Innovative Aufbaulösungen für den modernen Waren- und Stückguttransport. Vom Leichtbaukoffer bis zum Pritschen- oder Planenaufbau bietet Junge für nahezu jede Transportaufgabe die passende Lösung.',
            'specialties' => 'Kofferaufbauten • Pritschen • Planenaufbauten • Leichtbau'
        ),
        'Kress Fahrzeugbau' => array(
            'description' => 'Hochwertige Kühl- und Tiefkühlkoffer für temperaturempfindliche Waren. Innovative Kofferaufbauten mit hervorragender Isolation, geringem Eigengewicht und flexiblen Mehrkammerlösungen für eine effiziente Logistik.',
            'specialties' => 'Kühlkoffer • Tiefkühlkoffer • Pharma-Transporte • Mehrkammerlösungen'
        ),
        'Schoon Fahrzeugsysteme' => array(
            'description' => 'Robuste Fahrzeugaufbauten für Kommunal-, Bau- und Spezialfahrzeuge. Ergänzt wird das Portfolio durch individuelle Mobilitätslösungen für den sicheren Personentransport.',
            'specialties' => 'Kipper • Pritschen • Kommunalfahrzeuge • Behindertentransport'
        ),
    );

    public static function boot() {
        if (null === self::$instance) self::$instance = new self();
    }

    public static function activate() {
        self::register_types();
        self::seed_manufacturers();
        if (!get_role('abh_partner')) {
            add_role('abh_partner', 'ABH Aufbauhersteller', array('read' => true));
        }
        flush_rewrite_rules();
    }

    private function __construct() {
        add_action('init', array(__CLASS__, 'register_types'));
        add_action('init', array($this, 'register_assets'));
        add_action('init', array($this, 'handle_frontend_posts'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_abh_manufacturer', array($this, 'save_manufacturer_meta'));
        add_action('save_post_abh_vehicle', array($this, 'save_vehicle_meta'));
        add_action('show_user_profile', array($this, 'user_manufacturer_field'));
        add_action('edit_user_profile', array($this, 'user_manufacturer_field'));
        add_action('personal_options_update', array($this, 'save_user_manufacturer_field'));
        add_action('edit_user_profile_update', array($this, 'save_user_manufacturer_field'));
        add_action('admin_menu', array($this, 'settings_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_abhsp_review_submission', array($this, 'review_submission'));
        add_shortcode('abh_fahrzeugfinder', array($this, 'shortcode_finder'));
        add_shortcode('abh_fahrzeugkarten', array($this, 'shortcode_featured'));
        add_shortcode('abh_aktuelles', array($this, 'shortcode_news'));
        add_shortcode('abh_partnerportal', array($this, 'shortcode_portal'));
        add_shortcode('abh_fahrzeuganfrage', array($this, 'shortcode_inquiry'));
    }

    public static function register_types() {
        register_post_type('abh_vehicle', array(
            'labels' => array('name' => 'ABH Fahrzeuge', 'singular_name' => 'Fahrzeug', 'add_new_item' => 'Fahrzeug hinzufügen', 'edit_item' => 'Fahrzeug bearbeiten'),
            'public' => true, 'has_archive' => 'fahrzeugfinder', 'rewrite' => array('slug' => 'fahrzeug', 'with_front' => false),
            'show_in_rest' => true, 'menu_icon' => 'dashicons-car', 'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
        ));
        register_post_type('abh_update', array(
            'labels' => array('name' => 'ABH Aktuelles', 'singular_name' => 'Aktuelles', 'add_new_item' => 'Aktuelles hinzufügen', 'edit_item' => 'Aktuelles bearbeiten'),
            'public' => true, 'has_archive' => 'aktuelles-hersteller', 'rewrite' => array('slug' => 'hersteller-aktuelles', 'with_front' => false),
            'show_in_rest' => true, 'menu_icon' => 'dashicons-megaphone', 'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
        ));
        register_post_type('abh_manufacturer', array(
            'labels' => array('name' => 'Aufbauhersteller', 'singular_name' => 'Aufbauhersteller'),
            'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_icon' => 'dashicons-building',
            'supports' => array('title', 'editor', 'thumbnail'),
        ));
        register_post_type('abh_submission', array(
            'labels' => array('name' => 'ABH Einreichungen', 'singular_name' => 'Einreichung'),
            'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_icon' => 'dashicons-clipboard',
            'supports' => array('title', 'editor'),
        ));
    }

    private static function seed_manufacturers() {
        $items = (new ReflectionClass(__CLASS__))->getProperty('manufacturer_data');
        $items->setAccessible(true);
        $data = $items->getValue(null);
        foreach ($data as $name => $profile) {
            $existing = get_page_by_title($name, OBJECT, 'abh_manufacturer');
            if ($existing) continue;
            $id = wp_insert_post(array('post_type' => 'abh_manufacturer', 'post_status' => 'publish', 'post_title' => $name, 'post_content' => $profile['description']));
            if (!is_wp_error($id) && $id) update_post_meta($id, '_abhsp_specialties', $profile['specialties']);
        }
    }

    public function register_assets() {
        wp_register_style('abhsp-platform', ABHSP_URL . 'assets/abh-platform.css', array(), ABHSP_VERSION);
        wp_register_script('abhsp-platform', ABHSP_URL . 'assets/abh-platform.js', array(), ABHSP_VERSION, true);
    }

    private function assets() {
        wp_enqueue_style('abhsp-platform');
        wp_enqueue_script('abhsp-platform');
    }

    public function add_meta_boxes() {
        add_meta_box('abhsp_manufacturer', 'Herstellerprofil & Anfragen', array($this, 'manufacturer_metabox'), 'abh_manufacturer', 'normal', 'high');
        add_meta_box('abhsp_vehicle', 'Fahrzeugdaten', array($this, 'vehicle_metabox'), 'abh_vehicle', 'normal', 'high');
        add_meta_box('abhsp_submission_review', 'Einreichung prüfen', array($this, 'submission_metabox'), 'abh_submission', 'side', 'high');
    }

    public function manufacturer_metabox($post) {
        wp_nonce_field('abhsp_save_manufacturer', 'abhsp_manufacturer_nonce');
        $email = get_post_meta($post->ID, '_abhsp_email', true);
        $specialties = get_post_meta($post->ID, '_abhsp_specialties', true);
        echo '<p><label for="abhsp_email"><strong>Anfragen-E-Mail</strong></label><input class="widefat" type="email" id="abhsp_email" name="abhsp_email" value="' . esc_attr($email) . '" placeholder="anfragen@hersteller.de"></p>';
        echo '<p class="description">Preis- und Verfügbarkeitsanfragen zu den Fahrzeugen dieses Herstellers werden an diese Adresse gesendet.</p>';
        echo '<p><label for="abhsp_specialties"><strong>Schwerpunkte</strong></label><textarea class="widefat" rows="3" id="abhsp_specialties" name="abhsp_specialties">' . esc_textarea($specialties) . '</textarea></p>';
    }

    public function save_manufacturer_meta($post_id) {
        if (!isset($_POST['abhsp_manufacturer_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['abhsp_manufacturer_nonce'])), 'abhsp_save_manufacturer')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        update_post_meta($post_id, '_abhsp_email', sanitize_email(wp_unslash($_POST['abhsp_email'] ?? '')));
        update_post_meta($post_id, '_abhsp_specialties', sanitize_textarea_field(wp_unslash($_POST['abhsp_specialties'] ?? '')));
    }

    public function vehicle_metabox($post) {
        wp_nonce_field('abhsp_save_vehicle', 'abhsp_vehicle_nonce');
        $fields = array(
            'manufacturer_id' => 'Hersteller-ID',
            'vehicle_type' => 'Fahrzeugart',
            'body_type' => 'Aufbauart',
            'application' => 'Einsatzbereich',
            'base_vehicle' => 'Basisfahrzeug',
            'location' => 'Standort',
            'payload' => 'Nutzlast',
            'availability' => 'Verfügbarkeit',
            'featured' => 'Auf Startseite zeigen',
            'featured_order' => 'Reihenfolge Startseite'
        );
        foreach ($fields as $key => $label) {
            $value = get_post_meta($post->ID, '_abhsp_' . $key, true);
            if ($key === 'availability') {
                echo '<p><label><strong>' . esc_html($label) . '</strong></label><select class="widefat" name="abhsp_' . esc_attr($key) . '">';
                foreach (array('available'=>'Sofort verfügbar','on_request'=>'Auf Anfrage','preparing'=>'In Vorbereitung','reserved'=>'Reserviert','sold'=>'Verkauft') as $val=>$text) echo '<option value="' . esc_attr($val) . '" ' . selected($value ?: 'available', $val, false) . '>' . esc_html($text) . '</option>';
                echo '</select></p>';
            } elseif ($key === 'featured') {
                echo '<p><label><input type="checkbox" name="abhsp_featured" value="1" ' . checked($value, '1', false) . '> ' . esc_html($label) . '</label></p>';
            } else {
                echo '<p><label><strong>' . esc_html($label) . '</strong><input class="widefat" type="' . ($key === 'featured_order' ? 'number' : 'text') . '" name="abhsp_' . esc_attr($key) . '" value="' . esc_attr($value) . '"></label></p>';
            }
        }
        echo '<p class="description">Öffentliche Preise werden nicht angezeigt. Anfragen laufen über „Preis und Verfügbarkeit anfragen“.</p>';
    }

    public function save_vehicle_meta($post_id) {
        if (!isset($_POST['abhsp_vehicle_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['abhsp_vehicle_nonce'])), 'abhsp_save_vehicle')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $keys = array('manufacturer_id','vehicle_type','body_type','application','base_vehicle','location','payload','availability','featured_order');
        foreach ($keys as $key) update_post_meta($post_id, '_abhsp_' . $key, sanitize_text_field(wp_unslash($_POST['abhsp_' . $key] ?? '')));
        update_post_meta($post_id, '_abhsp_featured', isset($_POST['abhsp_featured']) ? '1' : '0');
    }

    public function user_manufacturer_field($user) {
        if (!current_user_can('manage_options')) return;
        $selected = absint(get_user_meta($user->ID, '_abhsp_manufacturer_id', true));
        $manufacturers = get_posts(array('post_type'=>'abh_manufacturer','numberposts'=>-1,'orderby'=>'title','order'=>'ASC'));
        echo '<h2>ABH Partnerkonto</h2><table class="form-table"><tr><th><label for="abhsp_manufacturer_id">Zugeordneter Hersteller</label></th><td><select name="abhsp_manufacturer_id" id="abhsp_manufacturer_id"><option value="0">Keiner</option>';
        foreach ($manufacturers as $manufacturer) echo '<option value="' . esc_attr($manufacturer->ID) . '" ' . selected($selected, $manufacturer->ID, false) . '>' . esc_html($manufacturer->post_title) . '</option>';
        echo '</select><p class="description">Partner sehen im Frontend ausschließlich Inhalte des zugeordneten Herstellers.</p></td></tr></table>';
    }

    public function save_user_manufacturer_field($user_id) {
        if (!current_user_can('manage_options')) return;
        update_user_meta($user_id, '_abhsp_manufacturer_id', absint($_POST['abhsp_manufacturer_id'] ?? 0));
    }

    public function settings_menu() {
        add_options_page('ABH Solutions Plattform', 'ABH Solutions', 'manage_options', 'abhsp-settings', array($this, 'settings_page'));
    }

    public function register_settings() {
        register_setting('abhsp_settings', 'abhsp_moderation_email', array('sanitize_callback'=>'sanitize_email'));
        register_setting('abhsp_settings', 'abhsp_inquiry_cc', array('sanitize_callback'=>'sanitize_email'));
    }

    public function settings_page() {
        echo '<div class="wrap"><h1>ABH Solutions Plattform</h1><form method="post" action="options.php">';
        settings_fields('abhsp_settings');
        echo '<table class="form-table"><tr><th><label for="abhsp_moderation_email">Zentrale Freigabe-E-Mail</label></th><td><input class="regular-text" type="email" id="abhsp_moderation_email" name="abhsp_moderation_email" value="' . esc_attr(get_option('abhsp_moderation_email', get_option('admin_email'))) . '"><p class="description">Neue Fahrzeuge und Aktuelles warten immer auf die zentrale Freigabe.</p></td></tr>';
        echo '<tr><th><label for="abhsp_inquiry_cc">Kopie an ABH bei Fahrzeuganfragen (optional)</label></th><td><input class="regular-text" type="email" id="abhsp_inquiry_cc" name="abhsp_inquiry_cc" value="' . esc_attr(get_option('abhsp_inquiry_cc', '')) . '"></td></tr></table>';
        submit_button('Einstellungen speichern'); echo '</form></div>';
    }

    public function handle_frontend_posts() {
        if (isset($_POST['abhsp_portal_submit'])) $this->handle_portal_submit();
        if (isset($_POST['abhsp_inquiry_submit'])) $this->handle_inquiry_submit();
    }

    public function shortcode_portal() {
        $this->assets();
        if (!is_user_logged_in()) {
            return '<section class="abhsp-shell abhsp-login"><div class="abhsp-panel"><p class="abhsp-eyebrow">ABH PARTNERBEREICH</p><h2>Willkommen im Herstellerportal</h2><p>Melden Sie sich an, um Fahrzeuge oder Aktuelles zur Freigabe einzureichen.</p>' . wp_login_form(array('echo'=>false,'remember'=>true)) . '</div></section>';
        }
        $user = wp_get_current_user();
        $manufacturer_id = absint(get_user_meta($user->ID, '_abhsp_manufacturer_id', true));
        if (!current_user_can('manage_options') && (!in_array('abh_partner', (array)$user->roles, true) || !$manufacturer_id)) {
            return '<div class="abhsp-notice abhsp-notice-error">Für dieses Konto ist noch kein Herstellerprofil hinterlegt. Bitte wenden Sie sich an ABH Solutions.</div>';
        }
        $notice = isset($_GET['abhsp_sent']) ? '<div class="abhsp-notice">Ihre Einreichung wurde an die zentrale Redaktion übermittelt. Sie erscheint nach der Freigabe auf der Website.</div>' : '';
        $manufacturer = $manufacturer_id ? get_the_title($manufacturer_id) : 'ABH Administration';
        ob_start();
        echo '<section class="abhsp-shell abhsp-portal"><div class="abhsp-portal-head"><div><p class="abhsp-eyebrow">PARTNERPORTAL</p><h2>Guten Tag, ' . esc_html($manufacturer) . '</h2><p>Was möchten Sie einstellen? Ihre Inhalte werden vor der Veröffentlichung zentral geprüft.</p></div><span class="abhsp-step-chip">Einfach in wenigen Schritten</span></div>' . $notice;
        echo '<form class="abhsp-panel abhsp-wizard" method="post" enctype="multipart/form-data">';
        wp_nonce_field('abhsp_portal_submit', 'abhsp_portal_nonce');
        echo '<input type="hidden" name="abhsp_portal_submit" value="1"><label class="abhsp-label" for="abhsp_content_type">Inhaltstyp auswählen</label><select id="abhsp_content_type" name="content_type" class="abhsp-select" required><option value="">Bitte auswählen</option><option value="vehicle">Fahrzeug einstellen</option><option value="news">Aktuelles veröffentlichen</option></select>';
        echo '<div class="abhsp-form-grid"><div class="abhsp-field abhsp-common"><label for="abhsp_title">Titel</label><input id="abhsp_title" name="title" required maxlength="120" placeholder="Zum Beispiel: Kühlfahrzeug auf Basis MAN TGE"><small>Ein klarer Titel hilft Interessenten, Ihr Angebot sofort einzuordnen.</small></div>';
        echo '<div class="abhsp-field abhsp-common"><label for="abhsp_summary">Kurzbeschreibung</label><textarea id="abhsp_summary" name="summary" rows="3" maxlength="500" required placeholder="Was zeichnet Ihr Angebot aus?"></textarea><small>Beschreiben Sie den wichtigsten Vorteil in wenigen Sätzen.</small></div>';
        echo '<div class="abhsp-field abhsp-vehicle-fields"><label for="abhsp_vehicle_type">Fahrzeugart</label><input id="abhsp_vehicle_type" name="vehicle_type" placeholder="z. B. Kastenwagen, Pritsche"></div>';
        echo '<div class="abhsp-field abhsp-vehicle-fields"><label for="abhsp_body_type">Aufbauart</label><input id="abhsp_body_type" name="body_type" placeholder="z. B. Kühlkoffer, Regalsystem"></div>';
        echo '<div class="abhsp-field abhsp-vehicle-fields"><label for="abhsp_application">Einsatzbereich</label><input id="abhsp_application" name="application" placeholder="z. B. Lebensmittel- oder Pharmalogistik"></div>';
        echo '<div class="abhsp-field abhsp-vehicle-fields"><label for="abhsp_base_vehicle">Basisfahrzeug</label><input id="abhsp_base_vehicle" name="base_vehicle" placeholder="z. B. MAN TGE"></div>';
        echo '<div class="abhsp-field abhsp-vehicle-fields"><label for="abhsp_location">Standort</label><input id="abhsp_location" name="location" placeholder="Ort oder PLZ"></div>';
        echo '<div class="abhsp-field abhsp-vehicle-fields"><label for="abhsp_payload">Nutzlast</label><input id="abhsp_payload" name="payload" placeholder="z. B. 820 kg"></div>';
        echo '<div class="abhsp-field abhsp-vehicle-fields"><label for="abhsp_availability">Verfügbarkeit</label><select id="abhsp_availability" name="availability"><option value="available">Sofort verfügbar</option><option value="on_request">Auf Anfrage</option><option value="preparing">In Vorbereitung</option><option value="reserved">Reserviert</option></select></div>';
        echo '<div class="abhsp-field abhsp-vehicle-fields"><label for="abhsp_image">Fahrzeugbild</label><input id="abhsp_image" type="file" name="vehicle_image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG oder WebP. Preisangaben werden nicht veröffentlicht.</small></div>';
        echo '<div class="abhsp-field abhsp-news-fields"><label for="abhsp_news_kind">Art des Aktuellen</label><select id="abhsp_news_kind" name="news_kind"><option value="novelty">Neuheit</option><option value="trade_fair">Messe / Hausmesse</option><option value="event">Veranstaltung</option><option value="sale_of_month">Verkauf des Monats</option><option value="other">Sonstige Nachricht</option></select></div>';
        echo '<div class="abhsp-field abhsp-news-fields"><label for="abhsp_event_date">Termin (falls zutreffend)</label><input id="abhsp_event_date" type="date" name="event_date"></div>';
        echo '<div class="abhsp-field abhsp-news-fields"><label for="abhsp_event_location">Ort (falls zutreffend)</label><input id="abhsp_event_location" name="event_location" placeholder="Ort / Messegelände"></div>';
        echo '<div class="abhsp-field abhsp-common"><label for="abhsp_details">Weitere Informationen</label><textarea id="abhsp_details" name="details" rows="5" placeholder="Ergänzen Sie Details, technische Besonderheiten oder Hinweise."></textarea><small>Bitte keine Preise eintragen. Interessenten fragen Preise direkt beim Hersteller an.</small></div></div>';
        echo '<label class="abhsp-check"><input type="checkbox" name="feature_home" value="1"> Fahrzeug zusätzlich für die Startseite vormerken <span>(maximal sechs; Auswahl durch ABH)</span></label>';
        echo '<label class="abhsp-check"><input type="checkbox" name="privacy" value="1" required> Ich bestätige, dass die Angaben zur Veröffentlichung freigegeben sind.</label>';
        echo '<div class="abhsp-wizard-actions"><button class="abhsp-button" type="submit">Zur zentralen Freigabe senden <span aria-hidden="true">→</span></button><p>Sie erhalten eine E-Mail, sobald Ihre Einreichung geprüft wurde.</p></div></form></section>';
        return ob_get_clean();
    }

    private function handle_portal_submit() {
        if (!isset($_POST['abhsp_portal_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['abhsp_portal_nonce'])), 'abhsp_portal_submit')) wp_die('Sicherheitsprüfung fehlgeschlagen.');
        if (!is_user_logged_in()) wp_die('Bitte melden Sie sich an.');
        $user = wp_get_current_user();
        $manufacturer_id = absint(get_user_meta($user->ID, '_abhsp_manufacturer_id', true));
        if (!current_user_can('manage_options') && (!in_array('abh_partner', (array)$user->roles, true) || !$manufacturer_id)) wp_die('Kein Herstellerzugriff hinterlegt.');
        $type = sanitize_key(wp_unslash($_POST['content_type'] ?? ''));
        if (!in_array($type, array('vehicle','news'), true)) wp_die('Bitte wählen Sie einen Inhaltstyp.');
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $summary = sanitize_textarea_field(wp_unslash($_POST['summary'] ?? ''));
        if (!$title || !$summary || empty($_POST['privacy'])) wp_die('Bitte füllen Sie die Pflichtfelder aus und bestätigen Sie die Veröffentlichung.');
        $payload = array(
            'summary' => $summary,
            'details' => sanitize_textarea_field(wp_unslash($_POST['details'] ?? '')),
            'feature_home' => !empty($_POST['feature_home']) ? 1 : 0,
        );
        if ($type === 'vehicle') {
            foreach (array('vehicle_type','body_type','application','base_vehicle','location','payload') as $key) $payload[$key] = sanitize_text_field(wp_unslash($_POST[$key] ?? ''));
            $payload['availability'] = sanitize_key(wp_unslash($_POST['availability'] ?? 'available'));
            if (!in_array($payload['availability'], array('available','on_request','preparing','reserved'), true)) $payload['availability'] = 'available';
        } else {
            $payload['news_kind'] = sanitize_key(wp_unslash($_POST['news_kind'] ?? 'other'));
            $payload['event_date'] = sanitize_text_field(wp_unslash($_POST['event_date'] ?? ''));
            $payload['event_location'] = sanitize_text_field(wp_unslash($_POST['event_location'] ?? ''));
            $payload['feature_home'] = 0;
        }
        $image_id = 0;
        if ($type === 'vehicle' && !empty($_FILES['vehicle_image']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $image_id = media_handle_upload('vehicle_image', 0);
            if (is_wp_error($image_id)) wp_die('Das Bild konnte nicht hochgeladen werden. Bitte versuchen Sie es erneut.');
        }
        $submission_id = wp_insert_post(array(
            'post_type'=>'abh_submission','post_status'=>'pending','post_title'=>$title,
            'post_content'=>$payload['details'],'post_author'=>$user->ID
        ), true);
        if (is_wp_error($submission_id)) wp_die('Die Einreichung konnte nicht gespeichert werden.');
        update_post_meta($submission_id, '_abhsp_type', $type);
        update_post_meta($submission_id, '_abhsp_manufacturer_id', $manufacturer_id);
        update_post_meta($submission_id, '_abhsp_payload', $payload);
        update_post_meta($submission_id, '_abhsp_summary', $summary);
        update_post_meta($submission_id, '_abhsp_image_id', absint($image_id));
        update_post_meta($submission_id, '_abhsp_review_state', 'pending');
        $moderation = sanitize_email(get_option('abhsp_moderation_email', get_option('admin_email')));
        $review_url = admin_url('post.php?post=' . absint($submission_id) . '&action=edit');
        $subject = 'Neue ABH-Einreichung zur Freigabe: ' . $title;
        $message = '<div style="font-family:Arial,sans-serif;color:#14233f;max-width:640px"><div style="background:#005eab;padding:18px 24px;color:#fff;font-size:18px;font-weight:bold">ABH SOLUTIONS · FREIGABE</div><div style="padding:24px;border:1px solid #e3e9f0"><p>Eine neue Einreichung wartet auf Prüfung.</p><p><strong>Typ:</strong> ' . esc_html($type === 'vehicle' ? 'Fahrzeug' : 'Aktuelles') . '<br><strong>Hersteller:</strong> ' . esc_html(get_the_title($manufacturer_id)) . '<br><strong>Titel:</strong> ' . esc_html($title) . '</p><p>' . nl2br(esc_html($summary)) . '</p><p><a style="display:inline-block;background:#005eab;color:#fff;text-decoration:none;padding:12px 18px;font-weight:bold" href="' . esc_url($review_url) . '">Einreichung prüfen</a></p></div></div>';
        $this->send_html_mail($moderation, $subject, $message);
        wp_safe_redirect(add_query_arg('abhsp_sent', '1', wp_get_referer() ?: home_url('/')));
        exit;
    }

    public function submission_metabox($post) {
        if (!current_user_can('edit_post', $post->ID)) { echo '<p>Keine Berechtigung.</p>'; return; }
        $type = get_post_meta($post->ID, '_abhsp_type', true);
        $payload = get_post_meta($post->ID, '_abhsp_payload', true);
        $payload = is_array($payload) ? $payload : array();
        echo '<p><strong>Typ:</strong> ' . esc_html($type === 'vehicle' ? 'Fahrzeug' : 'Aktuelles') . '<br><strong>Hersteller:</strong> ' . esc_html(get_the_title(absint(get_post_meta($post->ID, '_abhsp_manufacturer_id', true)))) . '</p>';
        echo '<p><strong>Kurzbeschreibung</strong><br>' . esc_html(get_post_meta($post->ID, '_abhsp_summary', true)) . '</p>';
        foreach ($payload as $key=>$value) if ($key !== 'details' && $key !== 'summary') echo '<p><strong>' . esc_html(ucwords(str_replace('_',' ',$key))) . ':</strong> ' . esc_html(is_scalar($value) ? (string)$value : '') . '</p>';
        if (!empty($payload['details'])) echo '<p><strong>Details</strong><br>' . nl2br(esc_html($payload['details'])) . '</p>';
        $state = get_post_meta($post->ID, '_abhsp_review_state', true);
        echo '<p><strong>Freigabestatus:</strong> ' . esc_html($state ?: 'pending') . '</p>';
        if ($post->post_status === 'pending') {
            $approve = wp_nonce_url(admin_url('admin-post.php?action=abhsp_review_submission&decision=approve&submission=' . $post->ID), 'abhsp_review_' . $post->ID);
            $changes = wp_nonce_url(admin_url('admin-post.php?action=abhsp_review_submission&decision=changes&submission=' . $post->ID), 'abhsp_review_' . $post->ID);
            echo '<p><a class="button button-primary" href="' . esc_url($approve) . '">Freigeben & veröffentlichen</a></p><p><label for="abhsp_change_note">Hinweis an Hersteller</label><textarea id="abhsp_change_note" class="widefat" name="abhsp_change_note" rows="3" placeholder="Was soll angepasst werden?"></textarea></p><p><a class="button" href="' . esc_url($changes) . '">Änderung anfordern</a></p>';
        }
    }

    public function review_submission() {
        $id = absint($_GET['submission'] ?? 0);
        if (!$id || !current_user_can('edit_post', $id) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'abhsp_review_' . $id)) wp_die('Freigabe nicht zulässig.');
        $submission = get_post($id);
        if (!$submission || $submission->post_type !== 'abh_submission') wp_die('Einreichung nicht gefunden.');
        $decision = sanitize_key(wp_unslash($_GET['decision'] ?? ''));
        $type = get_post_meta($id, '_abhsp_type', true);
        $manufacturer_id = absint(get_post_meta($id, '_abhsp_manufacturer_id', true));
        $payload = get_post_meta($id, '_abhsp_payload', true);
        $payload = is_array($payload) ? $payload : array();
        if ($decision === 'approve') {
            $target_type = $type === 'vehicle' ? 'abh_vehicle' : 'abh_update';
            $target_id = wp_insert_post(array('post_type'=>$target_type,'post_status'=>'publish','post_title'=>$submission->post_title,'post_content'=>wp_kses_post($payload['details'] ?? ''),'post_excerpt'=>sanitize_textarea_field($payload['summary'] ?? ''),'post_author'=>get_current_user_id()), true);
            if (is_wp_error($target_id)) wp_die('Der Inhalt konnte nicht veröffentlicht werden.');
            update_post_meta($target_id, '_abhsp_manufacturer_id', $manufacturer_id);
            update_post_meta($target_id, '_abhsp_summary', sanitize_textarea_field($payload['summary'] ?? ''));
            if ($type === 'vehicle') {
                foreach (array('vehicle_type','body_type','application','base_vehicle','location','payload','availability') as $key) update_post_meta($target_id, '_abhsp_' . $key, sanitize_text_field($payload[$key] ?? ''));
                $featured = !empty($payload['feature_home']) && $this->featured_count() < 6;
                update_post_meta($target_id, '_abhsp_featured', $featured ? '1' : '0');
                if (!empty($payload['availability']) && $payload['availability'] === 'sold') update_post_meta($target_id, '_abhsp_featured', '0');
                $image_id = absint(get_post_meta($id, '_abhsp_image_id', true));
                if ($image_id) set_post_thumbnail($target_id, $image_id);
            } else {
                foreach (array('news_kind','event_date','event_location') as $key) update_post_meta($target_id, '_abhsp_' . $key, sanitize_text_field($payload[$key] ?? ''));
            }
            update_post_meta($id, '_abhsp_review_state', 'approved');
            update_post_meta($id, '_abhsp_published_id', $target_id);
            wp_update_post(array('ID'=>$id,'post_status'=>'publish'));
            $this->notify_submitter($id, 'approved', '');
        } elseif ($decision === 'changes') {
            update_post_meta($id, '_abhsp_review_state', 'changes');
            update_post_meta($id, '_abhsp_review_note', sanitize_textarea_field(wp_unslash($_GET['note'] ?? '')));
            $this->notify_submitter($id, 'changes', get_post_meta($id, '_abhsp_review_note', true));
        }
        wp_safe_redirect(admin_url('edit.php?post_type=abh_submission'));
        exit;
    }

    private function featured_count() {
        $query = new WP_Query(array('post_type'=>'abh_vehicle','post_status'=>'publish','posts_per_page'=>7,'fields'=>'ids','meta_query'=>array(array('key'=>'_abhsp_featured','value'=>'1'),array('key'=>'_abhsp_availability','value'=>'sold','compare'=>'!='))));
        return (int)$query->found_posts;
    }

    private function notify_submitter($id, $state, $note) {
        $submission = get_post($id);
        if (!$submission) return;
        $user = get_userdata($submission->post_author);
        if (!$user || !is_email($user->user_email)) return;
        $approved = $state === 'approved';
        $subject = $approved ? 'Ihre ABH-Einreichung wurde freigegeben' : 'Bitte prüfen Sie Ihre ABH-Einreichung';
        $body = '<div style="font-family:Arial,sans-serif;color:#14233f"><h2 style="color:#005eab">' . esc_html($subject) . '</h2><p>Guten Tag,</p><p>Ihre Einreichung <strong>' . esc_html($submission->post_title) . '</strong> wurde ' . ($approved ? 'freigegeben und veröffentlicht.' : 'geprüft. Bitte berücksichtigen Sie den folgenden Hinweis:') . '</p>' . ($note ? '<blockquote>' . nl2br(esc_html($note)) . '</blockquote>' : '') . '<p>Viele Grüße<br>Ihr ABH Solutions Team</p></div>';
        $this->send_html_mail($user->user_email, $subject, $body);
    }

    private function shortcode_finder($atts) {
        $this->assets();
        $filters = array();
        foreach (array('vehicle_type'=>'Fahrzeugart','body_type'=>'Aufbauart','application'=>'Einsatzbereich','manufacturer_id'=>'Aufbauhersteller','base_vehicle'=>'Basisfahrzeug','location'=>'Standort') as $key=>$label) {
            $filters[$key] = sanitize_text_field(wp_unslash($_GET['abh_' . $key] ?? ''));
        }
        $meta_query = array('relation'=>'AND', array('key'=>'_abhsp_availability','value'=>'sold','compare'=>'!='));
        foreach ($filters as $key=>$value) if ($value !== '') $meta_query[] = array('key'=>'_abhsp_' . $key,'value'=>$value,'compare'=>'LIKE');
        $args = array('post_type'=>'abh_vehicle','post_status'=>'publish','posts_per_page'=>12,'meta_query'=>$meta_query,'orderby'=>'date','order'=>'DESC');
        $query = new WP_Query($args);
        ob_start();
        echo '<section class="abhsp-shell abhsp-finder"><div class="abhsp-finder-heading"><div><p class="abhsp-eyebrow">ABH READY TO GO</p><h2>Fahrzeugfinder</h2><p>Finden Sie passende Fahrzeuge und Aufbaulösungen unserer Hersteller.</p></div><span class="abhsp-result-count">' . esc_html((string)$query->found_posts) . ' Treffer</span></div>';
        echo '<form class="abhsp-filter" method="get"><div class="abhsp-filter-grid">';
        $manufacturers = get_posts(array('post_type'=>'abh_manufacturer','numberposts'=>-1,'orderby'=>'title','order'=>'ASC'));
        foreach ($filters as $key=>$selected) {
            $label = array('vehicle_type'=>'Fahrzeugart','body_type'=>'Aufbauart','application'=>'Einsatzbereich','manufacturer_id'=>'Aufbauhersteller','base_vehicle'=>'Basisfahrzeug','location'=>'Standort')[$key];
            echo '<label><span>' . esc_html($label) . '</span><select name="abh_' . esc_attr($key) . '"><option value="">Alle ' . esc_html(strtolower($label)) . '</option>';
            if ($key === 'manufacturer_id') {
                foreach ($manufacturers as $m) echo '<option value="' . esc_attr($m->ID) . '" ' . selected($selected,(string)$m->ID,false) . '>' . esc_html($m->post_title) . '</option>';
            } else {
                foreach ($this->distinct_values('_abhsp_' . $key) as $value) echo '<option value="' . esc_attr($value) . '" ' . selected($selected,$value,false) . '>' . esc_html($value) . '</option>';
            }
            echo '</select></label>';
        }
        echo '</div><div class="abhsp-filter-actions"><button class="abhsp-button" type="submit">Fahrzeuge anzeigen</button><a href="' . esc_url(remove_query_arg(array_keys(array_map(function($k){return 'abh_'.$k;}, $filters)))) . '">Filter zurücksetzen</a></div></form>';
        echo '<div class="abhsp-card-grid">';
        if ($query->have_posts()) { while ($query->have_posts()) { $query->the_post(); echo $this->vehicle_card(get_the_ID()); } wp_reset_postdata(); }
        else echo '<div class="abhsp-empty"><h3>Keine passenden Fahrzeuge gefunden</h3><p>Ändern Sie einen Filter oder setzen Sie die Suche zurück.</p></div>';
        echo '</div></section>';
        return ob_get_clean();
    }

    private function distinct_values($meta_key) {
        global $wpdb;
        $values = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_key=%s AND p.post_type='abh_vehicle' AND p.post_status='publish' AND pm.meta_value<>'' ORDER BY pm.meta_value ASC", $meta_key));
        return array_map('strval', (array)$values);
    }

    public function shortcode_featured($atts) {
        $this->assets();
        $atts = shortcode_atts(array('limit'=>6,'featured'=>'1'), $atts, 'abh_fahrzeugkarten');
        $limit = max(1, min(6, absint($atts['limit'])));
        $meta = array(array('key'=>'_abhsp_availability','value'=>'sold','compare'=>'!='));
        if ($atts['featured'] === '1') $meta[] = array('key'=>'_abhsp_featured','value'=>'1');
        $query = new WP_Query(array('post_type'=>'abh_vehicle','post_status'=>'publish','posts_per_page'=>$limit,'meta_query'=>$meta,'meta_key'=>'_abhsp_featured_order','orderby'=>array('meta_value_num'=>'ASC','date'=>'DESC')));
        ob_start(); echo '<section class="abhsp-shell"><div class="abhsp-card-grid">';
        if ($query->have_posts()) { while ($query->have_posts()) { $query->the_post(); echo $this->vehicle_card(get_the_ID()); } wp_reset_postdata(); }
        echo '</div></section>'; return ob_get_clean();
    }

    private function vehicle_card($id) {
        $manufacturer_id = absint(get_post_meta($id, '_abhsp_manufacturer_id', true));
        $status = get_post_meta($id, '_abhsp_availability', true) ?: 'available';
        $status_labels = array('available'=>'Sofort verfügbar','on_request'=>'Auf Anfrage','preparing'=>'In Vorbereitung','reserved'=>'Reserviert','sold'=>'Verkauft');
        $image = get_the_post_thumbnail($id, 'large', array('class'=>'abhsp-card-image'));
        if (!$image) $image = '<div class="abhsp-card-placeholder"><span>ABH</span></div>';
        $data = array(
            'vehicle_type'=>get_post_meta($id,'_abhsp_vehicle_type',true),
            'body_type'=>get_post_meta($id,'_abhsp_body_type',true),
            'base_vehicle'=>get_post_meta($id,'_abhsp_base_vehicle',true),
            'payload'=>get_post_meta($id,'_abhsp_payload',true),
            'location'=>get_post_meta($id,'_abhsp_location',true),
        );
        $facts = array_filter(array($data['base_vehicle'],$data['payload'],$data['location']));
        $html = '<article class="abhsp-card"><a class="abhsp-card-media" href="' . esc_url(get_permalink($id)) . '">' . $image . '<span class="abhsp-status abhsp-status-' . esc_attr($status) . '">' . esc_html($status_labels[$status] ?? 'Auf Anfrage') . '</span></a><div class="abhsp-card-body"><p class="abhsp-card-brand">' . esc_html(get_the_title($manufacturer_id)) . '</p><h3><a href="' . esc_url(get_permalink($id)) . '">' . esc_html(get_the_title($id)) . '</a></h3><p class="abhsp-card-type">' . esc_html(implode(' · ', array_filter(array($data['vehicle_type'],$data['body_type'])))) . '</p>';
        if ($facts) $html .= '<ul class="abhsp-facts">' . implode('', array_map(function($fact){return '<li>' . esc_html($fact) . '</li>';}, $facts)) . '</ul>';
        $html .= '<div class="abhsp-card-footer"><span class="abhsp-price-note">Preis auf Anfrage</span><a class="abhsp-text-link" href="' . esc_url(get_permalink($id)) . '">Details ansehen <span aria-hidden="true">→</span></a></div></div></article>';
        return $html;
    }

    public function shortcode_news($atts) {
        $this->assets();
        $atts = shortcode_atts(array('limit'=>6,'manufacturer'=>''), $atts, 'abh_aktuelles');
        $args = array('post_type'=>'abh_update','post_status'=>'publish','posts_per_page'=>max(1,min(12,absint($atts['limit']))),'orderby'=>'date','order'=>'DESC');
        if ($atts['manufacturer'] !== '') $args['meta_query'] = array(array('key'=>'_abhsp_manufacturer_id','value'=>absint($atts['manufacturer'])));
        $query = new WP_Query($args);
        ob_start(); echo '<section class="abhsp-shell abhsp-news-grid">';
        if ($query->have_posts()) { while ($query->have_posts()) { $query->the_post(); $id=get_the_ID(); $mid=absint(get_post_meta($id,'_abhsp_manufacturer_id',true)); echo '<article class="abhsp-news-card"><a class="abhsp-news-image" href="' . esc_url(get_permalink($id)) . '">' . (get_the_post_thumbnail($id,'large') ?: '<span>ABH SOLUTIONS</span>') . '</a><div class="abhsp-news-content"><p class="abhsp-card-brand">' . esc_html(get_the_title($mid)) . ' · ' . esc_html(get_the_date('', $id)) . '</p><h3><a href="' . esc_url(get_permalink($id)) . '">' . esc_html(get_the_title($id)) . '</a></h3><p>' . esc_html(get_the_excerpt($id)) . '</p><a class="abhsp-text-link" href="' . esc_url(get_permalink($id)) . '">Weiterlesen →</a></div></article>'; } wp_reset_postdata(); }
        else echo '<p class="abhsp-empty">Aktuell gibt es keine Beiträge.</p>';
        echo '</section>'; return ob_get_clean();
    }

    public function shortcode_inquiry($atts) {
        $this->assets();
        $atts = shortcode_atts(array('vehicle_id'=>0), $atts, 'abh_fahrzeuganfrage');
        $id = absint($atts['vehicle_id']) ?: get_the_ID();
        if (!$id || get_post_type($id) !== 'abh_vehicle') return '';
        $notice = isset($_GET['abhsp_inquiry']) ? '<div class="abhsp-notice">Vielen Dank. Ihre Anfrage wurde an den zuständigen Hersteller übermittelt.</div>' : '';
        return $notice . '<form class="abhsp-inquiry abhsp-panel" method="post"><h2>Preis und Verfügbarkeit anfragen</h2><p>Ihre Anfrage geht direkt an den zuständigen Aufbauhersteller. Preise werden individuell auf Anfrage mitgeteilt.</p>' .
            wp_nonce_field('abhsp_inquiry_' . $id, 'abhsp_inquiry_nonce', true, false) .
            '<input type="hidden" name="vehicle_id" value="' . esc_attr($id) . '"><input class="abhsp-honey" type="text" name="company_website" tabindex="-1" autocomplete="off" aria-hidden="true">' .
            '<div class="abhsp-form-grid"><label class="abhsp-field">Ihr Name<input name="name" required autocomplete="name"></label><label class="abhsp-field">E-Mail-Adresse<input name="email" type="email" required autocomplete="email"></label><label class="abhsp-field">Telefon (optional)<input name="phone" type="tel" autocomplete="tel"></label><label class="abhsp-field abhsp-wide">Ihre Nachricht<textarea name="message" rows="4" required placeholder="Wobei dürfen wir Sie unterstützen?"></textarea></label></div><label class="abhsp-check"><input type="checkbox" name="privacy" required> Ich bin mit der Verarbeitung meiner Angaben zur Bearbeitung der Anfrage einverstanden.</label><button class="abhsp-button" type="submit" name="abhsp_inquiry_submit" value="1">Anfrage an Hersteller senden →</button><p class="abhsp-privacy-note">Fahrzeug: ' . esc_html(get_the_title($id)) . ' · Preis auf Anfrage</p></form>';
    }

    private function handle_inquiry_submit() {
        $id = absint($_POST['vehicle_id'] ?? 0);
        if (!$id || get_post_type($id) !== 'abh_vehicle' || !isset($_POST['abhsp_inquiry_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['abhsp_inquiry_nonce'])), 'abhsp_inquiry_' . $id)) wp_die('Die Anfrage konnte nicht geprüft werden.');
        if (!empty($_POST['company_website'])) wp_die('Anfrage nicht zulässig.');
        if (empty($_POST['privacy'])) wp_die('Bitte bestätigen Sie den Datenschutzhinweis.');
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $message_text = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        if (!$name || !is_email($email) || !$message_text) wp_die('Bitte prüfen Sie Ihre Eingaben.');
        $manufacturer_id = absint(get_post_meta($id, '_abhsp_manufacturer_id', true));
        $to = sanitize_email(get_post_meta($manufacturer_id, '_abhsp_email', true));
        if (!$to) $to = sanitize_email(get_option('abhsp_moderation_email', get_option('admin_email')));
        $subject = 'Fahrzeuganfrage: ' . get_the_title($id);
        $body = '<div style="font-family:Arial,sans-serif;color:#14233f;max-width:680px"><div style="background:#005eab;padding:18px 24px;color:#fff;font-size:18px;font-weight:bold">ABH SOLUTIONS · FAHRZEUGANFRAGE</div><div style="padding:24px;border:1px solid #e3e9f0"><h2 style="margin-top:0">Anfrage zu ' . esc_html(get_the_title($id)) . '</h2><p><strong>Preis:</strong> auf Anfrage<br><strong>Hersteller:</strong> ' . esc_html(get_the_title($manufacturer_id)) . '<br><strong>Name:</strong> ' . esc_html($name) . '<br><strong>E-Mail:</strong> ' . esc_html($email) . '<br><strong>Telefon:</strong> ' . esc_html($phone ?: 'Nicht angegeben') . '</p><p><strong>Nachricht</strong><br>' . nl2br(esc_html($message_text)) . '</p><p><a href="' . esc_url(get_permalink($id)) . '">Fahrzeug ansehen</a></p></div></div>';
        $headers = array('Reply-To: ' . $name . ' <' . $email . '>');
        $cc = sanitize_email(get_option('abhsp_inquiry_cc', ''));
        if ($cc) $headers[] = 'Cc: ' . $cc;
        $this->send_html_mail($to, $subject, $body, $headers);
        wp_safe_redirect(add_query_arg('abhsp_inquiry', 'sent', get_permalink($id)) . '#fahrzeuganfrage');
        exit;
    }

    private function send_html_mail($to, $subject, $body, $headers = array()) {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        return wp_mail($to, $subject, $body, $headers);
    }
}
