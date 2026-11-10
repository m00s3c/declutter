<?php
/**
 * Core: settings storage, item discovery and the hide logic.
 *
 * Everything here is cosmetic. Nothing removes capabilities or blocks a
 * screen: hidden menu entries still open by direct URL.
 *
 * @package Declutter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
final class Declutter {

	const OPTION      = 'declutter_settings';
	const SEEN_OPTION = 'declutter_seen';
	const SECTIONS    = array( 'dashboard', 'toolbar', 'menu' );

	/**
	 * Singleton instance.
	 *
	 * @var Declutter|null
	 */
	private static $instance = null;

	/**
	 * Cached settings for this request.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Get the instance.
	 *
	 * @return Declutter
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook everything up.
	 */
	private function __construct() {
		// Dashboard: runs after wp_dashboard_setup has registered every widget.
		add_action( 'do_meta_boxes', array( $this, 'handle_dashboard' ), PHP_INT_MAX, 2 );
		// Toolbar: last stop before the admin bar renders (admin and front end).
		add_action( 'wp_before_admin_bar_render', array( $this, 'handle_toolbar' ), PHP_INT_MAX );
		// Menu: after access checks, before the sidebar is printed.
		add_action( 'admin_head', array( $this, 'handle_menu' ), 0 );
	}

	/**
	 * Activation: store defaults if nothing is saved yet.
	 */
	public static function activate() {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults() );
		}
		if ( false === get_option( self::SEEN_OPTION ) ) {
			add_option( self::SEEN_OPTION, array(), '', false );
		}
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	/**
	 * Shipped default config: upload, activate, done.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'version'   => 1,
			'dashboard' => array(
				'enabled'   => true,
				'roles'     => array(),
				'new_items' => 'hide',
				'smart'     => true,
				'items'     => array(),
			),
			'toolbar'   => array(
				'enabled'   => true,
				'roles'     => array(),
				'new_items' => 'show',
				'smart'     => true,
				'items'     => array(
					'wp-logo'  => 'hide',
					'comments' => 'hide',
				),
			),
			'menu'      => array(
				'enabled'   => false,
				'roles'     => array(),
				'new_items' => 'show',
				'smart'     => false,
				'items'     => array(),
				'rename'    => array(),
			),
		);
	}

	/**
	 * Built-in "smart defaults" for items that have no explicit setting yet.
	 * Each rule is a substring of the item ID and the state it gets.
	 *
	 * @param string $section Section key.
	 * @return array<string,string>
	 */
	public static function smart_rules( $section ) {
		$rules = array(
			'dashboard' => array(
				// Keep: At a Glance, Wordfence, and WordPress's own PHP/browser warnings.
				'dashboard_right_now'   => 'show',
				'wordfence'             => 'show',
				'dashboard_php_nag'     => 'show',
				'dashboard_browser_nag' => 'show',
			),
			'toolbar'   => array(
				// Common plugin clutter in the toolbar.
				'wpseo-menu'                  => 'hide', // Yoast SEO.
				'rank-math'                   => 'hide', // Rank Math.
				'aioseo'                      => 'hide', // All in One SEO.
				'seopress'                    => 'hide', // SEOPress.
				'litespeed-menu'              => 'hide', // LiteSpeed Cache.
				'wp-rocket'                   => 'hide', // WP Rocket.
				'autoptimize'                 => 'hide', // Autoptimize.
				'w3tc'                        => 'hide', // W3 Total Cache.
				'wpo_purge_cache'             => 'hide', // WP-Optimize.
				'sg-cachepress'               => 'hide', // SiteGround Optimizer.
				'nitropack'                   => 'hide', // NitroPack.
				'wpforms-menu'                => 'hide', // WPForms.
				'gform-forms'                 => 'hide', // Gravity Forms.
				'monsterinsights'             => 'hide', // MonsterInsights.
				'updraft_admin_node'          => 'hide', // UpdraftPlus.
				'tribe-events'                => 'hide', // The Events Calendar.
				'enable-jquery-migrate-helper' => 'hide',
				'imagify'                     => 'hide',
				'wp-mail-smtp-menu'           => 'hide', // WP Mail SMTP.
				'stats'                       => 'hide', // Jetpack stats sparkline.
				'notes'                       => 'hide', // Jetpack notifications.
			),
			'menu'      => array(),
		);
		return isset( $rules[ $section ] ) ? $rules[ $section ] : array();
	}

	/**
	 * Items that can never be hidden, so nobody locks themselves out of the UI.
	 *
	 * @param string $section Section key.
	 * @return string[]
	 */
	public static function protected_items( $section ) {
		$protected = array(
			'dashboard' => array(),
			'toolbar'   => array( 'my-account', 'user-actions', 'user-info', 'edit-profile', 'logout', 'menu-toggle', 'top-secondary' ),
			'menu'      => array( 'options-general.php', 'options-general.php > declutter' ),
		);
		return isset( $protected[ $section ] ) ? $protected[ $section ] : array();
	}

	/**
	 * Saved settings merged over defaults.
	 *
	 * @return array
	 */
	public function get_settings() {
		if ( null === $this->settings ) {
			$saved          = get_option( self::OPTION, false );
			$this->settings = is_array( $saved ) ? self::sanitize_settings( $saved ) : self::defaults();
		}
		return $this->settings;
	}

	/**
	 * Save settings.
	 *
	 * @param array $settings Raw settings.
	 */
	public function save_settings( $settings ) {
		$this->settings = self::sanitize_settings( $settings );
		update_option( self::OPTION, $this->settings );
	}

	/**
	 * Clean an item ID (dashboard widget ID, toolbar node ID or menu slug).
	 *
	 * @param mixed $id Raw ID.
	 * @return string
	 */
	public static function clean_id( $id ) {
		if ( ! is_scalar( $id ) ) {
			return '';
		}
		$id = wp_check_invalid_utf8( (string) $id );
		$id = preg_replace( '/[\x00-\x1F\x7F]/', '', $id );
		$id = trim( $id );
		if ( strlen( $id ) > 255 ) {
			$id = substr( $id, 0, 255 );
		}
		return $id;
	}

	/**
	 * Validate a full settings array (used for save, load and import).
	 *
	 * @param mixed $raw Raw settings.
	 * @return array
	 */
	public static function sanitize_settings( $raw ) {
		$defaults = self::defaults();
		$out      = $defaults;
		$raw      = is_array( $raw ) ? $raw : array();
		$roles    = function_exists( 'wp_roles' ) ? array_keys( wp_roles()->roles ) : array();

		foreach ( self::SECTIONS as $section ) {
			if ( ! isset( $raw[ $section ] ) || ! is_array( $raw[ $section ] ) ) {
				continue;
			}
			$in  = $raw[ $section ];
			$sec = $defaults[ $section ];

			if ( isset( $in['enabled'] ) ) {
				$sec['enabled'] = (bool) $in['enabled'];
			}
			if ( isset( $in['smart'] ) ) {
				$sec['smart'] = (bool) $in['smart'];
			}
			if ( isset( $in['new_items'] ) && in_array( $in['new_items'], array( 'show', 'hide' ), true ) ) {
				$sec['new_items'] = $in['new_items'];
			}

			$sec['roles'] = array();
			if ( isset( $in['roles'] ) && is_array( $in['roles'] ) ) {
				foreach ( $in['roles'] as $role ) {
					$role = sanitize_key( (string) $role );
					if ( '' !== $role && ( empty( $roles ) || in_array( $role, $roles, true ) ) ) {
						$sec['roles'][] = $role;
					}
				}
				$sec['roles'] = array_values( array_unique( $sec['roles'] ) );
			}

			if ( isset( $in['items'] ) && is_array( $in['items'] ) ) {
				$sec['items'] = array();
				foreach ( $in['items'] as $id => $state ) {
					$id = self::clean_id( $id );
					if ( '' !== $id && in_array( $state, array( 'show', 'hide' ), true ) ) {
						$sec['items'][ $id ] = $state;
					}
				}
			}

			if ( 'menu' === $section ) {
				$sec['rename'] = array();
				if ( isset( $in['rename'] ) && is_array( $in['rename'] ) ) {
					foreach ( $in['rename'] as $id => $label ) {
						$id    = self::clean_id( $id );
						$label = is_scalar( $label ) ? sanitize_text_field( (string) $label ) : '';
						if ( '' !== $id && '' !== $label ) {
							$sec['rename'][ $id ] = mb_substr( $label, 0, 100 );
						}
					}
				}
			}

			$out[ $section ] = $sec;
		}

		$out['version'] = 1;
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Decisions
	 * ------------------------------------------------------------------ */

	/**
	 * Is this request a "scan" (everything visible so it can all be listed)?
	 *
	 * @return bool
	 */
	public function is_scan() {
		static $scan = null;
		if ( null === $scan ) {
			$scan = false;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified right here.
			if ( is_admin() && isset( $_GET['declutter_scan'] ) && current_user_can( 'manage_options' ) ) {
				$scan = (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['declutter_scan'] ) ), 'declutter_scan' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		return $scan;
	}

	/**
	 * Does a section apply to the current user on this request?
	 *
	 * @param string $section Section key.
	 * @return bool
	 */
	public function applies( $section ) {
		$settings = $this->get_settings();
		if ( empty( $settings[ $section ]['enabled'] ) || $this->is_scan() || ! is_user_logged_in() ) {
			return false;
		}
		$roles = $settings[ $section ]['roles'];
		if ( empty( $roles ) ) {
			return true; // Everyone, administrators included.
		}
		$user = wp_get_current_user();
		return (bool) array_intersect( $roles, (array) $user->roles );
	}

	/**
	 * Effective state of one item: explicit choice, then smart rule, then the
	 * section's "new items" default.
	 *
	 * @param string $section Section key.
	 * @param string $id      Item ID.
	 * @return bool True if hidden.
	 */
	public function is_hidden( $section, $id ) {
		if ( in_array( $id, self::protected_items( $section ), true ) ) {
			return false;
		}
		$sec = $this->get_settings()[ $section ];
		if ( isset( $sec['items'][ $id ] ) ) {
			return 'hide' === $sec['items'][ $id ];
		}
		if ( ! empty( $sec['smart'] ) ) {
			foreach ( self::smart_rules( $section ) as $needle => $state ) {
				if ( false !== strpos( $id, $needle ) ) {
					return 'hide' === $state;
				}
			}
		}
		return 'hide' === $sec['new_items'];
	}

	/* ---------------------------------------------------------------------
	 * Discovery
	 * ------------------------------------------------------------------ */

	/**
	 * Only admins who can configure the plugin feed the discovered lists.
	 *
	 * @return bool
	 */
	private function can_capture() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Store a discovered list if it changed.
	 *
	 * @param string $section Section key.
	 * @param array  $items   Items keyed by ID.
	 * @param bool   $merge   Merge with what was seen before (true) or replace.
	 */
	private function store_seen( $section, array $items, $merge ) {
		$seen = get_option( self::SEEN_OPTION, array() );
		$seen = is_array( $seen ) ? $seen : array();
		$old  = isset( $seen[ $section ] ) && is_array( $seen[ $section ] ) ? $seen[ $section ] : array();
		$new  = $merge ? array_merge( $old, $items ) : $items;
		if ( maybe_serialize( $old ) === maybe_serialize( $new ) ) {
			return;
		}
		$seen[ $section ] = $new;
		update_option( self::SEEN_OPTION, $seen, false );
	}

	/**
	 * Discovered lists.
	 *
	 * @return array
	 */
	public static function get_seen() {
		$seen = get_option( self::SEEN_OPTION, array() );
		$seen = is_array( $seen ) ? $seen : array();
		foreach ( self::SECTIONS as $section ) {
			if ( ! isset( $seen[ $section ] ) || ! is_array( $seen[ $section ] ) ) {
				$seen[ $section ] = array();
			}
		}
		return $seen;
	}

	/**
	 * Forget discovered lists (the next scan rebuilds them).
	 */
	public static function clear_seen() {
		update_option( self::SEEN_OPTION, array(), false );
	}

	/**
	 * Turn a widget/node/menu title into plain text: drops icons, count
	 * bubbles and screen-reader-only text.
	 *
	 * @param mixed  $html     Raw title.
	 * @param string $fallback Used when nothing readable is left.
	 * @return string
	 */
	public static function clean_title( $html, $fallback = '' ) {
		$html = is_scalar( $html ) ? (string) $html : '';
		$sr   = '';
		if ( preg_match( '/<span[^>]*class=["\'][^"\']*screen-reader-text[^"\']*["\'][^>]*>(.*?)<\/span>/is', $html, $m ) ) {
			$sr = trim( wp_strip_all_tags( $m[1] ) );
		}
		$pattern = '/<span[^>]*class=["\'][^"\']*(?:awaiting-mod|update-plugins|count|screen-reader-text|menu-counter|ab-icon|ab-label|update-count|plugin-count|pending|hide-if-js)[^"\']*["\'][^>]*>(?:(?!<span).)*?<\/span>/is';
		$prev    = null;
		$guard   = 0;
		while ( $prev !== $html && $guard++ < 10 ) {
			$prev = $html;
			$html = preg_replace( $pattern, '', $html );
			$html = preg_replace( '/<span[^>]*>\s*<\/span>/is', '', $html );
		}
		$html = preg_replace( '/>\s*</', '> <', $html );
		$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( '' === $text && '' !== $sr ) {
			$text = trim( preg_replace( '/^\d+\s*/', '', html_entity_decode( $sr, ENT_QUOTES, 'UTF-8' ) ) );
		}
		if ( '' === $text ) {
			$text = $fallback;
		}
		return mb_substr( $text, 0, 120 );
	}

	/* ---------------------------------------------------------------------
	 * Dashboard widgets
	 * ------------------------------------------------------------------ */

	/**
	 * Capture and hide dashboard widgets. Hooked to do_meta_boxes, which
	 * wp_dashboard_setup() fires for the 'side' context last.
	 *
	 * @param string $screen  Screen ID.
	 * @param string $context Meta box context.
	 */
	public function handle_dashboard( $screen, $context ) {
		if ( 'dashboard' !== $screen || 'side' !== $context ) {
			return;
		}
		global $wp_meta_boxes;

		$found = array();
		if ( has_action( 'welcome_panel' ) ) {
			$found['welcome_panel'] = array(
				'title'   => __( 'Welcome panel', 'declutter' ),
				'context' => 'welcome',
			);
		}
		if ( isset( $wp_meta_boxes['dashboard'] ) && is_array( $wp_meta_boxes['dashboard'] ) ) {
			foreach ( $wp_meta_boxes['dashboard'] as $box_context => $priorities ) {
				foreach ( (array) $priorities as $boxes ) {
					foreach ( (array) $boxes as $id => $box ) {
						if ( ! is_array( $box ) || ! isset( $box['id'] ) ) {
							continue; // Already removed by someone else.
						}
						$found[ (string) $id ] = array(
							'title'   => self::clean_title( isset( $box['title'] ) ? $box['title'] : '', (string) $id ),
							'context' => (string) $box_context,
						);
					}
				}
			}
		}

		if ( $this->can_capture() ) {
			$this->store_seen( 'dashboard', $found, true );
		}

		if ( ! $this->applies( 'dashboard' ) ) {
			return;
		}
		foreach ( $found as $id => $info ) {
			if ( ! $this->is_hidden( 'dashboard', $id ) ) {
				continue;
			}
			if ( 'welcome' === $info['context'] ) {
				remove_all_actions( 'welcome_panel' );
			} else {
				remove_meta_box( $id, 'dashboard', $info['context'] );
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Toolbar
	 * ------------------------------------------------------------------ */

	/**
	 * Friendly names for core toolbar nodes whose titles are just icons.
	 *
	 * @return array
	 */
	private static function toolbar_labels() {
		return array(
			'wp-logo'     => __( 'WordPress logo menu', 'declutter' ),
			'site-name'   => __( 'Site name', 'declutter' ),
			'comments'    => __( 'Comments bubble', 'declutter' ),
			'updates'     => __( 'Updates', 'declutter' ),
			'new-content' => __( '+ New', 'declutter' ),
			'my-account'  => __( 'Howdy / My Account', 'declutter' ),
			'search'      => __( 'Search', 'declutter' ),
			'customize'   => __( 'Customize', 'declutter' ),
			'menu-toggle' => __( 'Mobile menu toggle', 'declutter' ),
		);
	}

	/**
	 * Capture and hide toolbar nodes.
	 */
	public function handle_toolbar() {
		global $wp_admin_bar;
		if ( ! is_object( $wp_admin_bar ) || ! method_exists( $wp_admin_bar, 'get_nodes' ) ) {
			return;
		}
		$nodes = $wp_admin_bar->get_nodes();
		if ( empty( $nodes ) ) {
			return;
		}

		if ( $this->can_capture() ) {
			$labels = self::toolbar_labels();
			$found  = array();
			foreach ( $nodes as $id => $node ) {
				if ( ! empty( $node->group ) || 'root' === $id ) {
					continue;
				}
				// Nearest non-group ancestor, so the list nests sensibly.
				$parent = (string) $node->parent;
				$guard  = 0;
				while ( '' !== $parent && isset( $nodes[ $parent ] ) && ! empty( $nodes[ $parent ]->group ) && $guard++ < 20 ) {
					$parent = (string) $nodes[ $parent ]->parent;
				}
				if ( 'root' === $parent || ! isset( $nodes[ $parent ] ) ) {
					$parent = '';
				}
				$title        = isset( $labels[ $id ] ) ? $labels[ $id ] : self::clean_title( $node->title, (string) $id );
				$found[ $id ] = array(
					'title'  => $title,
					'parent' => $parent,
				);
			}
			$this->store_seen( 'toolbar', $found, true );
		}

		if ( ! $this->applies( 'toolbar' ) ) {
			return;
		}
		foreach ( $nodes as $id => $node ) {
			if ( empty( $node->group ) && $this->is_hidden( 'toolbar', (string) $id ) ) {
				// Children of a removed node are skipped by WordPress automatically.
				$wp_admin_bar->remove_node( $id );
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Admin menu
	 * ------------------------------------------------------------------ */

	/**
	 * Submenu item ID.
	 *
	 * @param string $parent Parent slug.
	 * @param string $slug   Submenu slug.
	 * @return string
	 */
	public static function sub_id( $parent, $slug ) {
		return $parent . ' > ' . $slug;
	}

	/**
	 * Capture, rename and hide menu entries. Hidden entries get a CSS class
	 * instead of being removed, so access checks and parent links are untouched.
	 */
	public function handle_menu() {
		global $menu, $submenu;
		if ( ! is_array( $menu ) ) {
			return;
		}

		if ( $this->can_capture() ) {
			$found = array();
			foreach ( $menu as $item ) {
				if ( ! isset( $item[2] ) ) {
					continue;
				}
				$slug         = self::clean_id( $item[2] );
				$is_sep       = isset( $item[4] ) && false !== strpos( (string) $item[4], 'wp-menu-separator' );
				$found[ $slug ] = array(
					'title'  => $is_sep ? __( '— Separator —', 'declutter' ) : self::clean_title( $item[0], $slug ),
					'parent' => '',
				);
				if ( isset( $submenu[ $item[2] ] ) && is_array( $submenu[ $item[2] ] ) ) {
					foreach ( $submenu[ $item[2] ] as $sub ) {
						if ( ! isset( $sub[2] ) ) {
							continue;
						}
						$sid           = self::sub_id( $slug, self::clean_id( $sub[2] ) );
						$found[ $sid ] = array(
							'title'  => self::clean_title( $sub[0], (string) $sub[2] ),
							'parent' => $slug,
						);
					}
				}
			}
			$this->store_seen( 'menu', $found, false );
		}

		if ( ! $this->applies( 'menu' ) ) {
			return;
		}
		$settings = $this->get_settings();
		$rename   = $settings['menu']['rename'];
		$hid_any  = false;

		foreach ( $menu as $pos => $item ) {
			if ( ! isset( $item[2] ) ) {
				continue;
			}
			$slug = self::clean_id( $item[2] );
			if ( isset( $rename[ $slug ] ) ) {
				$menu[ $pos ][0] = esc_html( $rename[ $slug ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
			if ( $this->is_hidden( 'menu', $slug ) ) {
				$menu[ $pos ][4] = ( isset( $item[4] ) ? $item[4] . ' ' : '' ) . 'declutter-hidden'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$hid_any         = true;
			}
			if ( isset( $submenu[ $item[2] ] ) && is_array( $submenu[ $item[2] ] ) ) {
				foreach ( $submenu[ $item[2] ] as $k => $sub ) {
					if ( ! isset( $sub[2] ) ) {
						continue;
					}
					$sid = self::sub_id( $slug, self::clean_id( $sub[2] ) );
					if ( isset( $rename[ $sid ] ) ) {
						$submenu[ $item[2] ][ $k ][0] = esc_html( $rename[ $sid ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					}
					if ( $this->is_hidden( 'menu', $sid ) ) {
						$submenu[ $item[2] ][ $k ][4] = ( isset( $sub[4] ) ? $sub[4] . ' ' : '' ) . 'declutter-hidden'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
						$hid_any                      = true;
					}
				}
			}
		}

		if ( $hid_any ) {
			echo "<style id=\"declutter-menu\">#adminmenu .declutter-hidden{display:none!important}</style>\n";
		}
	}
}
