<?php
/**
 * BuddyBoss Media Functions.
 *
 * Functions are where all the magic happens in BuddyPress. They will
 * handle the actual saving or manipulation of information. Usually they will
 * hand off to a database class for data access, then return
 * true or false on success or failure.
 *
 * @package BuddyBoss\Media\Functions
 * @since BuddyBoss 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Create and upload the media file
 *
 * @since BuddyBoss 1.0.0
 *
 * @return array|null|WP_Error|WP_Post
 */
function bp_media_upload() {
	/**
	 * Make sure user is logged in
	 */
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'not_logged_in', __( 'Please login in order to upload file media.', 'buddyboss' ), array( 'status' => 500 ) );
	}

	$attachment = bp_media_upload_handler();

	if ( is_wp_error( $attachment ) ) {
		return $attachment;
	}

	$name = $attachment->post_title;

	$thumb_nfo = wp_get_attachment_image_src( $attachment->ID );
	$url_nfo   = wp_get_attachment_image_src( $attachment->ID, 'full' );

	$url       = is_array( $url_nfo ) && ! empty( $url_nfo ) ? $url_nfo[0] : null;
	$thumb_nfo = is_array( $thumb_nfo ) && ! empty( $thumb_nfo ) ? $thumb_nfo[0] : null;

	$result = array(
		'id'    => (int) $attachment->ID,
		'thumb' => esc_url( $thumb_nfo ),
		'url'   => esc_url( $url ),
		'name'  => esc_attr( $name )
	);

	return $result;
}

/**
 * Mine type for uploader allowed by buddyboss media for security reason
 *
 * @param  Array $mime_types carry mime information
 * @since BuddyBoss 1.0.0
 *
 * @return Array
 */
function bp_media_allowed_mimes( $mime_types ) {

	//Creating a new array will reset the allowed filetypes
	$mime_types = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'gif'          => 'image/gif',
		'png'          => 'image/png',
		'bmp'          => 'image/bmp',
	);

	return $mime_types;
}

/**
 * Media upload handler
 *
 * @param string $file_id
 *
 * @since BuddyBoss 1.0.0
 *
 * @return array|int|null|WP_Error|WP_Post
 */
function bp_media_upload_handler( $file_id = 'file' ) {

	/**
	 * Include required files
	 */

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once( ABSPATH . 'wp-admin' . '/includes/image.php' );
		require_once( ABSPATH . 'wp-admin' . '/includes/file.php' );
		require_once( ABSPATH . 'wp-admin' . '/includes/media.php' );
	}

	if ( ! function_exists( 'media_handle_upload' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/admin.php' );
	}

	add_image_size( 'bp-bb-media-thumbnail', 400, 400 );
	add_image_size( 'bp-bb-activity-media-thumbnail', 700, 700, true );

	add_filter( 'upload_mimes', 'bp_media_allowed_mimes', 9, 1 );

	$aid = media_handle_upload( $file_id, 0, array(), array(
		'test_form' => false,
		'upload_error_strings' => array(
			false,
			__( 'The uploaded file exceeds ', 'buddyboss' ) . bp_media_file_upload_max_size( true ),
			__( 'The uploaded file exceeds ', 'buddyboss' ) . bp_media_file_upload_max_size( true ),
			__( 'The uploaded file was only partially uploaded.', 'buddyboss' ),
			__( 'No file was uploaded.', 'buddyboss' ),
			'',
			__( 'Missing a temporary folder.', 'buddyboss' ),
			__( 'Failed to write file to disk.', 'buddyboss' ),
			__( 'File upload stopped by extension.', 'buddyboss' )
		)
	) );

	remove_image_size( 'bp-bb-media-thumbnail' );
	remove_image_size( 'bp-bb-activity-media-thumbnail' );

	// if has wp error then throw it.
	if ( is_wp_error( $aid ) ) {
		return $aid;
	}

	$attachment = get_post( $aid );

	if ( ! empty( $attachment ) ) {

		$file_path = get_attached_file( $attachment->ID );

		$attachment_data = wp_get_attachment_metadata( $attachment->ID );

		if ( ! empty( $file_path ) ) {
			$path        = @pathinfo( $file_path );
			$newfilename = $path['filename'] . '-buddyboss-reduced-sized-' . time();
			$newfile     = $path['dirname'] . "/" . $newfilename . "." . $path['extension'];
			bp_media_compress_image( $file_path, $newfile, 0.1 );
			$path                                      = @pathinfo( $newfile );
			$attachment_data['buddyboss_reduced_size'] = $newfilename . '.' . $path['extension'];
		}

		if ( $attachment_data ) {
			$attachment_data[ 'buddyboss_media_upload' ] = true;
			wp_update_attachment_metadata( $attachment->ID, $attachment_data );
		}

		return $attachment;

	}

	return new WP_Error( 'error_uploading', __( 'Error while uploading media.', 'buddyboss' ), array( 'status' => 500 ) );

}

