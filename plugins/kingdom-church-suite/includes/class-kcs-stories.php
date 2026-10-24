<?php
/**
 * Share your story: members share a testimony as text, with pictures, or as a YouTube video link
 * (no video uploads — they fill hosting space). The church sets how many pictures, how big, and
 * how long a video may be; stories wait for review in Church Admin → Testimonies.
 *
 * YouTube links are checked when the story is sent: the video must exist and not be private,
 * and when the church has a YouTube API key its length is checked too.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stories.
 */
class KCS_Stories {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_media' ) );
	}

	/**
	 * Limits and guidelines.
	 *
	 * @return array
	 */
	public static function settings() {
		return array(
			'photos'      => '0' !== (string) kcs_get_option( 'story_photos', '1' ),
			'video'       => '0' !== (string) kcs_get_option( 'story_video', '1' ),
			'max_photos'  => max( 1, min( 10, (int) kcs_get_option( 'story_max_photos', 4 ) ) ),
			'max_mb'      => max( 1, min( (int) floor( wp_max_upload_size() / MB_IN_BYTES ), (int) kcs_get_option( 'story_photo_mb', 5 ) ) ),
			'max_minutes' => max( 1, min( 60, (int) kcs_get_option( 'story_video_minutes', 5 ) ) ),
			'guidelines'  => (string) kcs_get_option( 'story_guidelines', '' ),
		);
	}

	/**
	 * Default video guidelines (one per line).
	 *
	 * @param int $minutes Max minutes.
	 * @return string[]
	 */
	public static function guidelines( $minutes ) {
		$own = trim( (string) kcs_get_option( 'story_guidelines', '' ) );
		if ( '' !== $own ) {
			return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $own ) ) ) );
		}
		return array(
			/* translators: %d: minutes */
			sprintf( _n( 'Keep it short: %d minute or less.', 'Keep it short: %d minutes or less.', $minutes, 'kingdom-church-suite' ), $minutes ),
			__( 'Upload it to YouTube as “Public” or “Unlisted” — not “Private” — then paste the link here.', 'kingdom-church-suite' ),
			__( 'Film in a quiet, well-lit place and hold your phone steady (sideways looks best).', 'kingdom-church-suite' ),
			__( 'Share what happened, what God did, and how your life changed.', 'kingdom-church-suite' ),
			__( 'Only include other people, children or private details with their permission.', 'kingdom-church-suite' ),
			__( 'No music you do not own, adverts or links to other channels.', 'kingdom-church-suite' ),
		);
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		register_rest_route(
			'kcs/v1',
			'/stories',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_submit' ),
			)
		);
		register_rest_route(
			'kcs/v1',
			'/stories/check-video',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function ( $req ) {
					if ( ! kcs_rate_limit( 'story_check', 30, HOUR_IN_SECONDS ) ) {
						return new WP_Error( 'kcs_rate', __( 'Too many checks. Please try again later.', 'kingdom-church-suite' ), array( 'status' => 429 ) );
					}
					$v = self::check_video( (string) $req['url'] );
					return is_wp_error( $v ) ? $v : rest_ensure_response( $v );
				},
			)
		);
	}

	/**
	 * Seconds in an ISO 8601 duration (PT4M13S).
	 *
	 * @param string $iso Duration.
	 * @return int
	 */
	private static function seconds( $iso ) {
		try {
			$d = new DateInterval( $iso );
			return $d->d * 86400 + $d->h * 3600 + $d->i * 60 + $d->s;
		} catch ( Exception $e ) {
			return 0;
		}
	}

	/**
	 * Check a YouTube link.
	 *
	 * @param string $url Link.
	 * @return array|WP_Error id, title, seconds (0 = unknown), thumb.
	 */
	public static function check_video( $url ) {
		$url = trim( $url );
		$id  = KCS_Post_Types::youtube_id( $url );
		if ( '' === $url || ! $id || ! preg_match( '~^(https?://)?(www\.|m\.)?(youtube\.com|youtu\.be)/~i', $url ) ) {
			return new WP_Error( 'kcs_story_video', __( 'Please paste a YouTube link (for example https://youtu.be/…). Other video sites are not accepted.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		$max   = self::settings()['max_minutes'];
		$cache = get_transient( 'kcs_ytchk_' . $id );
		if ( is_array( $cache ) ) {
			$out = $cache;
		} else {
			$out = array( 'id' => $id, 'title' => '', 'seconds' => 0, 'thumb' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg' );
			// Without a key: YouTube's oEmbed tells us the video exists and is not private.
			$res = wp_remote_get( 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode( 'https://www.youtube.com/watch?v=' . $id ), array( 'timeout' => 8 ) );
			if ( ! is_wp_error( $res ) ) {
				$code = (int) wp_remote_retrieve_response_code( $res );
				if ( 401 === $code || 403 === $code ) {
					return new WP_Error( 'kcs_story_video', __( 'This video is private. On YouTube, change it to “Unlisted” or “Public”, then try again.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
				}
				if ( 404 === $code || 400 === $code ) {
					return new WP_Error( 'kcs_story_video', __( 'We could not find this video on YouTube. Check the link and try again.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
				}
				$body         = json_decode( (string) wp_remote_retrieve_body( $res ), true );
				$out['title'] = is_array( $body ) ? sanitize_text_field( $body['title'] ?? '' ) : '';
			}
			// With the church's YouTube key (Settings → Live stream): length and embedding too.
			$key = trim( (string) kcs_get_option( 'yt_api_key' ) );
			if ( $key ) {
				$api = wp_remote_get( add_query_arg( array( 'part' => 'contentDetails,status', 'id' => $id, 'key' => $key ), 'https://www.googleapis.com/youtube/v3/videos' ), array( 'timeout' => 8 ) );
				$data = is_wp_error( $api ) ? null : json_decode( (string) wp_remote_retrieve_body( $api ), true );
				if ( is_array( $data ) && isset( $data['items'] ) ) {
					if ( ! $data['items'] ) {
						return new WP_Error( 'kcs_story_video', __( 'This video is private or no longer on YouTube. Make it “Unlisted” or “Public”, then try again.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
					}
					$item = $data['items'][0];
					if ( isset( $item['status']['embeddable'] ) && ! $item['status']['embeddable'] ) {
						return new WP_Error( 'kcs_story_video', __( 'This video does not allow playing on other websites. On YouTube, turn on “Allow embedding”, then try again.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
					}
					$out['seconds'] = self::seconds( (string) ( $item['contentDetails']['duration'] ?? '' ) );
				}
			}
			set_transient( 'kcs_ytchk_' . $id, $out, 10 * MINUTE_IN_SECONDS );
		}
		if ( $out['seconds'] > $max * 60 + 15 ) {
			return new WP_Error(
				'kcs_story_video',
				sprintf(
					/* translators: 1: video length, 2: limit */
					__( 'This video is %1$s long. Please share a video of %2$s or less — you can trim it in the YouTube app.', 'kingdom-church-suite' ),
					gmdate( $out['seconds'] >= 3600 ? 'G:i:s' : 'i:s', $out['seconds'] ),
					sprintf( _n( '%d minute', '%d minutes', $max, 'kingdom-church-suite' ), $max )
				),
				array( 'status' => 400 )
			);
		}
		return $out;
	}

	/**
	 * POST /stories (multipart: fields + photos[]).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_submit( $req ) {
		if ( ! empty( $req['website'] ) ) {
			return rest_ensure_response( array( 'id' => 0, 'message' => __( 'Thank you!', 'kingdom-church-suite' ) ) );
		}
		if ( ! kcs_rate_limit( 'testimony', 3, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'kcs_rate', __( 'Too many submissions. Please try again later.', 'kingdom-church-suite' ), array( 'status' => 429 ) );
		}
		$id = self::submit( $req->get_params(), $req->get_file_params() );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return rest_ensure_response( array( 'id' => $id, 'message' => __( 'Thank you for sharing! Your story will appear once our team has reviewed it. 🙌', 'kingdom-church-suite' ) ) );
	}

	/**
	 * The uploaded photos, as a flat list of file arrays.
	 *
	 * @param array $files $_FILES-style params.
	 * @return array
	 */
	private static function photo_files( $files ) {
		$f = $files['photos'] ?? null;
		if ( ! $f || ! isset( $f['name'] ) ) {
			return array();
		}
		if ( ! is_array( $f['name'] ) ) {
			return array( $f );
		}
		$out = array();
		foreach ( array_keys( $f['name'] ) as $i ) {
			$out[] = array( 'name' => $f['name'][ $i ], 'type' => $f['type'][ $i ], 'tmp_name' => $f['tmp_name'][ $i ], 'error' => $f['error'][ $i ], 'size' => $f['size'][ $i ] );
		}
		return $out;
	}

	/**
	 * Save a story for review.
	 *
	 * @param array $data  Fields.
	 * @param array $files Files.
	 * @return int|WP_Error
	 */
	public static function submit( $data, $files = array() ) {
		$s     = self::settings();
		$type  = in_array( $data['type'] ?? '', array( 'text', 'photos', 'video' ), true ) ? $data['type'] : 'text';
		$name  = sanitize_text_field( $data['name'] ?? '' );
		$story = sanitize_textarea_field( $data['story'] ?? '' );
		if ( ! $name ) {
			return new WP_Error( 'kcs_story', __( 'Please add your name.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		if ( mb_strlen( $story ) < ( 'text' === $type ? 20 : 5 ) ) {
			return new WP_Error( 'kcs_story', 'text' === $type ? __( 'Please write a few sentences about what God has done.', 'kingdom-church-suite' ) : __( 'Please add a sentence or two about your story.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		if ( empty( $data['consent'] ) ) {
			return new WP_Error( 'kcs_story', __( 'Please tick the box to let us share your story.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		$photos = 'photos' === $type ? self::photo_files( $files ) : array();
		$video  = null;
		if ( 'photos' === $type ) {
			if ( ! $s['photos'] ) {
				return new WP_Error( 'kcs_story', __( 'Sharing pictures is turned off.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
			}
			if ( ! $photos ) {
				return new WP_Error( 'kcs_story', __( 'Please add at least one picture.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
			}
			if ( count( $photos ) > $s['max_photos'] ) {
				/* translators: %d: count */
				return new WP_Error( 'kcs_story', sprintf( _n( 'You can add up to %d picture.', 'You can add up to %d pictures.', $s['max_photos'], 'kingdom-church-suite' ), $s['max_photos'] ), array( 'status' => 400 ) );
			}
			foreach ( $photos as $p ) {
				if ( UPLOAD_ERR_OK !== (int) $p['error'] || (int) $p['size'] > $s['max_mb'] * MB_IN_BYTES ) {
					/* translators: %d: megabytes */
					return new WP_Error( 'kcs_story', sprintf( __( 'Each picture must be %d MB or smaller.', 'kingdom-church-suite' ), $s['max_mb'] ), array( 'status' => 400 ) );
				}
				$check = wp_check_filetype_and_ext( $p['tmp_name'], $p['name'], array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif' ) );
				if ( ! $check['type'] || ! @getimagesize( $p['tmp_name'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					return new WP_Error( 'kcs_story', __( 'Only pictures (JPG, PNG, WebP or GIF) can be added.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
				}
			}
		} elseif ( 'video' === $type ) {
			if ( ! $s['video'] ) {
				return new WP_Error( 'kcs_story', __( 'Sharing videos is turned off.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
			}
			$video = self::check_video( (string) ( $data['video_url'] ?? '' ) );
			if ( is_wp_error( $video ) ) {
				return $video;
			}
		}
		$title = sanitize_text_field( $data['title'] ?? '' );
		$id    = wp_insert_post(
			array(
				'post_type'    => 'kcs_testimony',
				'post_status'  => 'pending',
				'post_title'   => $title ? $title : wp_trim_words( $story, 6, '…' ),
				'post_content' => wpautop( $story ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_kcs_person', $name );
		update_post_meta( $id, '_kcs_location', sanitize_text_field( $data['location'] ?? '' ) );
		update_post_meta( $id, '_kcs_submitter_email', sanitize_email( $data['email'] ?? '' ) );
		update_post_meta( $id, '_kcs_story_type', $type );
		update_post_meta( $id, '_kcs_consent', current_time( 'mysql' ) );
		if ( $video ) {
			update_post_meta( $id, '_kcs_video_url', 'https://www.youtube.com/watch?v=' . $video['id'] );
			update_post_meta( $id, '_kcs_video_seconds', (int) $video['seconds'] );
		}
		if ( $photos ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$ids = array();
			foreach ( $photos as $n => $p ) {
				$p['name'] = sanitize_file_name( 'story-' . $id . '-' . ( $n + 1 ) . '.' . pathinfo( $p['name'], PATHINFO_EXTENSION ) );
				$att       = media_handle_sideload( $p, $id, wp_trim_words( $story, 8, '' ) );
				if ( ! is_wp_error( $att ) ) {
					$ids[] = (int) $att;
				}
			}
			if ( ! $ids ) {
				wp_delete_post( $id, true );
				return new WP_Error( 'kcs_story', __( 'Your pictures could not be saved. Please try again with smaller pictures.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
			}
			update_post_meta( $id, '_kcs_gallery', $ids );
		}
		KCS_Email::send(
			KCS_Email::admin_recipients(),
			/* translators: %s: name */
			sprintf( __( 'New story from %s awaiting review', 'kingdom-church-suite' ), $name ),
			'<p>' . nl2br( esc_html( $story ) ) . '</p>' . ( $video ? '<p>' . esc_html__( 'Video', 'kingdom-church-suite' ) . ': ' . esc_html( 'https://youtu.be/' . $video['id'] ) . '</p>' : '' ) . ( $photos ? '<p>' . esc_html( sprintf( /* translators: %d: count */ _n( '%d picture attached', '%d pictures attached', count( $photos ), 'kingdom-church-suite' ), count( $photos ) ) ) . '</p>' : '' ) . '<p><a class="btn" href="' . esc_url( KCS_Church_Admin::url( 'testimonies' ) ) . '">' . esc_html__( 'Review & publish', 'kingdom-church-suite' ) . '</a></p>'
		);
		do_action( 'kcs_testimony_submitted', $id );
		return (int) $id;
	}

	/**
	 * Picture IDs of a story.
	 *
	 * @param int $post_id Story.
	 * @return int[]
	 */
	public static function gallery( $post_id ) {
		return array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post_id, '_kcs_gallery', true ) ), 'wp_attachment_is_image' ) );
	}

	/**
	 * Cover picture for cards: the first photo, else the YouTube thumbnail.
	 *
	 * @param int $post_id Story.
	 * @return array url, video (bool).
	 */
	public static function cover( $post_id ) {
		$g = self::gallery( $post_id );
		if ( $g ) {
			return array( 'url' => (string) wp_get_attachment_image_url( $g[0], 'medium_large' ), 'video' => false );
		}
		$yt = KCS_Post_Types::youtube_id( (string) get_post_meta( $post_id, '_kcs_video_url', true ) );
		return $yt ? array( 'url' => 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg', 'video' => true ) : array( 'url' => '', 'video' => false );
	}

	/**
	 * Photo grid (opens a viewer) for the story page.
	 *
	 * @param int $post_id Story.
	 * @return string
	 */
	public static function gallery_html( $post_id ) {
		$g = self::gallery( $post_id );
		if ( ! $g ) {
			return '';
		}
		wp_enqueue_script( 'kcs-stories', KCS_URL . 'assets/js/stories.js', array( 'kcs-public' ), KCS_VERSION, true );
		$out = '<div class="kcs-gallery kcs-gallery--' . min( 4, count( $g ) ) . '" data-kcs-gallery>';
		foreach ( $g as $att ) {
			$out .= '<a href="' . esc_url( (string) wp_get_attachment_image_url( $att, 'large' ) ) . '" data-kcs-lightbox>' . wp_get_attachment_image( $att, 'medium_large', false, array( 'loading' => 'lazy' ) ) . '</a>';
		}
		return $out . '</div>';
	}

	/**
	 * Remove a story's pictures when the story is deleted.
	 *
	 * @param int $post_id Post.
	 */
	public static function delete_media( $post_id ) {
		if ( 'kcs_testimony' !== get_post_type( $post_id ) ) {
			return;
		}
		foreach ( self::gallery( $post_id ) as $att ) {
			wp_delete_attachment( $att, true );
		}
	}

	/**
	 * [kcs_testimony_form] / [kcs_share_story].
	 *
	 * @return string
	 */
	public static function form() {
		$s = self::settings();
		wp_enqueue_script( 'kcs-stories', KCS_URL . 'assets/js/stories.js', array( 'kcs-public' ), KCS_VERSION, true );
		$cfg = array(
			'maxPhotos'  => $s['max_photos'],
			'maxMb'      => $s['max_mb'],
			'maxMinutes' => $s['max_minutes'],
			'i18n'       => array(
				/* translators: %d: count */
				'tooMany'   => sprintf( _n( 'You can add up to %d picture.', 'You can add up to %d pictures.', $s['max_photos'], 'kingdom-church-suite' ), $s['max_photos'] ),
				/* translators: %d: megabytes */
				'tooBig'    => sprintf( __( 'Each picture must be %d MB or smaller.', 'kingdom-church-suite' ), $s['max_mb'] ),
				'notImage'  => __( 'Only pictures can be added here.', 'kingdom-church-suite' ),
				'needPhoto' => __( 'Please add at least one picture.', 'kingdom-church-suite' ),
				'needVideo' => __( 'Please paste your YouTube link.', 'kingdom-church-suite' ),
				'notYt'     => __( 'Please paste a YouTube link (for example https://youtu.be/…).', 'kingdom-church-suite' ),
				'checking'  => __( 'Checking the video…', 'kingdom-church-suite' ),
				'videoOk'   => __( 'Video found ✓', 'kingdom-church-suite' ),
				/* translators: %s: length */
				'videoLen'  => __( 'Video found ✓ · %s long', 'kingdom-church-suite' ),
				'remove'    => __( 'Remove picture', 'kingdom-church-suite' ),
				'sending'   => __( 'Sending…', 'kingdom-church-suite' ),
				'error'     => __( 'Something went wrong. Please try again.', 'kingdom-church-suite' ),
				'storyText' => __( 'What has God done?', 'kingdom-church-suite' ),
				'storyCap'  => __( 'Tell us a little about your story', 'kingdom-church-suite' ),
			),
		);
		ob_start();
		?>
		<form class="kcs-form kcs-story" data-kcs-story data-config="<?php echo esc_attr( wp_json_encode( $cfg ) ); ?>" novalidate>
			<div class="kcs-story__done" data-story-done hidden>
				<span class="kcs-story__check" aria-hidden="true">🙌</span>
				<h3><?php esc_html_e( 'Thank you for sharing!', 'kingdom-church-suite' ); ?></h3>
				<p data-story-done-text></p>
				<button type="button" class="kcs-btn kcs-btn--ghost" data-story-again><?php esc_html_e( 'Share another story', 'kingdom-church-suite' ); ?></button>
			</div>
			<div class="kcs-story__fields" data-story-fields>
				<h3 class="kcs-form__title"><?php esc_html_e( 'Share your story', 'kingdom-church-suite' ); ?></h3>
				<p class="kcs-muted kcs-story__lead"><?php esc_html_e( 'What has God done in your life? Your story could be the hope someone needs today.', 'kingdom-church-suite' ); ?></p>
				<div class="kcs-story__types" role="radiogroup" aria-label="<?php esc_attr_e( 'How would you like to share?', 'kingdom-church-suite' ); ?>">
					<label><input type="radio" name="type" value="text" checked><span><b aria-hidden="true">✍️</b><?php esc_html_e( 'Write it', 'kingdom-church-suite' ); ?></span></label>
					<?php if ( $s['photos'] ) : ?><label><input type="radio" name="type" value="photos"><span><b aria-hidden="true">📷</b><?php esc_html_e( 'With pictures', 'kingdom-church-suite' ); ?></span></label><?php endif; ?>
					<?php if ( $s['video'] ) : ?><label><input type="radio" name="type" value="video"><span><b aria-hidden="true">▶️</b><?php esc_html_e( 'YouTube video', 'kingdom-church-suite' ); ?></span></label><?php endif; ?>
				</div>

				<?php if ( $s['photos'] ) : ?>
					<div class="kcs-story__panel" data-story-panel="photos" hidden>
						<div class="kcs-drop" data-story-drop tabindex="0" role="button" aria-label="<?php esc_attr_e( 'Add pictures', 'kingdom-church-suite' ); ?>">
							<input type="file" accept="image/*" multiple data-story-input hidden>
							<span class="kcs-drop__ico" aria-hidden="true">📷</span>
							<strong><?php esc_html_e( 'Add pictures', 'kingdom-church-suite' ); ?></strong>
							<small><?php /* translators: 1: count, 2: MB */ echo esc_html( sprintf( __( 'Up to %1$d pictures, %2$d MB each. Tap to choose or drop them here.', 'kingdom-church-suite' ), $s['max_photos'], $s['max_mb'] ) ); ?></small>
						</div>
						<div class="kcs-thumbs" data-story-thumbs></div>
					</div>
				<?php endif; ?>

				<?php if ( $s['video'] ) : ?>
					<div class="kcs-story__panel" data-story-panel="video" hidden>
						<div class="kcs-guide">
							<strong><?php esc_html_e( 'Video guidelines', 'kingdom-church-suite' ); ?></strong>
							<ol>
								<?php foreach ( self::guidelines( $s['max_minutes'] ) as $g ) : ?><li><?php echo esc_html( $g ); ?></li><?php endforeach; ?>
							</ol>
							<small class="kcs-muted"><?php esc_html_e( 'We do not take video uploads here: put your video on YouTube first, then share the link.', 'kingdom-church-suite' ); ?></small>
						</div>
						<label><?php esc_html_e( 'YouTube link', 'kingdom-church-suite' ); ?><input type="url" name="video_url" inputmode="url" placeholder="https://youtu.be/…" data-story-yt></label>
						<div class="kcs-yt-preview" data-story-yt-preview hidden><img alt="" data-story-yt-img><span data-story-yt-status></span></div>
					</div>
				<?php endif; ?>

				<label><span data-story-label><?php esc_html_e( 'What has God done?', 'kingdom-church-suite' ); ?></span><textarea name="story" rows="5" maxlength="5000" required></textarea></label>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'Your name', 'kingdom-church-suite' ); ?><input type="text" name="name" required autocomplete="name"></label>
					<label><?php esc_html_e( 'City / country (optional)', 'kingdom-church-suite' ); ?><input type="text" name="location"></label>
				</div>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'Email (not published)', 'kingdom-church-suite' ); ?><input type="email" name="email" autocomplete="email"></label>
					<label><?php esc_html_e( 'Headline (optional)', 'kingdom-church-suite' ); ?><input type="text" name="title" placeholder="<?php esc_attr_e( 'e.g. Healed after 3 years', 'kingdom-church-suite' ); ?>"></label>
				</div>
				<label class="kcs-check"><input type="checkbox" name="consent" value="1" required> <?php esc_html_e( 'I agree that the church may share my story, pictures and video on its website, app and social media.', 'kingdom-church-suite' ); ?></label>
				<input type="text" name="website" class="kcs-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
				<div class="kcs-progress" data-story-progress hidden><span></span></div>
				<button type="submit" class="kcs-btn kcs-btn--lg"><?php esc_html_e( 'Share my story', 'kingdom-church-suite' ); ?></button>
				<div class="kcs-form-msg" aria-live="polite"></div>
			</div>
		</form>
		<?php
		return ob_get_clean();
	}
}
