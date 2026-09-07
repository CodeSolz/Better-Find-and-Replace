<?php namespace RealTimeAutoFindReplace\Maintenance\Duplicate;

use RealTimeAutoFindReplace\Maintenance\Data\ActivityLog;
use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;
use RealTimeAutoFindReplace\Maintenance\Support\Logger;

/**
 * Copy a post, without copying the things that make it that post.
 *
 * Three rules shape every decision here:
 *
 * **A clone is always a draft.** Whatever the original was - published,
 * scheduled, private - the copy starts as a draft. A cloner that can publish is
 * one mis-click from a second live page competing with the first for the same
 * search result, and undoing that is harder than pressing publish again.
 *
 * **Nothing that references the original by id travels.** Not its comments, not
 * its edit lock, not its old slugs. `MetaPolicy` holds that line for custom
 * fields; this class holds it for everything else.
 *
 * **`wp_insert_post()`, never a raw INSERT.** The clone has to look like a post
 * being created to every plugin on the site, because for most of them it is one.
 * A row written straight into wp_posts is a post that search indexes, caches and
 * translation tables never hear about.
 *
 * @package Maintenance
 * @since 1.11.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Cloner {

	/**
	 * Post types that can never be cloned, whatever their settings say.
	 *
	 * An attachment's row is half of the object - the file is the other half -
	 * and a revision belongs to a post that already exists.
	 *
	 * @var array
	 */
	private static $never = array( 'attachment', 'revision', 'nav_menu_item' );

	/**
	 * Clone one post.
	 *
	 * @param int $post_id Post to copy.
	 * @return array {
	 *     @type bool   $ok
	 *     @type int    $id      The new post, when there is one.
	 *     @type string $code    Machine-readable reason on failure.
	 *     @type string $message Human-readable reason on failure.
	 * }
	 */
	public static function clone_post( $post_id ) {
		$post = get_post( (int) $post_id );

		if ( ! $post ) {
			return self::fail( 'no_post', __( 'That post no longer exists.', 'real-time-auto-find-and-replace' ) );
		}

		if ( ! self::is_cloneable( $post->post_type ) ) {
			return self::fail( 'bad_type', __( 'That kind of content cannot be duplicated.', 'real-time-auto-find-and-replace' ) );
		}

		if ( ! self::user_can_clone( $post ) ) {
			return self::fail( 'not_allowed', __( 'You do not have permission to duplicate that.', 'real-time-auto-find-and-replace' ) );
		}

		$new_id = wp_insert_post( self::row_for( $post ), true );

		if ( is_wp_error( $new_id ) ) {
			return self::fail( 'insert_failed', $new_id->get_error_message() );
		}

		$new_id = (int) $new_id;

		self::copy_terms( $post, $new_id );
		$meta = self::copy_meta( $post->ID, $new_id );

		/**
		 * Fires after a post has been cloned.
		 *
		 * Everything a plugin needs to finish the job for its own data: the
		 * source, the copy, and what the meta policy refused to carry.
		 *
		 * @param int   $new_id  The clone.
		 * @param int   $post_id The original.
		 * @param array $skipped Meta key => why it did not travel.
		 */
		do_action( 'bfr_post_cloned', $new_id, (int) $post->ID, $meta['skipped'] );

		self::record( $post, $new_id, $meta['skipped'] );

		return array(
			'ok'      => true,
			'id'      => $new_id,
			'code'    => '',
			'message' => '',
		);
	}

	/**
	 * The row the clone starts life as.
	 *
	 * @param object $post The original.
	 * @return array
	 */
	private static function row_for( $post ) {
		$row = array(
			// Always a draft. This is the rule, not a default.
			'post_status'    => 'draft',
			'post_type'      => $post->post_type,
			'post_title'     => self::title_for( $post ),
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_parent'    => $post->post_parent,
			'menu_order'     => $post->menu_order,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_password'  => $post->post_password,
			'to_ping'        => $post->to_ping,
			'post_mime_type' => $post->post_mime_type,

			// The person making the copy owns it. Attributing it to the
			// original author would put a draft they did not write, and may not
			// be able to see, under their name.
			'post_author'    => get_current_user_id(),
		);

		/**
		 * Filter the post data a clone is created from.
		 *
		 * @param array  $row  The new post's fields.
		 * @param object $post The original.
		 */
		return (array) apply_filters( 'bfr_clone_post_data', $row, $post );
	}

	/**
	 * What the copy is called.
	 *
	 * @param object $post The original.
	 * @return string
	 */
	public static function title_for( $post ) {
		$title = isset( $post->post_title ) ? (string) $post->post_title : '';

		/* translators: %s: the original post title */
		$cloned = sprintf( __( '%s (copy)', 'real-time-auto-find-and-replace' ), $title );

		/**
		 * Filter the title a clone is given.
		 *
		 * @param string $cloned The new title.
		 * @param object $post   The original.
		 */
		return (string) apply_filters( 'bfr_clone_title', $cloned, $post );
	}

	/**
	 * Give the clone the original's terms, in every taxonomy.
	 *
	 * @param object $post   The original.
	 * @param int    $new_id The clone.
	 * @return void
	 */
	private static function copy_terms( $post, $new_id ) {
		$taxonomies = get_object_taxonomies( $post->post_type );

		foreach ( (array) $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( (int) $post->ID, $taxonomy, array( 'fields' => 'ids' ) );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			wp_set_object_terms( $new_id, array_map( 'intval', $terms ), $taxonomy, false );
		}
	}

	/**
	 * Copy the custom fields the policy allows.
	 *
	 * @param int $post_id The original.
	 * @param int $new_id  The clone.
	 * @return array The partition, so callers can report what was left behind.
	 */
	private static function copy_meta( $post_id, $new_id ) {
		$meta      = get_post_meta( (int) $post_id );
		$partition = MetaPolicy::partition( is_array( $meta ) ? $meta : array() );

		foreach ( $partition['copy'] as $key => $values ) {
			foreach ( (array) $values as $value ) {
				// maybe_unserialize, because get_post_meta() without a key
				// returns raw strings - adding them back as-is would store a
				// serialised array as a literal string.
				add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
			}
		}

		return $partition;
	}

	/**
	 * Can this post type be cloned at all?
	 *
	 * @param string $type Post type name.
	 * @return bool
	 */
	public static function is_cloneable( $type ) {
		$type = (string) $type;

		if ( in_array( $type, self::$never, true ) ) {
			return false;
		}

		$object = get_post_type_object( $type );

		if ( ! $object || empty( $object->public ) ) {
			return false;
		}

		/**
		 * Filter whether a post type may be duplicated.
		 *
		 * @param bool   $can  Whether it may.
		 * @param string $type Post type name.
		 */
		return (bool) apply_filters( 'bfr_clone_post_type', true, $type );
	}

	/**
	 * May the current user clone this post?
	 *
	 * Checked against the *target type's* own capabilities rather than a blanket
	 * `manage_options`: somebody who cannot create a product must not be able to
	 * make one by copying it.
	 *
	 * @param object $post The original.
	 * @return bool
	 */
	public static function user_can_clone( $post ) {
		if ( ! isset( $post->post_type, $post->ID ) ) {
			return false;
		}

		$object = get_post_type_object( $post->post_type );

		if ( ! $object || ! isset( $object->cap->create_posts, $object->cap->edit_posts ) ) {
			return false;
		}

		// Creating one of these, and being allowed to read the one being copied.
		return current_user_can( $object->cap->create_posts )
			&& current_user_can( 'read_post', (int) $post->ID );
	}

	/**
	 * Write the clone into the activity log.
	 *
	 * A clone is a new content object appearing on the site. It belongs in the
	 * same record as everything else that creates one.
	 *
	 * @param object $post    The original.
	 * @param int    $new_id  The clone.
	 * @param array  $skipped Meta that did not travel.
	 * @return void
	 */
	private static function record( $post, $new_id, array $skipped ) {
		// Unlike every other caller of the log, this one runs on the ordinary
		// post list - on sites where the maintenance tables may have failed to
		// create. Logging into a table that is not there would put database
		// errors on a screen that has nothing to do with this plugin.
		if ( class_exists( '\RealTimeAutoFindReplace\Maintenance\Data\ActivityLog' ) && Tables::installed() ) {
			ActivityLog::record(
				'post_cloned',
				array(
					'object_type' => 'post',
					'object_id'   => $new_id,
					'summary'     => sprintf(
						/* translators: %s: the original post title */
						__( 'Duplicated "%s" as a draft', 'real-time-auto-find-and-replace' ),
						(string) $post->post_title
					),
					'metadata'    => array(
						'source_id' => (int) $post->ID,
						'new_id'    => $new_id,
						'skipped'   => array_keys( $skipped ),
					),
				)
			);
		}

		Logger::log(
			'duplicate.cloned',
			array(
				'source' => (int) $post->ID,
				'clone'  => $new_id,
			)
		);
	}

	/**
	 * A refusal, in the shape every caller here expects.
	 *
	 * @param string $code    Machine-readable reason.
	 * @param string $message Human-readable reason.
	 * @return array
	 */
	private static function fail( $code, $message ) {
		return array(
			'ok'      => false,
			'id'      => 0,
			'code'    => (string) $code,
			'message' => (string) $message,
		);
	}
}
