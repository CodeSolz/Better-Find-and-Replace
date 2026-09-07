<?php namespace RealTimeAutoFindReplace\admin\functions;

/**
 * Media Replacer Class
 *
 * @package Function
 * @since 1.6.7
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

use RealTimeAutoFindReplace\lib\Util;


class MediaImageReplacer{

    /**
     * Suffix given to a file that is moved aside while a replacement is in flight.
     *
     * It is appended rather than substituted, so the original extension ends up
     * in the middle of the name and a stashed file is not servable where it sits.
     */
    const STASH_SUFFIX = '.bfar-stash';

    /**
	 * The old file path.
	 *
	 * @var string
	 */
	private $old_file_path;

    /**
     * Files moved aside for the replacement in flight: original path => stash path.
     *
     * Non-empty only between the stash and the commit. Every exit path either
     * restores it or discards it, and a shutdown guard restores it if the
     * request dies in between.
     *
     * @var array
     */
    private $stash = array();

    /**
     * Replaces an existing media file in the WordPress Media Library.
     *
     * Handles file upload, updates attachment metadata (title, caption, description, alt text),
     * and removes the old file to avoid redundancy.
     *
     * The old files are moved aside rather than deleted until the new upload has
     * landed and the attachment record has been rewritten. A rejected upload, a
     * failed resize or a failed post update all roll back to the original media,
     * which used to be gone by the time any of them could happen.
     *
     * @param array $user_input User-provided metadata for the media file.
     *
     * @return void Outputs a JSON response with success or error details.
     */
    public function handleMediaReplace( $user_input ){

        if ( !current_user_can( 'manage_options' ) && !current_user_can( Util::bfar_nav_cap('media_replacer') ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Access Denied', 'real-time-auto-find-and-replace' ),
                    'text'   => __( 'You do not have permission to perform this action.', 'real-time-auto-find-and-replace' ),
				)
			);
        }

        if ( ! isset( $user_input['attachment_id'] ) || ! isset( $_FILES['media_file'] ) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK ) {
            return wp_send_json([
                'success' => false,
                'message' => 'Invalid request. Missing attachment ID or file.'
            ]);
        }

        $attachment_id = absint( $user_input['attachment_id'] ); // Get the attachment ID

        $attachment = get_post( $attachment_id );
        if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
            return wp_send_json([
                'success' => false,
                'message' => 'Invalid attachment ID.'
            ]);
        }

        // Replacement mode: 'new_name' renames the file and repoints links;
        // anything else keeps the existing file name in place.
        $replace_mode = ( isset( $user_input['replace_mode'] ) && 'new_name' === $user_input['replace_mode'] )
            ? 'new_name'
            : 'keep_name';

        // Include WordPress media library functions
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $old_url       = wp_get_attachment_url( $attachment_id );
        $old_file_path = get_attached_file( $attachment_id, true );
        $metadata      = wp_get_attachment_metadata( $attachment_id );
        $backup_sizes  = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
        $uploaded_file = $_FILES['media_file'];

        // Nothing on disk moves until the replacement has been checked, so a
        // file we were never going to accept cannot cost anyone their original.
        $checked = $this->check_replacement( $uploaded_file, $attachment_id, $old_file_path, $replace_mode );
        if ( is_wp_error( $checked ) ) {
            return wp_send_json([
                'success' => false,
                'message' => $checked->get_error_message(),
            ]);
        }

        // The current files are moved aside, not deleted, so every step below
        // still has something to roll back to.
        $stashed = $this->stash_current_files( $attachment_id, $metadata, $backup_sizes, $old_file_path );
        if ( is_wp_error( $stashed ) ) {
            return wp_send_json([
                'success' => false,
                'message' => $stashed->get_error_message(),
            ]);
        }

        // A fatal in between - wp_generate_attachment_metadata() running the
        // memory limit out on a large image is the usual one - would otherwise
        // leave the original media stashed under a name nothing points at.
        register_shutdown_function( array( $this, 'restore_stash' ) );

        $this->old_file_path = $old_file_path;

        $upload_overrides = [
            'test_form' => false, // Bypass form submission check
        ];

        // Keep-name mode forces the original file name; new-name mode lets
        // WordPress assign the uploaded file's own (sanitised, unique) name.
        if ( 'keep_name' === $replace_mode ) {
            $upload_overrides['unique_filename_callback'] = array( $this, 'unique_filename_callback' );
        }

        $upload_result = wp_handle_upload(
            $uploaded_file,
            $upload_overrides,
            \gmdate( 'Y/m', \strtotime( $attachment->post_date ) )
        );

        // wp_handle_upload() vets the upload error, the size, the extension and
        // the sniffed type before it writes anything, so this is where a
        // malformed or disallowed file lands - with the original still stashed.
        if ( ! isset( $upload_result['file'] ) ) {
            $this->restore_stash();

            return wp_send_json([
                'success' => false,
                'message' => 'Failed to replace file: ' . ( isset( $upload_result['error'] ) ? $upload_result['error'] : 'the upload was rejected.' )
            ]);
        }

        $new_file_path = $upload_result['file'];

        // Keep-name has to end up at the original path. The month in the
        // attachment post_date is not always the directory its file lives in,
        // and a quietly relocated file would break every URL pointing at it.
        if ( 'keep_name' === $replace_mode && wp_normalize_path( $new_file_path ) !== wp_normalize_path( $old_file_path ) ) {
            wp_mkdir_p( dirname( $old_file_path ) );

            if ( ! @rename( $new_file_path, $old_file_path ) ) {
                wp_delete_file( $new_file_path );
                $this->restore_stash();

                return wp_send_json([
                    'success' => false,
                    'message' => 'Failed to replace file: the upload could not be moved into place. The original file is unchanged.'
                ]);
            }

            $new_file_path = $old_file_path;
        }

        add_filter( 'big_image_size_threshold', '__return_false' );
        $new_attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $new_file_path );
        remove_filter( 'big_image_size_threshold', '__return_false' );

        if ( is_wp_error( $new_attachment_metadata ) || ! file_exists( $new_file_path ) ) {
            $this->delete_generated_files( $new_attachment_metadata, $new_file_path );
            wp_delete_file( $new_file_path );
            $this->restore_stash();

            return wp_send_json([
                'success' => false,
                'message' => 'Failed to replace file: the new file could not be processed. The original file is unchanged.'
            ]);
        }

        if ( ! is_array( $new_attachment_metadata ) ) {
            $new_attachment_metadata = array();
        }

        wp_update_attachment_metadata($attachment_id, $new_attachment_metadata);
        $new_media = wp_get_attachment_image_src( $attachment_id, 'large' );
        update_attached_file($attachment_id, $new_file_path);

        $alt_text = sanitize_text_field($user_input['alt_text'] ?? '');
        $caption = sanitize_text_field($user_input['caption'] ?? '');
        $description = sanitize_textarea_field($user_input['description'] ?? '');
        $title = sanitize_textarea_field($user_input['title'] ?? '');

        $updated_post = [
            'ID' => $attachment_id,
            'post_mime_type' => $upload_result['type'], // Update MIME type
            'post_title' => empty($title) ? sanitize_file_name(basename($new_file_path)) : $title,
            'post_excerpt' => $caption, // Caption
            'post_content' => $description, // Description
        ];

        $updated = wp_update_post( $updated_post, true );

        if ( is_wp_error( $updated ) ) {
            // Point the record back at the original files before restoring them,
            // so a failed write here does not leave a row describing a file that
            // is about to stop existing.
            wp_update_attachment_metadata( $attachment_id, $metadata );
            update_attached_file( $attachment_id, $old_file_path );
            $this->delete_generated_files( $new_attachment_metadata, $new_file_path );
            wp_delete_file( $new_file_path );
            $this->restore_stash();

            return wp_send_json([
                'success' => false,
                'message' => 'Failed to replace file: ' . $updated->get_error_message() . ' The original file is unchanged.'
            ]);
        }

        if ( !empty( $alt_text ) && wp_attachment_is_image( $attachment_id ) ) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        }

        // Committed. The old files go now rather than after the link rewrite
        // below, which is long enough to time out - and a shutdown restore
        // after this point would put the old file back over the new one.
        $this->discard_stash();

        // New-name mode: repoint every reference from the old URL to the new one.
        $links_updated = 0;
        if ( 'new_name' === $replace_mode ) {
            $new_url = wp_get_attachment_url( $attachment_id );
            if ( $new_url && $old_url && $new_url !== $old_url ) {
                $links_updated = ( new DbReplacer() )->replace_links( $old_url, $new_url, true );
            }
        }

        return wp_send_json([
            'success' => true,
            'message' => ( 'new_name' === $replace_mode )
                /* translators: %d: number of links updated */
                ? sprintf( __( 'File replaced and %d link(s) updated.', 'real-time-auto-find-and-replace' ), (int) $links_updated )
                : __( 'File replaced successfully.', 'real-time-auto-find-and-replace' ),
            'new_media_url' => wp_get_attachment_url($attachment_id),
            'media_id' => $attachment_id,
            'mode' => $replace_mode,
            'links_updated' => (int) $links_updated,
        ]);

     }

    /**
     * Checks a replacement before anything on disk is touched.
     *
     * The upload itself is vetted by wp_handle_upload() - upload error, size,
     * extension and sniffed type - but only once it runs. What that cannot know
     * is the rule keep-name mode adds: the new bytes are written under the
     * existing file name, so they have to be the same kind of file. The browser
     * enforces that today, which is exactly why the server has to as well.
     *
     * @param array  $uploaded_file The $_FILES entry for the replacement.
     * @param int    $attachment_id Attachment being replaced.
     * @param string $old_file_path Absolute path of the file being replaced.
     * @param string $replace_mode  'keep_name' or 'new_name'.
     *
     * @return true|\WP_Error True when the replacement may proceed.
     */
    private function check_replacement( $uploaded_file, $attachment_id, $old_file_path, $replace_mode ) {

        $tmp_name = isset( $uploaded_file['tmp_name'] ) ? $uploaded_file['tmp_name'] : '';

        if ( empty( $tmp_name ) || ! @is_readable( $tmp_name ) || ! @filesize( $tmp_name ) ) {
            return new \WP_Error(
                'bfar_empty_upload',
                __( 'Failed to replace file: the uploaded file is empty or unreadable.', 'real-time-auto-find-and-replace' )
            );
        }

        if ( 'keep_name' !== $replace_mode ) {
            return true;
        }

        $new_check = wp_check_filetype_and_ext(
            $tmp_name,
            ! empty( $uploaded_file['name'] ) ? $uploaded_file['name'] : wp_basename( $old_file_path )
        );
        $new_mime = ! empty( $new_check['type'] ) ? $new_check['type'] : '';

        if ( empty( $new_mime ) ) {
            return new \WP_Error(
                'bfar_unknown_type',
                __( 'Failed to replace file: the file type could not be determined, or is not allowed here.', 'real-time-auto-find-and-replace' )
            );
        }

        $old_mime = get_post_mime_type( $attachment_id );
        if ( empty( $old_mime ) ) {
            $old_check = wp_check_filetype( wp_basename( $old_file_path ) );
            $old_mime  = ! empty( $old_check['type'] ) ? $old_check['type'] : '';
        }

        // No recorded type to compare against - let wp_handle_upload() decide.
        if ( empty( $old_mime ) ) {
            return true;
        }

        if ( $this->primary_mime_type( $new_mime ) !== $this->primary_mime_type( $old_mime ) ) {
            return new \WP_Error(
                'bfar_type_mismatch',
                sprintf(
                    /* translators: 1: MIME type of the uploaded file, 2: MIME type of the file being replaced. */
                    __( 'Failed to replace file: keeping the file name needs a file of the same type (uploaded %1$s, expected %2$s). Choose the rename method to switch the file type.', 'real-time-auto-find-and-replace' ),
                    $new_mime,
                    $old_mime
                )
            );
        }

        return true;
    }

    /**
     * The part of a MIME type before the slash: image, video, application.
     *
     * @param string $mime MIME type.
     *
     * @return string
     */
    private function primary_mime_type( $mime ) {
        $mime  = (string) $mime;
        $slash = strpos( $mime, '/' );

        return false === $slash ? $mime : substr( $mime, 0, $slash );
    }

    /**
     * Moves every file belonging to the attachment aside.
     *
     * This is what wp_delete_attachment_files() used to do here, except the
     * files are renamed instead of unlinked, so restore_stash() can put them
     * back. Recorded in $this->stash for the rest of the request.
     *
     * @param int    $attachment_id Attachment being replaced.
     * @param mixed  $metadata      Its attachment metadata.
     * @param mixed  $backup_sizes  Its _wp_attachment_backup_sizes meta.
     * @param string $file          Absolute path of its main file.
     *
     * @return true|\WP_Error True once the files are aside.
     */
    private function stash_current_files( $attachment_id, $metadata, $backup_sizes, $file ) {

        $this->stash = array();

        if ( empty( $file ) ) {
            return new \WP_Error(
                'bfar_no_file',
                __( 'Failed to replace file: this attachment has no file on disk.', 'real-time-auto-find-and-replace' )
            );
        }

        $uploads = wp_get_upload_dir();
        $basedir = isset( $uploads['basedir'] ) ? $uploads['basedir'] : '';

        // Core will not delete an attachment file that sits outside the uploads
        // directory, and moving one aside is no safer. Refuse rather than write
        // an upload over whatever is actually there.
        if ( ! $this->is_inside( $file, $basedir ) ) {
            return new \WP_Error(
                'bfar_outside_uploads',
                __( 'Failed to replace file: this attachment file is outside the uploads directory.', 'real-time-auto-find-and-replace' )
            );
        }

        foreach ( $this->collect_current_files( $attachment_id, $metadata, $backup_sizes, $file ) as $path ) {

            if ( ! file_exists( $path ) || ! $this->is_inside( $path, $basedir ) ) {
                continue;
            }

            $stashed = $path . self::STASH_SUFFIX;

            // Left behind by a request that died mid-replacement.
            if ( file_exists( $stashed ) ) {
                wp_delete_file( $stashed );
            }

            if ( ! @rename( $path, $stashed ) ) {
                $this->restore_stash();

                return new \WP_Error(
                    'bfar_stash_failed',
                    __( 'Failed to replace file: the current files could not be moved aside. Check the permissions on the uploads directory.', 'real-time-auto-find-and-replace' )
                );
            }

            $this->stash[ $path ] = $stashed;
        }

        return true;
    }

    /**
     * Every file on disk that belongs to the attachment.
     *
     * Mirrors what wp_delete_attachment_files() walks: the legacy thumbnail, the
     * intermediate sizes, the scaled original, the backup sizes, and the file
     * itself - the file last, so it is the first one restored.
     *
     * @param int    $attachment_id Attachment ID.
     * @param mixed  $metadata      Attachment metadata.
     * @param mixed  $backup_sizes  _wp_attachment_backup_sizes meta.
     * @param string $file          Absolute path of the main file.
     *
     * @return array Absolute paths, de-duplicated.
     */
    private function collect_current_files( $attachment_id, $metadata, $backup_sizes, $file ) {

        global $wpdb;

        $paths    = array();
        $metadata = is_array( $metadata ) ? $metadata : array();

        // Every derivative sits beside the file itself, so they are built from
        // its directory rather than joined onto the uploads base. path_join()
        // reads a Windows path written with forward slashes as relative and
        // would hand back a doubled-up path that matches nothing.
        $dir = trailingslashit( dirname( $file ) );

        if ( ! empty( $metadata['thumb'] ) ) {
            // A legacy thumbnail can be shared; core leaves one alone while
            // another attachment still points at it, and so do we.
            $shared = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attachment_metadata' AND meta_value LIKE %s AND post_id <> %d",
                    '%' . $wpdb->esc_like( $metadata['thumb'] ) . '%',
                    $attachment_id
                )
            );

            if ( ! $shared ) {
                $paths[] = $dir . wp_basename( $metadata['thumb'] );
            }
        }

        if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
            foreach ( $metadata['sizes'] as $sizeinfo ) {
                if ( ! empty( $sizeinfo['file'] ) ) {
                    $paths[] = $dir . wp_basename( $sizeinfo['file'] );
                }
            }
        }

        if ( ! empty( $metadata['original_image'] ) ) {
            $paths[] = $dir . wp_basename( $metadata['original_image'] );
        }

        if ( is_array( $backup_sizes ) ) {
            foreach ( $backup_sizes as $size ) {
                if ( ! empty( $size['file'] ) ) {
                    $paths[] = $dir . wp_basename( $size['file'] );
                }
            }
        }

        $paths[] = $file;

        return array_values( array_unique( array_filter( $paths ) ) );
    }

    /**
     * Puts the files that were moved aside back where they came from.
     *
     * Public because it doubles as the shutdown guard registered by
     * handleMediaReplace(); it is a no-op once the replacement has either
     * committed or already rolled back.
     *
     * @return void
     */
    public function restore_stash() {

        if ( empty( $this->stash ) ) {
            return;
        }

        foreach ( array_reverse( $this->stash, true ) as $original => $stashed ) {

            if ( ! file_exists( $stashed ) ) {
                continue;
            }

            // Whatever sits at the original path now is a half-finished
            // replacement; the file being held is the one that belongs there.
            if ( file_exists( $original ) ) {
                wp_delete_file( $original );
            }

            @rename( $stashed, $original );
        }

        $this->stash = array();
    }

    /**
     * Drops the files that were moved aside, once the replacement has committed.
     *
     * @return void
     */
    private function discard_stash() {

        foreach ( $this->stash as $stashed ) {
            wp_delete_file( $stashed );
        }

        $this->stash = array();
    }

    /**
     * Removes the intermediate files generated for a replacement that failed.
     *
     * The stashed originals are never touched: they carry the stash suffix, so
     * no name generated from the new upload can collide with one.
     *
     * @param mixed  $metadata Metadata returned by wp_generate_attachment_metadata().
     * @param string $file     Absolute path the sizes were generated from.
     *
     * @return void
     */
    private function delete_generated_files( $metadata, $file ) {

        if ( ! is_array( $metadata ) || empty( $file ) ) {
            return;
        }

        $dir = trailingslashit( wp_normalize_path( dirname( $file ) ) );

        if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
            foreach ( $metadata['sizes'] as $sizeinfo ) {
                if ( ! empty( $sizeinfo['file'] ) ) {
                    wp_delete_file( $dir . wp_basename( $sizeinfo['file'] ) );
                }
            }
        }

        if ( ! empty( $metadata['original_image'] ) ) {
            wp_delete_file( $dir . wp_basename( $metadata['original_image'] ) );
        }
    }

    /**
     * Whether a path sits inside a directory.
     *
     * @param string $path      Path to test.
     * @param string $directory Directory it should be under.
     *
     * @return bool
     */
    private function is_inside( $path, $directory ) {

        if ( empty( $path ) || empty( $directory ) ) {
            return false;
        }

        if ( false !== strpos( wp_normalize_path( $path ), '../' ) ) {
            return false;
        }

        $real_path = realpath( $path );
        $real_dir  = realpath( $directory );

        $path      = wp_normalize_path( $real_path ? $real_path : $path );
        $directory = trailingslashit( wp_normalize_path( $real_dir ? $real_dir : $directory ) );

        // Windows hands back the same directory in different cases often enough
        // for a case-sensitive compare to reject a perfectly ordinary path.
        return '\\' === DIRECTORY_SEPARATOR
            ? 0 === stripos( $path, $directory )
            : 0 === strpos( $path, $directory );
    }

    /**
     * Overrides the unique filename callback to replace the original file.
     *
     * This function ensures that when a file is uploaded, it overrides
     * the existing file with the same name instead of generating a unique
     * filename.
     *
     * @param string $dir      The directory path where the file will be uploaded.
     * @param string $filename The name of the file being uploaded.
     * @param string $ext      The file extension, including the leading dot (e.g., '.jpg').
     *
     * @return string The modified filename to use for the upload.
     */
	public function unique_filename_callback( $dir, $filename, $ext = '' ) {
		if ( isset( $this->old_file_path ) ) {
			return basename( $this->old_file_path );
		} else {
			return $filename;
		}
	}

}
