<?php
/**
 * YouTube live: "always live" from the church's channel.
 *
 * The church pastes its channel link, @handle or channel ID once. The theme then:
 * - finds the channel ID automatically (no need to dig for "UC…");
 * - checks every minute or so whether the channel is live, about to go live, or offline
 *   (with a YouTube API key if one is set, otherwise from the channel's public /live page);
 * - shows the live video with chat, the scheduled stream's waiting room, or a replay;
 * - updates open pages by itself when the stream starts or ends (no refresh needed).
 *
 * One check is shared by all visitors (cached), so traffic never multiplies requests to YouTube.
 *
 * @package Kingdom
 */

defined( 'ABSPATH' ) || exit;

/**
 * Seconds a live status is reused before YouTube is checked again.
 *
 * @param string $state live|upcoming|offline.
 * @return int
 */
function kingdom_ytlive_cache_ttl( $state ) {
	return (int) apply_filters( 'kingdom_ytlive_cache_ttl', 'offline' === $state ? 120 : 60, $state );
}

/**
 * Fetch a YouTube page as a regular browser would (skipping the EU consent screen).
 *
 * @param string $url URL.
 * @return string HTML ('' on failure).
 */
function kingdom_ytlive_fetch( $url ) {
	$res = wp_remote_get(
		$url,
		array(
			'timeout'    => 8,
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
			'headers'    => array(
				'Accept-Language' => 'en-US,en;q=0.9',
				'Cookie'          => 'CONSENT=YES+1; SOCS=CAI',
			),
		)
	);
	return is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
}

/**
 * The church's channel ID, worked out from whatever they typed: a UC… ID, a channel link,
 * an @handle or a /c/ or /user/ link. The result is remembered until the setting changes.
 *
 * @return string UC… ID or ''.
 */
function kingdom_ytlive_channel_id() {
	$raw = trim( (string) get_theme_mod( 'kingdom_ytlive_channel', '' ) );
	if ( '' === $raw && function_exists( 'kcs_get_option' ) ) {
		$raw = trim( (string) kcs_get_option( 'yt_channel_id' ) );
	}
	if ( '' === $raw ) {
		return '';
	}
	if ( preg_match( '~(UC[A-Za-z0-9_-]{22})~', $raw, $m ) ) {
		return $m[1];
	}
	$saved = get_option( 'kingdom_ytlive_resolved', array() );
	if ( is_array( $saved ) && ( $saved['raw'] ?? '' ) === $raw && ! empty( $saved['id'] ) ) {
		return $saved['id'];
	}
	if ( get_transient( 'kingdom_ytlive_resolve_failed' ) === $raw ) {
		return '';
	}
	if ( preg_match( '~^@?([A-Za-z0-9._-]{3,100})$~', $raw, $m ) ) {
		$url = 'https://www.youtube.com/@' . $m[1];
	} elseif ( preg_match( '~youtube\.com/((?:@|c/|user/)[^/?#]+)~i', $raw, $m ) ) {
		$url = 'https://www.youtube.com/' . $m[1];
	} else {
		return '';
	}
	$html = kingdom_ytlive_fetch( $url );
	$id   = '';
	foreach ( array( '~<link rel="canonical" href="https://www\.youtube\.com/channel/(UC[A-Za-z0-9_-]{22})~', '~"externalId":"(UC[A-Za-z0-9_-]{22})"~', '~"channelId":"(UC[A-Za-z0-9_-]{22})"~', '~itemprop="identifier" content="(UC[A-Za-z0-9_-]{22})"~' ) as $re ) {
		if ( preg_match( $re, $html, $m ) ) {
			$id = $m[1];
			break;
		}
	}
	if ( $id ) {
		update_option( 'kingdom_ytlive_resolved', array( 'raw' => $raw, 'id' => $id ), false );
	} else {
		set_transient( 'kingdom_ytlive_resolve_failed', $raw, 10 * MINUTE_IN_SECONDS );
	}
	return $id;
}

/**
 * YouTube Data API key (theme setting, or the one saved in Church Suite).
 *
 * @return string
 */
function kingdom_ytlive_api_key() {
	$key = trim( (string) get_theme_mod( 'kingdom_ytlive_api_key', '' ) );
	if ( '' === $key && function_exists( 'kcs_get_option' ) ) {
		$key = trim( (string) kcs_get_option( 'yt_api_key' ) );
	}
	return $key;
}

