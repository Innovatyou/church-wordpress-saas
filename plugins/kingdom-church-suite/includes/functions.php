<?php
/**
 * Helper functions.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Full table name for a plugin table.
 *
 * @param string $name Short name, e.g. "partners".
 * @return string
 */
function kcs_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'kcs_' . $name;
}

/**
 * Read a plugin setting.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function kcs_get_option( $key, $default = '' ) {
	$settings = get_option( 'kcs_settings', array() );
	if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
		return $settings[ $key ];
	}
	if ( '' === $default && class_exists( 'KCS_Settings' ) ) {
		$defaults = KCS_Settings::defaults();
		if ( isset( $defaults[ $key ] ) ) {
			return $defaults[ $key ];
		}
	}
	return $default;
}

/**
 * Update a single plugin setting.
 *
 * @param string $key   Setting key.
 * @param mixed  $value Value.
 */
function kcs_update_option( $key, $value ) {
	$settings         = get_option( 'kcs_settings', array() );
	$settings[ $key ] = $value;
	update_option( 'kcs_settings', $settings );
}

/**
 * Supported currencies (code => label).
 *
 * @return array
 */
function kcs_currencies() {
	return apply_filters(
		'kcs_currencies',
		array(
			'NGN' => 'Nigerian Naira (₦)',
			'USD' => 'US Dollar ($)',
			'GBP' => 'British Pound (£)',
			'EUR' => 'Euro (€)',
			'GHS' => 'Ghanaian Cedi (GH₵)',
			'KES' => 'Kenyan Shilling (KSh)',
			'ZAR' => 'South African Rand (R)',
			'UGX' => 'Ugandan Shilling (USh)',
			'TZS' => 'Tanzanian Shilling (TSh)',
			'RWF' => 'Rwandan Franc (FRw)',
			'XOF' => 'West African CFA (CFA)',
			'XAF' => 'Central African CFA (FCFA)',
			'CAD' => 'Canadian Dollar (C$)',
			'AUD' => 'Australian Dollar (A$)',
			'INR' => 'Indian Rupee (₹)',
		)
	);
}

/**
 * Currency symbols.
 *
 * @param string $code Currency code.
 * @return string
 */
function kcs_currency_symbol( $code ) {
	$symbols = array(
		'NGN' => '₦',
		'USD' => '$',
		'GBP' => '£',
		'EUR' => '€',
		'GHS' => 'GH₵',
		'KES' => 'KSh ',
		'ZAR' => 'R',
		'UGX' => 'USh ',
		'TZS' => 'TSh ',
		'RWF' => 'FRw ',
		'XOF' => 'CFA ',
		'XAF' => 'FCFA ',
		'CAD' => 'C$',
		'AUD' => 'A$',
		'INR' => '₹',
	);
	return isset( $symbols[ $code ] ) ? $symbols[ $code ] : $code . ' ';
}

/**
 * Format an amount of money.
 *
 * @param float  $amount   Amount.
 * @param string $currency Currency code.
 * @return string
 */
function kcs_format_money( $amount, $currency = '' ) {
	$currency = $currency ? $currency : kcs_get_option( 'currency', 'USD' );
	return kcs_currency_symbol( $currency ) . number_format_i18n( (float) $amount, 2 );
}

/**
 * Partnership frequencies.
 *
 * @return array
 */
function kcs_frequencies() {
	return array(
		'one_time'  => __( 'One-time', 'kingdom-church-suite' ),
		'weekly'    => __( 'Weekly', 'kingdom-church-suite' ),
		'monthly'   => __( 'Monthly', 'kingdom-church-suite' ),
		'quarterly' => __( 'Quarterly', 'kingdom-church-suite' ),
		'yearly'    => __( 'Yearly', 'kingdom-church-suite' ),
	);
}

/**
 * Compute the next due date for a frequency.
 *
 * @param string      $frequency Frequency key.
 * @param string|null $from      Start date (Y-m-d). Defaults to today.
 * @return string|null
 */
