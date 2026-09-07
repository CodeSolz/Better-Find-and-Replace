<?php namespace RealTimeAutoFindReplace\Maintenance\Admin;

use RealTimeAutoFindReplace\Maintenance\Data\NotFoundRepository;

/**
 * The 404 list.
 *
 * Ordered by hit count, because the whole point of the monitor is to answer
 * "which dead URL is costing me the most" - a chronological list of every 404
 * is a log, not a tool.
 *
 * The two actions that matter both hand off to work done elsewhere: Create
 * Redirect pre-fills the M3 form, and Find References searches the content with
 * the M2 extractor. Neither is reimplemented here.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class NotFoundTable extends \WP_List_Table {

	/**
	 * Rows per page.
	 *
	 * @var int
	 */
	private $per_page = 20;

	/**
	 * Set up the list table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'bfr_404',
				'plural'   => 'bfr_404s',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'path'      => __( 'Requested URL', 'real-time-auto-find-and-replace' ),
			'hits'      => __( 'Hits', 'real-time-auto-find-and-replace' ),
			'referrer'  => __( 'Last referrer', 'real-time-auto-find-and-replace' ),
			'last_seen' => __( 'Last seen', 'real-time-auto-find-and-replace' ),
			'status'    => __( 'Status', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * Load the rows.
	 *
	 * @return void
	 */
	public function prepare_items() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : NotFoundRepository::STATUS_NEW;

		$this->items = NotFoundRepository::find(
			array(
				'status'   => $status,
				'search'   => $search,
				'page'     => $paged,
				'per_page' => $this->per_page,
			)
		);

		$total = NotFoundRepository::count( $status );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $this->per_page,
				'total_pages' => (int) ceil( $total / $this->per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), array() );
	}

	/**
	 * The requested path, with its actions.
	 *
	 * @param object $item 404 row.
	 * @return string
	 */
	public function column_path( $item ) {
		$actions = array();

		// The overflow row is a counter, not a URL. Offering to redirect it
		// would be nonsense.
		if ( NotFoundRepository::OVERFLOW_PATH !== $item->normalized_path ) {
			$actions['redirect'] = sprintf(
				'<a href="#" class="bfr-404-redirect" data-id="%d" data-path="%s">%s</a>',
				(int) $item->id,
				esc_attr( $item->normalized_path ),
				esc_html__( 'Create redirect', 'real-time-auto-find-and-replace' )
			);

			$actions['references'] = sprintf(
				'<a href="#" class="bfr-404-references" data-id="%d" data-path="%s">%s</a>',
				(int) $item->id,
				esc_attr( $item->normalized_path ),
				esc_html__( 'Find references', 'real-time-auto-find-and-replace' )
			);

			if ( NotFoundRepository::STATUS_IGNORED !== $item->status ) {
				$actions['ignore'] = sprintf(
					'<a href="#" class="bfr-404-status" data-id="%d" data-status="ignored">%s</a>',
					(int) $item->id,
					esc_html__( 'Ignore', 'real-time-auto-find-and-replace' )
				);
			}
		}

		/**
		 * Filter the row actions on a 404.
		 *
		 * Markup returned here is echoed as-is, so an implementer escapes its
		 * own output.
		 *
		 * @param array  $actions Action key => link markup.
		 * @param object $item    The 404 row.
		 */
		$actions = (array) apply_filters( 'bfr_404_row_actions', $actions, $item );

		return sprintf(
			'<span class="bfrmaint-url">%s</span>%s',
			esc_html( $item->normalized_path ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * How many times it was requested.
	 *
	 * @param object $item 404 row.
	 * @return string
	 */
	public function column_hits( $item ) {
		return esc_html( number_format_i18n( (int) $item->hit_count ) );
	}

	/**
	 * Where the visitor came from.
	 *
	 * @param object $item 404 row.
	 * @return string
	 */
	public function column_referrer( $item ) {
		if ( empty( $item->referrer ) ) {
			return '<span class="bfrmaint-anchor">' . esc_html__( 'direct or unknown', 'real-time-auto-find-and-replace' ) . '</span>';
		}

		return '<span class="bfrmaint-url">' . esc_html( $item->referrer ) . '</span>';
	}

	/**
	 * When it was last requested.
	 *
	 * @param object $item 404 row.
	 * @return string
	 */
	public function column_last_seen( $item ) {
		if ( empty( $item->last_seen_at ) ) {
			return '&mdash;';
		}

		return esc_html(
			sprintf(
				/* translators: %s: human-readable time difference, e.g. "2 hours" */
				__( '%s ago', 'real-time-auto-find-and-replace' ),
				human_time_diff( strtotime( $item->last_seen_at . ' UTC' ), time() )
			)
		);
	}

	/**
	 * What has been done about it.
	 *
	 * @param object $item 404 row.
	 * @return string
	 */
	public function column_status( $item ) {
		$labels = array(
			NotFoundRepository::STATUS_NEW        => __( 'Unhandled', 'real-time-auto-find-and-replace' ),
			NotFoundRepository::STATUS_IGNORED    => __( 'Ignored', 'real-time-auto-find-and-replace' ),
			NotFoundRepository::STATUS_REDIRECTED => __( 'Redirected', 'real-time-auto-find-and-replace' ),
			NotFoundRepository::STATUS_RESOLVED   => __( 'Resolved', 'real-time-auto-find-and-replace' ),
		);

		$label = isset( $labels[ $item->status ] ) ? $labels[ $item->status ] : $item->status;
		$class = NotFoundRepository::STATUS_NEW === $item->status ? 'bfrmaint-badge-missing' : '';

		return '<span class="bfrmaint-badge ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Anything without its own method.
	 *
	 * @param object $item        404 row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item->{$column_name} ) ? esc_html( (string) $item->{$column_name} ) : '';
	}

	/**
	 * What an empty list should say.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No 404s recorded yet.', 'real-time-auto-find-and-replace' );
	}
}
