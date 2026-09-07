<?php namespace RealTimeAutoFindReplace\Maintenance\Admin;

use RealTimeAutoFindReplace\Maintenance\Data\RedirectRepository;

/**
 * The list of redirects.
 *
 * Small on purpose. Redirects are simple rows and the interesting behaviour is
 * in the validator and the executor; this just shows what exists and offers the
 * two actions a person needs on each one.
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

class RedirectsTable extends \WP_List_Table {

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
				'singular' => 'bfr_redirect',
				'plural'   => 'bfr_redirects',
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
			'source'      => __( 'From', 'real-time-auto-find-and-replace' ),
			'destination' => __( 'To', 'real-time-auto-find-and-replace' ),
			'type'        => __( 'Type', 'real-time-auto-find-and-replace' ),
			'hits'        => __( 'Hits', 'real-time-auto-find-and-replace' ),
			'status'      => __( 'Status', 'real-time-auto-find-and-replace' ),
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

		$this->items = RedirectRepository::find(
			array(
				'search'   => $search,
				'page'     => $paged,
				'per_page' => $this->per_page,
			)
		);

		$total = RedirectRepository::count_all();

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
	 * The source, with its actions.
	 *
	 * @param object $item Redirect row.
	 * @return string
	 */
	public function column_source( $item ) {
		$actions = array(
			'toggle' => sprintf(
				'<a href="#" class="bfr-redirect-toggle" data-id="%d" data-enabled="%d">%s</a>',
				(int) $item->id,
				(int) $item->enabled,
				esc_html( $item->enabled ? __( 'Disable', 'real-time-auto-find-and-replace' ) : __( 'Enable', 'real-time-auto-find-and-replace' ) )
			),
			'delete' => sprintf(
				'<a href="#" class="bfr-redirect-delete submitdelete" data-id="%d">%s</a>',
				(int) $item->id,
				esc_html__( 'Delete', 'real-time-auto-find-and-replace' )
			),
		);

		/**
		 * Filter the row actions on a redirect.
		 *
		 * Markup returned here is echoed as-is, so an implementer escapes its
		 * own output.
		 *
		 * @param array  $actions Action key => link markup.
		 * @param object $item    The redirect row.
		 */
		$actions = (array) apply_filters( 'bfr_redirect_row_actions', $actions, $item );

		return sprintf(
			'<span class="bfrmaint-url">%s</span>%s',
			esc_html( $item->source ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * The destination.
	 *
	 * @param object $item Redirect row.
	 * @return string
	 */
	public function column_destination( $item ) {
		return '<span class="bfrmaint-url">' . esc_html( $item->destination ) . '</span>';
	}

	/**
	 * The HTTP status code.
	 *
	 * @param object $item Redirect row.
	 * @return string
	 */
	public function column_type( $item ) {
		return esc_html( (string) (int) $item->redirect_type );
	}

	/**
	 * How often it has fired.
	 *
	 * @param object $item Redirect row.
	 * @return string
	 */
	public function column_hits( $item ) {
		return esc_html( number_format_i18n( (int) $item->hit_count ) );
	}

	/**
	 * Whether it is live.
	 *
	 * @param object $item Redirect row.
	 * @return string
	 */
	public function column_status( $item ) {
		if ( (int) $item->enabled ) {
			return '<span class="bfrmaint-badge">' . esc_html__( 'Active', 'real-time-auto-find-and-replace' ) . '</span>';
		}

		return '<span class="bfrmaint-badge bfrmaint-badge-non_public">' . esc_html__( 'Disabled', 'real-time-auto-find-and-replace' ) . '</span>';
	}

	/**
	 * Anything without its own method.
	 *
	 * @param object $item        Redirect row.
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
		esc_html_e( 'No redirects yet.', 'real-time-auto-find-and-replace' );
	}
}