function kcs_next_due_date( $frequency, $from = null ) {
	$intervals = array(
		'weekly'    => '+1 week',
		'monthly'   => '+1 month',
		'quarterly' => '+3 months',
		'yearly'    => '+1 year',
	);
	if ( ! isset( $intervals[ $frequency ] ) ) {
		return null;
	}
	$base = $from ? strtotime( $from ) : current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
	if ( 'weekly' === $frequency ) {
		return gmdate( 'Y-m-d', strtotime( '+1 week', $base ) );
	}
	// Month-based: keep the day, but never spill into the next month (31 Jan → 28/29 Feb).
	$months = array( 'monthly' => 1, 'quarterly' => 3, 'yearly' => 12 );
	$y      = (int) gmdate( 'Y', $base );
	$m      = (int) gmdate( 'n', $base ) + $months[ $frequency ];
	$y     += (int) floor( ( $m - 1 ) / 12 );
	$m      = ( ( $m - 1 ) % 12 ) + 1;
	$d      = min( (int) gmdate( 'j', $base ), (int) gmdate( 't', gmmktime( 0, 0, 0, $m, 1, $y ) ) );
	return sprintf( '%04d-%02d-%02d', $y, $m, $d );
}

/**
 * Currencies that have no minor unit (amounts are whole numbers).
 *
 * @return string[]
 */
function kcs_zero_decimal_currencies() {
	return array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );
}

/**
 * Giving purposes.
 *
 * @return array
 */
function kcs_giving_purposes() {
	$raw  = kcs_get_option( 'giving_purposes', "Tithe\nOffering\nThanksgiving\nFirst Fruit\nSeed\nPartnership\nBuilding Project\nMissions\nWelfare" );
	$list = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
	$out  = array();
	foreach ( $list as $item ) {
		$out[ sanitize_title( $item ) ] = $item;
	}
	return apply_filters( 'kcs_giving_purposes', $out );
}

/**
 * Giving types shown on the Tithe & Offering page.
 *
 * @return array slug => label.
 */
function kcs_tithe_types() {
	$raw = kcs_get_option( 'tithe_types', "Tithe\nOffering\nThanksgiving\nFirst Fruit\nSeed" );
	$out = array();
	foreach ( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) as $item ) {
		$out[ sanitize_title( $item ) ] = $item;
	}
	return apply_filters( 'kcs_tithe_types', $out );
}

/**
 * Every purpose a payment may be recorded against.
 *
 * @return array slug => label.
 */
function kcs_all_purposes() {
	return array_merge(
		array(
			'donation'    => __( 'Donation', 'kingdom-church-suite' ),
			'partnership' => __( 'Partnership', 'kingdom-church-suite' ),
		),
		kcs_giving_purposes(),
		kcs_tithe_types()
	);
}

/**
 * Local currency, or '' when the church only uses its international currency.
 *
 * @return string
 */
function kcs_local_currency() {
	$intl  = kcs_get_option( 'currency', 'USD' );
	$local = strtoupper( (string) kcs_get_option( 'local_currency', 'NGN' ) );
	return ( $local !== $intl && array_key_exists( $local, kcs_currencies() ) ) ? $local : '';
}

/**
 * Partner/giver regions. International (the default currency) always comes first.
 *
 * @return array region => array( label, currency ).
 */
function kcs_regions() {
	$out = array(
		'international' => array(
			'label'    => kcs_get_option( 'international_label', __( 'International', 'kingdom-church-suite' ) ),
			'currency' => kcs_get_option( 'currency', 'USD' ),
		),
	);
	$local = kcs_local_currency();
	if ( $local ) {
		$out['local'] = array(
			'label'    => kcs_get_option( 'local_label', __( 'Local', 'kingdom-church-suite' ) ),
			'currency' => $local,
		);
	}
	return $out;
}

/**
 * Clean a region value, falling back to international.
 *
 * @param string $region Region.
 * @return string
 */
function kcs_sanitize_region( $region ) {
	return array_key_exists( (string) $region, kcs_regions() ) ? (string) $region : 'international';
}

/**
 * Currency for a region.
 *
 * @param string $region Region.
 * @return string
 */
function kcs_region_currency( $region ) {
	$regions = kcs_regions();
	return $regions[ kcs_sanitize_region( $region ) ]['currency'];
}

/**
 * Region a currency belongs to.
 *
 * @param string $currency Currency.
 * @return string
 */
function kcs_currency_region( $currency ) {
	$local = kcs_local_currency();
	return $local && strtoupper( (string) $currency ) === $local ? 'local' : 'international';
}

/**
 * Quick-pick amounts for a region.
 *
 * @param string $region Region.
 * @return float[]
 */
function kcs_region_amounts( $region ) {
	$raw = 'local' === $region ? kcs_get_option( 'amounts_local', '5000,10000,20000,50000,100000,250000' ) : kcs_get_option( 'amounts_international', '10,25,50,100,250,500' );
	return array_values( array_filter( array_map( 'floatval', explode( ',', (string) $raw ) ) ) );
}

