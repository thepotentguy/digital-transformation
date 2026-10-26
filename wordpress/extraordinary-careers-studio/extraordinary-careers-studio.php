<?php
/**
 * Plugin Name: Extraordinary Careers Studio
 * Description: Guided, branded career posts with secure drafts and publishing.
 * Version: 0.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Extraordinary
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }
define('ECS_VERSION', '0.1.0');
define('ECS_URL', plugin_dir_url(__FILE__));
require_once __DIR__ . '/includes/studio.php';
