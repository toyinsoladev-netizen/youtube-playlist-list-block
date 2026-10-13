<?php
/**
 * Plugin Name:       Playlist List Block for YouTube
 * Description:       Display a YouTube playlist as a clean, numbered list of videos. Just enter the playlist ID.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Toyinsola Adebayo
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       playlist-list-block
 *
 * @package PlaylistListBlock
 */

defined( 'ABSPATH' ) || exit;

define( 'PLB_VERSION', '1.0.0' );

/* -------------------------------------------------------------------------
 * Block registration
 * ---------------------------------------------------------------------- */

add_action( 'init', 'plb_register_block' );

function plb_register_block() {
	// Registered manually so script dependencies are declared (no build step needed).
	wp_register_script(
		'plb-editor',
		plugins_url( 'assets/editor.js', __FILE__ ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
		PLB_VERSION,
		true
	);
	wp_set_script_translations( 'plb-editor', 'playlist-list-block' );

	register_block_type(
		__DIR__,
		array( 'render_callback' => 'plb_render_block' )
	);
}

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/**
 * API key: wp-config.php constant wins over the saved option.
 */
function plb_get_api_key() {
	if ( defined( 'PLB_API_KEY' ) && PLB_API_KEY ) {
		return trim( PLB_API_KEY );
	}
	return trim( (string) get_option( 'plb_api_key', '' ) );
}

/**
 * Accepts a bare playlist ID or a full YouTube URL containing ?list=...
 */
function plb_parse_playlist_id( $input ) {
	$input = trim( (string) $input );
	if ( preg_match( '/[?&]list=([A-Za-z0-9_-]+)/', $input, $m ) ) {
		return $m[1];
	}
	return preg_replace( '/[^A-Za-z0-9_-]/', '', $input );
}

/**
 * Message box, only visible to people who can edit posts (never to visitors).
 */
function plb_notice( $message ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return '';
	}
	return '<div class="plb-notice">' . esc_html( $message ) . '</div>';
}

function plb_format_duration( $iso ) {
	try {
		$interval = new DateInterval( $iso );
	} catch ( Exception $e ) {
		return '';
	}
	$seconds = ( $interval->d * 86400 ) + ( $interval->h * 3600 ) + ( $interval->i * 60 ) + $interval->s;
	if ( $seconds < 1 ) {
		return '';
	}
	$h = intdiv( $seconds, 3600 );
	$m = intdiv( $seconds % 3600, 60 );
	$s = $seconds % 60;
	return $h > 0 ? sprintf( '%d:%02d:%02d', $h, $m, $s ) : sprintf( '%d:%02d', $m, $s );
}

/* -------------------------------------------------------------------------
 * YouTube Data API
 * ---------------------------------------------------------------------- */

function plb_api_get( $endpoint, $args, $api_key ) {
	$url      = add_query_arg( array_merge( $args, array( 'key' => $api_key ) ), 'https://www.googleapis.com/youtube/v3/' . $endpoint );
	$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'plb_http_error', __( 'Could not reach the YouTube API.', 'playlist-list-block' ) );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $code || ! is_array( $data ) ) {
		$message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unexpected response from the YouTube API.', 'playlist-list-block' );
		return new WP_Error( 'plb_api_error', $message );
	}

	return $data;
}

/**
 * Returns an array of items, or WP_Error. Results (and errors) are cached.
 */
