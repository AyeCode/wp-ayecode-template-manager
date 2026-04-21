<?php
/**
 * Plugin Name: WP AyeCode Template Manager
 * Plugin URI: https://ayecode.io/
 * Description: Centralized template management hub for the AyeCode ecosystem.
 * Version: 3.0.5-beta
 * Author: AyeCode Ltd
 * Author URI: https://ayecode.io/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ayecode-connect
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package WP_AyeCode_Template_Manager
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Manually boot the package loader so the framework works as a standalone plugin.
require_once __DIR__ . '/package-loader.php';

// Update version:
// 1. Here
// 2. pacakge-loader.php
// 3. composer.json
