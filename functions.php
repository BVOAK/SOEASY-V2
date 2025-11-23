<?php
/**
 * Theme functions and definitions
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_VERSION', '3.4.4' );
define( 'EHP_THEME_SLUG', 'so-easy' );

define( 'HELLO_THEME_PATH', get_template_directory() );
define( 'HELLO_THEME_URL', get_template_directory_uri() );
define( 'HELLO_THEME_ASSETS_PATH', HELLO_THEME_PATH . '/assets/' );
define( 'HELLO_THEME_ASSETS_URL', HELLO_THEME_URL . '/assets/' );
define( 'HELLO_THEME_SCRIPTS_PATH', HELLO_THEME_ASSETS_PATH . 'js/' );
define( 'HELLO_THEME_SCRIPTS_URL', HELLO_THEME_ASSETS_URL . 'js/' );
define( 'HELLO_THEME_STYLE_PATH', HELLO_THEME_ASSETS_PATH . 'css/' );
define( 'HELLO_THEME_STYLE_URL', HELLO_THEME_ASSETS_URL . 'css/' );
define( 'HELLO_THEME_IMAGES_PATH', HELLO_THEME_ASSETS_PATH . 'images/' );
define( 'HELLO_THEME_IMAGES_URL', HELLO_THEME_ASSETS_URL . 'images/' );

if ( ! isset( $content_width ) ) {
	$content_width = 800; // Pixels.
}

if ( ! function_exists( 'hello_elementor_setup' ) ) {
	/**
	 * Set up theme support.
	 *
	 * @return void
	 */
	function hello_elementor_setup() {
		if ( is_admin() ) {
			hello_maybe_update_theme_version_in_db();
		}

		if ( apply_filters( 'hello_elementor_register_menus', true ) ) {
			register_nav_menus( [ 'menu-1' => esc_html__( 'Header', 'hello-elementor' ) ] );
			register_nav_menus( [ 'menu-2' => esc_html__( 'Footer', 'hello-elementor' ) ] );
		}

		if ( apply_filters( 'hello_elementor_post_type_support', true ) ) {
			add_post_type_support( 'page', 'excerpt' );
		}

		if ( apply_filters( 'hello_elementor_add_theme_support', true ) ) {
			add_theme_support( 'post-thumbnails' );
			add_theme_support( 'automatic-feed-links' );
			add_theme_support( 'title-tag' );
			add_theme_support(
				'html5',
				[
					'search-form',
					'comment-form',
					'comment-list',
					'gallery',
					'caption',
					'script',
					'style',
					'navigation-widgets',
				]
			);
			add_theme_support(
				'custom-logo',
				[
					'height'      => 100,
					'width'       => 350,
					'flex-height' => true,
					'flex-width'  => true,
				]
			);
			add_theme_support( 'align-wide' );
			add_theme_support( 'responsive-embeds' );

			/*
			 * Editor Styles
			 */
			add_theme_support( 'editor-styles' );
			add_editor_style( 'editor-styles.css' );

			/*
			 * WooCommerce.
			 */
			if ( apply_filters( 'hello_elementor_add_woocommerce_support', true ) ) {
				// WooCommerce in general.
				add_theme_support( 'woocommerce' );
				// Enabling WooCommerce product gallery features (are off by default since WC 3.0.0).
				// zoom.
				add_theme_support( 'wc-product-gallery-zoom' );
				// lightbox.
				add_theme_support( 'wc-product-gallery-lightbox' );
				// swipe.
				add_theme_support( 'wc-product-gallery-slider' );
			}
		}
	}
}
add_action( 'after_setup_theme', 'hello_elementor_setup' );

function hello_maybe_update_theme_version_in_db() {
	$theme_version_option_name = 'hello_theme_version';
	// The theme version saved in the database.
	$hello_theme_db_version = get_option( $theme_version_option_name );

	// If the 'hello_theme_version' option does not exist in the DB, or the version needs to be updated, do the update.
	if ( ! $hello_theme_db_version || version_compare( $hello_theme_db_version, HELLO_ELEMENTOR_VERSION, '<' ) ) {
		update_option( $theme_version_option_name, HELLO_ELEMENTOR_VERSION );
	}
}

