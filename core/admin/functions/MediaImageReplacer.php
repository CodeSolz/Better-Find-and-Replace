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
	 * The old file path.
	 *
	 * @var string
	 */
	private $old_file_path;

    /**
     * Replaces an existing media file in the WordPress Media Library.
     *
     * Handles file upload, updates attachment metadata (title, caption, description, alt text),
     * and deletes the old file to avoid redundancy.
     *
     * @param array $user_input User-provided metadata for the media file.
     *
     * @return void Outputs a JSON response with success or error details.
     */
    public function handleMediaReplace( $user_input ){

        if ( !current_user_can( 'manage_options' ) && !current_user_can( Util::bfar_nav_cap('add_masking_rule') ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Access Denied', 'real-time-auto-find-and-replace' ),
                    'text'   => __( 'You do not have permission to perform this action.', 'real-time-auto-find-and-replace' ),
				)
			);
        }

        if (isset($user_input['attachment_id']) && isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
            $attachment_id = absint($user_input['attachment_id']); // Get the attachment ID

            $attachment = get_post($attachment_id);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                wp_send_json([
                    'success' => false,
                    'message' => 'Invalid attachment ID.'
                ]);
                exit;
            }

            $old_file_path = get_attached_file( $attachment_id, true );
            $metadata     = wp_get_attachment_metadata( $attachment_id );
            $backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
            wp_delete_attachment_files( $attachment_id, $metadata, $backup_sizes, $old_file_path );

            // Include WordPress media library functions
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $new_file_path = $old_file_path;
            $this->old_file_path = $old_file_path;

            $uploaded_file = $_FILES['media_file'];
            $upload_overrides = [
                'test_form' => false, // Bypass form submission check
                'unique_filename_callback' => array( $this, 'unique_filename_callback' )
            ];

            $upload_result = wp_handle_upload(
                $uploaded_file, 
                $upload_overrides,
                \gmdate( 'Y/m', \strtotime( $attachment->post_date ) )
            );

            if (isset($upload_result['file'])) {

                add_filter( 'big_image_size_threshold', '__return_false' );
                $new_attachment_metadata = wp_generate_attachment_metadata($attachment_id, $new_file_path);
                wp_update_attachment_metadata($attachment_id, $new_attachment_metadata);
                $new_media = wp_get_attachment_image_src( $attachment_id, 'large' );
                update_attached_file($attachment_id, $new_file_path);

                $alt_text = sanitize_text_field($user_input['alt_text'] ?? '');
                $caption = sanitize_text_field($user_input['caption'] ?? '');
                $description = sanitize_textarea_field($user_input['description'] ?? '');
                $title = sanitize_textarea_field($user_input['title'] ?? '');

                // $updated_post = [
                //     'ID' => $attachment_id,
                //     'post_excerpt' => $caption, // Caption
                //     'post_content' => $description, // Description
                // ];
                $updated_post = [
                    'ID' => $attachment_id,
                    'post_mime_type' => $upload_result['type'], // Update MIME type
                    'post_title' => empty($title) ? sanitize_file_name(basename($new_file_path)) : $title,
                    'post_excerpt' => $caption, // Caption
                    'post_content' => $description, // Description
                ];
                wp_update_post($updated_post);

                // \pre_print(
                //     $updated_post
                // );

                if ( !empty( $alt_text ) && wp_attachment_is_image( $attachment_id ) ) {
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
                }

                return wp_send_json([
                    'success' => true,
                    'message' => 'File replaced successfully. ',
                    'new_media_url' => wp_get_attachment_url($attachment_id),
                    'media_id' => $attachment_id
                ]);
            } else {
                return wp_send_json([
                    'success' => false,
                    'message' => 'Failed to replace file: ' . $upload_result['error']
                ]);
            }
        } else {
            return wp_send_json([
                'success' => false,
                'message' => 'Invalid request. Missing attachment ID or file.'
            ]);
        }

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