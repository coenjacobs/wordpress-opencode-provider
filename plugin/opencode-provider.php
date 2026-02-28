<?php

declare(strict_types=1);

/**
 * Plugin Name: OpenCode Provider
 * Description: Adds OpenCode as an AI provider for the WordPress AI Client.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Author: Coen Jacobs
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

use CoenJacobs\OpenCodeProvider\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

add_action( 'plugins_loaded', function () {
    Plugin::instance()->setup();
} );
