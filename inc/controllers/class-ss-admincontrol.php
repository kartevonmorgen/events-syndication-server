<?php
/**
  * Controller SSAdminControl
  * Settings page of the ESS Feed Server.
  *
  * @author  	Sjoerd Takken
  * @copyright 	No Copyright.
  * @license   	GNU/GPLv2, see https://www.gnu.org/licenses/gpl-2.0.html
  */
class SSAdminControl 
{
  private static $instance = null;

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
      self::$instance = new SSAdminControl();
    }
    return self::$instance;
  }

  public function start() 
  {
    $page = new UISettingsPage('events-syndicatoin-server', 
                               'Syndication Server Settings');
    $page->set_submenu_page(true, 'events-interface-options-menu');
    $section = $page->add_section('ss_section_one', 'General Settings');
    $section->set_description(
       "This section defines the way the imported feeds " . 
       "events will appears in your event dashboard.");

    $section->add_checkbox('ss_syndication_status', 
                           'Publish events directly');
    $section->add_checkbox('ss_backlink_enabled', 
                           'Add link from source');
    
    $page->register();
  }
}