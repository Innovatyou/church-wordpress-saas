<?php
// YouTube live tests (wp eval-file on a church site with the Ministry OS theme). YouTube replies are mocked.
$ok = function ( $c, $m ) { echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; };
$UC = 'UCabcdefghijklmnopqrstuv';
$GLOBALS['yt_mode'] = 'live';
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( $UC ) {
	$m = $GLOBALS['yt_mode'];
	$r = function ( $body, $code = 200 ) { return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => $code, 'message' => 'OK' ), 'cookies' => array() ); };
	if ( false !== strpos( $url, 'youtube.com/@theroadmaptojesusinternati7638' ) ) {
		return $r( '<html><link rel="canonical" href="https://www.youtube.com/channel/' . $UC . '">ytInitialData</html>' );
	}
	if ( false !== strpos( $url, '/channel/' . $UC . '/live' ) ) {
		if ( 'live' === $m ) { return $r( '<link rel="canonical" href="https://www.youtube.com/watch?v=LIVEVID0001"><meta name="title" content="Sunday Service">"isLiveNow":true ytInitialData' ); }
		if ( 'offline' === $m ) { return $r( '<link rel="canonical" href="https://www.youtube.com/channel/' . $UC . '">ytInitialData' ); }
		return $r( '<html>Sign in to confirm you are not a bot</html>' ); // blocked
	}
	if ( false !== strpos( $url, 'youtube.com/oembed' ) ) {
		if ( 'blocked' === $m && ! empty( $GLOBALS['yt_oembed_live'] ) ) { return $r( wp_json_encode( array( 'title' => 'Sunday Live', 'thumbnail_url' => 'https://i.ytimg.com/vi/OEMBEDLIVE1/hqdefault_live.jpg', 'html' => '<iframe src="https://www.youtube.com/embed/OEMBEDLIVE1?feature=oembed"></iframe>' ) ) ); }
		return $r( 'Bad Request', 400 );
	}
	if ( false !== strpos( $url, 'playlistItems' ) ) { return $r( wp_json_encode( array( 'items' => array( array( 'contentDetails' => array( 'videoId' => 'OLDVID00001' ) ), array( 'contentDetails' => array( 'videoId' => 'APILIVE0001' ) ) ) ) ) ); }
	if ( false !== strpos( $url, 'videos?' ) || false !== strpos( $url, '/videos&' ) || false !== strpos( $url, 'youtube/v3/videos' ) ) {
		return $r( wp_json_encode( array( 'items' => array( array( 'id' => 'OLDVID00001', 'snippet' => array( 'title' => 'Old' ), 'liveStreamingDetails' => array( 'actualStartTime' => '2026-10-01T09:00:00Z', 'actualEndTime' => '2026-10-01T11:00:00Z' ) ), array( 'id' => 'APILIVE0001', 'snippet' => array( 'title' => 'Live via API' ), 'liveStreamingDetails' => array( 'actualStartTime' => '2026-10-18T09:00:00Z' ) ) ) ) ) );
	}
	return $pre;
}, 10, 3 );
$reset = function () { delete_option( 'kingdom_ytlive_status_store' ); delete_option( 'kingdom_ytlive_resolved' ); delete_transient( 'kingdom_ytlive_resolve_failed' ); delete_transient( 'kcs_live_status' ); delete_option( 'kingdom_ytlive_lock' ); };
// Church pasted its @handle link (with ?si=) in Church Suite's channel field; no theme setting, no API key.
$o = get_option( 'kcs_settings' ); $o['yt_channel_id'] = 'https://youtube.com/@theroadmaptojesusinternati7638?si=8svCc17VdMXwTt03'; $o['yt_api_key'] = ''; $o['yt_only_service_times'] = '1'; $o['service_schedule'] = "Sunday|09:00|Sunday Service"; update_option( 'kcs_settings', $o );
remove_theme_mod( 'kingdom_ytlive_channel' );
$reset();
$s = KCS_Livestream::status( true );
$ok( $s['is_live'] && 'LIVEVID0001' === $s['video_id'], 'handle link, no API key, outside service times → LIVE (' . $s['source'] . ', ' . $s['video_id'] . ')' );
$ok( $UC === $s['channel_id'], 'channel ID worked out from the @handle' );
$ok( false !== strpos( KCS_Livestream::render(), 'embed/LIVEVID0001' ), 'Watch Live player plays the live video' );
$ok( false !== strpos( do_shortcode( '[kcs_live_badge]' ), 'LIVE NOW' ), 'badge says LIVE NOW' );
$ok( 'live' === ( kingdom_youtube_live()['state'] ?? '' ), 'theme section agrees' );
$GLOBALS['yt_mode'] = 'offline'; $reset();
$s = KCS_Livestream::status( true );
$ok( ! $s['is_live'], 'channel offline → not live' );
// YouTube blocks the page from the server: last answer kept; with API key, uploads are used.
$GLOBALS['yt_mode'] = 'live'; $reset(); KCS_Livestream::status( true );
$GLOBALS['yt_mode'] = 'blocked'; delete_transient( 'kcs_live_status' );
$s = KCS_Livestream::status( true );
$ok( $s['is_live'], 'page blocked, no key → keeps last answer (live), not a false "offline"' );
$o['yt_api_key'] = 'TESTKEY'; update_option( 'kcs_settings', $o ); $reset();
$s = KCS_Livestream::status( true );
$ok( $s['is_live'] && 'APILIVE0001' === $s['video_id'], 'page blocked + API key → live found in latest uploads (' . $s['video_id'] . ')' );
$o['yt_api_key'] = ''; update_option( 'kcs_settings', $o ); $reset();
// YouTube hides the /live page from the server, but oEmbed says the channel is on air → live.
$GLOBALS['yt_mode'] = 'blocked'; $GLOBALS['yt_oembed_live'] = true; $reset();
$s = KCS_Livestream::status( true );
$ok( $s['is_live'] && 'OEMBEDLIVE1' === $s['video_id'], 'page hidden from server, oEmbed live → LIVE (' . $s['video_id'] . ')' );
// Nothing detected and no replay video: the homepage section uses YouTube's own channel live player.
$GLOBALS['yt_oembed_live'] = false; $reset(); remove_theme_mod( 'kingdom_ytlive_url' );
$l = kingdom_youtube_live( true );
$ok( false !== strpos( $l['embed'], 'embed/live_stream?channel=' . $UC ), 'nothing detected → section embeds the channel live player' );
$ok( false === strpos( $l['label'], 'Not live' ), 'label does not claim "not live" over the channel player (' . $l['label'] . ')' );
// Offline with a replay video: the replay is shown instead.
set_theme_mod( 'kingdom_ytlive_url', 'https://www.youtube.com/watch?v=REPLAYVID01' ); $reset();
$l = kingdom_youtube_live( true );
$ok( false !== strpos( $l['embed'], 'REPLAYVID01' ), 'offline with a replay video → replay shown' );
remove_theme_mod( 'kingdom_ytlive_url' ); $reset();