/**
 * Ask YouTube what the channel is doing right now.
 *
 * @param string $channel Channel ID.
 * @return array state (live|upcoming|offline), video_id, title, starts_at (unix), source.
 */
function kingdom_ytlive_check( $channel ) {
	$out = kingdom_ytlive_check_page( $channel );
	// The page did not show a stream (or YouTube hid it from this server): ask oEmbed, which
	// hosting servers can usually reach, whether the channel's live video is on air.
	if ( ! $out['video_id'] ) {
		$oembed = kingdom_ytlive_check_oembed( $channel );
		if ( $oembed ) {
			$out = $oembed;
		}
	}
	$key = kingdom_ytlive_api_key();
	// YouTube sometimes hides the /live page from hosting servers: with an API key, look at the
	// channel's latest uploads instead (2 quota units, instead of a 100-unit search).
	if ( $key && ! $out['video_id'] ) {
		$api = kingdom_ytlive_check_uploads( $channel, $key );
		if ( $api ) {
			return $api;
		}
	}
	// With an API key, confirm the stream found on the page (1 quota unit, instead of a 100-unit search).
	if ( $key && $out['video_id'] ) {
		$res  = wp_remote_get( add_query_arg( array( 'part' => 'snippet,liveStreamingDetails', 'id' => $out['video_id'], 'key' => $key ), 'https://www.googleapis.com/youtube/v3/videos' ), array( 'timeout' => 8 ) );
		$item = ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ? ( json_decode( wp_remote_retrieve_body( $res ), true )['items'][0] ?? null ) : null;
		if ( $item ) {
			$live  = $item['liveStreamingDetails'] ?? array();
			$state = ! empty( $live['actualStartTime'] ) && empty( $live['actualEndTime'] ) ? 'live' : ( ! empty( $live['scheduledStartTime'] ) && empty( $live['actualStartTime'] ) ? 'upcoming' : 'offline' );
			return array(
				'state'     => $state,
				'video_id'  => 'offline' === $state ? '' : $out['video_id'],
				'title'     => 'offline' === $state ? '' : html_entity_decode( (string) ( $item['snippet']['title'] ?? $out['title'] ), ENT_QUOTES ),
				'starts_at' => ! empty( $live['scheduledStartTime'] ) ? (int) strtotime( $live['scheduledStartTime'] ) : $out['starts_at'],
				'viewers'   => (int) ( $live['concurrentViewers'] ?? 0 ),
				'source'    => 'api',
			);
		}
	}
	return $out;
}

/**
 * Is the channel on air? YouTube's oEmbed answers for the channel's /live address only while a
 * broadcast is live or scheduled; a live broadcast's thumbnail ends in "_live".
 *
 * @param string $channel Channel ID.
 * @return array|null Status when a live broadcast was found, otherwise null.
 */
function kingdom_ytlive_check_oembed( $channel ) {
	$res = wp_remote_get( add_query_arg( array( 'url' => rawurlencode( 'https://www.youtube.com/channel/' . $channel . '/live' ), 'format' => 'json' ), 'https://www.youtube.com/oembed' ), array( 'timeout' => 8 ) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $data ) || ! preg_match( '~/embed/([A-Za-z0-9_-]{11})~', (string) ( $data['html'] ?? '' ), $m ) ) {
		return null;
	}
	if ( false === strpos( (string) ( $data['thumbnail_url'] ?? '' ), '_live.' ) ) {
		return null; // Scheduled or finished: the /live page decides those.
	}
	return array( 'state' => 'live', 'video_id' => $m[1], 'title' => html_entity_decode( (string) ( $data['title'] ?? '' ), ENT_QUOTES ), 'starts_at' => 0, 'viewers' => 0, 'source' => 'oembed' );
}

/**
 * Live or upcoming stream among the channel's latest uploads (YouTube Data API).
 *
 * @param string $channel Channel ID.
 * @param string $key     API key.
 * @return array|null Status, or null when the API did not answer.
 */
