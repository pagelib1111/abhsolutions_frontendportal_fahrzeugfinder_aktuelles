<?php
if (!defined('ABSPATH')) exit;

final class ABHSP_Platform {
    private static $instance = null;
    private static $manufacturer_data = array(
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
        if (!get_role('abh_partner_pending')) {
            add_role('abh_partner_pending', 'ABH Zugang wartet auf Freigabe', array('read' => true));
        }
        self::ensure_portal_page();
        flush_rewrite_rules();
    }

    public static function ensure_portal_page() {
        $saved_id = absint(get_option('abhsp_portal_page_id', 0));
        $saved_page = $saved_id ? get_post($saved_id) : null;
        if ($saved_page && $saved_page->post_type === 'page' && has_shortcode($saved_page->post_content, 'abh_partnerportal')) {
            return $saved_id;
        }

        $existing_pages = get_pages(array('post_status' => array('publish', 'private', 'draft', 'pending')));
        foreach ($existing_pages as $page) {
            if (has_shortcode($page->post_content, 'abh_partnerportal')) {
                update_option('abhsp_portal_page_id', $page->ID);
                return $page->ID;
            }
        }

        $page = get_page_by_path('herstellerportal', OBJECT, 'page');
        if ($page && trim($page->post_content) === '') {
            $page_id = wp_update_post(array('ID' => $page->ID, 'post_content' => '[abh_partnerportal]'), true);
        } elseif (!$page) {
            $page_id = wp_insert_post(array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => 'Herstellerportal',
                'post_name' => 'herstellerportal',
                'post_content' => '[abh_partnerportal]',
            ), true);
        } else {
            $page_id = wp_insert_post(array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => 'ABH Herstellerportal',
                'post_name' => 'abh-herstellerportal',
                'post_content' => '[abh_partnerportal]',
            ), true);
        }

        if (!is_wp_error($page_id) && $page_id) {
            update_option('abhsp_portal_page_id', absint($page_id));
            return absint($page_id);
        }
        return 0;
    }

    private function __construct() {
        add_action('admin_init', array(__CLASS__, 'ensure_portal_page'));

        add_action('init', array(__CLASS__, 'register_types'));
        add_action('init', array($this, 'ensure_partner_roles'));
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
        add_action('admin_post_nopriv_abhsp_partner_register', array($this, 'handle_partner_registration'));
        add_action('admin_post_abhsp_partner_register', array($this, 'handle_partner_registration'));
        add_action('admin_post_nopriv_abhsp_verify_partner', array($this, 'verify_partner_email'));
        add_action('admin_post_abhsp_verify_partner', array($this, 'verify_partner_email'));
        add_action('admin_post_abhsp_partner_decision', array($this, 'handle_partner_decision'));
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

    public function ensure_partner_roles() {
        if (!get_role('abh_partner')) add_role('abh_partner', 'ABH Aufbauhersteller', array('read' => true));
        if (!get_role('abh_partner_pending')) add_role('abh_partner_pending', 'ABH Zugang wartet auf Freigabe', array('read' => true));
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
            if ($key === 'manufacturer_id') {
                $manufacturers = get_posts(array('post_type'=>'abh_manufacturer','numberposts'=>-1,'orderby'=>'title','order'=>'ASC'));
                echo '<p><label><strong>' . esc_html($label) . '</strong><select class="widefat" name="abhsp_manufacturer_id"><option value="">Hersteller auswählen</option>';
                foreach ($manufacturers as $manufacturer) echo '<option value="' . esc_attr($manufacturer->ID) . '" ' . selected((string)$value,(string)$manufacturer->ID,false) . '>' . esc_html($manufacturer->post_title) . '</option>';
                echo '</select></label></p>';
            } elseif ($key === 'availability') {
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
        add_options_page('ABH Partnerzugänge', 'ABH Partnerzugänge', 'manage_options', 'abhsp-partners', array($this, 'partner_requests_page'));
        add_submenu_page(null, 'ABH Einreichung prüfen', 'ABH Einreichung prüfen', 'edit_posts', 'abhsp-review', array($this, 'review_page'));
    }

    public function register_settings() {
        register_setting('abhsp_settings', 'abhsp_moderation_email', array('sanitize_callback'=>'sanitize_email'));
        register_setting('abhsp_settings', 'abhsp_inquiry_cc', array('sanitize_callback'=>'sanitize_email'));
    }

    public function settings_page() {
        $portal_page_id = absint(get_option('abhsp_portal_page_id', 0));
        $portal_url = $portal_page_id ? get_permalink($portal_page_id) : '';
        echo '<div class="wrap"><h1>ABH Solutions Plattform</h1>';
        if ($portal_url) echo '<p><strong>Herstellerportal:</strong> <a href="' . esc_url($portal_url) . '" target="_blank" rel="noopener">Portal-Seite öffnen</a> · Shortcode: <code>[abh_partnerportal]</code></p>';
        echo '<form method="post" action="options.php">';
        settings_fields('abhsp_settings');
        echo '<table class="form-table"><tr><th><label for="abhsp_moderation_email">Zentrale Freigabe-E-Mail</label></th><td><input class="regular-text" type="email" id="abhsp_moderation_email" name="abhsp_moderation_email" value="' . esc_attr(get_option('abhsp_moderation_email', get_option('admin_email'))) . '"><p class="description">Neue Fahrzeuge und Aktuelles warten immer auf die zentrale Freigabe.</p></td></tr>';
        echo '<tr><th><label for="abhsp_inquiry_cc">Kopie an ABH bei Fahrzeuganfragen (optional)</label></th><td><input class="regular-text" type="email" id="abhsp_inquiry_cc" name="abhsp_inquiry_cc" value="' . esc_attr(get_option('abhsp_inquiry_cc', '')) . '"></td></tr></table>';
        submit_button('Einstellungen speichern'); echo '</form></div>';
    }

    public function handle_frontend_posts() {
        if (isset($_POST['abhsp_quick_status_submit'])) $this->handle_quick_status_submit();
        if (isset($_POST['abhsp_portal_submit'])) $this->handle_portal_submit();
        if (isset($_POST['abhsp_inquiry_submit'])) $this->handle_inquiry_submit();
    }

    public function shortcode_portal() {
        $this->assets();
        if (!is_user_logged_in()) {
            $portal_url = get_permalink();
            $notice = '';
            if (isset($_GET['abhsp_registration']) && $_GET['abhsp_registration'] === 'sent') $notice = '<div class="abhsp-notice">Bitte bestätigen Sie Ihre E-Mail-Adresse über den Link, den wir Ihnen gerade geschickt haben. Danach prüft ABH Ihre Zugangs-Anfrage.</div>';
            if (isset($_GET['abhsp_verified'])) $notice = '<div class="abhsp-notice">Ihre E-Mail-Adresse ist bestätigt. ABH prüft jetzt Ihre Zugangs-Anfrage.</div>';
            $manufacturers = get_posts(array('post_type'=>'abh_manufacturer','numberposts'=>-1,'orderby'=>'title','order'=>'ASC'));
            $register = '<div class="abhsp-register"><h3>Partnerzugang beantragen</h3><p>Wählen Sie Ihr Unternehmen aus. Nach E-Mail-Bestätigung schaltet ABH den Zugang persönlich frei.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="abhsp_partner_register">' . wp_nonce_field('abhsp_partner_register', '_abhsp_register_nonce', true, false) . '<label class="abhsp-field">Unternehmen<select name="manufacturer_id" required><option value="">Bitte auswählen</option>';
            foreach ($manufacturers as $manufacturer) $register .= '<option value="' . esc_attr($manufacturer->ID) . '">' . esc_html($manufacturer->post_title) . '</option>';
            $register .= '</select></label><label class="abhsp-field">Ansprechpartner<input name="contact_name" required maxlength="100" autocomplete="name"></label><label class="abhsp-field">Geschäftliche E-Mail-Adresse<input name="email" type="email" required maxlength="190" autocomplete="email"></label><label class="abhsp-field abhsp-honeypot">Website<input name="company_website" tabindex="-1" autocomplete="off"></label><label class="abhsp-check"><input type="checkbox" name="privacy" value="1" required> Ich habe die <a href="' . esc_url(get_privacy_policy_url()) . '" target="_blank" rel="noopener">Datenschutzhinweise</a> zur Bearbeitung meiner Zugangsanfrage gelesen.</label><button class="abhsp-button" type="submit">Zugang anfragen →</button></form></div>';
            return '<section class="abhsp-shell abhsp-login"><div class="abhsp-login-grid"><div class="abhsp-panel"><p class="abhsp-eyebrow">ABH PARTNERBEREICH</p><h2>Willkommen im Herstellerportal</h2><p>Melden Sie sich an, um Fahrzeuge oder Aktuelles einzureichen und Ihre Inhalte zu verwalten.</p>' . $notice . wp_login_form(array('echo'=>false,'remember'=>true,'redirect'=>$portal_url)) . '<p class="abhsp-lost-password"><a href="' . esc_url(wp_lostpassword_url($portal_url)) . '">Passwort vergessen?</a></p></div>' . $register . '</div></section>';
        }
        $user = wp_get_current_user();
        $manufacturer_id = absint(get_user_meta($user->ID, '_abhsp_manufacturer_id', true));
        if (in_array('abh_partner_pending', (array)$user->roles, true)) {
            $verified = get_user_meta($user->ID, '_abhsp_email_verified', true) === '1';
            return '<section class="abhsp-shell"><div class="abhsp-notice"><strong>Ihr Partnerzugang ist noch nicht freigeschaltet.</strong><br>' . ($verified ? 'Ihre E-Mail-Adresse ist bestätigt. ABH prüft Ihre Anfrage.' : 'Bitte bestätigen Sie zuerst Ihre E-Mail-Adresse über den Link in Ihrem Postfach.') . '</div></section>';
        }
        if (!current_user_can('manage_options') && (!in_array('abh_partner', (array)$user->roles, true) || !$manufacturer_id)) {
            return '<div class="abhsp-notice abhsp-notice-error">Für dieses Konto ist noch kein Herstellerprofil hinterlegt. Bitte wenden Sie sich an ABH Solutions.</div>';
        }
        $edit_id = absint($_GET['abhsp_edit'] ?? 0);
        $correction_id = absint($_GET['abhsp_correct'] ?? 0);
        $edit_post = $edit_id ? get_post($edit_id) : null;
        $correction_post = $correction_id ? get_post($correction_id) : null;
        if ($edit_post && (!in_array($edit_post->post_type, array('abh_vehicle','abh_update'), true) || $edit_post->post_status !== 'publish')) $edit_post = null;
        if ($edit_post && !current_user_can('manage_options') && absint(get_post_meta($edit_id, '_abhsp_manufacturer_id', true)) !== $manufacturer_id) $edit_post = null;
        if ($edit_id && !$edit_post) return '<div class="abhsp-notice abhsp-notice-error">Dieser Eintrag ist nicht verfügbar oder gehört nicht zu Ihrem Herstellerkonto.</div>';
        if ($correction_post && ($correction_post->post_type !== 'abh_submission' || get_post_meta($correction_id, '_abhsp_review_state', true) !== 'changes')) $correction_post = null;
        if ($correction_post && !current_user_can('manage_options') && absint(get_post_meta($correction_id, '_abhsp_manufacturer_id', true)) !== $manufacturer_id) $correction_post = null;
        if ($correction_id && !$correction_post) return '<div class="abhsp-notice abhsp-notice-error">Diese Rückmeldung ist nicht verfügbar oder gehört nicht zu Ihrem Herstellerkonto.</div>';
        if ($edit_post && $correction_post) return '<div class="abhsp-notice abhsp-notice-error">Bitte öffnen Sie jeweils nur einen Eintrag zur Bearbeitung.</div>';
        $type = $correction_post ? get_post_meta($correction_id, '_abhsp_type', true) : ($edit_post ? ($edit_post->post_type === 'abh_vehicle' ? 'vehicle' : 'news') : '');
        $prefill = array();
        if ($correction_post) {
            $data = get_post_meta($correction_id, '_abhsp_payload', true);
            $data = is_array($data) ? $data : array();
            $prefill = array_merge($data, array(
                'content_type'=>$type,
                'title'=>$correction_post->post_title,
                'summary'=>get_post_meta($correction_id, '_abhsp_summary', true),
                'details'=>$data['details'] ?? '',
                'feature_home'=>!empty($data['feature_home']),
            ));
        } elseif ($edit_post) {
            $prefill = array(
                'content_type'=>$type,
                'title'=>$edit_post->post_title,
                'summary'=>get_post_meta($edit_id, '_abhsp_summary', true) ?: $edit_post->post_excerpt,
                'details'=>$edit_post->post_content,
                'vehicle_type'=>get_post_meta($edit_id, '_abhsp_vehicle_type', true),
                'body_type'=>get_post_meta($edit_id, '_abhsp_body_type', true),
                'application'=>get_post_meta($edit_id, '_abhsp_application', true),
                'base_vehicle'=>get_post_meta($edit_id, '_abhsp_base_vehicle', true),
                'location'=>get_post_meta($edit_id, '_abhsp_location', true),
                'payload'=>get_post_meta($edit_id, '_abhsp_payload', true),
                'availability'=>get_post_meta($edit_id, '_abhsp_availability', true) ?: 'available',
                'news_kind'=>get_post_meta($edit_id, '_abhsp_news_kind', true) ?: 'other',
                'event_date'=>get_post_meta($edit_id, '_abhsp_event_date', true),
                'event_location'=>get_post_meta($edit_id, '_abhsp_event_location', true),
                'feature_home'=>get_post_meta($edit_id, '_abhsp_featured', true) === '1',
            );
        }
        $notice = isset($_GET['abhsp_sent']) ? '<div class="abhsp-notice">Ihre Einreichung wurde an die zentrale Redaktion übermittelt. Die bestehende Veröffentlichung bleibt bis zur Freigabe unverändert.</div>' : '';
        if (isset($_GET['abhsp_status_same'])) $notice = '<div class="abhsp-notice">Dieser Status ist bereits hinterlegt. Es wurde keine neue Einreichung erstellt.</div>';
        $manufacturer = $manufacturer_id ? get_the_title($manufacturer_id) : 'ABH Administration';
        $entries = get_posts(array(
            'post_type'=>array('abh_vehicle','abh_update'),
            'post_status'=>'publish',
            'posts_per_page'=>30,
            'orderby'=>'date',
            'order'=>'DESC',
            'meta_query'=>array(array('key'=>'_abhsp_manufacturer_id','value'=>$manufacturer_id,'compare'=>'='))
        ));
        ob_start();
        echo '<section class="abhsp-shell abhsp-portal"><div class="abhsp-portal-head"><div><p class="abhsp-eyebrow">PARTNERPORTAL</p><h2>Guten Tag, ' . esc_html($manufacturer) . '</h2><p>Verwalten Sie Ihre veröffentlichten Einträge und reichen Sie Änderungen zur Prüfung ein.</p></div><span class="abhsp-step-chip">Geschützter Herstellerzugang</span></div>' . $notice;
        echo '<div class="abhsp-my-content"><div class="abhsp-my-content-head"><div><h3>Meine veröffentlichten Einträge</h3><p>Änderungen werden erst nach der zentralen Freigabe sichtbar.</p></div><a class="abhsp-button abhsp-button-small" href="' . esc_url(remove_query_arg(array('abhsp_edit','abhsp_sent'), get_permalink()) . '#abhsp-neueinreichung') . '">Neuen Eintrag erstellen +</a></div>';
        if ($entries) {
            echo '<div class="abhsp-entry-list">';
            foreach ($entries as $entry) {
                $is_vehicle = $entry->post_type === 'abh_vehicle';
                $state = $is_vehicle ? (get_post_meta($entry->ID, '_abhsp_availability', true) ?: 'available') : 'published';
                $labels = array('available'=>'Sofort verfügbar','on_request'=>'Auf Anfrage','preparing'=>'In Vorbereitung','reserved'=>'Reserviert','sold'=>'Verkauft','published'=>'Veröffentlicht');
                $edit_url = add_query_arg('abhsp_edit', $entry->ID, remove_query_arg(array('abhsp_edit','abhsp_sent'), get_permalink())) . '#abhsp-neueinreichung';
                echo '<article class="abhsp-entry-row"><div><span class="abhsp-entry-kind">' . esc_html($is_vehicle ? 'FAHRZEUG' : 'AKTUELLES') . '</span><h4>' . esc_html($entry->post_title) . '</h4><p>Status: ' . esc_html($labels[$state] ?? 'Veröffentlicht') . ' · zuletzt geändert am ' . esc_html(get_the_modified_date('', $entry)) . '</p>';
                if ($is_vehicle) {
                    echo '<form class="abhsp-quick-status" method="post"><input type="hidden" name="abhsp_quick_status_submit" value="1">' . wp_nonce_field('abhsp_quick_status_' . $entry->ID, 'abhsp_quick_status_nonce', true, false) . '<input type="hidden" name="vehicle_id" value="' . esc_attr($entry->ID) . '"><label for="abhsp_status_' . esc_attr($entry->ID) . '">Verfügbarkeit ändern</label><select id="abhsp_status_' . esc_attr($entry->ID) . '" name="availability"><option value="available" ' . selected($state,'available',false) . '>Sofort verfügbar</option><option value="on_request" ' . selected($state,'on_request',false) . '>Auf Anfrage</option><option value="preparing" ' . selected($state,'preparing',false) . '>In Vorbereitung</option><option value="reserved" ' . selected($state,'reserved',false) . '>Reserviert</option><option value="sold" ' . selected($state,'sold',false) . '>Verkauft</option></select><button type="submit">Status zur Freigabe senden</button></form>';
                }
                echo '</div><a class="abhsp-text-link" href="' . esc_url($edit_url) . '">Bearbeiten →</a></article>';
            }
            echo '</div>';
        } else {
            echo '<div class="abhsp-empty"><p>Sie haben noch keine veröffentlichten Einträge. Starten Sie mit Ihrem ersten Fahrzeug oder einem Aktuelles-Beitrag.</p></div>';
        }
        $waiting = get_posts(array(
            'post_type'=>'abh_submission','post_status'=>array('pending','publish'),'posts_per_page'=>20,'orderby'=>'date','order'=>'DESC',
            'meta_query'=>array(array('key'=>'_abhsp_manufacturer_id','value'=>$manufacturer_id,'compare'=>'='),array('key'=>'_abhsp_review_state','value'=>'pending','compare'=>'='))
        ));
        if ($waiting) {
            echo '<div class="abhsp-corrections abhsp-review-queue"><h4>Zur Prüfung bei ABH</h4>';
            foreach ($waiting as $item) echo '<article><div><strong>' . esc_html($item->post_title) . '</strong><p>' . esc_html(get_post_meta($item->ID, '_abhsp_type', true) === 'vehicle' ? 'Fahrzeug' : 'Aktuelles') . ' · eingereicht am ' . esc_html(get_the_date('', $item)) . ' · Status: in Prüfung</p></div><span class="abhsp-review-badge">In Prüfung</span></article>';
            echo '</div>';
        }
        $corrections = get_posts(array(
            'post_type'=>'abh_submission','post_status'=>'pending','posts_per_page'=>20,
            'meta_query'=>array(array('key'=>'_abhsp_manufacturer_id','value'=>$manufacturer_id,'compare'=>'='),array('key'=>'_abhsp_review_state','value'=>'changes','compare'=>'='))
        ));
        if ($corrections) {
            echo '<div class="abhsp-corrections"><h4>Rückmeldungen zur Überarbeitung</h4>';
            foreach ($corrections as $correction) {
                $note = get_post_meta($correction->ID, '_abhsp_review_note', true);
                $correction_url = add_query_arg('abhsp_correct', $correction->ID, remove_query_arg(array('abhsp_edit','abhsp_correct','abhsp_sent'), get_permalink())) . '#abhsp-neueinreichung';
                echo '<article><div><strong>' . esc_html($correction->post_title) . '</strong>' . ($note ? '<p>' . esc_html($note) . '</p>' : '') . '</div><a class="abhsp-text-link" href="' . esc_url($correction_url) . '">Überarbeiten →</a></article>';
            }
            echo '</div>';
        }
        $activity = get_posts(array('post_type'=>'abh_submission','post_status'=>array('pending','publish'),'posts_per_page'=>10,'orderby'=>'modified','order'=>'DESC','meta_query'=>array(array('key'=>'_abhsp_manufacturer_id','value'=>$manufacturer_id,'compare'=>'='))));
        if ($activity) {
            $activity_labels = array('submitted'=>'Eingereicht','approved'=>'Freigegeben','changes'=>'Überarbeitung angefragt','rejected'=>'Abgelehnt');
            echo '<div class="abhsp-activity"><h4>Letzte Vorgänge</h4>';
            foreach ($activity as $item) {
                $log = get_post_meta($item->ID, '_abhsp_review_log', true);
                $latest = is_array($log) && $log ? end($log) : array('state'=>get_post_meta($item->ID, '_abhsp_review_state', true),'note'=>get_post_meta($item->ID, '_abhsp_review_note', true));
                $state = $latest['state'] ?? 'pending';
                echo '<article><div><strong>' . esc_html($item->post_title) . '</strong><p>' . esc_html($activity_labels[$state] ?? 'In Prüfung') . ' · ' . esc_html(mysql2date('d.m.Y H:i', $latest['date'] ?? $item->post_modified)) . '</p>' . (!empty($latest['note']) ? '<p class="abhsp-activity-note">' . esc_html($latest['note']) . '</p>' : '') . '</div><span class="abhsp-review-badge">' . esc_html($activity_labels[$state] ?? 'In Prüfung') . '</span></article>';
            }
            echo '</div>';
        }
        echo '</div>';
        $form_title = $correction_post ? 'Korrektur einreichen' : ($edit_post ? 'Änderung einreichen' : 'Neuen Eintrag erstellen');
        $form_intro = $correction_post ? 'Überarbeiten Sie die angefragten Punkte. Ihre Ergänzung wird erneut zentral geprüft.' : ($edit_post ? 'Ihre Änderung wird zentral geprüft. Die aktuelle Veröffentlichung bleibt bis zur Freigabe bestehen.' : 'Der Wizard führt Sie durch die Eingabe. Vor der Veröffentlichung prüft die zentrale Redaktion Ihre Angaben.');
        $prefill_json = esc_attr(wp_json_encode($prefill));
        $draft_key = 'abhsp-draft-' . absint($user->ID) . '-' . absint($edit_post ? $edit_post->ID : 0) . '-' . absint($correction_post ? $correction_post->ID : 0);
        echo '<form id="abhsp-neueinreichung" class="abhsp-panel abhsp-wizard" method="post" enctype="multipart/form-data" data-step="1" data-prefill="' . $prefill_json . '" data-draft-key="' . esc_attr($draft_key) . '">';
        wp_nonce_field('abhsp_portal_submit', 'abhsp_portal_nonce');
        echo '<input type="hidden" name="abhsp_portal_submit" value="1"><input type="hidden" name="target_id" value="' . esc_attr($correction_post ? absint(get_post_meta($correction_id, '_abhsp_target_id', true)) : ($edit_post ? $edit_post->ID : 0)) . '"><input type="hidden" name="resubmission_id" value="' . esc_attr($correction_post ? $correction_id : 0) . '"><div class="abhsp-wizard-title"><div><p class="abhsp-eyebrow">INHALTSPFLEGE</p><h3>' . esc_html($form_title) . '</h3><p>' . esc_html($form_intro) . '</p></div>';
        if ($edit_post) echo '<a class="abhsp-text-link" href="' . esc_url(remove_query_arg(array('abhsp_edit','abhsp_sent'), get_permalink())) . '">Abbrechen</a>';
        echo '</div><div class="abhsp-progress" aria-live="polite"><div class="abhsp-progress-track"><span class="abhsp-progress-fill"></span></div><p class="abhsp-progress-label">Schritt 1 von 3 · Inhalt auswählen</p></div>';
        echo '<div class="abhsp-wizard-step" data-step-panel="1"><h3>Was möchten Sie einstellen?</h3><p class="abhsp-step-help">Wir zeigen Ihnen anschließend nur die passenden Eingabefelder.</p>';
        echo '<label class="abhsp-field" for="abhsp_content_type">Inhaltstyp<select id="abhsp_content_type" name="content_type_select" required ' . ($edit_post || $correction_post ? 'disabled' : '') . '><option value="">Bitte auswählen</option><option value="vehicle" ' . selected($type,'vehicle',false) . '>Fahrzeug für den ABH-Fahrzeugfinder</option><option value="news" ' . selected($type,'news',false) . '>Aktuelles für die Hersteller-Sektion</option></select></label><input type="hidden" name="content_type" value="' . esc_attr($type) . '">';
        echo '<div class="abhsp-form-grid"><div><label class="abhsp-field">Titel<input id="abhsp_title" name="title" required maxlength="120" value="' . esc_attr($prefill['title'] ?? '') . '" placeholder="Zum Beispiel: Kühlfahrzeug auf Basis MAN TGE"><small>Ein klarer Titel hilft Interessenten, Ihr Angebot sofort einzuordnen.</small></label></div><div><label class="abhsp-field">Kurzbeschreibung<textarea id="abhsp_summary" name="summary" rows="3" maxlength="500" required placeholder="Was zeichnet Ihr Angebot oder Ihre Neuigkeit aus?">' . esc_textarea($prefill['summary'] ?? '') . '</textarea><button type="button" class="abhsp-text-button" data-abh-suggest-copy>Textvorschlag erstellen</button><small>Der Vorschlag lässt sich frei bearbeiten und wird nicht automatisch veröffentlicht.</small></div></div></div>';
        echo '<div class="abhsp-wizard-step" data-step-panel="2" hidden><h3>Ergänzen Sie die passenden Informationen</h3><p class="abhsp-step-help">Pflichtangaben sind gekennzeichnet. Nicht zutreffende Felder können Sie überspringen.</p>';
        echo '<fieldset class="abhsp-fieldset abhsp-vehicle-fields" disabled><legend>Fahrzeugdaten</legend><div class="abhsp-form-grid"><label class="abhsp-field">Fahrzeugart<input name="vehicle_type" required placeholder="z. B. Kastenwagen, Pritsche"></label><label class="abhsp-field">Aufbauart<input name="body_type" required placeholder="z. B. Kühlkoffer, Regalsystem"></label><label class="abhsp-field">Einsatzbereich<input name="application" required placeholder="z. B. Lebensmittel- oder Pharmalogistik"></label><label class="abhsp-field">Basisfahrzeug<input name="base_vehicle" placeholder="z. B. MAN TGE"></label><label class="abhsp-field">Standort<input name="location" placeholder="Ort oder PLZ"></label><label class="abhsp-field">Nutzlast<input name="payload" placeholder="z. B. 820 kg"></label><label class="abhsp-field">Verfügbarkeit<select name="availability"><option value="available">Sofort verfügbar</option><option value="on_request">Auf Anfrage</option><option value="preparing">In Vorbereitung</option><option value="reserved">Reserviert</option><option value="sold">Verkauft</option></select></label><div class="abhsp-field"><label for="abhsp_vehicle_image">Fahrzeugbild</label><label class="abhsp-dropzone" data-abh-dropzone><input id="abhsp_vehicle_image" type="file" name="vehicle_image" accept="image/jpeg,image/png,image/webp"><span>Bild hier ablegen oder auswählen</span></label><small>JPG, PNG oder WebP, maximal 10 MB. Querformat empfohlen.</small><div class="abhsp-image-preview" data-abh-image-preview></div></div></div></fieldset>';
        echo '<fieldset class="abhsp-fieldset abhsp-news-fields" disabled><legend>Details zu Aktuelles</legend><div class="abhsp-form-grid"><label class="abhsp-field">Thema<select name="news_kind"><option value="novelty">Neuheit</option><option value="trade_fair">Messe / Hausmesse</option><option value="event">Veranstaltung</option><option value="sale_of_month">Verkauf des Monats</option><option value="other">Sonstige Nachricht</option></select></label><label class="abhsp-field">Termin (falls zutreffend)<input type="date" name="event_date"></label><label class="abhsp-field">Ort (falls zutreffend)<input name="event_location" placeholder="Ort oder Messegelände"></label><div class="abhsp-field"><label for="abhsp_news_image">Beitragsbild</label><label class="abhsp-dropzone" data-abh-dropzone><input id="abhsp_news_image" type="file" name="news_image" accept="image/jpeg,image/png,image/webp"><span>Bild hier ablegen oder auswählen</span></label><small>JPG, PNG oder WebP, maximal 10 MB.</small><div class="abhsp-image-preview" data-abh-image-preview></div></div></div></fieldset></div>';
        echo '<div class="abhsp-wizard-step" data-step-panel="3" hidden><h3>Prüfen und einreichen</h3><p class="abhsp-step-help">Kontrollieren Sie Ihre Angaben. Die zentrale Redaktion prüft den Inhalt vor der Veröffentlichung.</p><div class="abhsp-preview"><p class="abhsp-eyebrow" data-preview-type>VORSCHAU</p><h4 data-preview-title>Ihr Titel erscheint hier</h4><p data-preview-summary>Ihre Kurzbeschreibung erscheint hier.</p><div data-preview-facts></div></div><button type="button" class="abhsp-text-button" data-abh-suggest-copy>Textvorschlag aktualisieren</button>'; 
        echo '<label class="abhsp-field abhsp-details-field">Weitere Informationen<textarea name="details" rows="5" placeholder="Technische Besonderheiten, Zusatzinformationen oder Hinweise. Bitte keine Preise eintragen.">' . esc_textarea($prefill['details'] ?? '') . '</textarea><small>Preise werden nicht veröffentlicht. Interessenten fragen direkt beim Hersteller an.</small></label>';
        echo '<label class="abhsp-check abhsp-feature-home"><input type="checkbox" name="feature_home" value="1" ' . checked(!empty($prefill['feature_home']), true, false) . '> Fahrzeug zusätzlich für die Startseite vormerken <span>(maximal sechs; Auswahl durch ABH)</span></label>';
        echo '<label class="abhsp-check"><input type="checkbox" name="privacy" value="1" required> Ich bestätige, dass die Angaben zur Veröffentlichung freigegeben sind.</label></div>';
        echo '<div class="abhsp-wizard-actions"><button class="abhsp-button abhsp-step-back" type="button" data-abh-back hidden>← Zurück</button><button class="abhsp-button abhsp-step-next" type="button" data-abh-next>Weiter →</button><button class="abhsp-button abhsp-step-submit" type="submit" hidden>' . esc_html($correction_post ? 'Korrektur erneut einreichen →' : ($edit_post ? 'Änderung zur Freigabe senden →' : 'Zur zentralen Freigabe senden →')) . '</button></div><div class="abhsp-autosave" aria-live="polite"><span data-abh-autosave-status>Entwurf wird in diesem Browser automatisch gespeichert.</span><button type="button" class="abhsp-text-button" data-abh-restore-draft hidden>Gespeicherten Entwurf laden</button><button type="button" class="abhsp-text-button" data-abh-clear-draft>Entwurf verwerfen</button></div><p class="abhsp-wizard-footnote">Sie erhalten eine E-Mail, sobald Ihre Einreichung geprüft wurde.</p></form></section>';
        return ob_get_clean();
    }

    public function handle_partner_registration() {
        if (!isset($_POST['_abhsp_register_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_abhsp_register_nonce'])), 'abhsp_partner_register')) wp_die('Sicherheitsprüfung fehlgeschlagen. Bitte laden Sie die Seite neu.');
        if (!empty($_POST['company_website'])) wp_die('Die Anfrage konnte nicht verarbeitet werden.');
        if (empty($_POST['privacy'])) wp_die('Bitte bestätigen Sie die Datenschutzhinweise.');
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        $rate_key = 'abhsp_partner_reg_' . md5($ip);
        $rate = (int)get_transient($rate_key);
        if ($rate >= 5) wp_die('Zu viele Anfragen. Bitte versuchen Sie es später erneut.');
        set_transient($rate_key, $rate + 1, HOUR_IN_SECONDS);

        $manufacturer_id = absint($_POST['manufacturer_id'] ?? 0);
        $manufacturer = get_post($manufacturer_id);
        $name = sanitize_text_field(wp_unslash($_POST['contact_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        if (!$manufacturer || $manufacturer->post_type !== 'abh_manufacturer' || !$name || !is_email($email)) wp_die('Bitte prüfen Sie Unternehmen, Ansprechpartner und E-Mail-Adresse.');
        if (email_exists($email)) wp_die('Für diese E-Mail-Adresse besteht bereits ein Konto. Nutzen Sie bitte den Login oder die Passwort-zurücksetzen-Funktion.');

        $login = sanitize_user('abhp_' . strtolower(str_replace(array('@','.','+'), '_', $email)), true);
        $login = substr($login, 0, 45) . '_' . strtolower(wp_generate_password(6, false, false));
        $user_id = wp_create_user($login, wp_generate_password(40, true, true), $email);
        if (is_wp_error($user_id)) wp_die('Das Konto konnte nicht angelegt werden. Bitte prüfen Sie die Angaben oder wenden Sie sich an ABH.');
        $user = new WP_User($user_id);
        $user->set_role('abh_partner_pending');
        wp_update_user(array('ID'=>$user_id,'display_name'=>$name,'first_name'=>$name));
        update_user_meta($user_id, '_abhsp_manufacturer_id', $manufacturer_id);
        update_user_meta($user_id, '_abhsp_contact_name', $name);
        update_user_meta($user_id, '_abhsp_privacy_accepted_at', current_time('mysql'));
        update_user_meta($user_id, '_abhsp_email_verified', '0');
        update_user_meta($user_id, '_abhsp_access_state', 'email_pending');
        $token = wp_generate_password(40, false, false);
        update_user_meta($user_id, '_abhsp_email_token_hash', wp_hash($token, 'abhsp_partner_email'));
        update_user_meta($user_id, '_abhsp_email_token_expires', time() + DAY_IN_SECONDS);
        $verify_url = add_query_arg(array('action'=>'abhsp_verify_partner','uid'=>$user_id,'token'=>$token), admin_url('admin-post.php'));
        $subject = 'Bitte bestätigen Sie Ihre E-Mail-Adresse für das ABH Partnerportal';
        $body = '<div style="font-family:Arial,sans-serif;color:#14233f;max-width:640px"><div style="background:#005eab;padding:18px 24px;color:#fff;font-size:18px;font-weight:bold">ABH SOLUTIONS · PARTNERPORTAL</div><div style="padding:24px;border:1px solid #e3e9f0"><h2>Bitte E-Mail-Adresse bestätigen</h2><p>Guten Tag ' . esc_html($name) . ',</p><p>Sie haben einen Partnerzugang für <strong>' . esc_html($manufacturer->post_title) . '</strong> angefragt. Bitte bestätigen Sie Ihre E-Mail-Adresse innerhalb von 24 Stunden.</p><p><a style="display:inline-block;background:#005eab;color:#fff;text-decoration:none;padding:12px 18px;font-weight:bold" href="' . esc_url($verify_url) . '">E-Mail-Adresse bestätigen</a></p><p>ABH prüft anschließend Ihre Anfrage und informiert Sie über die Freischaltung.</p></div></div>';
        $this->send_html_mail($email, $subject, $body);
        $portal_url = get_permalink(absint(get_option('abhsp_portal_page_id', 0))) ?: home_url('/');
        wp_safe_redirect(add_query_arg('abhsp_registration', 'sent', $portal_url));
        exit;
    }

    public function verify_partner_email() {
        $user_id = absint($_GET['uid'] ?? 0);
        $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
        $user = $user_id ? get_user_by('id', $user_id) : false;
        $stored = $user ? (string)get_user_meta($user_id, '_abhsp_email_token_hash', true) : '';
        $expires = $user ? absint(get_user_meta($user_id, '_abhsp_email_token_expires', true)) : 0;
        if (!$user || !$stored || !$token || $expires < time() || !hash_equals($stored, wp_hash($token, 'abhsp_partner_email'))) wp_die('Der Bestätigungslink ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen Partnerzugang an.');
        update_user_meta($user_id, '_abhsp_email_verified', '1');
        update_user_meta($user_id, '_abhsp_access_state', 'approval_pending');
        delete_user_meta($user_id, '_abhsp_email_token_hash');
        delete_user_meta($user_id, '_abhsp_email_token_expires');
        $manufacturer_id = absint(get_user_meta($user_id, '_abhsp_manufacturer_id', true));
        $moderation = sanitize_email(get_option('abhsp_moderation_email', get_option('admin_email')));
        $review_url = admin_url('options-general.php?page=abhsp-partners');
        $this->send_html_mail($moderation, 'E-Mail bestätigt: ABH Partnerzugang prüfen', '<p>Der Ansprechpartner <strong>' . esc_html($user->display_name) . '</strong> für <strong>' . esc_html(get_the_title($manufacturer_id)) . '</strong> hat seine E-Mail-Adresse bestätigt.</p><p><a href="' . esc_url($review_url) . '">Partnerzugang prüfen</a></p>');
        $portal_url = get_permalink(absint(get_option('abhsp_portal_page_id', 0))) ?: home_url('/');
        wp_safe_redirect(add_query_arg('abhsp_verified', '1', $portal_url));
        exit;
    }

    public function partner_requests_page() {
        if (!current_user_can('manage_options')) wp_die('Keine Berechtigung.');
        $users = get_users(array('role'=>'abh_partner_pending','number'=>200,'orderby'=>'registered','order'=>'ASC'));
        echo '<div class="wrap"><h1>ABH Partnerzugänge</h1><p>Geprüfte E-Mail-Adressen können hier für das jeweilige Herstellerprofil freigeschaltet werden.</p><table class="widefat striped"><thead><tr><th>Kontakt</th><th>Unternehmen</th><th>E-Mail</th><th>E-Mail-Status</th><th>Anfrage</th><th>Aktion</th></tr></thead><tbody>';
        if (!$users) echo '<tr><td colspan="6">Keine offenen Zugangs-Anfragen.</td></tr>';
        foreach ($users as $user) {
            $manufacturer_id = absint(get_user_meta($user->ID, '_abhsp_manufacturer_id', true));
            $verified = get_user_meta($user->ID, '_abhsp_email_verified', true) === '1';
            echo '<tr><td>' . esc_html(get_user_meta($user->ID, '_abhsp_contact_name', true) ?: $user->display_name) . '</td><td>' . esc_html(get_the_title($manufacturer_id)) . '</td><td><a href="mailto:' . esc_attr($user->user_email) . '">' . esc_html($user->user_email) . '</a></td><td>' . esc_html($verified ? 'Bestätigt' : 'Ausstehend') . '</td><td>' . esc_html(mysql2date('d.m.Y H:i', $user->user_registered)) . '</td><td>';
            if ($verified) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:6px">';
                wp_nonce_field('abhsp_partner_decision_' . $user->ID);
                echo '<input type="hidden" name="action" value="abhsp_partner_decision"><input type="hidden" name="user_id" value="' . esc_attr($user->ID) . '"><input type="hidden" name="decision" value="approve"><button class="button button-primary">Freischalten</button></form>';
            } else {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:6px">';
                wp_nonce_field('abhsp_partner_decision_' . $user->ID);
                echo '<input type="hidden" name="action" value="abhsp_partner_decision"><input type="hidden" name="user_id" value="' . esc_attr($user->ID) . '"><input type="hidden" name="decision" value="resend"><button class="button">Bestätigungslink erneut senden</button></form>';
            }
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block">';
            wp_nonce_field('abhsp_partner_decision_' . $user->ID);
            echo '<input type="hidden" name="action" value="abhsp_partner_decision"><input type="hidden" name="user_id" value="' . esc_attr($user->ID) . '"><input type="hidden" name="decision" value="reject"><button class="button">Ablehnen</button></form></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function handle_partner_decision() {
        if (!current_user_can('manage_options')) wp_die('Keine Berechtigung.');
        $user_id = absint($_POST['user_id'] ?? 0);
        $decision = sanitize_key(wp_unslash($_POST['decision'] ?? ''));
        if (!$user_id || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'abhsp_partner_decision_' . $user_id)) wp_die('Sicherheitsprüfung fehlgeschlagen.');
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('abh_partner_pending', (array)$user->roles, true)) wp_die('Diese Anfrage ist nicht mehr offen.');
        $manufacturer_id = absint(get_user_meta($user_id, '_abhsp_manufacturer_id', true));
        if ($decision === 'approve') {
            if (get_user_meta($user_id, '_abhsp_email_verified', true) !== '1') wp_die('Die E-Mail-Adresse wurde noch nicht bestätigt.');
            $user->set_role('abh_partner');
            update_user_meta($user_id, '_abhsp_access_state', 'approved');
            $key = get_password_reset_key($user);
            if (!is_wp_error($key)) {
                $reset_url = network_site_url('wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login), 'login');
                $portal_url = get_permalink(absint(get_option('abhsp_portal_page_id', 0))) ?: home_url('/');
                $body = '<div style="font-family:Arial,sans-serif;color:#14233f;max-width:640px"><div style="background:#005eab;padding:18px 24px;color:#fff;font-size:18px;font-weight:bold">ABH SOLUTIONS · PARTNERPORTAL</div><div style="padding:24px;border:1px solid #e3e9f0"><h2>Ihr Partnerzugang ist freigeschaltet</h2><p>Guten Tag ' . esc_html(get_user_meta($user_id, '_abhsp_contact_name', true) ?: $user->display_name) . ',</p><p>Ihr Zugang für <strong>' . esc_html(get_the_title($manufacturer_id)) . '</strong> wurde freigeschaltet. Legen Sie jetzt Ihr persönliches Passwort fest und melden Sie sich anschließend im Herstellerportal an.</p><p><a style="display:inline-block;background:#005eab;color:#fff;text-decoration:none;padding:12px 18px;font-weight:bold" href="' . esc_url($reset_url) . '">Passwort festlegen</a></p><p>Zum Portal: <a href="' . esc_url($portal_url) . '">' . esc_html($portal_url) . '</a></p></div></div>';
                $this->send_html_mail($user->user_email, 'Ihr ABH Partnerzugang ist freigeschaltet', $body);
            }
        } elseif ($decision === 'reject') {
            $user->set_role('subscriber');
            update_user_meta($user_id, '_abhsp_access_state', 'rejected');
            $this->send_html_mail($user->user_email, 'Rückmeldung zu Ihrer ABH Partnerzugangs-Anfrage', '<p>Vielen Dank für Ihre Anfrage für das ABH Partnerportal. Der Zugang für <strong>' . esc_html(get_the_title($manufacturer_id)) . '</strong> wurde derzeit nicht freigeschaltet. Bei Rückfragen wenden Sie sich bitte an ABH Solutions.</p>');
        } elseif ($decision === 'resend' && get_user_meta($user_id, '_abhsp_email_verified', true) !== '1') {
            $token = wp_generate_password(40, false, false);
            update_user_meta($user_id, '_abhsp_email_token_hash', wp_hash($token, 'abhsp_partner_email'));
            update_user_meta($user_id, '_abhsp_email_token_expires', time() + DAY_IN_SECONDS);
            $verify_url = add_query_arg(array('action'=>'abhsp_verify_partner','uid'=>$user_id,'token'=>$token), admin_url('admin-post.php'));
            $body = '<div style="font-family:Arial,sans-serif;color:#14233f;max-width:640px"><div style="background:#005eab;padding:18px 24px;color:#fff;font-size:18px;font-weight:bold">ABH SOLUTIONS · PARTNERPORTAL</div><div style="padding:24px;border:1px solid #e3e9f0"><h2>Bestätigen Sie Ihre E-Mail-Adresse</h2><p>Bitte bestätigen Sie Ihre E-Mail-Adresse für den angefragten Partnerzugang zu <strong>' . esc_html(get_the_title($manufacturer_id)) . '</strong>.</p><p><a style="display:inline-block;background:#005eab;color:#fff;text-decoration:none;padding:12px 18px;font-weight:bold" href="' . esc_url($verify_url) . '">E-Mail-Adresse bestätigen</a></p><p>Der Link ist 24 Stunden gültig.</p></div></div>';
            $this->send_html_mail($user->user_email, 'Neuer Bestätigungslink für das ABH Partnerportal', $body);
        }
        wp_safe_redirect(admin_url('options-general.php?page=abhsp-partners'));
        exit;
    }

    private function handle_quick_status_submit() {
        if (!is_user_logged_in()) wp_die('Bitte melden Sie sich an.');
        $vehicle_id = absint($_POST['vehicle_id'] ?? 0);
        if (!$vehicle_id || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['abhsp_quick_status_nonce'] ?? '')), 'abhsp_quick_status_' . $vehicle_id)) wp_die('Sicherheitsprüfung fehlgeschlagen.');
        $user = wp_get_current_user();
        $manufacturer_id = absint(get_user_meta($user->ID, '_abhsp_manufacturer_id', true));
        $vehicle = get_post($vehicle_id);
        if (!$vehicle || $vehicle->post_type !== 'abh_vehicle') wp_die('Dieses Fahrzeug ist nicht verfügbar.');
        if (!current_user_can('manage_options') && (!in_array('abh_partner', (array)$user->roles, true) || !$manufacturer_id || absint(get_post_meta($vehicle_id, '_abhsp_manufacturer_id', true)) !== $manufacturer_id)) wp_die('Dieses Fahrzeug gehört nicht zu Ihrem Partnerkonto.');
        $availability = sanitize_key(wp_unslash($_POST['availability'] ?? ''));
        if (!in_array($availability, array('available','on_request','preparing','reserved','sold'), true)) wp_die('Bitte wählen Sie einen gültigen Fahrzeugstatus.');
        if ($availability === get_post_meta($vehicle_id, '_abhsp_availability', true)) {
            $referer = wp_get_referer() ?: get_permalink(absint(get_option('abhsp_portal_page_id', 0)));
            wp_safe_redirect(add_query_arg('abhsp_status_same', '1', $referer));
            exit;
        }
        $summary = get_post_meta($vehicle_id, '_abhsp_summary', true) ?: $vehicle->post_excerpt;
        $payload = array();
        foreach (array('vehicle_type','body_type','application','base_vehicle','location','payload') as $key) $payload[$key] = get_post_meta($vehicle_id, '_abhsp_' . $key, true);
        $payload['availability'] = $availability;
        $payload['summary'] = $summary;
        $payload['details'] = $vehicle->post_content;
        $payload['feature_home'] = $availability === 'sold' ? 0 : (get_post_meta($vehicle_id, '_abhsp_featured', true) === '1' ? 1 : 0);
        $submission_id = wp_insert_post(array('post_type'=>'abh_submission','post_status'=>'pending','post_title'=>$vehicle->post_title,'post_content'=>$vehicle->post_content,'post_author'=>$user->ID), true);
        if (is_wp_error($submission_id)) wp_die('Die Statusänderung konnte nicht gespeichert werden.');
        update_post_meta($submission_id, '_abhsp_type', 'vehicle');
        update_post_meta($submission_id, '_abhsp_manufacturer_id', $manufacturer_id);
        update_post_meta($submission_id, '_abhsp_target_id', $vehicle_id);
        update_post_meta($submission_id, '_abhsp_action', 'update');
        update_post_meta($submission_id, '_abhsp_payload', $payload);
        update_post_meta($submission_id, '_abhsp_summary', $summary);
        update_post_meta($submission_id, '_abhsp_image_id', get_post_thumbnail_id($vehicle_id));
        update_post_meta($submission_id, '_abhsp_review_state', 'pending');
        $this->append_review_log($submission_id, 'submitted', 'Statusänderung eingereicht.');
        $portal_url = get_permalink(absint(get_option('abhsp_portal_page_id', 0))) ?: home_url('/');
        update_post_meta($submission_id, '_abhsp_portal_url', $portal_url);
        $moderation = sanitize_email(get_option('abhsp_moderation_email', get_option('admin_email')));
        $review_url = admin_url('admin.php?page=abhsp-review&submission=' . absint($submission_id));
        $subject = 'Fahrzeugstatus zur ABH-Freigabe: ' . $vehicle->post_title;
        $status_labels = array('available'=>'Sofort verfügbar','on_request'=>'Auf Anfrage','preparing'=>'In Vorbereitung','reserved'=>'Reserviert','sold'=>'Verkauft');
        $body = '<div style="font-family:Arial,sans-serif;color:#14233f;max-width:640px"><div style="background:#005eab;padding:18px 24px;color:#fff;font-size:18px;font-weight:bold">ABH SOLUTIONS · STATUSÄNDERUNG</div><div style="padding:24px;border:1px solid #e3e9f0"><p>Ein Hersteller möchte den Fahrzeugstatus ändern.</p><p><strong>Fahrzeug:</strong> ' . esc_html($vehicle->post_title) . '<br><strong>Hersteller:</strong> ' . esc_html(get_the_title($manufacturer_id)) . '<br><strong>Neuer Status:</strong> ' . esc_html($status_labels[$availability] ?? $availability) . '</p><p><a style="display:inline-block;background:#005eab;color:#fff;text-decoration:none;padding:12px 18px;font-weight:bold" href="' . esc_url($review_url) . '">Status prüfen</a></p></div></div>';
        $this->send_html_mail($moderation, $subject, $body);
        $this->send_html_mail($user->user_email, 'Statusänderung wurde zur Prüfung eingereicht', '<p>Ihre Statusänderung für <strong>' . esc_html($vehicle->post_title) . '</strong> wurde an ABH übermittelt. Der öffentliche Status bleibt bis zur Freigabe unverändert.</p>');
        $referer = wp_get_referer() ?: $portal_url;
        wp_safe_redirect(add_query_arg('abhsp_sent', '1', remove_query_arg(array('abhsp_edit','abhsp_correct'), $referer)));
        exit;
    }

    private function handle_portal_submit() {
        if (!isset($_POST['abhsp_portal_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['abhsp_portal_nonce'])), 'abhsp_portal_submit')) wp_die('Sicherheitsprüfung fehlgeschlagen.');
        if (!is_user_logged_in()) wp_die('Bitte melden Sie sich an.');
        $user = wp_get_current_user();
        $manufacturer_id = absint(get_user_meta($user->ID, '_abhsp_manufacturer_id', true));
        if (!current_user_can('manage_options') && (!in_array('abh_partner', (array)$user->roles, true) || !$manufacturer_id)) wp_die('Kein Herstellerzugriff hinterlegt.');
        $type = sanitize_key(wp_unslash($_POST['content_type'] ?? ''));
        if (!in_array($type, array('vehicle','news'), true)) wp_die('Bitte wählen Sie einen Inhaltstyp.');
        $target_id = absint($_POST['target_id'] ?? 0);
        $resubmission_id = absint($_POST['resubmission_id'] ?? 0);
        $resubmission = $resubmission_id ? get_post($resubmission_id) : null;
        if ($resubmission_id) {
            if (!$resubmission || $resubmission->post_type !== 'abh_submission' || get_post_meta($resubmission_id, '_abhsp_review_state', true) !== 'changes') wp_die('Diese Korrektur kann nicht mehr bearbeitet werden.');
            if (!current_user_can('manage_options') && absint(get_post_meta($resubmission_id, '_abhsp_manufacturer_id', true)) !== $manufacturer_id) wp_die('Sie dürfen diese Korrektur nicht bearbeiten.');
            if (get_post_meta($resubmission_id, '_abhsp_type', true) !== $type) wp_die('Der Inhaltstyp dieser Korrektur darf nicht geändert werden.');
            $stored_target_id = absint(get_post_meta($resubmission_id, '_abhsp_target_id', true));
            if ($target_id !== $stored_target_id) wp_die('Die Zuordnung dieser Korrektur ist ungültig.');
        }
        $target = $target_id ? get_post($target_id) : null;
        if ($target_id) {
            $expected = $type === 'vehicle' ? 'abh_vehicle' : 'abh_update';
            if (!$target || $target->post_type !== $expected) wp_die('Der ausgewählte Eintrag passt nicht zum Inhaltstyp.');
            if (!current_user_can('manage_options') && absint(get_post_meta($target_id, '_abhsp_manufacturer_id', true)) !== $manufacturer_id) wp_die('Sie dürfen diesen Eintrag nicht bearbeiten.');
        }
        if (!$manufacturer_id && $resubmission_id) $manufacturer_id = absint(get_post_meta($resubmission_id, '_abhsp_manufacturer_id', true));
        if (!$manufacturer_id && $target_id) $manufacturer_id = absint(get_post_meta($target_id, '_abhsp_manufacturer_id', true));
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
            foreach (array('vehicle_type','body_type','application') as $required) if (!$payload[$required]) wp_die('Bitte ergänzen Sie Fahrzeugart, Aufbauart und Einsatzbereich.');
            $payload['availability'] = sanitize_key(wp_unslash($_POST['availability'] ?? 'available'));
            if (!in_array($payload['availability'], array('available','on_request','preparing','reserved','sold'), true)) $payload['availability'] = 'available';
        } else {
            $payload['news_kind'] = sanitize_key(wp_unslash($_POST['news_kind'] ?? 'other'));
            $payload['event_date'] = sanitize_text_field(wp_unslash($_POST['event_date'] ?? ''));
            if ($payload['event_date'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['event_date'])) $payload['event_date'] = '';
            $payload['event_location'] = sanitize_text_field(wp_unslash($_POST['event_location'] ?? ''));
            $payload['feature_home'] = 0;
        }
        $image_id = $resubmission_id ? absint(get_post_meta($resubmission_id, '_abhsp_image_id', true)) : 0;
            $upload_key = $type === 'vehicle' ? 'vehicle_image' : 'news_image';
        if (!empty($_FILES[$upload_key]['name'])) {
            if (!empty($_FILES[$upload_key]['size']) && (int)$_FILES[$upload_key]['size'] > 10 * MB_IN_BYTES) wp_die('Das Bild ist größer als 10 MB. Bitte verkleinern Sie die Datei.');
            $file_check = wp_check_filetype_and_ext($_FILES[$upload_key]['tmp_name'], $_FILES[$upload_key]['name']);
            if (empty($file_check['type']) || !in_array($file_check['type'], array('image/jpeg','image/png','image/webp'), true)) wp_die('Bitte laden Sie ein JPG-, PNG- oder WebP-Bild hoch.');
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $image_id = media_handle_upload($upload_key, 0);
            if (is_wp_error($image_id)) wp_die('Das Bild konnte nicht hochgeladen werden. Bitte versuchen Sie es erneut.');
        }
        if ($resubmission) {
            $submission_id = wp_update_post(array('ID'=>$resubmission_id,'post_status'=>'pending','post_title'=>$title,'post_content'=>$payload['details']), true);
        } else {
            $submission_id = wp_insert_post(array('post_type'=>'abh_submission','post_status'=>'pending','post_title'=>$title,'post_content'=>$payload['details'],'post_author'=>$user->ID), true);
        }
        if (is_wp_error($submission_id)) wp_die('Die Einreichung konnte nicht gespeichert werden.');
        update_post_meta($submission_id, '_abhsp_type', $type);
        update_post_meta($submission_id, '_abhsp_manufacturer_id', $manufacturer_id);
        update_post_meta($submission_id, '_abhsp_target_id', $target_id);
        update_post_meta($submission_id, '_abhsp_action', $target_id ? 'update' : 'create');
        update_post_meta($submission_id, '_abhsp_payload', $payload);
        update_post_meta($submission_id, '_abhsp_summary', $summary);
        update_post_meta($submission_id, '_abhsp_image_id', absint($image_id));
        update_post_meta($submission_id, '_abhsp_review_state', 'pending');
        $this->append_review_log($submission_id, 'submitted', 'Einreichung zur Prüfung übermittelt.');
        delete_post_meta($submission_id, '_abhsp_review_note');
        $referer = wp_get_referer() ?: home_url('/');
        update_post_meta($submission_id, '_abhsp_portal_url', remove_query_arg(array('abhsp_edit','abhsp_correct','abhsp_sent'), $referer));
        $moderation = sanitize_email(get_option('abhsp_moderation_email', get_option('admin_email')));
        $review_url = admin_url('admin.php?page=abhsp-review&submission=' . absint($submission_id));
        $action_label = $resubmission ? 'Korrigierte Einreichung' : ($target_id ? 'Änderung' : 'Neue Einreichung');
        $subject = $action_label . ' zur ABH-Freigabe: ' . $title;
        $message = '<div style="font-family:Arial,sans-serif;color:#14233f;max-width:640px"><div style="background:#005eab;padding:18px 24px;color:#fff;font-size:18px;font-weight:bold">ABH SOLUTIONS · FREIGABE</div><div style="padding:24px;border:1px solid #e3e9f0"><p>' . esc_html($action_label) . ' wartet auf Prüfung.</p><p><strong>Typ:</strong> ' . esc_html($type === 'vehicle' ? 'Fahrzeug' : 'Aktuelles') . '<br><strong>Hersteller:</strong> ' . esc_html(get_the_title($manufacturer_id)) . '<br><strong>Titel:</strong> ' . esc_html($title) . '</p><p>' . nl2br(esc_html($summary)) . '</p><p><a style="display:inline-block;background:#005eab;color:#fff;text-decoration:none;padding:12px 18px;font-weight:bold" href="' . esc_url($review_url) . '">Einreichung prüfen</a></p></div></div>';
        $this->send_html_mail($moderation, $subject, $message);
        $this->send_html_mail($user->user_email, 'Ihre ABH-Einreichung ist eingegangen', '<div style="font-family:Arial,sans-serif;color:#14233f;max-width:640px"><div style="background:#005eab;padding:18px 24px;color:#fff;font-size:18px;font-weight:bold">ABH SOLUTIONS · PARTNERPORTAL</div><div style="padding:24px;border:1px solid #e3e9f0"><h2>Einreichung erfolgreich übermittelt</h2><p>Guten Tag ' . esc_html($user->display_name) . ',</p><p>Ihre ' . esc_html($type === 'vehicle' ? 'Fahrzeugeinreichung' : 'Meldung für Aktuelles') . ' <strong>' . esc_html($title) . '</strong> ist bei ABH eingegangen und wird geprüft. Ihre bestehende Veröffentlichung bleibt bis zur Freigabe unverändert.</p><p>Wir informieren Sie per E-Mail über das Prüfergebnis.</p></div></div>');
        $referer = remove_query_arg(array('abhsp_edit','abhsp_correct'), $referer);
        wp_safe_redirect(add_query_arg('abhsp_sent', '1', $referer));
        exit;
    }

    public function submission_metabox($post) {
        if (!current_user_can('edit_post', $post->ID)) { echo '<p>Keine Berechtigung.</p>'; return; }
        $type = get_post_meta($post->ID, '_abhsp_type', true);
        $manufacturer_id = absint(get_post_meta($post->ID, '_abhsp_manufacturer_id', true));
        $target_id = absint(get_post_meta($post->ID, '_abhsp_target_id', true));
        $action_label = $target_id ? 'Änderung eines bestehenden Eintrags' : 'Neue Einreichung';
        echo '<p><strong>Aktion:</strong> ' . esc_html($action_label) . '<br><strong>Typ:</strong> ' . esc_html($type === 'vehicle' ? 'Fahrzeug' : 'Aktuelles') . '<br><strong>Hersteller:</strong> ' . esc_html(get_the_title($manufacturer_id)) . '</p>';
        if ($target_id) echo '<p><strong>Bestehender Eintrag:</strong> <a href="' . esc_url(get_edit_post_link($target_id)) . '">' . esc_html(get_the_title($target_id)) . '</a></p>';
        echo '<p><strong>Freigabestatus:</strong> ' . esc_html(get_post_meta($post->ID, '_abhsp_review_state', true) ?: 'pending') . '</p>';
        $url = add_query_arg(array('page'=>'abhsp-review','submission'=>$post->ID), admin_url('admin.php'));
        echo '<p><a class="button button-primary" href="' . esc_url($url) . '">Einreichung prüfen</a></p>';
        echo '<p class="description">Öffnen Sie die Prüfseite, um die vollständige Vorschau anzusehen, freizugeben oder Änderungen anzufordern.</p>';
    }

    public function review_page() {
        $id = absint($_GET['submission'] ?? 0);
        $post = $id ? get_post($id) : null;
        if (!$post || $post->post_type !== 'abh_submission' || !current_user_can('edit_post', $id)) wp_die('Sie dürfen diese Einreichung nicht prüfen.');
        $type = get_post_meta($id, '_abhsp_type', true);
        $manufacturer_id = absint(get_post_meta($id, '_abhsp_manufacturer_id', true));
        $target_id = absint(get_post_meta($id, '_abhsp_target_id', true));
        $payload = get_post_meta($id, '_abhsp_payload', true);
        $payload = is_array($payload) ? $payload : array();
        echo '<div class="wrap"><h1>ABH-Einreichung prüfen</h1><p><strong>Aktion:</strong> ' . esc_html($target_id ? 'Änderung eines bestehenden Eintrags' : 'Neue Einreichung') . ' · <strong>Typ:</strong> ' . esc_html($type === 'vehicle' ? 'Fahrzeug' : 'Aktuelles') . '</p>';
        echo '<div style="max-width:900px;background:#fff;border:1px solid #dcdcde;padding:24px;margin:20px 0"><p><strong>Hersteller:</strong> ' . esc_html(get_the_title($manufacturer_id)) . '</p>';
        if ($target_id) echo '<p><strong>Bestehender Eintrag:</strong> <a href="' . esc_url(get_permalink($target_id)) . '" target="_blank" rel="noopener">' . esc_html(get_the_title($target_id)) . '</a></p>';
        echo '<h2>' . esc_html($post->post_title) . '</h2><p>' . nl2br(esc_html(get_post_meta($id, '_abhsp_summary', true))) . '</p>';
        if ($type === 'vehicle') {
            echo '<p><strong>Fahrzeugart:</strong> ' . esc_html($payload['vehicle_type'] ?? '') . ' · <strong>Aufbauart:</strong> ' . esc_html($payload['body_type'] ?? '') . '</p>';
            echo '<p><strong>Einsatzbereich:</strong> ' . esc_html($payload['application'] ?? '') . ' · <strong>Basisfahrzeug:</strong> ' . esc_html($payload['base_vehicle'] ?? '') . '</p>';
            echo '<p><strong>Standort:</strong> ' . esc_html($payload['location'] ?? '') . ' · <strong>Nutzlast:</strong> ' . esc_html($payload['payload'] ?? '') . '</p>';
            echo '<p><strong>Status:</strong> ' . esc_html($payload['availability'] ?? '') . ' · <strong>Startseite:</strong> ' . (!empty($payload['feature_home']) ? 'vorgemerkt' : 'nein') . '</p>';
            $image_id = absint(get_post_meta($id, '_abhsp_image_id', true));
            if ($image_id) echo '<div style="max-width:420px">' . wp_get_attachment_image($image_id, 'large', false, array('style'=>'width:100%;height:auto')) . '</div>';
        } else {
            echo '<p><strong>Thema:</strong> ' . esc_html($payload['news_kind'] ?? '') . ' · <strong>Termin:</strong> ' . esc_html($payload['event_date'] ?? '') . ' · <strong>Ort:</strong> ' . esc_html($payload['event_location'] ?? '') . '</p>';
            $image_id = absint(get_post_meta($id, '_abhsp_image_id', true));
            if ($image_id) echo '<div style="max-width:420px">' . wp_get_attachment_image($image_id, 'large', false, array('style'=>'width:100%;height:auto')) . '</div>';
        }
        if (!empty($payload['details'])) echo '<h3>Weitere Informationen</h3><p>' . nl2br(esc_html($payload['details'])) . '</p>';
        echo '</div>';
        if ($post->post_status === 'pending') {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:900px;background:#fff;border:1px solid #dcdcde;padding:24px">';
            wp_nonce_field('abhsp_review_' . $id);
            echo '<input type="hidden" name="action" value="abhsp_review_submission"><input type="hidden" name="submission" value="' . esc_attr($id) . '"><label for="abhsp_review_note"><strong>Hinweis bei Änderungsbedarf oder Ablehnung</strong></label><textarea id="abhsp_review_note" class="large-text" name="note" rows="4" placeholder="Optionaler Hinweis bei Freigabe; bei Rückfrage oder Ablehnung bitte kurz begründen."></textarea><p class="submit"><button class="button button-primary" type="submit" name="decision" value="approve">Freigeben & veröffentlichen</button> <button class="button" type="submit" name="decision" value="changes">Änderungen anfordern</button> <button class="button" type="submit" name="decision" value="reject">Ablehnen</button></p></form>';
        } else {
            echo '<p>Diese Einreichung wurde bereits bearbeitet.</p>';
        }
        echo '<p><a href="' . esc_url(admin_url('edit.php?post_type=abh_submission')) . '">← Zurück zur Einreichungsliste</a></p></div>';
    }

    public function review_submission() {
        $id = absint($_POST['submission'] ?? 0);
        if (!$id || !current_user_can('edit_post', $id) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'abhsp_review_' . $id)) wp_die('Freigabe nicht zulässig.');
        $submission = get_post($id);
        if (!$submission || $submission->post_type !== 'abh_submission') wp_die('Einreichung nicht gefunden.');
        $decision = sanitize_key(wp_unslash($_POST['decision'] ?? ''));
        $type = get_post_meta($id, '_abhsp_type', true);
        $manufacturer_id = absint(get_post_meta($id, '_abhsp_manufacturer_id', true));
        $payload = get_post_meta($id, '_abhsp_payload', true);
        $payload = is_array($payload) ? $payload : array();
        if ($decision === 'approve') {
            $target_type = $type === 'vehicle' ? 'abh_vehicle' : 'abh_update';
            $target_id = absint(get_post_meta($id, '_abhsp_target_id', true));
            $existing = $target_id ? get_post($target_id) : null;
            if ($existing && ($existing->post_type !== $target_type || absint(get_post_meta($target_id, '_abhsp_manufacturer_id', true)) !== $manufacturer_id)) wp_die('Der vorhandene Eintrag ist nicht mehr verfügbar oder gehört einem anderen Hersteller.');
            $post_data = array('post_type'=>$target_type,'post_status'=>'publish','post_title'=>$submission->post_title,'post_content'=>wp_kses_post($payload['details'] ?? ''),'post_excerpt'=>sanitize_textarea_field($payload['summary'] ?? ''),'post_author'=>$existing ? $existing->post_author : get_current_user_id());
            if ($existing) {
                $post_data['ID'] = $target_id;
                $saved_id = wp_update_post($post_data, true);
            } else {
                $saved_id = wp_insert_post($post_data, true);
            }
            if (is_wp_error($saved_id)) wp_die('Der Inhalt konnte nicht veröffentlicht werden.');
            $target_id = absint($saved_id);
            update_post_meta($target_id, '_abhsp_manufacturer_id', $manufacturer_id);
            update_post_meta($target_id, '_abhsp_summary', sanitize_textarea_field($payload['summary'] ?? ''));
            if ($type === 'vehicle') {
                foreach (array('vehicle_type','body_type','application','base_vehicle','location','payload','availability') as $key) update_post_meta($target_id, '_abhsp_' . $key, sanitize_text_field($payload[$key] ?? ''));
                $was_featured = $existing && get_post_meta($target_id, '_abhsp_featured', true) === '1';
                $featured = !empty($payload['feature_home']) && ($was_featured || $this->featured_count() < 6);
                update_post_meta($target_id, '_abhsp_featured', $featured ? '1' : '0');
                if (!get_post_meta($target_id, '_abhsp_featured_order', true)) update_post_meta($target_id, '_abhsp_featured_order', 9999);
                if (!empty($payload['availability']) && $payload['availability'] === 'sold') update_post_meta($target_id, '_abhsp_featured', '0');
            } else {
                foreach (array('news_kind','event_date','event_location') as $key) update_post_meta($target_id, '_abhsp_' . $key, sanitize_text_field($payload[$key] ?? ''));
            }
            $image_id = absint(get_post_meta($id, '_abhsp_image_id', true));
            if ($image_id) set_post_thumbnail($target_id, $image_id);
            update_post_meta($id, '_abhsp_review_state', 'approved');
            $this->append_review_log($id, 'approved', sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')));
            update_post_meta($id, '_abhsp_published_id', $target_id);
            wp_update_post(array('ID'=>$id,'post_status'=>'publish'));
            $this->notify_submitter($id, 'approved', '');
        } elseif ($decision === 'changes') {
            $review_note = sanitize_textarea_field(wp_unslash($_POST['note'] ?? ''));
            if (!$review_note) wp_die('Bitte geben Sie einen Hinweis für den Hersteller ein.');
            update_post_meta($id, '_abhsp_review_state', 'changes');
            update_post_meta($id, '_abhsp_review_note', $review_note);
            $this->append_review_log($id, 'changes', $review_note);
            $this->notify_submitter($id, 'changes', get_post_meta($id, '_abhsp_review_note', true));
        } elseif ($decision === 'reject') {
            $review_note = sanitize_textarea_field(wp_unslash($_POST['note'] ?? ''));
            if (!$review_note) wp_die('Bitte geben Sie einen Grund für die Ablehnung ein.');
            update_post_meta($id, '_abhsp_review_state', 'rejected');
            update_post_meta($id, '_abhsp_review_note', $review_note);
            $this->append_review_log($id, 'rejected', $review_note);
            wp_update_post(array('ID'=>$id,'post_status'=>'publish'));
            $this->notify_submitter($id, 'rejected', $review_note);
        }
        wp_safe_redirect(admin_url('edit.php?post_type=abh_submission'));
        exit;
    }

    private function featured_count() {
        $query = new WP_Query(array('post_type'=>'abh_vehicle','post_status'=>'publish','posts_per_page'=>7,'fields'=>'ids','meta_query'=>array(array('key'=>'_abhsp_featured','value'=>'1'),array('key'=>'_abhsp_availability','value'=>'sold','compare'=>'!='))));
        return (int)$query->found_posts;
    }

    private function append_review_log($id, $state, $note = '') {
        $log = get_post_meta($id, '_abhsp_review_log', true);
        $log = is_array($log) ? $log : array();
        $log[] = array('state'=>sanitize_key($state),'note'=>sanitize_textarea_field($note),'date'=>current_time('mysql'),'user_id'=>get_current_user_id());
        update_post_meta($id, '_abhsp_review_log', array_slice($log, -20));
    }

    private function notify_submitter($id, $state, $note) {
        $submission = get_post($id);
        if (!$submission) return;
        $user = get_userdata($submission->post_author);
        if (!$user || !is_email($user->user_email)) return;
        $approved = $state === 'approved';
        $rejected = $state === 'rejected';
        $subject = $approved ? 'Ihre ABH-Einreichung wurde freigegeben' : ($rejected ? 'Rückmeldung zu Ihrer ABH-Einreichung' : 'Bitte überarbeiten Sie Ihre ABH-Einreichung');
        $portal_url = get_post_meta($id, '_abhsp_portal_url', true);
        $correction_url = $portal_url ? add_query_arg('abhsp_correct', $id, $portal_url) . '#abhsp-neueinreichung' : '';
        $published_id = absint(get_post_meta($id, '_abhsp_published_id', true));
        $action = $state === 'changes' && $correction_url ? '<p><a style="display:inline-block;background:#005eab;color:#fff;text-decoration:none;padding:12px 18px;font-weight:bold" href="' . esc_url($correction_url) . '">Einreichung im Partnerportal überarbeiten</a></p>' : ($approved && $published_id ? '<p><a style="display:inline-block;background:#005eab;color:#fff;text-decoration:none;padding:12px 18px;font-weight:bold" href="' . esc_url(get_permalink($published_id)) . '">Veröffentlichten Eintrag ansehen</a></p>' : '');
        $result = $approved ? 'freigegeben und veröffentlicht.' : ($rejected ? 'geprüft. Die Einreichung wurde nicht veröffentlicht.' : 'geprüft. Bitte berücksichtigen Sie den folgenden Hinweis:');
        $body = '<div style="font-family:Arial,sans-serif;color:#14233f;max-width:640px"><div style="background:#005eab;padding:18px 24px;color:#fff;font-size:18px;font-weight:bold">ABH SOLUTIONS</div><div style="padding:24px;border:1px solid #e3e9f0"><h2 style="color:#005eab">' . esc_html($subject) . '</h2><p>Guten Tag ' . esc_html($user->display_name) . ',</p><p>Ihre Einreichung <strong>' . esc_html($submission->post_title) . '</strong> wurde ' . esc_html($result) . '</p>' . ($note ? '<blockquote style="border-left:3px solid #005eab;padding:8px 14px;margin:16px 0;background:#f4f7fb">' . nl2br(esc_html($note)) . '</blockquote>' : '') . $action . '<p>Viele Grüße<br>Ihr ABH Solutions Team</p></div></div>';
        $this->send_html_mail($user->user_email, $subject, $body);
    }

    public function shortcode_finder($atts) {
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
        echo '</div><div class="abhsp-filter-actions"><button class="abhsp-button" type="submit">Fahrzeuge anzeigen</button><a href="' . esc_url(remove_query_arg(array_map(function($k){return 'abh_'.$k;}, array_keys($filters)))) . '">Filter zurücksetzen</a></div></form>';
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
        if ($query->have_posts()) { while ($query->have_posts()) { $query->the_post(); $id=get_the_ID(); $mid=absint(get_post_meta($id,'_abhsp_manufacturer_id',true)); $kind=get_post_meta($id,'_abhsp_news_kind',true); $event_date=get_post_meta($id,'_abhsp_event_date',true); $event_location=get_post_meta($id,'_abhsp_event_location',true); $is_event=in_array($kind,array('event','trade_fair'),true); $is_past=$is_event && $event_date && $event_date < current_time('Y-m-d'); echo '<article class="abhsp-news-card"><a class="abhsp-news-image" href="' . esc_url(get_permalink($id)) . '">' . (get_the_post_thumbnail($id,'large') ?: '<span>ABH SOLUTIONS</span>') . '</a><div class="abhsp-news-content"><p class="abhsp-card-brand">' . esc_html(get_the_title($mid)) . ' · ' . esc_html(get_the_date('', $id)) . '</p><h3><a href="' . esc_url(get_permalink($id)) . '">' . esc_html(get_the_title($id)) . '</a></h3>';
            if ($is_event && ($event_date || $event_location)) echo '<p class="abhsp-event-meta">' . ($event_date ? '<strong>' . esc_html(wp_date('d.m.Y', strtotime($event_date))) . '</strong>' : '') . ($event_location ? ' · ' . esc_html($event_location) : '') . ($is_past ? ' <span class="abhsp-event-past">Termin vorbei</span>' : '') . '</p>';
            echo '<p>' . esc_html(get_the_excerpt($id)) . '</p><a class="abhsp-text-link" href="' . esc_url(get_permalink($id)) . '">Weiterlesen →</a></div></article>'; } wp_reset_postdata(); }
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