function plb_get_items( $playlist_id, $max, $durations ) {
	$api_key = plb_get_api_key();
	if ( '' === $api_key ) {
		return new WP_Error( 'plb_no_key', __( 'No YouTube API key set. Add one under Settings → YouTube Playlist.', 'playlist-list-block' ) );
	}

	$cache_key = 'plb_' . md5( implode( '|', array( $playlist_id, $max, (int) $durations, $api_key ) ) );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		if ( isset( $cached['error'] ) ) {
			return new WP_Error( 'plb_cached_error', $cached['error'] );
		}
		return $cached['items'];
	}

	$data = plb_api_get(
		'playlistItems',
		array(
			'part'       => 'snippet',
			'playlistId' => $playlist_id,
			'maxResults' => $max,
		),
		$api_key
	);

	if ( is_wp_error( $data ) ) {
		// Cache failures briefly so a bad key/ID does not hammer the API quota.
		set_transient( $cache_key, array( 'error' => $data->get_error_message() ), MINUTE_IN_SECONDS );
		return $data;
	}

	$items = array();
	$rows  = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();

	foreach ( $rows as $row ) {
		$snippet  = isset( $row['snippet'] ) ? $row['snippet'] : array();
		$video_id = isset( $snippet['resourceId']['videoId'] ) ? $snippet['resourceId']['videoId'] : '';
		$thumbs   = isset( $snippet['thumbnails'] ) ? $snippet['thumbnails'] : array();

		// Private / deleted videos have no thumbnails; skip them.
		if ( '' === $video_id || empty( $thumbs ) ) {
			continue;
		}

		$thumb = isset( $thumbs['medium'] ) ? $thumbs['medium'] : ( isset( $thumbs['high'] ) ? $thumbs['high'] : reset( $thumbs ) );

		$items[] = array(
			'id'      => $video_id,
			'title'   => isset( $snippet['title'] ) ? $snippet['title'] : '',
			'channel' => isset( $snippet['videoOwnerChannelTitle'] ) ? $snippet['videoOwnerChannelTitle'] : ( isset( $snippet['channelTitle'] ) ? $snippet['channelTitle'] : '' ),
			'thumb'   => isset( $thumb['url'] ) ? $thumb['url'] : '',
			'width'   => isset( $thumb['width'] ) ? absint( $thumb['width'] ) : 320,
			'height'  => isset( $thumb['height'] ) ? absint( $thumb['height'] ) : 180,
			'badge'   => '',
		);
	}

	// Optional second call (1 quota unit): durations + upcoming/live status.
	if ( $durations && $items ) {
		$videos = plb_api_get(
			'videos',
			array(
				'part'       => 'contentDetails,snippet',
				'id'         => implode( ',', wp_list_pluck( $items, 'id' ) ),
				'maxResults' => count( $items ),
			),
			$api_key
		);

		if ( ! is_wp_error( $videos ) && ! empty( $videos['items'] ) ) {
			$map = array();
			foreach ( $videos['items'] as $video ) {
				if ( isset( $video['id'] ) ) {
					$map[ $video['id'] ] = $video;
				}
			}
			foreach ( $items as $i => $item ) {
				if ( ! isset( $map[ $item['id'] ] ) ) {
					continue;
				}
				$video = $map[ $item['id'] ];
				$state = isset( $video['snippet']['liveBroadcastContent'] ) ? $video['snippet']['liveBroadcastContent'] : 'none';
				if ( 'upcoming' === $state ) {
					$items[ $i ]['badge'] = __( 'UPCOMING', 'playlist-list-block' );
				} elseif ( 'live' === $state ) {
					$items[ $i ]['badge'] = __( 'LIVE', 'playlist-list-block' );
				} elseif ( isset( $video['contentDetails']['duration'] ) ) {
					$items[ $i ]['badge'] = plb_format_duration( $video['contentDetails']['duration'] );
				}
			}
		}
	}

	$ttl = (int) apply_filters( 'plb_cache_ttl', HOUR_IN_SECONDS );
	set_transient( $cache_key, array( 'items' => $items ), $ttl );

	return $items;
}

/* -------------------------------------------------------------------------
 * Front-end / editor-preview render
 * ---------------------------------------------------------------------- */

