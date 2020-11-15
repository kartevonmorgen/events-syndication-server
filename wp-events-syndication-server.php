<?php
/*
 * Plugin Name: WP Events Syndication Server
 * Version: 	1.0
 * Plugin URI: 	https://github.com/kartevonmorgen/
 * Description: Loads Events into an Events Calender for Wordpress from other Websites. One implementation ist ESS.
 * Domain Path: /languages
 * Author:      Sjoerd Takken
 * Author URI: 	https://www.sjoerdscomputerwelten.de
 */
/*
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


defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


$loaderClass = WP_PLUGIN_DIR . '/wp-libraries/inc/lib/plugin/class-wp-pluginloader.php';
if(!file_exists($loaderClass))
{
  echo "Das Plugin 'wp-libraries' muss erst installiert und aktiviert werden";
  exit;
}

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
include_once( $loaderClass);


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

class WPEventsSyndicationServerPluginLoader 
  extends WPPluginLoader
{
  public function init()
  {
    $this->add_dependency('wp-libraries/wp-libraries.php');
    $this->add_dependency('wp-events-interface/wp-events-interface.php');

    $this->add_include('/inc/models/class-ss-importtype.php');
    $this->add_include('/inc/models/class-ss-importerfactory.php');
    $this->add_include('/inc/models/class-ss-feeds.php');
    $this->add_include('/inc/controllers/class-ss-io.php');
    // -- MODELS
    $this->add_include('/inc/models/class-ss-notices.php');
    $this->add_include('/inc/models/class-ss-abstractimport.php');
    $this->add_include('/inc/models/class-ss-essimport.php');
    $this->add_include('/inc/models/class-ss-icalimport.php');

    // -- CONTROLLERS
    $this->add_include('/inc/controllers/class-ss-admincontrol.php');
    $this->add_include('/inc/controllers/class-ss-io.php');
  }

  public function start()
  {
    $this->add_starter( SSFeeds::get_instance());
    $this->add_starter( SSNotices::get_instance());

    $this->add_starter( SS_IO::get_instance());

    // Start UI Settings Part
    $this->add_starter( new SSAdminControl());

    add_action( SS_IO::CRON_EVENT_HOOK, 
                array($this, 'update_feeds_daily'));
  }

	public function activate()
	{

		flush_rewrite_rules();

		if ( !current_user_can( 'activate_plugins' ) ) 
    {
      return;
    }

    $plugin = $_REQUEST[ 'plugin' ];
    check_admin_referer( "activate-plugin_{$plugin}" );

    $role = get_role( 'administrator' );
    $role->add_cap( 'manage_event_feeds');
    $role->add_cap( 'manage_other_event_feeds');
    $role = get_role( 'editor' );
    $role->add_cap( 'manage_event_feeds');
    $role = get_role( 'author' );
    $role->add_cap( 'manage_event_feeds');

    // -- Set Schedule Hook (CRON tasks)
    if (!wp_next_scheduled ( SS_IO::CRON_EVENT_HOOK )) 
    {
      //daily | hourly | tenminutely
		  wp_schedule_event( time(), 'daily', 
        SS_IO::CRON_EVENT_HOOK ); 
    }
	}

	public function deactivate()
	{
		if ( !current_user_can( 'activate_plugins' ) ) 
    {
      return;
    }
    return;

    //$plugin = $this->get_plugin();;
    $plugin = $_REQUEST[ 'plugin' ];
    check_admin_referer( "deactivate-plugin_{$plugin}" );

		// -- Remove Schedule Hook (CRON tasks)
		wp_clear_scheduled_hook( SS_IO::CRON_EVENT_HOOK );
	}

	public function uninstall()
  {
    if ( ! current_user_can( 'activate_plugins' ) ) 
    {
      return;
    }

    check_admin_referer( 'bulk-plugins' );

		// -- Remove Schedule Hook (CRON tasks)
		wp_clear_scheduled_hook( SS_IO::CRON_EVENT_HOOK );
  }

  function update_feeds_daily()
  {
    if ( class_exists('SSFeeds' ) )
    {
      $feeds = SSFeeds::get_instance();
      $feeds->update_feeds_daily();
    }
  }

}

$loader = new WPEventsSyndicationServerPluginLoader();
$loader->register( __FILE__, 30);


