<?php

namespace DCMM\Premium\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MailChimp sync status for Members.
 *
 * Shows whether a Member is on the configured MailChimp list, and their tags:
 * - a "MailChimp" metabox on the Edit Member screen (live, with a Refresh button)
 * - a "MailChimp" column on the All Members screen
 * - a MailChimp status filter on the All Members screen
 *
 * The status is cached in post meta so the All Members screen never calls
 * MailChimp once per row. The cache is refreshed from the Edit Member screen
 * and whenever the integration subscribes/unsubscribes a member.
 *
 * Only loaded while the MailChimp integration is enabled; see
 * MailChimp_Integration::initialize().
 *
 * @since 1.6.0
 */
class MailChimp_Sync_Status {

	/**
	 * Post meta key holding the cached status array.
	 */
	const META_KEY = 'dcmm_mailchimp_sync';

	/**
	 * Query var used by the All Members filter.
	 */
	const FILTER_VAR = 'dcmm_mailchimp';

	/**
	 * Filter value for members that have never been checked.
	 */
	const FILTER_UNCHECKED = 'unchecked';

	/**
	 * AJAX action / nonce name for refreshing a member's status.
	 */
	const AJAX_ACTION = 'dcmm_refresh_mailchimp_status';

	/**
	 * How old a cached status can be (seconds) before the Edit Member
	 * screen refreshes it automatically.
	 */
	const STALE_AFTER = HOUR_IN_SECONDS;

	/**
	 * Separate meta key holding just the status string, so the All Members
	 * filter can query it with a plain meta_query.
	 */
	const STATUS_META_KEY = 'dcmm_mailchimp_status';

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		$post_type = \DCMM_Member::get_post_type();

		add_action( "add_meta_boxes_{$post_type}", array( $this, 'add_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_refresh' ) );

