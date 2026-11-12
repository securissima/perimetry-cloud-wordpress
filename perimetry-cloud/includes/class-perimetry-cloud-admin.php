<?php
/**
 * The plugin's page (Settings › Perimetry Cloud) and its dashboard widget. Every action needs manage_options and a nonce.
 *
 * @package PerimetryCloud
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin side: free check, connection, ownership proof, sharing of the WordPress report, disconnection.
 */
class Perimetry_Cloud_Admin {

	const PAGE         = 'perimetry-cloud';
	const CHECK_OPTION = 'perimetry_cloud_check';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'textdomain' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'widget' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'check', 'connect', 'verify', 'share', 'send', 'disconnect', 'refresh' ) as $a ) {
			add_action( 'admin_post_perimetry_cloud_' . $a, array( __CLASS__, 'do_' . $a ) );
		}
		add_filter( 'plugin_action_links_' . plugin_basename( PERIMETRY_CLOUD_FILE ), array( __CLASS__, 'links' ) );
	}

	/**
	 * The English, French and German texts shipped with the plugin.
	 */
	public static function textdomain() {
		load_plugin_textdomain( 'perimetry-cloud', false, dirname( plugin_basename( PERIMETRY_CLOUD_FILE ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- French and German ship with the plugin.
	}

	/**
	 * Settings › Perimetry Cloud.
	 */
	public static function menu() {
		$hook = add_options_page( __( 'Perimetry Cloud', 'perimetry-cloud' ), __( 'Perimetry Cloud', 'perimetry-cloud' ), 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
		add_action( 'load-' . $hook, array( __CLASS__, 'back_from_connection' ) );
	}

	/**
	 * A "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'perimetry-cloud' ) . '</a>' );
		return $links;
	}

	/**
	 * Styles and the small script that refreshes a running check.
	 *
	 * @param string $hook Current admin screen.
	 */
	public static function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook && 'index.php' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'perimetry-cloud', plugins_url( 'assets/admin.css', PERIMETRY_CLOUD_FILE ), array(), PERIMETRY_CLOUD_VERSION );
		wp_enqueue_script( 'perimetry-cloud', plugins_url( 'assets/admin.js', PERIMETRY_CLOUD_FILE ), array(), PERIMETRY_CLOUD_VERSION, true );
	}

	/**
	 * The plugin's page.
	 *
	 * @param array $args Query arguments.
	 * @return string
	 */
	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'options-general.php' ) );
	}

	/**
	 * A message for the next page shown to this administrator.
	 *
	 * @param string $type    success, error or info.
	 * @param string $message Text.
	 */
	private static function notice( $type, $message ) {
		set_transient( 'perimetry_cloud_notice_' . get_current_user_id(), array( $type, $message ), 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Checks rights and nonce of an action, then sends the administrator back to the page.
	 *
	 * @param string $action Action name.
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'perimetry-cloud' ), 403 );
		}
		check_admin_referer( 'perimetry_cloud_' . $action );
	}

	/**
	 * A form that posts one action.
	 *
	 * @param string $action Action name.
	 * @param string $label  Button text.
	 * @param string $class  Button class.
	 * @param string $extra  Extra fields (already escaped).
	 */
	private static function button( $action, $label, $class = 'button button-primary', $extra = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="perimetry-inline">';
		echo '<input type="hidden" name="action" value="' . esc_attr( 'perimetry_cloud_' . $action ) . '">';
		wp_nonce_field( 'perimetry_cloud_' . $action );
		echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by this class from escaped parts.
		echo '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	// --- Actions ------------------------------------------------------------------------------------------------

	/**
	 * Free check: the service gives a challenge, the site serves it, the check is queued (or last week's is reused).
	 */
	public static function do_check() {
		self::guard( 'check' );
		$r = Perimetry_Cloud_API::request( 'POST', 'check', array( 'site' => Perimetry_Cloud_API::site() ) );
		if ( ! is_wp_error( $r ) && isset( $r['token'], $r['value'] ) ) {
			set_transient( Perimetry_Cloud_Proof::PROOF_TRANSIENT, array( 'token' => sanitize_text_field( $r['token'] ), 'value' => sanitize_text_field( $r['value'] ) ), 10 * MINUTE_IN_SECONDS );
			$r = Perimetry_Cloud_API::request( 'POST', 'check/confirm', array( 'site' => Perimetry_Cloud_API::site(), 'token' => $r['token'] ) );
			delete_transient( Perimetry_Cloud_Proof::PROOF_TRANSIENT );
		}
		if ( is_wp_error( $r ) ) {
			self::notice( 'error', $r->get_error_message() );
		} elseif ( isset( $r['scanId'] ) ) {
			update_option( self::CHECK_OPTION, array( 'id' => sanitize_text_field( $r['scanId'] ), 'at' => time() ), false );
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Connection: off to Perimetry Cloud to sign in and choose the workspace; back with a one-time code.
	 */
	public static function do_connect() {
		self::guard( 'connect' );
		$state = wp_generate_password( 32, false );
		set_transient( 'perimetry_cloud_state_' . get_current_user_id(), $state, 30 * MINUTE_IN_SECONDS );
		$to = add_query_arg(
			array(
				'site'   => rawurlencode( Perimetry_Cloud_API::site() ),
				'return' => rawurlencode( self::url() ),
				'state'  => $state,
			),
			untrailingslashit( PERIMETRY_CLOUD_API ) . '/connect/wordpress'
		);
		wp_redirect( $to ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- The service's own address, set by the plugin.
		exit;
	}

	/**
	 * Back from Perimetry Cloud: trade the code for the key.
	 */
	public static function back_from_connection() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The state checked below plays the nonce's part.
		if ( ! isset( $_GET['state'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$state    = sanitize_text_field( wp_unslash( $_GET['state'] ) );
		$expected = get_transient( 'perimetry_cloud_state_' . get_current_user_id() );
		delete_transient( 'perimetry_cloud_state_' . get_current_user_id() );
		if ( ! $expected || ! hash_equals( (string) $expected, $state ) ) {
			self::notice( 'error', __( 'This connection did not start here, or took too long. Connect again.', 'perimetry-cloud' ) );
		} elseif ( isset( $_GET['perimetry_code'] ) ) {
			$r = Perimetry_Cloud_API::request( 'POST', 'token', array( 'code' => sanitize_text_field( wp_unslash( $_GET['perimetry_code'] ) ), 'site' => Perimetry_Cloud_API::site() ) );
			if ( is_wp_error( $r ) || empty( $r['key'] ) ) {
				self::notice( 'error', is_wp_error( $r ) ? $r->get_error_message() : __( 'The connection failed. Connect again.', 'perimetry-cloud' ) );
			} else {
				update_option( Perimetry_Cloud_API::KEY_OPTION, sanitize_text_field( $r['key'] ), false );
				update_option( Perimetry_Cloud_API::DOMAIN_OPTION, sanitize_text_field( $r['domain'] ?? '' ), false );
				delete_transient( 'perimetry_cloud_summary' );
				self::notice( 'success', __( 'Connected to Perimetry Cloud.', 'perimetry-cloud' ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Ownership proved by this site: it serves the domain's token, Perimetry Cloud reads it.
	 */
	public static function do_verify() {
		self::guard( 'verify' );
		$s = self::summary( true );
		if ( ! is_wp_error( $s ) && ! empty( $s['verify']['token'] ) ) {
			update_option( Perimetry_Cloud_Proof::VERIFY_OPTION, sanitize_text_field( $s['verify']['token'] ), false );
			$r = Perimetry_Cloud_API::request( 'POST', 'verify', array( 'site' => Perimetry_Cloud_API::site() ), true );
			if ( is_wp_error( $r ) ) {
				self::notice( 'error', $r->get_error_message() );
			} else {
				self::notice( 'success', __( 'Ownership proved. The first full audit starts within a few minutes.', 'perimetry-cloud' ) );
			}
			delete_transient( 'perimetry_cloud_summary' );
		} elseif ( is_wp_error( $s ) ) {
			self::notice( 'error', $s->get_error_message() );
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Consent to share the WordPress report, saved; the first report goes at once.
	 */
	public static function do_share() {
		self::guard( 'share' );
		$on = isset( $_POST['share'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in guard().
		update_option( Perimetry_Cloud_Inventory::SHARE_OPTION, $on ? 1 : 0, false );
		Perimetry_Cloud_Inventory::schedule( $on );
		if ( $on ) {
			$r = Perimetry_Cloud_Inventory::send();
			self::notice( is_wp_error( $r ) ? 'error' : 'success', is_wp_error( $r ) ? $r->get_error_message() : __( 'Sharing is on. The report is sent once a day.', 'perimetry-cloud' ) );
		} else {
			self::notice( 'success', __( 'Sharing is off. Nothing more is sent.', 'perimetry-cloud' ) );
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Sends the report now.
	 */
	public static function do_send() {
		self::guard( 'send' );
		$r = Perimetry_Cloud_Inventory::send();
		self::notice( is_wp_error( $r ) ? 'error' : 'success', is_wp_error( $r ) ? $r->get_error_message() : __( 'Report sent.', 'perimetry-cloud' ) );
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Disconnects: the key is revoked at Perimetry Cloud and forgotten here.
	 */
	public static function do_disconnect() {
		self::guard( 'disconnect' );
		Perimetry_Cloud_API::request( 'POST', 'disconnect', array(), true );
		Perimetry_Cloud_API::forget();
		delete_option( Perimetry_Cloud_Inventory::SHARE_OPTION );
		self::notice( 'success', __( 'Disconnected from Perimetry Cloud.', 'perimetry-cloud' ) );
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Reads the latest results again.
	 */
	public static function do_refresh() {
		self::guard( 'refresh' );
		delete_transient( 'perimetry_cloud_summary' );
		wp_safe_redirect( self::url() );
		exit;
	}

	// --- Data ---------------------------------------------------------------------------------------------------

	/**
	 * The connected domain's summary, kept ten minutes.
	 *
	 * @param bool $fresh Ask again.
	 * @return array|WP_Error
	 */
	private static function summary( $fresh = false ) {
		$cached = get_transient( 'perimetry_cloud_summary' );
		if ( ! $fresh && is_array( $cached ) ) {
			return $cached;
		}
		$s = Perimetry_Cloud_API::request( 'GET', 'summary?lang=' . Perimetry_Cloud_API::lang(), null, true );
		if ( ! is_wp_error( $s ) ) {
			set_transient( 'perimetry_cloud_summary', $s, 10 * MINUTE_IN_SECONDS );
		}
		return $s;
	}

	/**
	 * The free check of this site, if one was asked in the last 7 days.
	 *
	 * @return array|null
	 */
	private static function check() {
		$c = get_option( self::CHECK_OPTION );
		if ( ! is_array( $c ) || empty( $c['id'] ) || time() - (int) $c['at'] > 7 * DAY_IN_SECONDS ) {
			return null;
		}
		$r = Perimetry_Cloud_API::request( 'GET', 'check/' . rawurlencode( $c['id'] ) . '?lang=' . Perimetry_Cloud_API::lang() );
		return is_wp_error( $r ) ? null : $r;
	}

	/**
	 * The name of a severity.
	 *
	 * @param string $s Severity.
	 * @return string
	 */
	private static function sev( $s ) {
		$names = array(
			'critical' => __( 'Critical', 'perimetry-cloud' ),
			'high'     => __( 'High', 'perimetry-cloud' ),
			'medium'   => __( 'Medium', 'perimetry-cloud' ),
			'low'      => __( 'Low', 'perimetry-cloud' ),
			'info'     => __( 'Info', 'perimetry-cloud' ),
		);
		return isset( $names[ $s ] ) ? $names[ $s ] : $s;
	}

	/**
	 * A grade and score.
	 *
	 * @param string $grade Grade.
	 * @param int    $score Score out of 100.
	 */
	private static function grade( $grade, $score ) {
		echo '<p class="perimetry-grade"><span class="perimetry-letter grade-' . esc_attr( strtolower( $grade ) ) . '">' . esc_html( $grade ) . '</span> ';
		/* translators: %d: score out of 100. */
		echo '<span>' . esc_html( sprintf( __( '%d / 100', 'perimetry-cloud' ), $score ) ) . '</span></p>';
	}

	// --- Screens ------------------------------------------------------------------------------------------------

	/**
	 * The plugin's page.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap perimetry-wrap"><h1>' . esc_html__( 'Perimetry Cloud', 'perimetry-cloud' ) . '</h1>';
		$n = get_transient( 'perimetry_cloud_notice_' . get_current_user_id() );
		if ( is_array( $n ) ) {
			delete_transient( 'perimetry_cloud_notice_' . get_current_user_id() );
			echo '<div class="notice notice-' . esc_attr( $n[0] ) . '"><p>' . esc_html( $n[1] ) . '</p></div>';
		}
		echo '<p class="perimetry-lede">' . esc_html__( 'Perimetry Cloud checks your domain from the outside, the way an attacker would see it: e-mail that anyone could send in your name, certificates, exposed services, forgotten subdomains, look-alike domains. Nothing is installed on your server and nothing is attacked.', 'perimetry-cloud' ) . '</p>';

		if ( Perimetry_Cloud_API::connected() ) {
			$s = self::summary();
			if ( is_wp_error( $s ) && ! Perimetry_Cloud_API::connected() ) {
				// The key was revoked from Perimetry Cloud: it is forgotten, and the site is back to the free check.
				echo '<div class="notice notice-info"><p>' . esc_html__( 'This site is no longer connected to Perimetry Cloud. Connect it again to see its findings.', 'perimetry-cloud' ) . '</p></div>';
				self::free_view();
			} else {
				self::connected_view();
			}
		} else {
			self::free_view();
		}
		self::wordpress_view();
		echo '</div>';
	}

	/**
	 * Not connected: the free check and the invitation to connect.
	 */
	private static function free_view() {
		echo '<div class="perimetry-box"><h2>' . esc_html__( 'Free check', 'perimetry-cloud' ) . '</h2>';
		$c = self::check();
		if ( $c && in_array( $c['status'] ?? '', array( 'queued', 'running' ), true ) ) {
			echo '<p data-perimetry-refresh="8">' . esc_html__( 'Checking your domain… This takes about two minutes; the page refreshes by itself.', 'perimetry-cloud' ) . '</p>';
		} elseif ( $c && 'done' === ( $c['status'] ?? '' ) && ! empty( $c['summary'] ) ) {
			$s = $c['summary'];
			/* translators: %s: domain name. */
			echo '<p>' . esc_html( sprintf( __( 'Result for %s:', 'perimetry-cloud' ), $c['domain'] ) ) . '</p>';
			self::grade( $s['grade'], (int) $s['score'] );
			if ( ! empty( $s['top'] ) ) {
				echo '<p>' . esc_html__( 'The most serious problems:', 'perimetry-cloud' ) . '</p><ul class="perimetry-findings">';
				foreach ( $s['top'] as $f ) {
					echo '<li><span class="perimetry-sev sev-' . esc_attr( $f['severity'] ) . '">' . esc_html( self::sev( $f['severity'] ) ) . '</span> ' . esc_html( $f['title'] ) . '</li>';
				}
				echo '</ul>';
			}
			echo '<p class="description">' . esc_html__( 'The free check shows the three most serious problems. Connect the site to see all of them, with how to fix each one, and to be told when something changes.', 'perimetry-cloud' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'See the score of your domain and its three most serious problems in about two minutes. Pressing the button sends the address of this site to Perimetry Cloud; nothing else is sent.', 'perimetry-cloud' ) . '</p>';
			self::button( 'check', __( 'Check my domain', 'perimetry-cloud' ) );
		}
		echo '</div><div class="perimetry-box"><h2>' . esc_html__( 'Connect to Perimetry Cloud', 'perimetry-cloud' ) . '</h2>';
		echo '<p>' . esc_html__( 'Free account: every finding with its fix, a weekly audit, alerts by e-mail, and the proof that the domain is yours in one click from this site.', 'perimetry-cloud' ) . '</p>';
		self::button( 'connect', __( 'Connect or create a free account', 'perimetry-cloud' ) );
		echo '</div>';
	}

	/**
	 * Connected: the domain, its proof, its latest audit and findings, the sharing of the WordPress report.
	 */
	private static function connected_view() {
		$s = self::summary();
		echo '<div class="perimetry-box">';
		if ( is_wp_error( $s ) ) {
			echo '<p>' . esc_html( $s->get_error_message() ) . '</p></div>';
			return;
		}
		/* translators: %s: domain name. */
		echo '<h2>' . esc_html( sprintf( __( 'Domain %s', 'perimetry-cloud' ), $s['domain'] ) ) . '</h2>';
		if ( 'verified' !== $s['status'] ) {
			echo '<p>' . esc_html__( 'Prove that the domain is yours to start the full audits. This site can prove it in one click: it shows Perimetry Cloud a code at its own address.', 'perimetry-cloud' ) . '</p>';
			self::button( 'verify', __( 'Prove ownership with this site', 'perimetry-cloud' ) );
			if ( ! empty( $s['verify']['txt'] ) ) {
				/* translators: 1: DNS record name, 2: record value. */
				echo '<p class="description">' . esc_html( sprintf( __( 'Or add this TXT record in your DNS: %1$s with the value %2$s.', 'perimetry-cloud' ), $s['verify']['txt']['name'], $s['verify']['txt']['value'] ) ) . '</p>';
			}
		} elseif ( empty( $s['audit'] ) ) {
			echo '<p>' . esc_html__( 'The first audit is running. Come back in a few minutes.', 'perimetry-cloud' ) . '</p>';
		} else {
			self::grade( $s['audit']['grade'], (int) $s['audit']['score'] );
			if ( null !== $s['audit']['previousScore'] ) {
				$d = (int) $s['audit']['score'] - (int) $s['audit']['previousScore'];
				/* translators: %s: change of score since the previous audit, such as +4. */
				echo '<p class="description">' . esc_html( sprintf( __( '%s since the previous audit.', 'perimetry-cloud' ), ( $d > 0 ? '+' : '' ) . $d ) ) . '</p>';
			}
			if ( empty( $s['findings'] ) ) {
				echo '<p>' . esc_html__( 'No open problem. Well done.', 'perimetry-cloud' ) . '</p>';
			} else {
				echo '<ul class="perimetry-findings">';
				foreach ( $s['findings'] as $f ) {
					echo '<li><details><summary><span class="perimetry-sev sev-' . esc_attr( $f['severity'] ) . '">' . esc_html( self::sev( $f['severity'] ) ) . '</span> ' . esc_html( $f['title'] ) . ' <span class="description">' . esc_html( $f['target'] ) . '</span></summary>';
					if ( '' !== $f['fix'] ) {
						echo '<p>' . esc_html( $f['fix'] ) . '</p>';
					}
					echo '</details></li>';
				}
				echo '</ul>';
			}
			if ( ! empty( $s['dnsActionsNeedTxt'] ) ) {
				echo '<p class="description">' . esc_html__( 'The domain was proved by this site. To let Perimetry Cloud change DNS records for you, add the TXT record shown in Perimetry Cloud.', 'perimetry-cloud' ) . '</p>';
			}
		}
		echo '<div class="perimetry-actions"><a class="button" href="' . esc_url( $s['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the full report', 'perimetry-cloud' ) . '</a> ';
		self::button( 'refresh', __( 'Refresh', 'perimetry-cloud' ), 'button' );
		echo '</div></div>';

		$share = (bool) get_option( Perimetry_Cloud_Inventory::SHARE_OPTION );
		echo '<div class="perimetry-box"><h2>' . esc_html__( 'Share what WordPress shows', 'perimetry-cloud' ) . '</h2>';
		echo '<p>' . esc_html__( 'With your consent, once a day, this site sends Perimetry Cloud: the versions of WordPress and PHP, the plugins and themes with their version and the update waiting, and a few settings (errors shown to visitors, file editing, XML-RPC, registration and its default role, how many administrators there are, never who). Perimetry Cloud then lists the points to fix with your other findings. You can stop at any time.', 'perimetry-cloud' ) . '</p>';
		$box = '<label class="perimetry-check"><input type="checkbox" name="share" value="1"' . checked( $share, true, false ) . '> ' . esc_html__( 'Send this report to Perimetry Cloud once a day', 'perimetry-cloud' ) . '</label> ';
		self::button( 'share', __( 'Save', 'perimetry-cloud' ), 'button button-primary', $box );
		$sent = (int) get_option( Perimetry_Cloud_Inventory::SENT_OPTION, 0 );
		if ( $share ) {
			if ( $sent ) {
				/* translators: %s: date and time. */
				echo '<p class="description">' . esc_html( sprintf( __( 'Last sent: %s.', 'perimetry-cloud' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sent ) ) ) . '</p>';
			}
			self::button( 'send', __( 'Send now', 'perimetry-cloud' ), 'button' );
		}
		echo '</div><div class="perimetry-box">';
		self::button( 'disconnect', __( 'Disconnect from Perimetry Cloud', 'perimetry-cloud' ), 'button button-link-delete' );
		echo '</div>';
	}

	/**
	 * What WordPress itself shows, read here and shown here (sent only with consent, above).
	 */
	private static function wordpress_view() {
		$inv    = Perimetry_Cloud_Inventory::collect();
		$issues = Perimetry_Cloud_Inventory::issues( $inv );
		echo '<div class="perimetry-box"><h2>' . esc_html__( 'What WordPress itself shows', 'perimetry-cloud' ) . '</h2>';
		/* translators: 1: WordPress version, 2: PHP version. */
		echo '<p class="description">' . esc_html( sprintf( __( 'WordPress %1$s · PHP %2$s. Read on this site, shown only to you.', 'perimetry-cloud' ), $inv['core']['version'], $inv['php'] ) ) . '</p>';
		if ( ! $issues ) {
			echo '<p>' . esc_html__( 'Nothing to fix here.', 'perimetry-cloud' ) . '</p>';
		} else {
			echo '<ul class="perimetry-findings">';
			foreach ( $issues as $i ) {
				echo '<li><span class="perimetry-sev sev-' . esc_attr( $i[0] ) . '">' . esc_html( self::sev( $i[0] ) ) . '</span> ' . esc_html( $i[1] );
				if ( $i[2] ) {
					echo '<ul class="perimetry-details">';
					foreach ( array_slice( $i[2], 0, 20 ) as $d ) {
						echo '<li>' . esc_html( $d ) . '</li>';
					}
					echo '</ul>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	/**
	 * The dashboard widget.
	 */
	public static function widget() {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget( 'perimetry_cloud', __( 'Perimetry Cloud – Security score', 'perimetry-cloud' ), array( __CLASS__, 'widget_body' ) );
		}
	}

	/**
	 * The widget's content: the score when connected or checked, else an invitation to check.
	 */
	public static function widget_body() {
		if ( Perimetry_Cloud_API::connected() ) {
			$s = self::summary();
			if ( ! is_wp_error( $s ) && ! empty( $s['audit'] ) ) {
				self::grade( $s['audit']['grade'], (int) $s['audit']['score'] );
				$c = $s['counts'];
				/* translators: 1: critical, 2: high, 3: medium, 4: low findings. */
				echo '<p>' . esc_html( sprintf( __( 'Open: %1$d critical, %2$d high, %3$d medium, %4$d low.', 'perimetry-cloud' ), $c['critical'] ?? 0, $c['high'] ?? 0, $c['medium'] ?? 0, $c['low'] ?? 0 ) ) . '</p>';
			} elseif ( ! is_wp_error( $s ) && 'verified' !== $s['status'] ) {
				echo '<p>' . esc_html__( 'Prove that the domain is yours to start the audits.', 'perimetry-cloud' ) . '</p>';
			}
		} else {
			$c = self::check();
			if ( $c && 'done' === ( $c['status'] ?? '' ) && ! empty( $c['summary'] ) ) {
				self::grade( $c['summary']['grade'], (int) $c['summary']['score'] );
			} else {
				echo '<p>' . esc_html__( 'How does your domain look from the outside? Free check, no account needed.', 'perimetry-cloud' ) . '</p>';
			}
		}
		$issues = count( Perimetry_Cloud_Inventory::issues( Perimetry_Cloud_Inventory::collect() ) );
		if ( $issues ) {
			/* translators: %d: number of points to fix in WordPress. */
			echo '<p>' . esc_html( sprintf( _n( '%d point to fix in WordPress itself.', '%d points to fix in WordPress itself.', $issues, 'perimetry-cloud' ), $issues ) ) . '</p>';
		}
		echo '<p><a class="button" href="' . esc_url( self::url() ) . '">' . esc_html__( 'Open Perimetry Cloud', 'perimetry-cloud' ) . '</a></p>';
	}
}
