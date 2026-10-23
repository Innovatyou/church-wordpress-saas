<?php
/**
 * Road Map to Jesus follow-up: "Give" on the Charity button, no service question on the visit
 * form, and only one Charity page (an earlier version could create two at the same moment).
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

return array(
	'match'          => 'road map to jesus',
	'mods'           => array(
		'kingdom_vp_ask_service' => false,
	),
	'replace'        => array(
		'kingdom_charity_btn_text' => array( 'Join Another Ministry', 'Give' ),
	),
	'dedupe_charity' => true,
);