/**
 * International / Local switch. With $name it is a real form field; otherwise it only changes the view.
 *
 * @param string $selected Selected region.
 * @param string $name     Field name ('' for a view-only switch).
 * @return string
 */
function kcs_region_toggle( $selected = 'international', $name = '' ) {
	$regions = kcs_regions();
	if ( count( $regions ) < 2 ) {
		return '';
	}
	$group = $name ? $name : 'kcs_region_view_' . wp_rand( 1000, 99999 );
	$html  = '<div class="kcs-region" role="radiogroup" aria-label="' . esc_attr__( 'Where are you giving from?', 'kingdom-church-suite' ) . '">';
	foreach ( $regions as $key => $r ) {
		$icon  = 'local' === $key ? '📍' : '🌍';
		$html .= '<label class="kcs-region__opt"><input type="radio" data-kcs-region data-currency="' . esc_attr( $r['currency'] ) . '" name="' . esc_attr( $group ) . '" value="' . esc_attr( $key ) . '"' . checked( $selected, $key, false ) . '><span>' . $icon . ' ' . esc_html( $r['label'] ) . ' <b>' . esc_html( $r['currency'] ) . '</b></span></label>';
	}
	return $html . '</div>';
}

/**
 * Unique payment reference.
 *
 * @param string $prefix Prefix.
 * @return string
 */
function kcs_generate_reference( $prefix = 'KCS' ) {
	return strtoupper( $prefix ) . '-' . gmdate( 'ymd' ) . '-' . strtoupper( wp_generate_password( 10, false, false ) );
}

/**
 * Get URL of a page configured in settings.
 *
 * @param string $key Page setting key (partner_login, partner_register, partner_dashboard, give, thank_you, partnership).
 * @return string
 */
function kcs_page_url( $key ) {
	$page_id = (int) kcs_get_option( 'page_' . $key, 0 );
	if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
		return get_permalink( $page_id );
	}
	return home_url( '/' );
}

/**
 * Client IP address (best effort, used only for rate limiting).
 *
 * @return string
 */
function kcs_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	// Behind Cloudflare or a load balancer every visitor arrives from the proxy's address. Only when
	// the site owner names the header the proxy sets (in wp-config.php, e.g.
	// define( 'KCS_TRUSTED_PROXY_HEADER', 'HTTP_CF_CONNECTING_IP' );) is that header trusted.
	if ( defined( 'KCS_TRUSTED_PROXY_HEADER' ) && ! empty( $_SERVER[ KCS_TRUSTED_PROXY_HEADER ] ) ) {
		$first = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ KCS_TRUSTED_PROXY_HEADER ] ) ) )[0] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
			$ip = $first;
		}
	}
	$ip = (string) apply_filters( 'kcs_client_ip', $ip );
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Simple transient-based rate limiter.
 *
 * @param string $action Action name.
 * @param int    $limit  Allowed hits.
 * @param int    $window Window in seconds.
 * @return bool True when allowed, false when limited.
 */
function kcs_rate_limit( $action, $limit = 10, $window = 600 ) {
	$key  = 'kcs_rl_' . md5( $action . '|' . kcs_client_ip() );
	$hits = (int) get_transient( $key );
	if ( $hits >= $limit ) {
		return false;
	}
	set_transient( $key, $hits + 1, $window );
	return true;
}

/**
 * Read a value from a nested array using dot notation (e.g. "data.link").
 *
 * @param array  $data Array.
 * @param string $path Dot path.
 * @return mixed|null
 */
function kcs_array_get( $data, $path ) {
	if ( '' === $path || null === $path ) {
		return null;
	}
	foreach ( explode( '.', $path ) as $segment ) {
		if ( is_array( $data ) && array_key_exists( $segment, $data ) ) {
			$data = $data[ $segment ];
		} else {
			return null;
		}
	}
	return $data;
}

/**
 * Is the current user a partner?
 *
 * @param int $user_id Optional user ID.
 * @return bool
 */
function kcs_is_partner( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	return $user_id && (bool) KCS_Partners::get_by_user( $user_id );
}

/**
 * Load a plugin template, allowing themes to override it in /kingdom-church-suite/.
 *
 * @param string $name Template file name (without .php).
 * @param array  $vars Variables to extract.
 * @return string Rendered HTML.
 */
