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
    $this->set_default_values();

    $page = new UISettingsPage('events-syndicatoin-server', 
                               'Syndication Server Settings');
    $page->set_submenu_page(true, 'events-interface-options-menu');
    $section = $page->add_section('ss_section_one', 'General Settings');
    $section->set_description(
       "This section defines the way the imported feeds " . 
       "events will appears in your event dashboard.");

    $field = $section->add_checkbox('ss_publish_directly', 
                                    'Publish events directly');
    $field->set_description('If events are imported, then publish them directly on the website, otherwise the state will be pending ');
    $field = $section->add_checkbox('ss_backlink_enabled', 
                                    'Add link from source');
    $field->set_description('Add at the bottom of the events description a link to the website where the event is coming from');

    $field = $section->add_textfield('ss_category_prefix', 
                                    'Strip prefix from category');
    $field->set_description('Strip this prefix from imported categories');

    $field = $section->add_textarea('ss_cron_message', 
                                    'Last message eventscron');
    $field->set_description('Last message from daily cron job to retrieve events');
    
    $page->register();
  }

  public function set_default_values()
	{
		$ss_options = array(
			// -- Syndication Settings
			'ss_syndication_status' 	=> FALSE, 
			'ss_backlink_enabled' 		=> FALSE,

				// -- Feed's Elements
				'ss_feed_import_images'	=> FALSE,
				'ss_feed_import_videos'	=> FALSE,
				'ss_feed_import_sounds'	=> FALSE
		);

		foreach( $ss_options as $key => $value )
			add_option( $key, $value );
	}
}