/**
 * Compress the image
 *
 * @param $source
 * @param $destination
 * @param int $quality
 *
 * @since BuddyBoss 1.0.0
 *
 * @return mixed
 */
function bp_media_compress_image( $source, $destination, $quality = 90 ) {

	$info = @getimagesize( $source );

	if ( $info['mime'] == 'image/jpeg' ) {
		$image = @imagecreatefromjpeg( $source );
	} elseif ( $info['mime'] == 'image/gif' ) {
		$image = @imagecreatefromgif( $source );
	} elseif ( $info['mime'] == 'image/png' ) {
		$image = @imagecreatefrompng( $source );
	}

	@imagejpeg( $image, $destination, $quality );

	return $destination;
}

/**
 * Get file media upload max size
 *
 * @param bool $post_string
 *
 * @since BuddyBoss 1.0.0
 *
 * @return string
 */
function bp_media_file_upload_max_size( $post_string = false ) {
	static $max_size = - 1;

	if ( $max_size < 0 ) {
		// Start with post_max_size.
		$size = @ini_get( 'post_max_size' );
		$unit = preg_replace( '/[^bkmgtpezy]/i', '', $size ); // Remove the non-unit characters from the size.
		$size = preg_replace( '/[^0-9\.]/', '', $size ); // Remove the non-numeric characters from the size.
		if ( $unit ) {
			$post_max_size = round( $size * pow( 1024, stripos( 'bkmgtpezy', $unit[0] ) ) );
		} else {
			$post_max_size = round( $size );
		}

		if ( $post_max_size > 0 ) {
			$max_size = $post_max_size;
		}

		// If upload_max_size is less, then reduce. Except if upload_max_size is
		// zero, which indicates no limit.
		$size = @ini_get( 'upload_max_filesize' );
		$unit = preg_replace( '/[^bkmgtpezy]/i', '', $size ); // Remove the non-unit characters from the size.
		$size = preg_replace( '/[^0-9\.]/', '', $size ); // Remove the non-numeric characters from the size.
		if ( $unit ) {
			$upload_max = round( $size * pow( 1024, stripos( 'bkmgtpezy', $unit[0] ) ) );
		} else {
			$upload_max = round( $size );
		}
		if ( $upload_max > 0 && $upload_max < $max_size ) {
			$max_size = $upload_max;
		}
	}

	return bp_media_format_size_units( $max_size, $post_string );
}

/**
 * Format file size units
 *
 * @param $bytes
 * @param bool $post_string
 *
 * @since BuddyBoss 1.0.0
 *
 * @return string
 */
function bp_media_format_size_units( $bytes, $post_string = false ) {
	if ( $bytes >= 1073741824 ) {
		$bytes = number_format( $bytes / 1073741824, 0 ) . ( $post_string ? ' GB' : '' );
	} elseif ( $bytes >= 1048576 ) {
		$bytes = number_format( $bytes / 1048576, 0 ) . ( $post_string ? ' MB' : '' );
	} elseif ( $bytes >= 1024 ) {
		$bytes = number_format( $bytes / 1024, 0 ) . ( $post_string ? ' KB' : '' );
	} elseif ( $bytes > 1 ) {
		$bytes = $bytes . ( $post_string ? ' bytes' : '' );
	} elseif ( $bytes == 1 ) {
		$bytes = $bytes . ( $post_string ? ' byte' : '' );
	} else {
		$bytes = '0'. ( $post_string ? ' bytes' : '' );
	}

	return $bytes;
}


/**
 * Update media for activity
 *
 * @param $bytes
 * @param bool $post_string
 *
 * @since BuddyBoss 1.0.0
 *
 * @return string
 */
function bp_media_update_media_meta( $content, $user_id, $activity_id ) {

	if ( ! isset( $_POST['media'] ) || empty( $_POST['media'] ) ) {
		return false;
	}

	$media_list = $_POST['media'];

	$media_model = new BP_Media();
	$exist_medias = $media_model::where( array( 'activity_id' => $activity_id ) );
	if ( ! empty( $exist_medias ) ) {
		$exist_medias = wp_list_pluck( $exist_medias, 'media_id' );
	}

	if ( ! empty( $media_list ) ) {
		foreach ( $media_list as $media_index => $media ) {
			$index = array_search( $media['id'], $exist_medias );
			if ( ! empty( $media['id'] ) && $index !== false && ! empty( $exist_medias ) ){
				$media_model::update( array(
					'menu_order'    => isset( $media['menu_order'] ) ? absint( $media['menu_order'] ) : $media_index,
				), array(
						'media_id' => ! empty( $media['id'] ) ? $media['id'] : 0,
					)
				);
				unset( $exist_medias[ $index ] );
				continue;
			}
			$media_model::insert( array(
					'blog_id'      => get_current_blog_id(),
					'media_author'    => ! empty( $media['user_id'] ) ? $media['user_id'] : $user_id,
					'media_title'        => ! empty( $media['name'] ) ? $media['name'] : '&nbsp;',
					'album_id'     => ! empty( $media['album_id'] ) ? $media['album_id'] : 0,
					'activity_id'  => $activity_id,
					'privacy'      => ! empty( $media['privacy'] ) ? $media['privacy'] : 'public',
					'upload_date' => $media_model::now(),
					'media_id'     => ! empty( $media['id'] ) ? $media['id'] : 0,
					'favorites'     => isset( $media['menu_order'] ) ? absint( $media['menu_order'] ) : $media_index,
					//'menu_order'    => isset( $media['menu_order'] ) ? absint( $media['menu_order'] ) : $media_index,
				)
			);
		}
	}

	if ( ! empty( $exist_medias ) ) {
		$exist_medias = $media_model::where( array( 'media_id' => $exist_medias ) );
		if ( ! empty( $exist_medias ) ) {
			foreach ( $exist_medias as $media ) {
				$media_model::delete( $media->id, $media->media_id );
			}
		}
	}
}
add_action( 'bp_activity_posted_update', 'bp_media_update_media_meta', 10, 3 );

