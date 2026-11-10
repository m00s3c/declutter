<?php
/**
 * Settings screen (Settings > Declutter) and its form handlers.
 *
 * @package Declutter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI.
 */
final class Declutter_Admin {

	const PAGE = 'declutter';

	/**
	 * Singleton instance.
	 *
	 * @var Declutter_Admin|null
	 */
	private static $instance = null;

	/**
	 * Settings page hook suffix.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Get the instance.
	 *
	 * @return Declutter_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DECLUTTER_FILE ), array( $this, 'action_links' ) );
		add_action( 'admin_footer', array( $this, 'scan_return' ) );

		foreach ( array( 'save', 'reset', 'export', 'import', 'rescan' ) as $action ) {
			add_action( 'admin_post_declutter_' . $action, array( $this, 'handle_' . $action ) );
		}
	}

	/**
	 * Settings page URL.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function page_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'options-general.php' ) );
	}

	/**
	 * Register Settings > Declutter.
	 */
	public function add_page() {
		$this->hook = add_options_page(
			__( 'Declutter', 'declutter' ),
			__( 'Declutter', 'declutter' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Settings', 'declutter' ) . '</a>' );
		return $links;
	}

	/**
	 * CSS/JS on our screen only.
	 *
	 * @param string $hook Current screen hook.
	 */
	public function assets( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_style( 'declutter-admin', DECLUTTER_URL . 'assets/admin.css', array(), DECLUTTER_VERSION );
		wp_enqueue_script( 'declutter-admin', DECLUTTER_URL . 'assets/admin.js', array(), DECLUTTER_VERSION, true );
		wp_localize_script(
			'declutter-admin',
			'declutterL10n',
			array(
				'confirmReset' => __( 'Reset all Declutter settings to the defaults?', 'declutter' ),
			)
		);
	}

	/**
	 * After a scan of the dashboard, bounce back to the settings screen.
	 */
	public function scan_return() {
		if ( ! Declutter::instance()->is_scan() ) {
			return;
		}
		$url = self::page_url( array( 'declutter_msg' => 'scanned' ) );
		echo '<script>window.location.replace(' . wp_json_encode( $url ) . ');</script>';
	}

	/* ---------------------------------------------------------------------
	 * Handlers
	 * ------------------------------------------------------------------ */

	/**
	 * Capability + nonce gate for every handler.
	 *
	 * @param string $action Nonce action.
	 */
	private function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'declutter' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * Redirect back with a message.
	 *
	 * @param string $msg Message key.
	 */
	private function back( $msg ) {
		wp_safe_redirect( self::page_url( array( 'declutter_msg' => $msg ) ) );
		exit;
	}

	/**
	 * Save the settings form.
	 */
	public function handle_save() {
		$this->guard( 'declutter_save' );
		$post     = isset( $_POST['declutter'] ) && is_array( $_POST['declutter'] ) ? wp_unslash( $_POST['declutter'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Declutter::sanitize_settings().
		$current  = Declutter::instance()->get_settings();
		$settings = $current;

		foreach ( Declutter::SECTIONS as $section ) {
			$in  = isset( $post[ $section ] ) && is_array( $post[ $section ] ) ? $post[ $section ] : array();
			$sec = $current[ $section ];

			$sec['enabled']   = ! empty( $in['enabled'] );
			$sec['smart']     = ! empty( $in['smart'] );
			$sec['new_items'] = ( isset( $in['new_items'] ) && 'hide' === $in['new_items'] ) ? 'hide' : 'show';
			$sec['roles']     = ( isset( $in['scope'] ) && 'roles' === $in['scope'] && isset( $in['roles'] ) && is_array( $in['roles'] ) ) ? $in['roles'] : array();

			$items  = array();
			$rename = array();
			if ( isset( $in['rows'] ) && is_array( $in['rows'] ) ) {
				foreach ( $in['rows'] as $row ) {
					if ( ! is_array( $row ) || ! isset( $row['id'] ) ) {
						continue;
					}
					$id = Declutter::clean_id( $row['id'] );
					if ( '' === $id ) {
						continue;
					}
					$items[ $id ] = ! empty( $row['hide'] ) ? 'hide' : 'show';
					if ( 'menu' === $section && isset( $row['rename'] ) && '' !== trim( (string) $row['rename'] ) ) {
						$rename[ $id ] = $row['rename'];
					}
				}
			}
			$sec['items'] = $items;
			if ( 'menu' === $section ) {
				$sec['rename'] = $rename;
			}
			$settings[ $section ] = $sec;
		}

		Declutter::instance()->save_settings( $settings );
		$this->back( 'saved' );
	}

	/**
	 * Reset to the shipped defaults.
	 */
	public function handle_reset() {
		$this->guard( 'declutter_reset' );
		Declutter::instance()->save_settings( Declutter::defaults() );
		$this->back( 'reset' );
	}

	/**
	 * Download the config as JSON.
	 */
	public function handle_export() {
		$this->guard( 'declutter_export' );
		$data = array(
			'plugin'   => 'declutter',
			'version'  => DECLUTTER_VERSION,
			'exported' => gmdate( 'c' ),
			'settings' => Declutter::instance()->get_settings(),
		);
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$name = 'declutter-' . sanitize_file_name( $host ? $host : 'site' ) . '-' . gmdate( 'Y-m-d' ) . '.json';
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Import a JSON config (file upload or pasted text).
	 */
	public function handle_import() {
		$this->guard( 'declutter_import' );
		$json = '';
		if ( ! empty( $_FILES['declutter_file']['tmp_name'] ) && is_uploaded_file( $_FILES['declutter_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( (int) $_FILES['declutter_file']['size'] > 1024 * 1024 ) {
				$this->back( 'import_error' );
			}
			$json = (string) file_get_contents( $_FILES['declutter_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.Security.ValidatedSanitizedInput
		} elseif ( isset( $_POST['declutter_json'] ) ) {
			$json = (string) wp_unslash( $_POST['declutter_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded and sanitized below.
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			$this->back( 'import_error' );
		}
		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$data = $data['settings'];
		}
		$found = false;
		foreach ( Declutter::SECTIONS as $section ) {
			if ( isset( $data[ $section ] ) && is_array( $data[ $section ] ) ) {
				$found = true;
			}
		}
		if ( ! $found ) {
			$this->back( 'import_error' );
		}
		Declutter::instance()->save_settings( $data );
		$this->back( 'imported' );
	}

	/**
	 * Forget the detected lists and re-scan via the dashboard.
	 */
	public function handle_rescan() {
		$this->guard( 'declutter_rescan' );
		Declutter::clear_seen();
		wp_safe_redirect( add_query_arg( 'declutter_scan', wp_create_nonce( 'declutter_scan' ), admin_url( 'index.php' ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	/**
	 * Rows for one section: everything detected, plus saved items that were
	 * not detected on this site (e.g. from an imported config).
	 *
	 * @param string $section Section key.
	 * @param array  $seen    Detected items.
	 * @param array  $config  Section settings.
	 * @return array[] Each: id, title, depth, detected.
	 */
	private function rows( $section, array $seen, array $config ) {
		$rows = array();

		if ( 'toolbar' === $section ) {
			$children = array();
			foreach ( $seen as $id => $info ) {
				$parent = isset( $info['parent'] ) ? (string) $info['parent'] : '';
				if ( '' !== $parent && ! isset( $seen[ $parent ] ) ) {
					$parent = '';
				}
				$children[ $parent ][] = (string) $id;
			}
			$walk = function ( $parent, $depth ) use ( &$walk, &$rows, $children, $seen ) {
				if ( empty( $children[ $parent ] ) || $depth > 10 ) {
					return;
				}
				foreach ( $children[ $parent ] as $id ) {
					$rows[ $id ] = array(
						'id'       => $id,
						'title'    => isset( $seen[ $id ]['title'] ) ? $seen[ $id ]['title'] : $id,
						'depth'    => $depth,
						'detected' => true,
					);
					$walk( $id, $depth + 1 );
				}
			};
			$walk( '', 0 );
		} else {
			foreach ( $seen as $id => $info ) {
				$id          = (string) $id;
				$rows[ $id ] = array(
					'id'       => $id,
					'title'    => isset( $info['title'] ) ? $info['title'] : $id,
					'depth'    => ( 'menu' === $section && ! empty( $info['parent'] ) ) ? 1 : 0,
					'detected' => true,
				);
			}
		}

		$extra = array_keys( $config['items'] );
		if ( 'menu' === $section ) {
			$extra = array_merge( $extra, array_keys( $config['rename'] ) );
		}
		foreach ( $extra as $id ) {
			$id = (string) $id;
			if ( ! isset( $rows[ $id ] ) ) {
				$rows[ $id ] = array(
					'id'       => $id,
					'title'    => $id,
					'depth'    => 0,
					'detected' => false,
				);
			}
		}
		return array_values( $rows );
	}

	/**
	 * Notices after redirects.
	 */
	private function notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$msg      = isset( $_GET['declutter_msg'] ) ? sanitize_key( wp_unslash( $_GET['declutter_msg'] ) ) : '';
		$messages = array(
			'saved'        => array( 'success', __( 'Settings saved.', 'declutter' ) ),
			'reset'        => array( 'success', __( 'Settings reset to the defaults.', 'declutter' ) ),
			'imported'     => array( 'success', __( 'Settings imported.', 'declutter' ) ),
			'scanned'      => array( 'success', __( 'Lists refreshed. Toolbar items that only appear on the front end of the site show up after you visit it while logged in.', 'declutter' ) ),
			'import_error' => array( 'error', __( 'That file or text is not a valid Declutter export.', 'declutter' ) ),
		);
		if ( isset( $messages[ $msg ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $messages[ $msg ][0] ),
				esc_html( $messages[ $msg ][1] )
			);
		}
	}

	/**
	 * The settings screen.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Declutter::instance()->get_settings();
		$seen     = Declutter::get_seen();
		$sections = array(
			'dashboard' => array(
				'title' => __( 'Dashboard widgets', 'declutter' ),
				'desc'  => __( 'Hide widgets on the Dashboard home screen.', 'declutter' ),
				'empty' => __( 'No widgets detected yet. Click "Refresh lists" or open the Dashboard once.', 'declutter' ),
			),
			'toolbar'   => array(
				'title' => __( 'Toolbar', 'declutter' ),
				'desc'  => __( 'Hide items from the black admin bar at the top of the screen, in wp-admin and on the front end. Hiding a parent hides its children too.', 'declutter' ),
				'empty' => __( 'No toolbar items detected yet. Reload this page.', 'declutter' ),
			),
			'menu'      => array(
				'title' => __( 'Admin menu', 'declutter' ),
				'desc'  => __( 'Hide or rename entries in the left sidebar. Hidden pages still open by direct URL; nothing is blocked.', 'declutter' ),
				'empty' => __( 'No menu items detected yet. Reload this page.', 'declutter' ),
			),
		);
		$roles    = wp_roles()->get_names();
		?>
		<div class="wrap declutter-wrap">
			<noscript>
				<style>
					.declutter-wrap .declutter-collapse { grid-template-rows: 1fr; }
					.declutter-wrap .declutter-section .declutter-head { border-bottom-color: #dcdcde; }
					.declutter-wrap .declutter-chevron { transform: rotate(45deg); }
				</style>
			</noscript>
			<h1><?php esc_html_e( 'Declutter', 'declutter' ); ?></h1>
			<p class="declutter-tagline"><?php esc_html_e( 'Declutter your WordPress admin.', 'declutter' ); ?></p>
			<?php $this->notices(); ?>
			<p class="declutter-intro">
				<?php esc_html_e( 'Each section is optional. Anything in a section that is switched off is left alone. Settings apply to everyone, administrators included, unless you limit a section to certain roles. This is cosmetic only: no permissions are changed.', 'declutter' ); ?>
			</p>
			<p class="declutter-expand-bar">
				<button type="button" class="button button-secondary declutter-expand-all"><?php esc_html_e( 'Expand all', 'declutter' ); ?></button>
				<button type="button" class="button button-secondary declutter-collapse-all"><?php esc_html_e( 'Collapse all', 'declutter' ); ?></button>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="declutter-form">
				<input type="hidden" name="action" value="declutter_save">
				<?php wp_nonce_field( 'declutter_save' ); ?>

				<?php foreach ( $sections as $key => $meta ) : ?>
					<?php
					$sec       = $settings[ $key ];
					$rows      = $this->rows( $key, $seen[ $key ], $sec );
					$protected = Declutter::protected_items( $key );
					$name      = 'declutter[' . $key . ']';
					?>
					<div class="declutter-section postbox<?php echo $sec['enabled'] ? '' : ' is-off'; ?>" data-section="<?php echo esc_attr( $key ); ?>">
						<div class="declutter-head">
							<h2>
								<button type="button" class="declutter-toggle" aria-expanded="false" aria-controls="declutter-panel-<?php echo esc_attr( $key ); ?>">
									<span class="declutter-chevron" aria-hidden="true"></span>
									<?php echo esc_html( $meta['title'] ); ?>
								</button>
							</h2>
							<label class="declutter-switch">
								<input type="checkbox" class="declutter-enable" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $sec['enabled'] ); ?>>
								<span class="declutter-slider" aria-hidden="true"></span>
								<span class="declutter-switch-label"><?php esc_html_e( 'Enabled', 'declutter' ); ?></span>
							</label>
						</div>
						<div class="declutter-collapse" id="declutter-panel-<?php echo esc_attr( $key ); ?>">
							<div class="declutter-collapse-inner">
						<div class="declutter-body">
							<p class="description"><?php echo esc_html( $meta['desc'] ); ?></p>

							<div class="declutter-options">
								<fieldset>
									<legend><?php esc_html_e( 'Apply to', 'declutter' ); ?></legend>
									<div class="declutter-choice-list">
										<label class="declutter-choice"><input type="radio" class="declutter-scope" name="<?php echo esc_attr( $name ); ?>[scope]" value="all" <?php checked( empty( $sec['roles'] ) ); ?>> <?php esc_html_e( 'All users (including administrators)', 'declutter' ); ?></label>
										<label class="declutter-choice"><input type="radio" class="declutter-scope" name="<?php echo esc_attr( $name ); ?>[scope]" value="roles" <?php checked( ! empty( $sec['roles'] ) ); ?>> <?php esc_html_e( 'Only these roles:', 'declutter' ); ?></label>
									</div>
									<div class="declutter-roles<?php echo empty( $sec['roles'] ) ? ' is-hidden' : ''; ?>">
										<?php foreach ( $roles as $role_key => $role_name ) : ?>
											<label class="declutter-choice"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $sec['roles'], true ) ); ?>> <?php echo esc_html( translate_user_role( $role_name ) ); ?></label>
										<?php endforeach; ?>
									</div>
								</fieldset>
								<fieldset>
									<legend><?php esc_html_e( 'Items detected later', 'declutter' ); ?></legend>
									<div class="declutter-choice-list">
										<label class="declutter-select-label">
											<select name="<?php echo esc_attr( $name ); ?>[new_items]">
												<option value="show" <?php selected( $sec['new_items'], 'show' ); ?>><?php esc_html_e( 'Show them', 'declutter' ); ?></option>
												<option value="hide" <?php selected( $sec['new_items'], 'hide' ); ?>><?php esc_html_e( 'Hide them', 'declutter' ); ?></option>
											</select>
										</label>
										<?php if ( Declutter::smart_rules( $key ) ) : ?>
											<label class="declutter-choice declutter-smart"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[smart]" value="1" <?php checked( $sec['smart'] ); ?>> <?php echo esc_html( 'dashboard' === $key ? __( 'Smart defaults: always keep At a Glance, Wordfence and WordPress warnings', 'declutter' ) : __( 'Smart defaults: hide known plugin clutter (SEO, cache, forms, analytics…)', 'declutter' ) ); ?></label>
										<?php else : ?>
											<input type="hidden" name="<?php echo esc_attr( $name ); ?>[smart]" value="<?php echo $sec['smart'] ? '1' : ''; ?>">
										<?php endif; ?>
									</div>
								</fieldset>
							</div>

							<?php if ( empty( $rows ) ) : ?>
								<p class="declutter-empty"><?php echo esc_html( $meta['empty'] ); ?></p>
							<?php else : ?>
								<div class="declutter-tools">
									<input type="search" class="declutter-filter" placeholder="<?php esc_attr_e( 'Filter…', 'declutter' ); ?>" aria-label="<?php esc_attr_e( 'Filter items', 'declutter' ); ?>">
									<div class="declutter-tools-actions">
										<button type="button" class="button button-secondary declutter-all" data-hide="1"><?php esc_html_e( 'Hide all', 'declutter' ); ?></button>
										<button type="button" class="button button-secondary declutter-all" data-hide="0"><?php esc_html_e( 'Show all', 'declutter' ); ?></button>
									</div>
								</div>
								<table class="widefat striped declutter-table">
									<thead>
										<tr>
											<th scope="col" class="declutter-col-item"><?php esc_html_e( 'Item', 'declutter' ); ?></th>
											<th scope="col" class="declutter-col-id"><?php esc_html_e( 'ID', 'declutter' ); ?></th>
											<?php if ( 'menu' === $key ) : ?>
												<th scope="col" class="declutter-col-rename"><?php esc_html_e( 'Rename', 'declutter' ); ?></th>
											<?php endif; ?>
											<th scope="col" class="declutter-col-hide"><?php esc_html_e( 'Hide', 'declutter' ); ?></th>
										</tr>
									</thead>
									<tbody>
									<?php foreach ( $rows as $i => $row ) : ?>
										<?php
										$locked = in_array( $row['id'], $protected, true );
										$hidden = ! $locked && Declutter::instance()->is_hidden( $key, $row['id'] );
										$field  = $name . '[rows][' . $i . ']';
										?>
										<tr class="declutter-row depth-<?php echo (int) min( $row['depth'], 4 ); ?>">
											<td class="declutter-title">
												<?php echo esc_html( $row['title'] ); ?>
												<?php if ( ! $row['detected'] ) : ?>
													<span class="declutter-badge"><?php esc_html_e( 'not detected on this site', 'declutter' ); ?></span>
												<?php endif; ?>
												<input type="hidden" name="<?php echo esc_attr( $field ); ?>[id]" value="<?php echo esc_attr( $row['id'] ); ?>">
											</td>
											<td class="declutter-col-id"><code><?php echo esc_html( $row['id'] ); ?></code></td>
											<?php if ( 'menu' === $key ) : ?>
												<td class="declutter-col-rename"><input type="text" class="declutter-rename" name="<?php echo esc_attr( $field ); ?>[rename]" value="<?php echo esc_attr( isset( $sec['rename'][ $row['id'] ] ) ? $sec['rename'][ $row['id'] ] : '' ); ?>" placeholder="<?php echo esc_attr( $row['title'] ); ?>" aria-label="<?php esc_attr_e( 'New label', 'declutter' ); ?>"></td>
											<?php endif; ?>
											<td class="declutter-col-hide">
												<?php if ( $locked ) : ?>
													<span class="declutter-locked" title="<?php esc_attr_e( 'Always shown so nobody gets locked out.', 'declutter' ); ?>"><?php esc_html_e( 'Always shown', 'declutter' ); ?></span>
												<?php else : ?>
													<label class="declutter-switch">
														<input type="checkbox" class="declutter-hide" name="<?php echo esc_attr( $field ); ?>[hide]" value="1" <?php checked( $hidden ); ?>>
														<span class="declutter-slider" aria-hidden="true"></span>
														<span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: item name */ __( 'Hide %s', 'declutter' ), $row['title'] ) ); ?></span>
													</label>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>

				<p class="submit declutter-submit">
					<?php submit_button( __( 'Save changes', 'declutter' ), 'primary', 'submit', false ); ?>
				</p>
			</form>

			<div class="declutter-section postbox declutter-tools-box" data-section="tools">
				<div class="declutter-head">
					<h2>
						<button type="button" class="declutter-toggle" aria-expanded="false" aria-controls="declutter-panel-tools">
							<span class="declutter-chevron" aria-hidden="true"></span>
							<?php esc_html_e( 'Tools', 'declutter' ); ?>
						</button>
					</h2>
				</div>
				<div class="declutter-collapse" id="declutter-panel-tools">
					<div class="declutter-collapse-inner">
				<div class="declutter-body declutter-tool-grid">
					<div>
						<h3><?php esc_html_e( 'Refresh lists', 'declutter' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Forget what was detected and scan again (quickly opens the Dashboard with nothing hidden, then comes back). Use after installing or removing plugins.', 'declutter' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="declutter_rescan">
							<?php wp_nonce_field( 'declutter_rescan' ); ?>
							<?php submit_button( __( 'Refresh lists', 'declutter' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>
					<div>
						<h3><?php esc_html_e( 'Export', 'declutter' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Download these settings as a JSON file to reuse on another site.', 'declutter' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="declutter_export">
							<?php wp_nonce_field( 'declutter_export' ); ?>
							<?php submit_button( __( 'Download JSON', 'declutter' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>
					<div>
						<h3><?php esc_html_e( 'Import', 'declutter' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Upload an export file or paste its contents. This replaces the current settings.', 'declutter' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
							<input type="hidden" name="action" value="declutter_import">
							<?php wp_nonce_field( 'declutter_import' ); ?>
							<p class="declutter-field"><input type="file" name="declutter_file" accept=".json,application/json"></p>
							<p class="declutter-field"><textarea name="declutter_json" rows="4" class="large-text code" placeholder="<?php esc_attr_e( '…or paste JSON here', 'declutter' ); ?>"></textarea></p>
							<?php submit_button( __( 'Import', 'declutter' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>
					<div>
						<h3><?php esc_html_e( 'Reset', 'declutter' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Go back to the built-in defaults: Dashboard keeps only At a Glance and Wordfence, toolbar drops the WordPress logo, comments bubble and known plugin clutter, menu untouched.', 'declutter' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="declutter-reset-form">
							<input type="hidden" name="action" value="declutter_reset">
							<?php wp_nonce_field( 'declutter_reset' ); ?>
							<?php submit_button( __( 'Reset to defaults', 'declutter' ), 'delete', 'submit', false ); ?>
						</form>
					</div>
				</div>
					</div>
				</div>
			</div>
		</div>
		<script>
		( function () {
			try {
				var state = JSON.parse( localStorage.getItem( 'declutter-sections' ) || '{}' );
				document.querySelectorAll( '.declutter-wrap .declutter-section[data-section]' ).forEach( function ( box ) {
					if ( state[ box.getAttribute( 'data-section' ) ] ) {
						box.classList.add( 'is-open' );
						var btn = box.querySelector( '.declutter-toggle' );
						if ( btn ) {
							btn.setAttribute( 'aria-expanded', 'true' );
						}
					}
				} );
			} catch ( e ) {}
		}() );
		</script>
		<?php
	}
}