function kingdom_ytlive_check_uploads( $channel, $key ) {
	$res = wp_remote_get( add_query_arg( array( 'part' => 'contentDetails', 'playlistId' => 'UU' . substr( $channel, 2 ), 'maxResults' => 5, 'key' => $key ), 'https://www.googleapis.com/youtube/v3/playlistItems' ), array( 'timeout' => 8 ) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$ids = array();
	foreach ( (array) ( json_decode( wp_remote_retrieve_body( $res ), true )['items'] ?? array() ) as $item ) {
		if ( ! empty( $item['contentDetails']['videoId'] ) ) {
			$ids[] = $item['contentDetails']['videoId'];
		}
	}
	$out = array( 'state' => 'offline', 'video_id' => '', 'title' => '', 'starts_at' => 0, 'viewers' => 0, 'source' => 'api' );
	if ( ! $ids ) {
		return $out;
	}
	$res = wp_remote_get( add_query_arg( array( 'part' => 'snippet,liveStreamingDetails', 'id' => implode( ',', $ids ), 'key' => $key ), 'https://www.googleapis.com/youtube/v3/videos' ), array( 'timeout' => 8 ) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	foreach ( (array) ( json_decode( wp_remote_retrieve_body( $res ), true )['items'] ?? array() ) as $item ) {
		$live = $item['liveStreamingDetails'] ?? array();
		if ( ! empty( $live['actualStartTime'] ) && empty( $live['actualEndTime'] ) ) {
			$state = 'live';
		} elseif ( ! empty( $live['scheduledStartTime'] ) && empty( $live['actualStartTime'] ) && 'upcoming' !== $out['state'] ) {
			$state = 'upcoming';
		} else {
			continue;
		}
		$out = array(
			'state'     => $state,
			'video_id'  => (string) $item['id'],
			'title'     => html_entity_decode( (string) ( $item['snippet']['title'] ?? '' ), ENT_QUOTES ),
			'starts_at' => ! empty( $live['scheduledStartTime'] ) ? (int) strtotime( $live['scheduledStartTime'] ) : 0,
			'viewers'   => (int) ( $live['concurrentViewers'] ?? 0 ),
			'source'    => 'api',
		);
		if ( 'live' === $state ) {
			break;
		}
	}
	return $out;
}

/**
 * Read the channel's public /live page (no key or quota needed).
 *
 * @param string $channel Channel ID.
 * @return array state, video_id, title, starts_at, viewers, source.
 */
function kingdom_ytlive_check_page( $channel ) {
	// The channel's public /live page points at the current (or next scheduled) stream.
	$html = kingdom_ytlive_fetch( 'https://www.youtube.com/channel/' . rawurlencode( $channel ) . '/live' );
	$out  = array( 'state' => 'offline', 'video_id' => '', 'title' => '', 'starts_at' => 0, 'viewers' => 0, 'source' => 'page' );
	if ( '' === $html ) {
		$out['source'] = 'error';
		return $out;
	}
	if ( ! preg_match( '~<link rel="canonical" href="https://www\.youtube\.com/watch\?v=([A-Za-z0-9_-]{11})"~', $html, $m ) ) {
		// A real channel page means "not live"; anything else (a consent or "are you a robot"
		// page shown to hosting servers) means we could not tell.
		if ( false === strpos( $html, 'ytInitialData' ) && false === strpos( $html, '"externalId"' ) ) {
			$out['source'] = 'error';
		}
		return $out;
	}
	$out['video_id'] = $m[1];
	if ( preg_match( '~<meta name="title" content="([^"]*)"~', $html, $t ) ) {
		$out['title'] = html_entity_decode( $t[1], ENT_QUOTES );
	}
	if ( false !== strpos( $html, '"isLiveNow":true' ) ) {
		$out['state'] = 'live';
		// "1,234 watching now" as shown on YouTube.
		if ( preg_match( '~"originalViewCount":"(\d+)"~', $html, $v ) || preg_match( '~"viewCount":\{"runs":\[\{"text":"([\d,. ]+)"\},\{"text":" watching~', $html, $v ) ) {
			$out['viewers'] = (int) preg_replace( '/\D/', '', $v[1] );
		}
	} elseif ( false !== strpos( $html, '"isUpcoming":true' ) || false !== strpos( $html, '"upcomingEventData"' ) ) {
		$out['state'] = 'upcoming';
		if ( preg_match( '~"upcomingEventData":\{"startTime":"(\d+)"~', $html, $s ) || preg_match( '~"scheduledStartTime":"(\d+)"~', $html, $s ) ) {
			$out['starts_at'] = (int) $s[1];
		}
	} else {
		$out['video_id'] = ''; // A finished stream: treat as offline.
		$out['title']    = '';
	}
	return $out;
}

/**
 * The channel's live status, shared by all visitors.
 *
 * The last answer is kept; when it is older than the cache time, one request (or the background
 * check) refreshes it while every other visitor gets the last answer straight away.
 *
 * @param string $channel Channel ID.
 * @param bool   $force   Refresh now.
 * @return array
 */
function kingdom_ytlive_status( $channel, $force = false ) {
	$stored = get_option( 'kingdom_ytlive_status_store' );
	$valid  = is_array( $stored ) && ( $stored['channel'] ?? '' ) === $channel;
	$fresh  = $valid && time() - (int) $stored['checked_at'] < kingdom_ytlive_cache_ttl( $stored['state'] );
	if ( $fresh && ! $force ) {
		return $stored;
	}
	// One refresh at a time; the lock frees itself after 30 seconds.
	$lock = (int) get_option( 'kingdom_ytlive_lock', 0 );
	if ( $lock > time() || ( $lock && ! delete_option( 'kingdom_ytlive_lock' ) ) || ! add_option( 'kingdom_ytlive_lock', time() + 30, '', false ) ) {
		if ( $valid ) {
			return $stored;
		}
		return array( 'state' => 'offline', 'video_id' => '', 'title' => '', 'starts_at' => 0, 'viewers' => 0, 'source' => 'pending', 'channel' => $channel, 'checked_at' => 0 );
	}
	$status = array_merge( kingdom_ytlive_check( $channel ), array( 'channel' => $channel, 'checked_at' => time() ) );
	if ( 'error' === $status['source'] && $valid ) {
		$status = array_merge( $stored, array( 'checked_at' => time() ) ); // YouTube unreachable: keep the last answer.
	}
	update_option( 'kingdom_ytlive_status_store', $status, false );
	delete_option( 'kingdom_ytlive_lock' );
	// First time this broadcast is seen live: notify once (add_option succeeds for one request only).
	if ( 'live' === $status['state'] && $status['video_id'] && add_option( 'kingdom_ytlive_live_' . md5( $status['video_id'] ), time(), '', false ) ) {
		$prev = get_option( 'kingdom_ytlive_last_live' );
		if ( $prev && $prev !== $status['video_id'] ) {
			delete_option( 'kingdom_ytlive_live_' . md5( $prev ) );
		}
		update_option( 'kingdom_ytlive_last_live', $status['video_id'], false );
		do_action( 'kingdom_ytlive_went_live', $status );
	}
	return $status;
}

/**
 * Everything the YouTube live section needs, cached and shared by all visitors.
 *
 * @param bool $force Check YouTube now instead of using the cached answer.
 * @return array|null mode (channel|video), state, video_id, title, starts_at, embed, chat, watch, label, next_service, checked_at.
 */
function kingdom_youtube_live( $force = false ) {
	$channel  = kingdom_ytlive_channel_id();
	$raw      = trim( (string) get_theme_mod( 'kingdom_ytlive_url', '' ) );
	$replay   = preg_match( '/^[A-Za-z0-9_-]{11}$/', $raw ) ? $raw : kingdom_youtube_id( $raw );
	$autoplay = (bool) get_theme_mod( 'kingdom_ytlive_autoplay', false );

	if ( $channel ) {
		$status = kingdom_ytlive_status( $channel, $force );
		$video = $status['video_id'] ? $status['video_id'] : $replay;
		$out   = array(
			'mode'       => 'channel',
			'state'      => $status['state'],
			'video_id'   => $video,
			'title'      => $status['title'],
			'starts_at'  => (int) $status['starts_at'],
			'viewers'    => 'live' === $status['state'] && get_theme_mod( 'kingdom_ytlive_viewers', true ) ? (int) ( $status['viewers'] ?? 0 ) : 0,
			'source'     => $status['source'],
			'checked_at' => (int) $status['checked_at'],
			'watch'      => $status['video_id'] ? 'https://www.youtube.com/watch?v=' . $status['video_id'] : 'https://www.youtube.com/channel/' . $channel . '/live',
		);
	} elseif ( $replay ) {
		$out = array( 'mode' => 'video', 'state' => 'video', 'video_id' => $replay, 'title' => '', 'starts_at' => 0, 'viewers' => 0, 'source' => 'setting', 'checked_at' => time(), 'watch' => 'https://www.youtube.com/watch?v=' . $replay );
	} else {
		return null;
	}

	$play         = $autoplay && 'offline' !== $out['state'];
	$out['embed'] = $out['video_id'] ? 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $out['video_id'] ) . '?rel=0&modestbranding=1&playsinline=1' . ( $play ? '&autoplay=1&mute=1' : '' ) : '';
	// Nothing to show and we could not see a stream: use YouTube's own channel live player, which
	// plays the broadcast whenever the channel is live (even if YouTube hid it from our check).
	$out['channel_player'] = false;
	if ( ! $out['embed'] && 'channel' === $out['mode'] ) {
		$out['embed']          = 'https://www.youtube.com/embed/live_stream?channel=' . rawurlencode( $channel ) . '&rel=0&playsinline=1' . ( $autoplay ? '&autoplay=1&mute=1' : '' );
		$out['channel_player'] = true;
	}
	$out['chat']  = get_theme_mod( 'kingdom_ytlive_chat', false ) && in_array( $out['state'], array( 'live', 'upcoming' ), true ) && $out['video_id']
		? 'https://www.youtube.com/live_chat?v=' . rawurlencode( $out['video_id'] ) . '&embed_domain=' . rawurlencode( (string) wp_parse_url( home_url(), PHP_URL_HOST ) )
		: '';
	$next                = class_exists( 'KCS_Livestream' ) ? KCS_Livestream::next_service() : null;
	$out['next_service'] = $next ? array( 'name' => $next['name'], 'timestamp' => (int) $next['timestamp'], 'label' => wp_date( 'l g:i a', $next['timestamp'] ) ) : null;
	$out['label']        = kingdom_ytlive_label( $out );
	return $out;
}

/**
 * Words for the status badge.
 *
 * @param array $s Status.
 * @return string
 */
function kingdom_ytlive_label( $s ) {
	switch ( $s['state'] ) {
		case 'live':
			return ! empty( $s['viewers'] )
				/* translators: %s: number of people watching */
				? sprintf( _n( 'Live now · %s watching', 'Live now · %s watching', $s['viewers'], 'kingdom' ), number_format_i18n( $s['viewers'] ) )
				: __( 'Live now', 'kingdom' );
		case 'upcoming':
			return $s['starts_at'] ? kingdom_ytlive_when( $s['starts_at'] ) : __( 'Starting soon', 'kingdom' );
		case 'offline':
			// YouTube's channel player is showing: it may be live even if our check missed it.
			if ( ! empty( $s['channel_player'] ) ) {
				/* translators: 1: service name, 2: day and time */
				return ! empty( $s['next_service'] ) ? sprintf( __( 'Next: %1$s, %2$s', 'kingdom' ), $s['next_service']['name'], $s['next_service']['label'] ) : __( 'Watch our live service', 'kingdom' );
			}
			if ( ! empty( $s['next_service'] ) ) {
				/* translators: 1: service name, 2: day and time */
				return sprintf( __( 'Not live right now · Next: %1$s, %2$s', 'kingdom' ), $s['next_service']['name'], $s['next_service']['label'] );
			}
			return $s['video_id'] ? __( 'Not live right now · Watch our latest service', 'kingdom' ) : __( 'Not live right now', 'kingdom' );
	}
	return '';
}

/**
 * "Starts today at 9:00 am" / "Starts tomorrow at …" / "Starts Sunday at …".
 *
 * @param int $ts Unix time.
 * @return string
 */
function kingdom_ytlive_when( $ts ) {
	$day  = wp_date( 'Y-m-d', $ts );
	$time = wp_date( get_option( 'time_format' ), $ts );
	if ( wp_date( 'Y-m-d' ) === $day ) {
		/* translators: %s: time */
		return sprintf( __( 'Starts today at %s', 'kingdom' ), $time );
	}
	if ( wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) === $day ) {
		/* translators: %s: time */
		return sprintf( __( 'Starts tomorrow at %s', 'kingdom' ), $time );
	}
	/* translators: 1: weekday, 2: time */
	return sprintf( __( 'Starts %1$s at %2$s', 'kingdom' ), wp_date( 'l', $ts ), $time );
}