function plb_render_block( $attributes ) {
	$playlist_id = plb_parse_playlist_id( isset( $attributes['playlistId'] ) ? $attributes['playlistId'] : '' );
	if ( '' === $playlist_id ) {
		return plb_notice( __( 'Enter a YouTube playlist ID.', 'playlist-list-block' ) );
	}

	$max       = isset( $attributes['maxResults'] ) ? min( 50, max( 1, absint( $attributes['maxResults'] ) ) ) : 10;
	$durations = ! isset( $attributes['showDurations'] ) || $attributes['showDurations'];

	$items = plb_get_items( $playlist_id, $max, $durations );
	if ( is_wp_error( $items ) ) {
		return plb_notice( $items->get_error_message() );
	}
	if ( empty( $items ) ) {
		return plb_notice( __( 'This playlist has no public videos.', 'playlist-list-block' ) );
	}

	ob_start();
	?>
	<ol <?php echo get_block_wrapper_attributes( array( 'class' => 'plb-list' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php foreach ( $items as $index => $item ) : ?>
			<?php
			$url = add_query_arg(
				array(
					'v'    => $item['id'],
					'list' => $playlist_id,
				),
				'https://www.youtube.com/watch'
			);
			?>
			<li class="plb-item">
				<a class="plb-link" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
					<span class="plb-number"><?php echo esc_html( $index + 1 ); ?></span>
					<span class="plb-thumb">
						<img src="<?php echo esc_url( $item['thumb'] ); ?>" width="<?php echo esc_attr( $item['width'] ); ?>" height="<?php echo esc_attr( $item['height'] ); ?>" alt="" loading="lazy" decoding="async">
						<?php if ( '' !== $item['badge'] ) : ?>
							<span class="plb-badge"><?php echo esc_html( $item['badge'] ); ?></span>
						<?php endif; ?>
					</span>
					<span class="plb-text">
						<span class="plb-title"><?php echo esc_html( $item['title'] ); ?></span>
						<?php if ( '' !== $item['channel'] ) : ?>
							<span class="plb-channel"><?php echo esc_html( $item['channel'] ); ?></span>
						<?php endif; ?>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ol>
	<?php
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * Settings page (Settings API: nonce + capability handled by WordPress)
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'plb_add_settings_page' );
add_action( 'admin_init', 'plb_register_settings' );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'plb_action_links' );

function plb_add_settings_page() {
	add_options_page(
		__( 'YouTube Playlist List', 'playlist-list-block' ),
		__( 'YouTube Playlist', 'playlist-list-block' ),
		'manage_options',
		'playlist-list-block',
		'plb_render_settings_page'
	);
}

function plb_register_settings() {
	register_setting(
		'plb_settings',
		'plb_api_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	add_settings_section( 'plb_main', '', '__return_false', 'playlist-list-block' );

	add_settings_field(
		'plb_api_key',
		__( 'YouTube Data API key', 'playlist-list-block' ),
		'plb_render_api_key_field',
		'playlist-list-block',
		'plb_main',
		array( 'label_for' => 'plb_api_key' )
	);
}

function plb_render_api_key_field() {
	$locked = defined( 'PLB_API_KEY' ) && PLB_API_KEY;
	printf(
		'<input type="text" id="plb_api_key" name="plb_api_key" value="%s" class="regular-text" autocomplete="off" %s />',
		esc_attr( $locked ? '' : get_option( 'plb_api_key', '' ) ),
		disabled( $locked, true, false )
	);
	echo '<p class="description">';
	if ( $locked ) {
		esc_html_e( 'The key is currently defined by the PLB_API_KEY constant in wp-config.php.', 'playlist-list-block' );
	} else {
		printf(
			/* translators: %s: link to Google Cloud console */
			esc_html__( 'Create a key in the %s and enable "YouTube Data API v3".', 'playlist-list-block' ),
			'<a href="https://console.cloud.google.com/apis/library/youtube.googleapis.com" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google Cloud console', 'playlist-list-block' ) . '</a>'
		);
	}
	echo '</p>';
}

function plb_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="wrap"><h1>' . esc_html( get_admin_page_title() ) . '</h1>';
	echo '<form action="options.php" method="post">';
	settings_fields( 'plb_settings' );
	do_settings_sections( 'playlist-list-block' );
	submit_button();
	echo '</form></div>';
}

function plb_action_links( $links ) {
	$settings = '<a href="' . esc_url( admin_url( 'options-general.php?page=playlist-list-block' ) ) . '">' . esc_html__( 'Settings', 'playlist-list-block' ) . '</a>';
	array_unshift( $links, $settings );
	return $links;
}
