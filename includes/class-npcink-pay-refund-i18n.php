<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.npc.ink
 * @since      1.0.0
 *
 * @package    Npcink_Pay_Refund
 * @subpackage Npcink_Pay_Refund/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Npcink_Pay_Refund
 * @subpackage Npcink_Pay_Refund/includes
 * @author     Muze <1355471563@qq.com>
 */
class Npcink_Pay_Refund_I18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Keep explicit loading for non-wp.org and local language files.
		load_plugin_textdomain(
			'npcink-pay-refund',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