function bp_media_groups_update_media_meta( $content, $user_id, $group_id, $activity_id ) {
	bp_media_update_media_meta( $content, $user_id, $activity_id );
}
add_action( 'bp_groups_posted_update', 'bp_media_groups_update_media_meta', 10, 4 );

function bp_media_activity_entry() {
	$media_list = bp_media_get_media( bp_get_activity_id() );

	if ( ! empty( $media_list ) ) {
		$media_list_length = sizeof( $media_list );
		?>
		<div class="bb-activity-media-wrap <?php echo 'bb-media-length-' . $media_list_length; echo $media_list_length > 5 ? 'bb-media-length-more' : ''; ?>">
		<?php
		foreach( array_splice( $media_list, 0, 5 ) as $media_index => $media ) {
			?>

				<div class="bb-activity-media-elem <?php echo $media_list_length == 1 || $media_list_length > 1 && $media_index == 0 ? 'act-grid-1-1 ' : ''; echo $media_list_length > 1 && $media_index > 0 ? 'act-grid-1-2 ' : ''; echo $media['meta']['width'] > $media['meta']['height'] ? 'bb-horizontal-layout' : ''; echo $media['meta']['height'] > $media['meta']['width'] ? 'bb-vertical-layout' : ''; ?>">
					<a href="#" class="entry-img">
						<img src="<?php echo $media['activity_thumb']; ?>" class="no-round photo" alt="<?php echo $media['title']; ?>" />
						<?php if ( $media_list_length > 5 && $media_index == 4 ) {
							?>
							<span class="bb-photos-length"><span><strong>+<?php echo $media_list_length - 5; ?></strong> <span><?php _e( 'More Photos', 'buddyboss' ); ?></span></span></span>
							<?php
						} ?>
					</a>
				</div>
			<?php
		}
		?>
		</div>
		<?php
	}
}
add_action( 'bp_activity_entry_content', 'bp_media_activity_entry' );

/**
 * Get media uploaded to activity
 *
 * @param $object
 * @param $request
 *
 * @return array
 */
function bp_media_get_media( $activity_id, $args = array() ){
	$response = array();
	//$orderby = ! empty( $args['photos_orderby'] ) ? $args['photos_orderby'] : 'menu_order';
	$orderby = ! empty( $args['photos_orderby'] ) ? $args['photos_orderby'] : 'favorites';
	$order = ! empty( $args['photos_order'] ) ? $args['photos_order'] : 'asc';
	$media_model = new BP_Media();
	$media_list = $media_model::where( array( 'activity_id' => $activity_id ), false, false, $orderby . ' ' . $order );
	$media_privacy = BP_Media_Privacy::instance();
	if ( ! empty( $media_list ) ) {
		foreach ( $media_list as $media ) {
			if ( $media_privacy->is_media_visible( $media->id ) ) {

				$data = array (
					'id' => $media->id,
					'author' => $media->media_author,
					'title' => $media->title,
					'album_id' => $media->album_id,
					'activity_id' => $media->activity_id,
					'privacy' => $media->privacy,
					'media_id' => $media->media_id,
					'upload_date' => $media->upload_date,
				);

				$data['full'] = wp_get_attachment_image_url( $media->media_id, 'full' );
				$data['thumb'] = wp_get_attachment_image_url( $media->media_id, 'bp-media-thumbnail' );
				$data['activity_thumb'] = wp_get_attachment_image_url( $media->media_id, 'bp-activity-media-thumbnail' );
				$data['meta'] = wp_get_attachment_metadata( $media->media_id );

//				if ( ! empty( $data['meta']['buddyboss_reduced_size'] ) ) {
//					$file_path = get_attached_file( $media->media_id );
//					$path        = @pathinfo( $file_path );
//					$data['reduced_size'] = \Boss\boss_loader()->get_url_from_path( $path['dirname'] . "/" . $data['meta']['buddyboss_reduced_size'] );
//				}


				$response[] = $data ;
			}
		}
	}
	return $response;
}
