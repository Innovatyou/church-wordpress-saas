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
