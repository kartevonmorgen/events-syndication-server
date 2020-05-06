<?php
/*
 * Plugin Name: Events Syndication Server
 * Version: 	1.0
 * Plugin URI: 	https://github.com/kartevonmorgen/
 * Description: Loads Events into an Events Calender for Wordpress from other Websites. One implementation ist ESS.
 * Text Domain: events-ss
 * Domain Path: /languages
 * Author:      Sjoerd Takken
 * Author URI: 	https://www.sjoerdscomputerwelten.de
 */
/*
 Copyright (c) 2014, Marcus Sykes (Events Manager), Brice Pissard (ESS)

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

if ( !defined('EVENTS_SS_NAME')) 
{
  define('EVENTS_SS_NAME', trim( 
    dirname( plugin_basename( __FILE__ ) ), '/' ) );
}

if ( !defined( 'EVENTS_SS_DIR')) 
{
  define( 'EVENTS_SS_DIR', WP_PLUGIN_DIR . '/' . EVENTS_SS_NAME );
}

if ( !defined( 'EVENTS_SS_URL' )) 
{
  define( 'EVENTS_SS_URL', WP_PLUGIN_URL . '/' . EVENTS_SS_NAME );
}

if ( !defined( 'EVENTS_SS_PATH' )) 
{
  define( 'EVENTS_SS_PATH', plugin_dir_url( __FILE__ ));
}

if ( !defined( 'EVENTS_SS_SECURE' )) 
{
  define( 'EVENTS_SS_SECURE',((!empty($_SERVER['HTTPS']) && @$_SERVER['HTTPS'] !== 'off') || @$_SERVER['SERVER_PORT'] == 443 || stripos( @$_SERVER[ 'SERVER_PROTOCOL' ], 'https' ) === TRUE) ? TRUE : FALSE);
}

if ( !defined( 'EVENTS_SS_VERSION'       ) ) 
{
  define( 'EVENTS_SS_VERSION', '1.4' );
}

$instance = EventsSyndicationServer::get_instance();
add_action( 'init', array( $instance, 'start' ) );

require_once( EVENTS_SS_DIR . "/inc/models/class-ss-database.php" );
require_once( EVENTS_SS_DIR . "/inc/controllers/class-ss-io.php" );

final class EventsSyndicationServer
{
	private static $instance;

	const NAME = 'events-ss';

	private function __construct()
  {
  }

  /** 
   * The object is created from within the class itself
   * only if the class has no instance.
   */
  public static function get_instance()
  {
    if (self::$instance == null)
    {
      self::$instance = new EventsSyndicationServer();
    }
    return self::$instance;
  }

	public function start()
	{
    if ( !is_plugin_active( 'events-interface/events-interface.php' ) ) 
    {
      // Plugin is not active
      // TODO: See https://waclawjacek.com/check-wordpress-plugin-dependencies/
      echo 'The plugin Events Interface must be activated';
      die();
    }

    add_action( current_filter(), 
                array( $this, 'load_MVC_files' ), 30 );
	}

	public function load_MVC_files()
  {
    $dir = plugin_dir_path( __FILE__ );

    // -- MODELS
    include_once( $dir . 'inc/models/class-ss-database.php');
    include_once( $dir . 'inc/models/class-ss-notices.php' 	);
    include_once( $dir . 'inc/models/class-ss-abstractimport.php');
    include_once( $dir . 'inc/models/class-ss-essimport.php');
    include_once( $dir . 'inc/models/class-ss-importerfactory.php');

    // -- VIEWS
    include_once( $dir . 'inc/views/class-ui-feedadminpage.php');

    // -- CONTROLLERS
    include_once( $dir . 'inc/controllers/class-ss-admincontrol.php');
    include_once( $dir . 'inc/controllers/class-ss-feedadmincontrol.php');
    include_once( $dir . 'inc/controllers/class-ss-io.php');

    // Start logging part
    $notices = SSNotices::get_instance();
    $notices->start();

    $io = SS_IO::get_instance();
    $io->start();

    // Set up database
    $db = SSDatabase::get_instance();
    $db->set_default_values();

    // Start UI Settings Part
    $adminControl = SSAdminControl::get_instance();
    $adminControl->start();

    // Start UI Feed Part
    $feedAdminControl = SSFeedAdminControl::get_instance();
    $feedAdminControl->start();
  }

}

$io = SS_IO::get_instance();
register_activation_hook( __FILE__, array($io,	'set_activation'));
register_deactivation_hook( __FILE__, array($io, 'set_deactivation'));
register_uninstall_hook( __FILE__, array( $io, 'set_uninstall'));

add_action( SS_IO::CRON_EVENT_HOOK, "update_feeds_daily" );

function update_feeds_daily()
{
  $instance = EventsSyndicationServer::get_instance();
  $instance->load_MVC_files();

  if ( class_exists('SSDatabase' ) )
  {
    $db = SSDatabase::get_instance();
    $db->update_feeds_daily();
  }
}