/**
 * Public status endpoint the section polls to switch to the stream as soon as it starts.
 */
function kingdom_ytlive_rest() {
	register_rest_route(
		'kingdom/v1',
		'/live',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				$s = kingdom_youtube_live();
				$r = rest_ensure_response( $s ? array_intersect_key( $s, array_flip( array( 'state', 'video_id', 'title', 'starts_at', 'viewers', 'embed', 'chat', 'watch', 'label' ) ) ) : array( 'state' => 'none' ) );
				$r->header( 'Cache-Control', 'public, max-age=30' );
				return $r;
			},
		)
	);
}
add_action( 'rest_api_init', 'kingdom_ytlive_rest' );

/**
 * Forget the cached channel lookup and status when the settings change.
 */
function kingdom_ytlive_reset() {
	delete_option( 'kingdom_ytlive_status_store' );
	delete_transient( 'kingdom_ytlive_resolve_failed' );
}
add_action( 'customize_save_after', 'kingdom_ytlive_reset' );
add_action( 'update_option_theme_mods_' . get_option( 'stylesheet' ), 'kingdom_ytlive_reset' );

/**
 * Status line for church staff under the live stream settings (Church Admin → Website).
 *
 * @param string $group Settings group.
 */
function kingdom_ytlive_admin_status( $group ) {
	if ( 'media' !== $group ) {
		return;
	}
	$force = ! empty( $_GET['ytlive_check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$raw   = trim( (string) get_theme_mod( 'kingdom_ytlive_channel', '' ) );
	$s     = kingdom_youtube_live( $force );
	if ( $raw && ! kingdom_ytlive_channel_id() ) {
		$msg  = __( 'We could not find that YouTube channel. Paste the link from your channel page, for example https://www.youtube.com/@yourchurch.', 'kingdom' );
		$type = 'error';
	} elseif ( $s && 'channel' === $s['mode'] ) {
		/* translators: 1: status, 2: time */
		$msg  = sprintf( __( 'Channel connected · %1$s (checked %2$s)', 'kingdom' ), $s['label'], wp_date( get_option( 'time_format' ), $s['checked_at'] ) );
		$type = 'error' === $s['source'] ? 'error' : 'success';
		if ( 'error' === $s['source'] ) {
			$msg = __( 'Channel connected, but YouTube did not answer just now. We will keep trying.', 'kingdom' );
		}
	} else {
		$msg  = __( 'Showing one video. Add your channel to switch to your live stream automatically.', 'kingdom' );
		$type = 'info';
	}
	$times = add_query_arg( array( 'view' => 'settings', 'tab' => 'general' ), remove_query_arg( array( 'ytlive_check', 'view', 'tab', 'group' ) ) ) . '#service-times';
	echo '<p class="kcs-alert kcs-alert--' . esc_attr( $type ) . '">' . esc_html( $msg ) . ' <a href="' . esc_url( add_query_arg( 'ytlive_check', 1 ) ) . '">' . esc_html__( 'Check now', 'kingdom' ) . '</a>'
		. ( current_user_can( 'manage_options' ) ? ' · <a href="' . esc_url( $times ) . '">' . esc_html__( 'Change service times', 'kingdom' ) . '</a>' : '' ) . '</p>';
}
add_action( 'kcs_website_group_intro', 'kingdom_ytlive_admin_status' );

/**
 * Check the channel every few minutes in the background, so app users get the "we're live"
 * notification even when nobody has the website open.
 */
function kingdom_ytlive_cron_schedules( $schedules ) {
	$schedules['kingdom_five_minutes'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every 5 minutes', 'kingdom' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'kingdom_ytlive_cron_schedules' );

/**
 * Keep the background check scheduled only while a channel is set.
 */
function kingdom_ytlive_schedule() {
	// Only the raw setting is checked here (resolving an @handle would mean a web request on every page).
	$set  = '' !== trim( (string) get_theme_mod( 'kingdom_ytlive_channel', '' ) ) || ( function_exists( 'kcs_get_option' ) && '' !== trim( (string) kcs_get_option( 'yt_channel_id' ) ) );
	$next = wp_next_scheduled( 'kingdom_ytlive_poll' );
	if ( $set && ! $next ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'kingdom_five_minutes', 'kingdom_ytlive_poll' );
	} elseif ( ! $set && $next ) {
		wp_clear_scheduled_hook( 'kingdom_ytlive_poll' );
	}
}
add_action( 'init', 'kingdom_ytlive_schedule' );
add_action( 'kingdom_ytlive_poll', 'kingdom_youtube_live' );