if ( ! function_exists( 'hello_elementor_display_header_footer' ) ) {
	/**
	 * Check whether to display header footer.
	 *
	 * @return bool
	 */
	function hello_elementor_display_header_footer() {
		$hello_elementor_header_footer = true;

		return apply_filters( 'hello_elementor_header_footer', $hello_elementor_header_footer );
	}
}

if ( ! function_exists( 'hello_elementor_scripts_styles' ) ) {
	/**
	 * Theme Scripts & Styles.
	 *
	 * @return void
	 */
	function hello_elementor_scripts_styles() {
		if ( apply_filters( 'hello_elementor_enqueue_style', true ) ) {
			wp_enqueue_style(
				'hello-elementor',
				HELLO_THEME_STYLE_URL . 'reset.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}

		if ( apply_filters( 'hello_elementor_enqueue_theme_style', true ) ) {
			wp_enqueue_style(
				'hello-elementor-theme-style',
				HELLO_THEME_STYLE_URL . 'theme.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}

		if ( hello_elementor_display_header_footer() ) {
			wp_enqueue_style(
				'hello-elementor-header-footer',
				HELLO_THEME_STYLE_URL . 'header-footer.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_scripts_styles' );

if ( ! function_exists( 'hello_elementor_register_elementor_locations' ) ) {
	/**
	 * Register Elementor Locations.
	 *
	 * @param ElementorPro\Modules\ThemeBuilder\Classes\Locations_Manager $elementor_theme_manager theme manager.
	 *
	 * @return void
	 */
	function hello_elementor_register_elementor_locations( $elementor_theme_manager ) {
		if ( apply_filters( 'hello_elementor_register_elementor_locations', true ) ) {
			$elementor_theme_manager->register_all_core_location();
		}
	}
}
add_action( 'elementor/theme/register_locations', 'hello_elementor_register_elementor_locations' );

if ( ! function_exists( 'hello_elementor_content_width' ) ) {
	/**
	 * Set default content width.
	 *
	 * @return void
	 */
	function hello_elementor_content_width() {
		$GLOBALS['content_width'] = apply_filters( 'hello_elementor_content_width', 800 );
	}
}
add_action( 'after_setup_theme', 'hello_elementor_content_width', 0 );

if ( ! function_exists( 'hello_elementor_add_description_meta_tag' ) ) {
	/**
	 * Add description meta tag with excerpt text.
	 *
	 * @return void
	 */
	function hello_elementor_add_description_meta_tag() {
		if ( ! apply_filters( 'hello_elementor_description_meta_tag', true ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( empty( $post->post_excerpt ) ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $post->post_excerpt ) ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'hello_elementor_add_description_meta_tag' );

// Settings page
require get_template_directory() . '/includes/settings-functions.php';

// Header & footer styling option, inside Elementor
require get_template_directory() . '/includes/elementor-functions.php';

if ( ! function_exists( 'hello_elementor_customizer' ) ) {
	// Customizer controls
	function hello_elementor_customizer() {
		if ( ! is_customize_preview() ) {
			return;
		}

		if ( ! hello_elementor_display_header_footer() ) {
			return;
		}

		require get_template_directory() . '/includes/customizer-functions.php';
	}
}
add_action( 'init', 'hello_elementor_customizer' );

if ( ! function_exists( 'hello_elementor_check_hide_title' ) ) {
	/**
	 * Check whether to display the page title.
	 *
	 * @param bool $val default value.
	 *
	 * @return bool
	 */
	function hello_elementor_check_hide_title( $val ) {
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$current_doc = Elementor\Plugin::instance()->documents->get( get_the_ID() );
			if ( $current_doc && 'yes' === $current_doc->get_settings( 'hide_title' ) ) {
				$val = false;
			}
		}
		return $val;
	}
}
add_filter( 'hello_elementor_page_title', 'hello_elementor_check_hide_title' );

/**
 * BC:
 * In v2.7.0 the theme removed the `hello_elementor_body_open()` from `header.php` replacing it with `wp_body_open()`.
 * The following code prevents fatal errors in child themes that still use this function.
 */
if ( ! function_exists( 'hello_elementor_body_open' ) ) {
	function hello_elementor_body_open() {
		wp_body_open();
	}
}

require HELLO_THEME_PATH . '/theme.php';

HelloTheme\Theme::instance();



/* CUSTOM */


/**
 * Support thème WordPress
 */
function soeasy_theme_support() {
    
    // Support WooCommerce
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    
    // Support WordPress
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('custom-logo');
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'script',
        'style'
    ));
}
add_action('after_setup_theme', 'soeasy_theme_support');


/**
 * Menus WordPress
 */
function soeasy_register_menus() {
    register_nav_menus(array(
        'primary' => __('Menu Principal', 'soeasy'),
        'footer' => __('Menu Footer', 'soeasy'),
    ));
}
add_action('after_setup_theme', 'soeasy_register_menus');


/**
 * Sidebars/Widgets
 */
function soeasy_widgets_init() {
    register_sidebar(array(
        'name' => __('Sidebar Principal', 'soeasy'),
        'id' => 'sidebar-1',
        'description' => __('Widgets pour la sidebar principale', 'soeasy'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget' => '</section>',
        'before_title' => '<h3 class="widget-title">',
        'after_title' => '</h3>',
    ));
}
add_action('widgets_init', 'soeasy_widgets_init');


/**
 * Désactiver certains scripts WordPress pas nécessaires
 */
function soeasy_cleanup() {
    
    // Supprimer emoji scripts
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    
    // Supprimer version WordPress du header
    remove_action('wp_head', 'wp_generator');
    
    // Supprimer RSS feeds si pas utilisés
    // remove_action('wp_head', 'feed_links', 2);
    // remove_action('wp_head', 'feed_links_extra', 3);
}
add_action('init', 'soeasy_cleanup');


/**
 * Customizer - Couleurs SoEasy
 */
function soeasy_customize_register($wp_customize) {
    
    // Section couleurs
    $wp_customize->add_section('soeasy_colors', array(
        'title' => __('Couleurs SoEasy', 'soeasy'),
        'priority' => 30,
    ));
    
    // Couleur primaire
    $wp_customize->add_setting('soeasy_primary_color', array(
        'default' => '#667eea',
        'sanitize_callback' => 'sanitize_hex_color',
    ));
    
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'soeasy_primary_color', array(
        'label' => __('Couleur Primaire', 'soeasy'),
        'section' => 'soeasy_colors',
        'settings' => 'soeasy_primary_color',
    )));
}
add_action('customize_register', 'soeasy_customize_register');


/**
 * CSS variables dynamiques
 */
function soeasy_dynamic_css() {
    $primary_color = get_theme_mod('soeasy_primary_color', '#667eea');
    
    echo '<style id="soeasy-dynamic-css">
        :root {
            --soeasy-primary: ' . esc_attr($primary_color) . ';
            --soeasy-primary-rgb: ' . implode(', ', sscanf($primary_color, "#%02x%02x%02x")) . ';
        }
    </style>';
}
add_action('wp_head', 'soeasy_dynamic_css');


require_once get_template_directory() . '/configurateur/functions-configurateur.php';
require_once get_template_directory() . '/includes/functions-cart.php';
require_once get_template_directory() . '/configurateur/database-schema.php';
require_once get_template_directory() . '/configurateur/config-manager.php';

/**
 * Enqueue Bootstrap + FontAwesome + Assets SoEasy
 */
function soeasy_enqueue_assets() {

	wp_enqueue_script('jquery');
    
    // Bootstrap 5
    wp_enqueue_style(
    'bootstrap-css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    array(),
    '5.3.3'
	);
	wp_enqueue_script(
		'bootstrap-js',
		'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
		array('jquery'),
		'5.3.3',
		true
	);

    // FontAwesome
    wp_enqueue_style(
        'fontawesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css',
        array(),
        '7.0.0'
    );

	// Custom
	wp_enqueue_script(
        'custom-js',
        get_template_directory_uri() . '/assets/js/custom.js',
        array('jquery'),
		'1.0',
        true
	);
	wp_enqueue_style(
        'custom',
        get_template_directory_uri() . '/assets/css/custom.css',
    );
	wp_enqueue_style(
        'input',
        get_template_directory_uri() . '/assets/css/input.css',
    );
}
add_action('wp_enqueue_scripts', 'soeasy_enqueue_assets');

function soeasy_enqueue_configurateur_assets_conditionnel() {
    if (is_page_template('page-configurateur.php')) {

        wp_enqueue_style(
            'configurateur',
            get_template_directory_uri() . '/assets/css/configurateur.css',
        );

		wp_enqueue_script(
			'soeasy-auth-reset',
			get_template_directory_uri() . '/assets/js/auth-reset.js',
			array('jquery'),
			filemtime(get_template_directory() . '/assets/js/auth-reset.js'),
			true
		);

		// Config Reconciliation - NOUVEAU (à placer APRÈS auth-reset.js, AVANT configurateur-fonctions.js)
		wp_enqueue_script(
			'soeasy-config-reconciliation',
			get_template_directory_uri() . '/assets/js/config-reconciliation.js',
			array('jquery'),
			filemtime(get_template_directory() . '/assets/js/config-reconciliation.js'),
			true
		);

        // ✅ 2. FONCTIONS CONFIGURATEUR
        wp_enqueue_script(
            'soeasy-configurateur-fonctions',
            get_template_directory_uri() . '/assets/js/configurateur-fonctions.js',
            array('jquery'),
            filemtime(get_template_directory() . '/assets/js/configurateur-fonctions.js'),
            true
        );

        // ✅ 3. SIDEBAR MANAGER
        wp_enqueue_script(
            'soeasy-sidebar-manager',
            get_template_directory_uri() . '/assets/js/sidebar-manager.js',
            array('jquery'),
            filemtime(get_template_directory() . '/assets/js/sidebar-manager.js'),
            true
        );

        // ✅ 4. CONFIGURATEUR
        wp_enqueue_script(
            'soeasy-configurateur',
            get_template_directory_uri() . '/assets/js/configurateur.js',
            array('jquery', 'soeasy-auth-reset', 'soeasy-config-reconciliation', 'soeasy-configurateur-fonctions'),
            filemtime(get_template_directory() . '/assets/js/configurateur.js'),
            true
        );

        wp_enqueue_script(
            'google-maps-api',
            'https://maps.googleapis.com/maps/api/js?key=AIzaSyBeIvkJPtLGSviPdBoluEUR0SI1M7eeK00&libraries=places',
            [],
            null,
            true
        );

        wp_localize_script('soeasy-configurateur', 'soeasyVars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'themeUrl' => get_template_directory_uri(),
            'security' => wp_create_nonce('soeasy_nonce'),
            'nonce_config' => wp_create_nonce('soeasy_config_action'),
            'nonce_cart' => wp_create_nonce('soeasy_cart_action'),
            'nonce_address' => wp_create_nonce('soeasy_address_action'),
			'userId' => get_current_user_id(),
			'userDisplayName' => is_user_logged_in() ? wp_get_current_user()->display_name : ''
        ));

        add_action('init', function () {
            if (class_exists('WooCommerce') && is_checkout() === false) {
                WC()->session;
            }
        }); 
    }
}
add_action('wp_enqueue_scripts', 'soeasy_enqueue_configurateur_assets_conditionnel');


/**
 * Action AJAX pour mettre à jour la session de configuration
 */
/* function soeasy_update_config_session() {
    // Vérifier le nonce
    if (!wp_verify_nonce($_POST['nonce'], 'soeasy_config_nonce')) {
        wp_die('Nonce invalide');
    }
    
    // Récupérer et valider la config
    $config = isset($_POST['config']) ? $_POST['config'] : array();
    
    if (is_array($config)) {
        // Sauvegarder en session
        if (!session_id()) {
            session_start();
        }
        $_SESSION['soeasy_configurateur'] = $config;
        
        wp_send_json_success(array(
            'message' => 'Configuration mise à jour en session',
            'config_count' => count($config)
        ));
    } else {
        wp_send_json_error('Configuration invalide');
    }
}
add_action('wp_ajax_soeasy_update_config_session', 'soeasy_update_config_session');
add_action('wp_ajax_nopriv_soeasy_update_config_session', 'soeasy_update_config_session'); */

function soeasy_force_cart_template($template) {
    
    if (is_cart()) {
        $custom_cart = get_template_directory() . '/woocommerce/cart/cart.php';
        if (file_exists($custom_cart)) {
            
            // Charger header
            get_header();
            
            // Inclure notre template
            include $custom_cart;
            
            // Charger footer
            get_footer();
            
            // Exit pour éviter que WP continue
            exit;
        }
    }
    
    return $template;
}

add_filter('template_include', 'soeasy_force_cart_template', 99);


/**
 * ============================================================================
 * RESET CONFIGURATION À CONNEXION/DÉCONNEXION
 * ============================================================================
 */

/**
 * Hook exécuté APRÈS connexion réussie
 */
/* function soeasy_on_user_login($user_login, $user) {
    // Cookie pour déclencher vidage localStorage côté JS
    setcookie('soeasy_force_clear', '1', time() + 10, '/');
    
    // Vider session PHP immédiatement
    if (function_exists('WC') && WC()->session) {
        WC()->session->set('soeasy_configurateur', []);
        WC()->session->set('soeasy_config_adresses', []);
        WC()->session->set('soeasy_duree_engagement', 0);
        WC()->session->set('soeasy_mode_financement', '');
        WC()->cart->empty_cart();
    }
    
    error_log("✅ SoEasy: Utilisateur #{$user->ID} ({$user_login}) connecté - session vidée");
}
add_action('wp_login', 'soeasy_on_user_login', 10, 2);
 */
/**
 * Hook exécuté AVANT déconnexion
 */
/* function soeasy_on_user_logout() {
    // Cookie pour déclencher vidage localStorage côté JS
    setcookie('soeasy_force_clear', '1', time() + 10, '/');
    
    // Vider session PHP
    if (function_exists('WC') && WC()->session) {
        WC()->session->set('soeasy_configurateur', []);
        WC()->session->set('soeasy_config_adresses', []);
        WC()->session->set('soeasy_duree_engagement', 0);
        WC()->session->set('soeasy_mode_financement', '');
        WC()->cart->empty_cart();
    }
    
    $user_id = get_current_user_id();
    error_log("✅ SoEasy: Utilisateur #{$user_id} déconnecté - session vidée");
}
add_action('wp_logout', 'soeasy_on_user_logout'); */


/**
 * Shortcode : Bouton connexion/déconnexion avec modal
 * Usage : [soeasy_auth_button]
 */
function soeasy_auth_button_shortcode($atts) {
    $atts = shortcode_atts(array(
        'class' => 'btn btn-primary',
        'text_login' => 'Se connecter',
        'text_logout' => 'Se déconnecter'
    ), $atts);
    
    $user_id = get_current_user_id();
    
    if ($user_id) {
        // Utilisateur connecté
        $user = wp_get_current_user();
        $logout_url = wp_logout_url(home_url('/configurateur/'));
        
        return sprintf(
            '<button type="button" class="%s" id="btn-logout-modal">
                <i class="fas fa-user-circle me-2"></i>%s
            </button>
            <script>
            jQuery(document).ready(function($) {
                $("#btn-logout-modal").on("click", function() {
                    if (confirm("Voulez-vous vraiment vous déconnecter ?")) {
                        window.location.href = "%s";
                    }
                });
            });
            </script>',
            esc_attr($atts['class']),
            esc_html($atts['text_logout']),
            esc_url($logout_url)
        );
    } else {
        // Utilisateur non connecté
        $login_url = wp_login_url(home_url('/configurateur/'));
        
        return sprintf(
            '<a href="%s" class="%s">
                <i class="fas fa-sign-in-alt me-2"></i>%s
            </a>',
            esc_url($login_url),
            esc_attr($atts['class']),
            esc_html($atts['text_login'])
        );
    }
}
add_shortcode('soeasy_auth_button', 'soeasy_auth_button_shortcode');