function kcs_get_template( $name, $vars = array() ) {
	$theme_file = locate_template( 'kingdom-church-suite/' . $name . '.php' );
	$file       = $theme_file ? $theme_file : KCS_PATH . 'templates/' . $name . '.php';
	if ( ! file_exists( $file ) ) {
		return '';
	}
	ob_start();
	// Include from a clean scope so template variables such as $name are not shadowed by this function's own.
	( static function ( $kcs_file, $kcs_vars ) {
		extract( $kcs_vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $kcs_file;
	} )( $file, $vars );
	return ob_get_clean();
}

/**
 * Status badge HTML.
 *
 * @param string $status Status.
 * @return string
 */
function kcs_status_badge( $status ) {
	return '<span class="kcs-badge kcs-badge--' . esc_attr( $status ) . '">' . esc_html( ucwords( str_replace( '_', ' ', $status ) ) ) . '</span>';
}

/**
 * Benefit lines of a plan to show, or none when the church hides plan benefits.
 *
 * @param object $plan Plan.
 * @return string[]
 */
function kcs_plan_benefits( $plan ) {
	if ( ! $plan || '1' !== (string) kcs_get_option( 'show_plan_benefits', '1' ) ) {
		return array();
	}
	return array_values( array_filter( array_map( 'trim', explode( "\n", (string) $plan->benefits ) ) ) );
}

/**
 * Countries (ISO code => English name), for country lists on forms.
 *
 * @return array
 */
function kcs_countries() {
	return (array) apply_filters(
		'kcs_countries',
		array(
			'AF' => 'Afghanistan',
			'AL' => 'Albania',
			'DZ' => 'Algeria',
			'AS' => 'American Samoa',
			'AD' => 'Andorra',
			'AO' => 'Angola',
			'AI' => 'Anguilla',
			'AQ' => 'Antarctica',
			'AG' => 'Antigua & Barbuda',
			'AR' => 'Argentina',
			'AM' => 'Armenia',
			'AW' => 'Aruba',
			'AU' => 'Australia',
			'AT' => 'Austria',
			'AZ' => 'Azerbaijan',
			'BS' => 'Bahamas',
			'BH' => 'Bahrain',
			'BD' => 'Bangladesh',
			'BB' => 'Barbados',
			'BY' => 'Belarus',
			'BE' => 'Belgium',
			'BZ' => 'Belize',
			'BJ' => 'Benin',
			'BM' => 'Bermuda',
			'BT' => 'Bhutan',
			'BO' => 'Bolivia',
			'BA' => 'Bosnia & Herzegovina',
			'BW' => 'Botswana',
			'BV' => 'Bouvet Island',
			'BR' => 'Brazil',
			'IO' => 'British Indian Ocean Territory',
			'BN' => 'Brunei',
			'BG' => 'Bulgaria',
			'BF' => 'Burkina Faso',
			'BI' => 'Burundi',
			'KH' => 'Cambodia',
			'CM' => 'Cameroon',
			'CA' => 'Canada',
			'CV' => 'Cape Verde',
			'BQ' => 'Caribbean NL',
			'KY' => 'Cayman Islands',
			'CF' => 'Central African Rep.',
			'TD' => 'Chad',
			'CL' => 'Chile',
			'CN' => 'China',
			'CX' => 'Christmas Island',
			'CC' => 'Cocos (Keeling) Islands',
			'CO' => 'Colombia',
			'KM' => 'Comoros',
			'CG' => 'Congo',
			'CD' => 'Congo (DR)',
			'CK' => 'Cook Islands',
			'CR' => 'Costa Rica',
			'HR' => 'Croatia',
			'CU' => 'Cuba',
			'CW' => 'Curaçao',
			'CY' => 'Cyprus',
			'CZ' => 'Czech Republic',
			'CI' => 'Côte d\'Ivoire',
			'DK' => 'Denmark',
			'DJ' => 'Djibouti',
			'DM' => 'Dominica',
			'DO' => 'Dominican Republic',
			'TL' => 'East Timor',
			'EC' => 'Ecuador',
			'EG' => 'Egypt',
			'SV' => 'El Salvador',
			'GQ' => 'Equatorial Guinea',
			'ER' => 'Eritrea',
			'EE' => 'Estonia',
			'SZ' => 'Eswatini (Swaziland)',
			'ET' => 'Ethiopia',
			'FK' => 'Falkland Islands',
			'FO' => 'Faroe Islands',
			'FJ' => 'Fiji',
			'FI' => 'Finland',
			'FR' => 'France',
			'GF' => 'French Guiana',
			'PF' => 'French Polynesia',
			'TF' => 'French S. Terr.',
			'GA' => 'Gabon',
			'GM' => 'Gambia',
			'GE' => 'Georgia',
			'DE' => 'Germany',
			'GH' => 'Ghana',
			'GI' => 'Gibraltar',
			'GR' => 'Greece',
			'GL' => 'Greenland',
			'GD' => 'Grenada',
			'GP' => 'Guadeloupe',
			'GU' => 'Guam',
			'GT' => 'Guatemala',
			'GG' => 'Guernsey',
			'GN' => 'Guinea',
			'GW' => 'Guinea-Bissau',
			'GY' => 'Guyana',
			'HT' => 'Haiti',
			'HM' => 'Heard Island & McDonald Islands',
			'HN' => 'Honduras',
			'HK' => 'Hong Kong',
			'HU' => 'Hungary',
			'IS' => 'Iceland',
			'IN' => 'India',
			'ID' => 'Indonesia',
			'IR' => 'Iran',
			'IQ' => 'Iraq',
			'IE' => 'Ireland',
			'IM' => 'Isle of Man',
			'IL' => 'Israel',
			'IT' => 'Italy',
			'JM' => 'Jamaica',
			'JP' => 'Japan',
			'JE' => 'Jersey',
			'JO' => 'Jordan',
			'KZ' => 'Kazakhstan',
			'KE' => 'Kenya',
			'KI' => 'Kiribati',
			'KW' => 'Kuwait',
			'KG' => 'Kyrgyzstan',
			'LA' => 'Laos',
			'LV' => 'Latvia',
			'LB' => 'Lebanon',
			'LS' => 'Lesotho',
			'LR' => 'Liberia',
			'LY' => 'Libya',
			'LI' => 'Liechtenstein',
			'LT' => 'Lithuania',
			'LU' => 'Luxembourg',
			'MO' => 'Macau',
			'MG' => 'Madagascar',
			'MW' => 'Malawi',
			'MY' => 'Malaysia',
			'MV' => 'Maldives',
			'ML' => 'Mali',
			'MT' => 'Malta',
			'MH' => 'Marshall Islands',
			'MQ' => 'Martinique',
			'MR' => 'Mauritania',
			'MU' => 'Mauritius',
			'YT' => 'Mayotte',
			'MX' => 'Mexico',
			'FM' => 'Micronesia',
			'MD' => 'Moldova',
			'MC' => 'Monaco',
			'MN' => 'Mongolia',
			'ME' => 'Montenegro',
			'MS' => 'Montserrat',
			'MA' => 'Morocco',
			'MZ' => 'Mozambique',
			'MM' => 'Myanmar (Burma)',
			'NA' => 'Namibia',
			'NR' => 'Nauru',
			'NP' => 'Nepal',
			'NL' => 'Netherlands',
			'NC' => 'New Caledonia',
			'NZ' => 'New Zealand',
			'NI' => 'Nicaragua',
			'NE' => 'Niger',
			'NG' => 'Nigeria',
			'NU' => 'Niue',
			'NF' => 'Norfolk Island',
			'KP' => 'North Korea',
			'MK' => 'North Macedonia',
			'MP' => 'Northern Mariana Islands',
			'NO' => 'Norway',
			'OM' => 'Oman',
			'PK' => 'Pakistan',
			'PW' => 'Palau',
			'PS' => 'Palestine',
			'PA' => 'Panama',
			'PG' => 'Papua New Guinea',
			'PY' => 'Paraguay',
			'PE' => 'Peru',
			'PH' => 'Philippines',
			'PN' => 'Pitcairn',
			'PL' => 'Poland',
			'PT' => 'Portugal',
			'PR' => 'Puerto Rico',
			'QA' => 'Qatar',
			'RO' => 'Romania',
			'RU' => 'Russia',
			'RW' => 'Rwanda',
			'RE' => 'Réunion',
			'BL' => 'St Barthelemy',
			'SH' => 'St Helena',
			'KN' => 'St Kitts & Nevis',
			'LC' => 'St Lucia',
			'SX' => 'St Maarten (Dutch)',
			'MF' => 'St Martin (French)',
			'PM' => 'St Pierre & Miquelon',
			'VC' => 'St Vincent',
			'WS' => 'Samoa',
			'SM' => 'San Marino',
			'ST' => 'Sao Tome & Principe',
			'SA' => 'Saudi Arabia',
			'SN' => 'Senegal',
			'RS' => 'Serbia',
			'SC' => 'Seychelles',
			'SL' => 'Sierra Leone',
			'SG' => 'Singapore',
			'SK' => 'Slovakia',
			'SI' => 'Slovenia',
			'SB' => 'Solomon Islands',
			'SO' => 'Somalia',
			'ZA' => 'South Africa',
			'GS' => 'South Georgia & the South Sandwich Islands',
			'KR' => 'South Korea',
			'SS' => 'South Sudan',
			'ES' => 'Spain',
			'LK' => 'Sri Lanka',
			'SD' => 'Sudan',
			'SR' => 'Suriname',
			'SJ' => 'Svalbard & Jan Mayen',
			'SE' => 'Sweden',
			'CH' => 'Switzerland',
			'SY' => 'Syria',
			'TW' => 'Taiwan',
			'TJ' => 'Tajikistan',
			'TZ' => 'Tanzania',
			'TH' => 'Thailand',
			'TG' => 'Togo',
			'TK' => 'Tokelau',
			'TO' => 'Tonga',
			'TT' => 'Trinidad & Tobago',
			'TN' => 'Tunisia',
			'TR' => 'Turkey',
			'TM' => 'Turkmenistan',
			'TC' => 'Turks & Caicos Islands',
			'TV' => 'Tuvalu',
			'UM' => 'US minor outlying islands',
			'UG' => 'Uganda',
			'UA' => 'Ukraine',
			'AE' => 'United Arab Emirates',
			'GB' => 'United Kingdom',
			'US' => 'United States',
			'UY' => 'Uruguay',
			'UZ' => 'Uzbekistan',
			'VU' => 'Vanuatu',
			'VA' => 'Vatican City',
			'VE' => 'Venezuela',
			'VN' => 'Vietnam',
			'VG' => 'Virgin Islands (UK)',
			'VI' => 'Virgin Islands (US)',
			'WF' => 'Wallis & Futuna',
			'EH' => 'Western Sahara',
			'YE' => 'Yemen',
			'ZM' => 'Zambia',
			'ZW' => 'Zimbabwe',
			'AX' => 'Åland Islands',
		)
	);
}

/**
 * A <select> of countries.
 *
 * @param string $name     Field name.
 * @param string $id       Field id.
 * @param bool   $required Required.
 * @param string $selected Country name to select.
 * @return string
 */
function kcs_country_select( $name, $id, $required = false, $selected = '' ) {
	$h = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" autocomplete="country-name"' . ( $required ? ' required' : '' ) . '><option value="">' . esc_html__( 'Choose a country…', 'kingdom-church-suite' ) . '</option>';
	foreach ( kcs_countries() as $country ) {
		$h .= '<option' . selected( $selected, $country, false ) . '>' . esc_html( $country ) . '</option>';
	}
	return $h . '</select>';
}

/**
 * Rows per page chosen by the visitor (?per=), limited to sensible sizes.
 *
 * @param int    $default Default.
 * @param string $param   Query arg.
 * @return int
 */
function kcs_per_page( $default = 20, $param = 'per' ) {
	$per = isset( $_GET[ $param ] ) ? absint( $_GET[ $param ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return in_array( $per, array( 10, 20, 50, 100 ), true ) ? $per : $default;
}

/**
 * Current page number from the query string.
 *
 * @param string $param Query arg.
 * @return int
 */
function kcs_current_page( $param = 'pg' ) {
	return max( 1, isset( $_GET[ $param ] ) ? absint( $_GET[ $param ] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

/**
 * Page links: "Showing 21–40 of 132", Previous / numbers / Next, and a rows-per-page choice.
 *
 * @param int    $total    Total rows.
 * @param int    $per      Rows per page.
 * @param int    $page     Current page.
 * @param string $base     Page URL.
 * @param array  $args     Query args to keep (filters).
 * @param array  $opts     param (page arg), per_param (false to hide the choice), fragment.
 * @return string
 */
function kcs_pager( $total, $per, $page, $base, $args = array(), $opts = array() ) {
	$opts  = wp_parse_args( $opts, array( 'param' => 'pg', 'per_param' => 'per', 'fragment' => '' ) );
	$total = (int) $total;
	$per   = max( 1, (int) $per );
	$pages = (int) ceil( $total / $per );
	if ( $total <= 10 ) {
		return '';
	}
	$page = min( max( 1, (int) $page ), max( 1, $pages ) );
	$args = array_filter( (array) $args, 'strlen' );
	if ( $opts['per_param'] && 20 !== $per ) {
		$args[ $opts['per_param'] ] = $per;
	}
	$link = function ( $n ) use ( $base, $args, $opts ) {
		return esc_url( add_query_arg( array_merge( $args, array( $opts['param'] => $n ) ), $base ) . ( $opts['fragment'] ? '#' . $opts['fragment'] : '' ) );
	};
	$out  = '<nav class="kcs-pager" aria-label="' . esc_attr__( 'Pages', 'kingdom-church-suite' ) . '">';
	$out .= '<span class="kcs-pager__info">' . esc_html(
		sprintf(
			/* translators: 1: first row, 2: last row, 3: total */
			__( 'Showing %1$s–%2$s of %3$s', 'kingdom-church-suite' ),
			number_format_i18n( ( $page - 1 ) * $per + 1 ),
			number_format_i18n( min( $total, $page * $per ) ),
			number_format_i18n( $total )
		)
	) . '</span>';
	if ( $pages > 1 ) {
		$out .= '<span class="kcs-pager__links">';
		$out .= $page > 1 ? '<a class="kcs-pager__step" rel="prev" href="' . $link( $page - 1 ) . '">‹ <span>' . esc_html__( 'Previous', 'kingdom-church-suite' ) . '</span></a>' : '<span class="kcs-pager__step is-off" aria-hidden="true">‹ <span>' . esc_html__( 'Previous', 'kingdom-church-suite' ) . '</span></span>';
		$shown = array_unique( array_filter( array( 1, 2, $page - 1, $page, $page + 1, $pages - 1, $pages ), function ( $n ) use ( $pages ) { return $n >= 1 && $n <= $pages; } ) );
		sort( $shown );
		$prev = 0;
		foreach ( $shown as $n ) {
			if ( $n - $prev > 1 ) {
				$out .= '<span class="kcs-pager__gap" aria-hidden="true">…</span>';
			}
			$out .= $n === $page ? '<span class="kcs-pager__num" aria-current="page">' . (int) $n . '</span>' : '<a class="kcs-pager__num" href="' . $link( $n ) . '">' . (int) $n . '</a>';
			$prev = $n;
		}
		$out .= $page < $pages ? '<a class="kcs-pager__step" rel="next" href="' . $link( $page + 1 ) . '"><span>' . esc_html__( 'Next', 'kingdom-church-suite' ) . '</span> ›</a>' : '<span class="kcs-pager__step is-off" aria-hidden="true"><span>' . esc_html__( 'Next', 'kingdom-church-suite' ) . '</span> ›</span>';
		$out .= '</span>';
	}
	if ( $opts['per_param'] ) {
		$keep = $args;
		unset( $keep[ $opts['per_param'] ], $keep[ $opts['param'] ] );
		$out .= '<form class="kcs-pager__per" method="get" action="' . esc_url( strtok( $base, '?' ) ) . '">';
		parse_str( (string) wp_parse_url( $base, PHP_URL_QUERY ), $base_args );
		foreach ( array_merge( $base_args, $keep ) as $k => $v ) {
			$out .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		$out .= '<label>' . esc_html__( 'Show', 'kingdom-church-suite' ) . ' <select name="' . esc_attr( $opts['per_param'] ) . '" onchange="this.form.submit()">';
		foreach ( array( 10, 20, 50, 100 ) as $n ) {
			$out .= '<option value="' . (int) $n . '"' . selected( $per, $n, false ) . '>' . (int) $n . '</option>';
		}
		$out .= '</select> ' . esc_html__( 'per page', 'kingdom-church-suite' ) . '</label><noscript><button class="kcs-btn kcs-btn--ghost">' . esc_html__( 'Go', 'kingdom-church-suite' ) . '</button></noscript></form>';
	}
	return $out . '</nav>';
}

/**
 * A stored date and time in the site's formats, e.g. "October 24, 2026 · 2:41 pm".
 *
 * @param string $mysql Local MySQL datetime.
 * @return string
 */
function kcs_datetime( $mysql ) {
	return $mysql ? mysql2date( get_option( 'date_format' ), $mysql ) . ' · ' . mysql2date( get_option( 'time_format' ), $mysql ) : '';
}

/**
 * Show a short branded message page and stop (in place of the plain WordPress error screen),
 * e.g. after a newsletter link or when a payment link has expired.
 *
 * @param string     $title   Heading.
 * @param string     $message Message (plain text).
 * @param string     $tone    success, error or info.
 * @param int        $code    HTTP status.
 * @param array|null $link    [ url, label ]; defaults to the homepage.
 */
function kcs_message_page( $title, $message, $tone = 'info', $code = 200, $link = null ) {
	$church  = kcs_get_option( 'church_name', get_bloginfo( 'name' ) );
	$logo    = kcs_get_option( 'church_logo' ) ? kcs_get_option( 'church_logo' ) : ( get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' ) : '' );
	$primary = sanitize_hex_color( get_theme_mod( 'kingdom_color_primary', '#5b2a86' ) );
	$primary = $primary ? $primary : '#5b2a86';
	$colors  = array( 'success' => '#1e8e5a', 'error' => '#c0392b', 'info' => $primary );
	$tone    = isset( $colors[ $tone ] ) ? $tone : 'info';
	$icons   = array(
		'success' => '<path d="M15 27l7 7 15-16"/>',
		'error'   => '<path d="M19 19l14 14M33 19L19 33"/>',
		'info'    => '<path d="M26 17v1M26 24v12"/>',
	);
	$link    = $link ? $link : array( home_url( '/' ), __( 'Go to homepage', 'kingdom-church-suite' ) );

	status_header( (int) $code );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title . ' · ' . $church ); ?></title>
<style>
:root { --p: <?php echo esc_html( $primary ); ?>; --t: <?php echo esc_html( $colors[ $tone ] ); ?>; --bg: #f6f3fa; --card: #fff; --text: #1f1b24; --muted: #6b6475; --line: #e4dfeb; color-scheme: light dark; }
@media (prefers-color-scheme: dark) { :root { --bg: #14111a; --card: #1e1a26; --text: #f3eff8; --muted: #a9a1b6; --line: #332c40; } }
* { box-sizing: border-box; }
body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--text); background: radial-gradient(80% 60% at 50% 0%, color-mix(in srgb, var(--p) 14%, transparent), transparent 70%), var(--bg); }
.card { width: 100%; max-width: 460px; overflow: hidden; text-align: center; background: var(--card); border: 1px solid var(--line); border-radius: 22px; box-shadow: 0 24px 60px -24px color-mix(in srgb, var(--t) 35%, rgba(20, 10, 40, .35)); animation: rise .5s cubic-bezier(.2, .8, .2, 1) both; }
.top { position: relative; padding: 40px 28px 8px; background: radial-gradient(120% 90% at 50% 0%, color-mix(in srgb, var(--t) 14%, transparent), transparent 70%); }
.top::before { content: ""; position: absolute; inset: 0 0 auto; height: 5px; background: var(--t); }
.logo { display: block; max-height: 48px; max-width: 160px; margin: 0 auto 20px; }
.icon { width: 80px; height: 80px; margin: 0 auto 16px; border-radius: 50%; display: grid; place-items: center; background: var(--t); color: #fff; box-shadow: 0 0 0 10px color-mix(in srgb, var(--t) 10%, transparent); }
.icon svg { width: 46px; height: 46px; fill: none; stroke: currentColor; stroke-width: 3.5; stroke-linecap: round; stroke-linejoin: round; }
.church { margin: 0 0 4px; font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--t); }
h1 { margin: 0; font-size: 1.6rem; line-height: 1.25; }
p.msg { margin: 12px auto 28px; max-width: 38ch; padding: 0 28px; color: var(--muted); }
.actions { padding: 20px 28px 26px; border-top: 1px solid var(--line); background: color-mix(in srgb, var(--p) 4%, var(--card)); }
.btn { display: inline-block; width: 100%; padding: 14px 24px; border-radius: 999px; background: var(--p); color: #fff; font-weight: 600; text-decoration: none; }
.btn:hover { filter: brightness(1.08); }
@keyframes rise { from { opacity: 0; transform: translateY(14px); } }
@media (prefers-reduced-motion: reduce) { .card { animation: none; } }
</style>
</head>
<body>
<main class="card" role="main">
	<div class="top">
		<?php if ( $logo ) : ?><img class="logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $church ); ?>"><?php endif; ?>
		<div class="icon"><svg viewBox="0 0 52 52" aria-hidden="true"><?php echo $icons[ $tone ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></svg></div>
		<p class="church"><?php echo esc_html( $church ); ?></p>
		<h1><?php echo esc_html( $title ); ?></h1>
	</div>
	<p class="msg"><?php echo esc_html( $message ); ?></p>
	<div class="actions"><a class="btn" href="<?php echo esc_url( $link[0] ); ?>"><?php echo esc_html( $link[1] ); ?></a></div>
</main>
</body>
</html>
	<?php
	exit;
}