		// run after DCMM_Post_Type\dcmm_custom_columns(), which rebuilds the column list
		add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ), 20 );
		add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );

		add_action( 'restrict_manage_posts', array( $this, 'render_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_filter' ) );
	}

	/**
	 * Remove admin hooks.
	 *
	 * @return void
	 */
	public function unregister_hooks() {
		$post_type = \DCMM_Member::get_post_type();

		remove_action( "add_meta_boxes_{$post_type}", array( $this, 'add_metabox' ) );
		remove_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		remove_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_refresh' ) );
		remove_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ), 20 );
		remove_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10 );
		remove_action( 'restrict_manage_posts', array( $this, 'render_filter' ) );
		remove_action( 'pre_get_posts', array( $this, 'apply_filter' ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * Cache
	 * ------------------------------------------------------------------
	 */

	/**
	 * Human-readable labels for each status we cache.
	 *
	 * The first four are MailChimp's own member statuses.
	 *
	 * @return array status => label
	 */
	public static function get_status_labels() {
		return array(
			'subscribed'   => __( 'Subscribed', DCMM_PLUGIN_SLUG ),
			'unsubscribed' => __( 'Unsubscribed', DCMM_PLUGIN_SLUG ),
			'pending'      => __( 'Pending', DCMM_PLUGIN_SLUG ),
			'cleaned'      => __( 'Cleaned', DCMM_PLUGIN_SLUG ),
			'archived'     => __( 'Archived', DCMM_PLUGIN_SLUG ),
			'not_found'    => __( 'Not on list', DCMM_PLUGIN_SLUG ),
		);
	}

	/**
	 * Get a member's cached sync status.
	 *
	 * Returns null when the member has never been checked, or when the cache
	 * no longer applies (the list setting or the member's email has changed
	 * since it was checked).
	 *
	 * @param int $post_id Member CPT post ID.
	 *
	 * @return array|null {
	 *     @type string   $status     One of the keys of get_status_labels().
	 *     @type string[] $tags       Tag names.
	 *     @type string   $list_id    List the status refers to.
	 *     @type string   $email      Email address that was checked.
	 *     @type int      $checked_at Unix timestamp of the check.
	 * }
	 */
	public static function get_cached( $post_id ) {
		$cached = get_post_meta( $post_id, self::META_KEY, true );

		if ( ! is_array( $cached ) || empty( $cached['status'] ) ) {
			return null;
		}

		$list_id = get_option( 'dcmm_mailchimp_list_id' );
		$email   = get_post_meta( $post_id, \DCMM_Member::get_meta_keys( 'email' ), true );

		if ( ( $cached['list_id'] ?? '' ) !== $list_id
			|| strtolower( $cached['email'] ?? '' ) !== strtolower( (string) $email ) ) {
			return null;
		}

		return $cached;
	}

	/**
	 * Look up a member on MailChimp and update the cache.
	 *
	 * @param int $post_id Member CPT post ID.
	 *
	 * @return array|\WP_Error The new cached status, or an error. On error the
	 *                         existing cache is left untouched.
	 */
	public static function refresh( $post_id ) {
		$list_id = get_option( 'dcmm_mailchimp_list_id' );
		if ( empty( $list_id ) ) {
			return new \WP_Error( 'dcmm_mailchimp_no_list', __( 'No MailChimp list is selected in Premium Features settings.', DCMM_PLUGIN_SLUG ) );
		}

		$email = get_post_meta( $post_id, \DCMM_Member::get_meta_keys( 'email' ), true );
		if ( empty( $email ) ) {
			return new \WP_Error( 'dcmm_mailchimp_no_email', __( 'This member has no email address.', DCMM_PLUGIN_SLUG ) );
		}

		try {
			$api    = new \DCMM\MailChimp\MailChimp_API();
			$result = $api->is_subscribed( $list_id, $email );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'dcmm_mailchimp_error', $e->getMessage() );
		}

		if ( in_array( $result['status'], array( 'error', 'invalid_email' ), true ) ) {
			return new \WP_Error( 'dcmm_mailchimp_error', $result['message'] );
		}

		$tags = array();
		if ( ! empty( $result['data']['tags'] ) && is_array( $result['data']['tags'] ) ) {
			$tags = array_values( array_filter( wp_list_pluck( $result['data']['tags'], 'name' ) ) );
		}

		$status = array_key_exists( $result['status'], self::get_status_labels() ) ? $result['status'] : 'not_found';

		$cached = array(
			'status'     => $status,
			'tags'       => $tags,
			'list_id'    => $list_id,
			'email'      => $email,
			'checked_at' => time(),
		);

		update_post_meta( $post_id, self::META_KEY, $cached );
		update_post_meta( $post_id, self::STATUS_META_KEY, $status );

		return $cached;
	}

	/*
	 * ------------------------------------------------------------------
	 * Edit Member metabox
	 * ------------------------------------------------------------------
	 */

	/**
	 * Register the MailChimp metabox.
	 *
	 * @return void
	 */
	public function add_metabox() {
		add_meta_box(
			'dcmm_mailchimp',
			__( 'MailChimp', DCMM_PLUGIN_SLUG ),
			array( $this, 'render_metabox' ),
			\DCMM_Member::get_post_type(),
			'side'
		);
	}

	/**
	 * Render the MailChimp metabox.
	 *
	 * Shows the cached status right away; the script refreshes it over AJAX
	 * when it's missing or stale, so the Edit screen never waits on MailChimp.
	 *
	 * @param \WP_Post $post
	 *
	 * @return void
	 */
	public function render_metabox( $post ) {
		$cached = self::get_cached( $post->ID );
		$stale  = ! $cached || ( time() - (int) $cached['checked_at'] ) > self::STALE_AFTER;

		printf(
			'<div class="dcmm-mailchimp-status" data-post-id="%d" data-stale="%d">',
			(int) $post->ID,
			$stale ? 1 : 0
		);
		echo self::get_metabox_body_html( $cached ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in builder
		echo '</div>';

		echo '<p><button type="button" class="button button-secondary dcmm-mailchimp-refresh">' . esc_html__( 'Refresh', DCMM_PLUGIN_SLUG ) . '</button>';
		echo ' <span class="spinner" style="float: none;"></span></p>';
	}

	/**
	 * Build the inner HTML of the metabox.
	 *
	 * @param array|null $cached
	 *
	 * @return string
	 */
	public static function get_metabox_body_html( $cached ) {
		if ( ! $cached ) {
			return '<p>' . esc_html__( 'Not checked yet.', DCMM_PLUGIN_SLUG ) . '</p>';
		}

		$html  = '<p><strong>' . esc_html__( 'Status:', DCMM_PLUGIN_SLUG ) . '</strong> ' . self::get_status_badge_html( $cached['status'] ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Tags:', DCMM_PLUGIN_SLUG ) . '</strong> ';
		$html .= empty( $cached['tags'] ) ? esc_html__( 'None', DCMM_PLUGIN_SLUG ) : esc_html( implode( ', ', $cached['tags'] ) );
		$html .= '</p>';

		$html .= '<p class="description">' . esc_html( sprintf(
			/* translators: %s: human-readable time difference, e.g. "5 mins" */
			__( 'Checked %s ago', DCMM_PLUGIN_SLUG ),
			human_time_diff( (int) $cached['checked_at'] )
		) ) . '</p>';

		return $html;
	}

	/**
	 * Build a status badge.
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function get_status_badge_html( $status ) {
		$labels = self::get_status_labels();
		$label  = $labels[ $status ] ?? ucfirst( $status );

		return '<span class="dcmm-mailchimp-badge dcmm-mailchimp-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Enqueue the metabox script on the Edit Member screen.
	 *
	 * @param string $hook
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'post.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || \DCMM_Member::get_post_type() !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'dcmm-admin-mailchimp-status',
			DCMM_URL . 'js/admin-mailchimp-status.js',
			array( 'jquery' ),
			DCMM_VERSION,
			true
		);

		wp_localize_script( 'dcmm-admin-mailchimp-status', 'dcmmMailChimpStatus', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action'  => self::AJAX_ACTION,
			'nonce'   => wp_create_nonce( self::AJAX_ACTION ),
			'error'   => __( 'Could not reach MailChimp.', DCMM_PLUGIN_SLUG ),
		) );
	}

	/**
	 * AJAX: refresh a member's status and return the metabox HTML.
	 *
	 * @return void
	 */
	public function ajax_refresh() {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || \DCMM_Member::get_post_type() !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid member.', DCMM_PLUGIN_SLUG ) ), 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', DCMM_PLUGIN_SLUG ) ), 403 );
		}

		$result = self::refresh( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'html' => self::get_metabox_body_html( $result ) ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * All Members column + filter
	 * ------------------------------------------------------------------
	 */

	/**
	 * Add the MailChimp column after the Email column.
	 *
	 * @param array $columns
	 *
	 * @return array
	 */
	public function add_column( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'email' === $key ) {
				$new['mailchimp'] = __( 'MailChimp', DCMM_PLUGIN_SLUG );
			}
		}

		if ( ! isset( $new['mailchimp'] ) ) {
			$new['mailchimp'] = __( 'MailChimp', DCMM_PLUGIN_SLUG );
		}

		return $new;
	}

	/**
	 * Render the MailChimp column from the cache (no API calls).
	 *
	 * @param string $column_name
	 * @param int    $post_id
	 *
	 * @return void
	 */
	public function render_column( $column_name, $post_id ) {
		if ( 'mailchimp' !== $column_name ) {
			return;
		}

		$cached = self::get_cached( $post_id );

		if ( ! $cached ) {
			echo '<span title="' . esc_attr__( 'Open the member to check their MailChimp status.', DCMM_PLUGIN_SLUG ) . '">---</span>';
			return;
		}

		echo self::get_status_badge_html( $cached['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in builder

		if ( ! empty( $cached['tags'] ) ) {
			echo '<br><span class="dcmm-mailchimp-tags">' . esc_html( implode( ', ', $cached['tags'] ) ) . '</span>';
		}
	}

	/**
	 * Output the MailChimp status filter dropdown on the All Members screen.
	 *
	 * @return void
	 */
	public function render_filter() {
		global $typenow;
		if ( \DCMM_Member::get_post_type() !== $typenow ) {
			return;
		}

		$selected = isset( $_GET[ self::FILTER_VAR ] ) ? sanitize_key( $_GET[ self::FILTER_VAR ] ) : '';

		echo '<select name="' . esc_attr( self::FILTER_VAR ) . '">';
		echo '<option value="">' . esc_html__( 'All MailChimp Statuses', DCMM_PLUGIN_SLUG ) . '</option>';
		foreach ( self::get_status_labels() as $status => $label ) {
			echo '<option value="' . esc_attr( $status ) . '"' . selected( $selected, $status, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '<option value="' . esc_attr( self::FILTER_UNCHECKED ) . '"' . selected( $selected, self::FILTER_UNCHECKED, false ) . '>' . esc_html__( 'Not checked', DCMM_PLUGIN_SLUG ) . '</option>';
		echo '</select>';
	}

	/**
	 * Filter the Members query by cached MailChimp status.
	 *
	 * The filter works on the status meta key alone, so a member whose email
	 * or list changed since their last check still matches their old status
	 * until they are refreshed (the column shows "---" for them).
	 *
	 * @param \WP_Query $query
	 *
	 * @return void
	 */
	public function apply_filter( $query ) {
		global $pagenow;

		if ( ! is_admin() || ! $query->is_main_query() || 'edit.php' !== $pagenow ) {
			return;
		}

		if ( \DCMM_Member::get_post_type() !== $query->get( 'post_type' ) ) {
			return;
		}

		$filter = isset( $_GET[ self::FILTER_VAR ] ) ? sanitize_key( $_GET[ self::FILTER_VAR ] ) : '';

		if ( self::FILTER_UNCHECKED === $filter ) {
			$clause = array(
				'key'     => self::STATUS_META_KEY,
				'compare' => 'NOT EXISTS',
			);
		} elseif ( array_key_exists( $filter, self::get_status_labels() ) ) {
			$clause = array(
				'key'   => self::STATUS_META_KEY,
				'value' => $filter,
			);
		} else {
			return;
		}

		$existing_meta_query = $query->get( 'meta_query' ) ?: array();

		$query->set( 'meta_query', array_merge( $existing_meta_query, array( $clause ) ) );
	}
}
