<?php
/**
 * PHPUnit bootstrap: constants and the few WordPress classes the code under
 * test touches. WordPress functions are mocked per test with Brain\Monkey.
 *
 * @package TheBlueEmail\Forms
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'ABSPATH', '/tmp/wordpress/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'TBEF_VERSION', '1.0.0' );
define( 'TBEF_FILE', dirname( __DIR__ ) . '/theblue-email-forms.php' );
define( 'TBEF_DIR', dirname( __DIR__ ) . '/' );
define( 'TBEF_URL', 'https://site.test/wp-content/plugins/theblue-email-forms/' );
define( 'TBEF_PLATFORM_URL', 'https://theblue.email' );

require_once dirname( __DIR__ ) . '/includes/autoload.php';
require_once __DIR__ . '/stubs/wordpress.php';
