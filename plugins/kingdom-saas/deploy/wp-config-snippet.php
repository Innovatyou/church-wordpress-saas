<?php
/**
 * Add these lines to wp-config.php (above "That's all, stop editing!").
 * The network setup screen (Tools → Network Setup) gives you the first block.
 */
define( 'WP_ALLOW_MULTISITE', true );
define( 'MULTISITE', true );
define( 'SUBDOMAIN_INSTALL', true );
define( 'DOMAIN_CURRENT_SITE', 'yourplatform.com' );
define( 'PATH_CURRENT_SITE', '/' );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );

// Custom domains (copy wp-content/plugins/kingdom-saas/deploy/sunrise.php to wp-content/sunrise.php).
define( 'SUNRISE', true );

// Run scheduled jobs from a real cron job instead of page visits. WP-Cron runs per site, so the
// job must run it for EVERY church site (billing checks run on the main site, email queues,
// notifications and scheduled posts on each church's site). With WP-CLI:
//   */5 * * * * cd /var/www/platform && wp site list --field=url --deleted=0 | xargs -I{} wp cron event run --due-now --url={} --quiet
define( 'DISABLE_WP_CRON', true );

// Behind Cloudflare or a load balancer: trust its visitor-IP header (used for rate limits).
// define( 'KCS_TRUSTED_PROXY_HEADER', 'HTTP_CF_CONNECTING_IP' );
