<?php
/**
 * Plugin Name: WEM Content Fields
 * Description: 一个轻量、可迁移的 WordPress 结构化字段管理插件，基于原生 Post Meta API。
 * Version: 1.1.0
 * Author: WEM
 * Text Domain: wem-content-fields
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEM_CF_VERSION', '1.1.0' );
define( 'WEM_CF_SCHEMA_VERSION', 2 );
define( 'WEM_CF_FILE', __FILE__ );
define( 'WEM_CF_PATH', plugin_dir_path( __FILE__ ) );
define( 'WEM_CF_URL', plugin_dir_url( __FILE__ ) );

require_once WEM_CF_PATH . 'includes/class-storage.php';
require_once WEM_CF_PATH . 'includes/class-normalizer.php';
require_once WEM_CF_PATH . 'includes/class-field-type.php';
require_once WEM_CF_PATH . 'includes/class-field-type-registry.php';
require_once WEM_CF_PATH . 'includes/field-types/class-text.php';
require_once WEM_CF_PATH . 'includes/field-types/class-textarea.php';
require_once WEM_CF_PATH . 'includes/field-types/class-editor.php';
require_once WEM_CF_PATH . 'includes/field-types/class-image.php';
require_once WEM_CF_PATH . 'includes/field-types/class-file.php';
require_once WEM_CF_PATH . 'includes/field-types/class-select.php';
require_once WEM_CF_PATH . 'includes/field-types/class-checkbox.php';
require_once WEM_CF_PATH . 'includes/class-validator.php';
require_once WEM_CF_PATH . 'includes/class-definitions.php';
require_once WEM_CF_PATH . 'includes/class-registry.php';
require_once WEM_CF_PATH . 'includes/transfer/class-exporter.php';
require_once WEM_CF_PATH . 'includes/transfer/class-importer.php';
require_once WEM_CF_PATH . 'admin/class-admin.php';
require_once WEM_CF_PATH . 'admin/class-meta-boxes.php';
require_once WEM_CF_PATH . 'admin/class-tools.php';
require_once WEM_CF_PATH . 'includes/class-plugin.php';

register_activation_hook(
	__FILE__,
	static function () {
		if ( false === get_option( WEM\Content_Fields\Storage::OPTION_GROUPS, false ) ) {
			add_option( WEM\Content_Fields\Storage::OPTION_GROUPS, [], '', false );
		}

		if ( false === get_option( WEM\Content_Fields\Storage::OPTION_SCHEMA_VERSION, false ) ) {
			add_option( WEM\Content_Fields\Storage::OPTION_SCHEMA_VERSION, WEM_CF_SCHEMA_VERSION, '', false );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		$plugin = new WEM\Content_Fields\Plugin();
		$plugin->boot();
	}
);
