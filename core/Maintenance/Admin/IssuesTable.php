<?php namespace RealTimeAutoFindReplace\Maintenance\Admin;

use RealTimeAutoFindReplace\Maintenance\Data\IssueQuery;
use RealTimeAutoFindReplace\Maintenance\Data\IssueRepository;
use RealTimeAutoFindReplace\Maintenance\LinkHealth\InternalResolver;
use RealTimeAutoFindReplace\Maintenance\LinkHealth\Scanner;
use RealTimeAutoFindReplace\Maintenance\MediaHealth\Classifier;

/**
 * The broken-links list.
 *
 * A WP_List_Table so it looks and behaves like the rest of wp-admin: sorting,
 * paging, search and the views row all work the way an editor already expects
 * them to.
 *
 * Everything a request supplies - sort column, direction, page, search, status
 * filter - is handed to IssueQuery, which matches each one against an
 * allow-list. Nothing from $_GET reaches SQL through this class.
 *
 * There are no bulk actions here that write. Bulk ignore is safe and will come
 * with the dashboard; bulk *fixing* is a pro capability precisely because
 * applying fifty content changes at once is the riskiest thing this product
 * could offer, and the free tier deliberately fixes one at a time.
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

class IssuesTable extends \WP_List_Table {

	/**
	 * Rows per page.
	 *
	 * @var int
	 */
	private $per_page = 20;

	/**
	 * Status counts, for the views row.
	 *
	 * @var array
	 */
	private $counts = array();

	/**
	 * The issue type this table lists.
	 *
	 * Was `Scanner::ISSUE_TYPE`, hard-coded in prepare_items(). M11 gave the
	 * scanner a second type to write - a missing picture is not a broken link -
	 * and the two need separate tabs rather than one mixed list, because the
	 * fix for one is not the fix for the other.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Set up the list table.
	 *
	 * @param string $type Issue type to list. Defaults to broken links, so
	 *                     every existing caller keeps working unchanged.
	 */
	public function __construct( $type = Scanner::ISSUE_TYPE ) {
		$this->type = (string) $type;

		parent::__construct(
			array(
				'singular' => 'bfr_issue',
				'plural'   => 'bfr_issues',
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
			'target_url'     => $this->is_media()
				? __( 'Missing file', 'real-time-auto-find-and-replace' )
				: __( 'Broken link', 'real-time-auto-find-and-replace' ),
			'source'         => __( 'Found in', 'real-time-auto-find-and-replace' ),
			'subtype'        => __( 'Problem', 'real-time-auto-find-and-replace' ),
			'occurrences'    => __( 'Times used', 'real-time-auto-find-and-replace' ),
			'priority_score' => __( 'Priority', 'real-time-auto-find-and-replace' ),
			'last_seen_at'   => __( 'Last seen', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'target_url'     => array( 'target_url', false ),
			'occurrences'    => array( 'occurrences', false ),
			'priority_score' => array( 'priority_score', true ),
			'last_seen_at'   => array( 'last_seen_at', false ),
		);
	}

	/**
	 * The status filter links above the table.
	 *
	 * @return array
	 */
	protected function get_views() {
		$current = $this->current_status();
		$base    = menu_page_url( Screen::SLUG, false );
		$views   = array();

		// A status link on the media tab has to come back to the media tab.
		// Without this the filter quietly moves you to the other list.
		if ( $this->is_media() ) {
			$base = add_query_arg( 'tab', 'media', $base );
		}

		$labels = array(
			IssueRepository::STATUS_OPEN     => __( 'Needs attention', 'real-time-auto-find-and-replace' ),
			IssueRepository::STATUS_IGNORED  => __( 'Ignored', 'real-time-auto-find-and-replace' ),
			IssueRepository::STATUS_RESOLVED => __( 'Fixed', 'real-time-auto-find-and-replace' ),
			IssueRepository::STATUS_STALE    => __( 'Out of date', 'real-time-auto-find-and-replace' ),
		);

		foreach ( $labels as $status => $label ) {
			$count = isset( $this->counts[ $status ] ) ? (int) $this->counts[ $status ] : 0;

			if ( 0 === $count && IssueRepository::STATUS_OPEN !== $status ) {
				continue;
			}

			$views[ $status ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( add_query_arg( 'status', $status, $base ) ),
				$status === $current ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		}

		return $views;
	}

	/**
	 * Load the rows.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$status = $this->current_status();
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// IssueQuery allow-lists both of these; passing them through raw is
		// safe only because of that.
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'priority_score'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$args = array(
			'type'     => $this->type,
			'status'   => $status,
			'search'   => $search,
			'orderby'  => $orderby,
			'order'    => $order,
			'page'     => $paged,
			'per_page' => $this->per_page,
		);

		$this->counts = IssueQuery::status_counts( $this->type );
		$this->items  = IssueQuery::find( $args );

		$total = IssueQuery::count( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $this->per_page,
				'total_pages' => (int) ceil( $total / $this->per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * The broken URL, with the actions under it.
	 *
	 * @param object $item Issue row.
	 * @return string
	 */
	public function column_target_url( $item ) {
		$actions = array();

		if ( IssueRepository::STATUS_OPEN === $item->status || IssueRepository::STATUS_STALE === $item->status ) {
			$actions['fix'] = sprintf(
				'<a href="#" class="bfrmaint-fix" data-issue="%d" data-url="%s">%s</a>',
				(int) $item->id,
				esc_attr( $item->target_url ),
				esc_html__( 'Replace URL', 'real-time-auto-find-and-replace' )
			);

			$actions['unlink'] = sprintf(
				'<a href="#" class="bfrmaint-unlink" data-issue="%d">%s</a>',
				(int) $item->id,
				esc_html__( 'Remove link', 'real-time-auto-find-and-replace' )
			);

			$actions['ignore'] = sprintf(
				'<a href="#" class="bfrmaint-issue-action" data-issue="%d" data-do="ignore">%s</a>',
				(int) $item->id,
				esc_html__( 'Ignore', 'real-time-auto-find-and-replace' )
			);
		}

		if ( IssueRepository::STATUS_IGNORED === $item->status ) {
			$actions['unignore'] = sprintf(
				'<a href="#" class="bfrmaint-issue-action" data-issue="%d" data-do="unignore">%s</a>',
				(int) $item->id,
				esc_html__( 'Stop ignoring', 'real-time-auto-find-and-replace' )
			);
		}

		$actions['recheck'] = sprintf(
			'<a href="#" class="bfrmaint-issue-action" data-issue="%d" data-do="recheck">%s</a>',
			(int) $item->id,
			esc_html__( 'Re-check', 'real-time-auto-find-and-replace' )
		);

		/**
		 * Filter the row actions on an issue.
		 *
		 * Declared now, before anything implements it, because it is the seam
		 * pro attaches "Fix everywhere" and "Suggest a destination" to. Markup
		 * returned here is echoed as-is, so an implementer escapes its own
		 * output.
		 *
		 * @param array  $actions Action key => link markup.
		 * @param object $item    The issue row.
		 */
		$actions = (array) apply_filters( 'bfr_maintenance_issue_actions', $actions, $item );

		return sprintf(
			'<span class="bfrmaint-url" title="%s">%s</span>%s',
			esc_attr( $item->target_url ),
			esc_html( $this->shorten( $item->target_url, 70 ) ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Where the link was found.
	 *
	 * @param object $item Issue row.
	 * @return string
	 */
	public function column_source( $item ) {
		$post_id = (int) $item->object_id;
		$title   = get_the_title( $post_id );

		if ( '' === $title ) {
			$title = __( '(no title)', 'real-time-auto-find-and-replace' );
		}

		$meta    = json_decode( (string) $item->metadata, true );
		$anchors = isset( $meta['anchors'] ) && is_array( $meta['anchors'] ) ? $meta['anchors'] : array();

		$out = sprintf(
			'<a href="%s">%s</a>',
			esc_url( (string) get_edit_post_link( $post_id ) ),
			esc_html( $title )
		);

		if ( ! empty( $anchors ) ) {
			$out .= '<br /><span class="bfrmaint-anchor">' . sprintf(
				/* translators: %s: the clickable text of the link */
				esc_html__( 'Link text: %s', 'real-time-auto-find-and-replace' ),
				esc_html( $this->shorten( implode( ', ', $anchors ), 60 ) )
			) . '</span>';
		}

		return $out;
	}

	/**
	 * What is wrong with it, in words.
	 *
	 * @param object $item Issue row.
	 * @return string
	 */
	public function column_subtype( $item ) {
		$labels = array(
			InternalResolver::MISSING    => __( 'Page not found', 'real-time-auto-find-and-replace' ),
			InternalResolver::TRASHED    => __( 'In the trash', 'real-time-auto-find-and-replace' ),
			InternalResolver::NON_PUBLIC => __( 'Not published', 'real-time-auto-find-and-replace' ),
			InternalResolver::MALFORMED  => __( 'Not a valid URL', 'real-time-auto-find-and-replace' ),
		);

		$label = isset( $labels[ $item->subtype ] ) ? $labels[ $item->subtype ] : $item->subtype;

		return '<span class="bfrmaint-badge bfrmaint-badge-' . esc_attr( $item->subtype ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * How many times the link appears in the post.
	 *
	 * @param object $item Issue row.
	 * @return string
	 */
	public function column_occurrences( $item ) {
		return esc_html( number_format_i18n( (int) $item->occurrences ) );
	}

	/**
	 * The explainable priority score.
	 *
	 * @param object $item Issue row.
	 * @return string
	 */
	public function column_priority_score( $item ) {
		return esc_html( (string) (int) $item->priority_score );
	}

	/**
	 * When the scanner last saw this problem.
	 *
	 * @param object $item Issue row.
	 * @return string
	 */
	public function column_last_seen_at( $item ) {
		if ( empty( $item->last_seen_at ) ) {
			return '&mdash;';
		}

		$stamp = strtotime( $item->last_seen_at . ' UTC' );

		return esc_html(
			sprintf(
				/* translators: %s: human-readable time difference, e.g. "2 hours" */
				__( '%s ago', 'real-time-auto-find-and-replace' ),
				human_time_diff( $stamp, time() )
			)
		);
	}

	/**
	 * Anything without its own method.
	 *
	 * @param object $item        Issue row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item->{$column_name} ) ? esc_html( (string) $item->{$column_name} ) : '';
	}

	/**
	 * The row of controls that act on the list itself.
	 *
	 * The band directly above the table, opposite the row count - which is
	 * where a reader looks for something that acts on what they are currently
	 * looking at, rather than in a box above the tabs that belongs to the
	 * whole screen. Downloading this list is exactly that kind of control, so
	 * this is where Pro's export link goes.
	 *
	 * Only at the top: repeating the controls under the table would be a
	 * second copy of the same link.
	 *
	 * @param string $which Which tablenav is being printed, 'top' or 'bottom'.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		/**
		 * Filter the controls shown above the issues list.
		 *
		 * @param string $actions Markup, already escaped by whoever built it.
		 * @param string $type    Issue type this table is listing.
		 */
		$actions = (string) apply_filters( 'bfr_maintenance_list_actions', '', $this->type );

		if ( '' === trim( $actions ) ) {
			return;
		}

		// Built by the module that supplied it, which escapes as it builds.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="alignleft actions bfrmaint-list-actions">' . $actions . '</div>';
	}

	/**
	 * What an empty list should say.
	 *
	 * @return void
	 */
	public function no_items() {
		if ( $this->is_media() ) {
			esc_html_e( 'No missing images or files found. Run a scan to check again.', 'real-time-auto-find-and-replace' );

			return;
		}

		esc_html_e( 'No broken links found. Run a scan to check again.', 'real-time-auto-find-and-replace' );
	}

	/**
	 * The search control, shown whether or not the list has rows.
	 *
	 * Core hides its search box when the list is empty and nothing has been
	 * searched for. Across two tabs of one screen that is worse than useless -
	 * the control is there on one tab and gone on the other, for a reason
	 * nobody can see - so the empty case is rendered here instead.
	 *
	 * @param string $text     Button label.
	 * @param string $input_id Field id.
	 * @return void
	 */
	public function search_box( $text, $input_id ) {
		if ( $this->has_items() || '' !== $this->current_search() ) {
			parent::search_box( $text, $input_id );

			return;
		}

		$input_id .= '-search-input';

		printf(
			'<p class="search-box"><label class="screen-reader-text" for="%1$s">%2$s</label><input type="search" id="%1$s" name="s" value="" />',
			esc_attr( $input_id ),
			esc_html( $text )
		);

		submit_button( $text, 'button button-compact', '', false, array( 'id' => 'search-submit' ) );

		echo '</p>';
	}

	/**
	 * Is this the missing-media list rather than the broken-link one?
	 *
	 * @return bool
	 */
	private function is_media() {
		return Classifier::MEDIA === $this->type;
	}

	/**
	 * What was searched for, if anything.
	 *
	 * @return string
	 */
	private function current_search() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
	}

	/**
	 * The status being viewed.
	 *
	 * @return string
	 */
	private function current_status() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : IssueRepository::STATUS_OPEN;

		return in_array( $status, IssueRepository::statuses(), true ) ? $status : IssueRepository::STATUS_OPEN;
	}

	/**
	 * Shorten a string for display, from the middle.
	 *
	 * The end of a URL is usually the informative part, so trimming only the
	 * tail would hide exactly what the reader needs.
	 *
	 * @param string $text  Raw text.
	 * @param int    $limit Maximum length.
	 * @return string
	 */
	private function shorten( $text, $limit ) {
		$text = (string) $text;

		if ( strlen( $text ) <= $limit ) {
			return $text;
		}

		$head = (int) floor( ( $limit - 3 ) / 2 );
		$tail = $limit - 3 - $head;

		return substr( $text, 0, $head ) . '...' . substr( $text, -$tail );
	}
}
