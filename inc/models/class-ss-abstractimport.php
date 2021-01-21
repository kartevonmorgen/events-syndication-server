<?php
/**
  * Controller SSAbstractImport
  * Control the import of ESS feed
  *
  * @author     Brice Pissard, Sjoerd Takken
  * @copyright 	No Copyright.
  * @license   	GNU/GPLv2, see http://www.gnu.org/licenses/gpl-2.0.html
  * @link	      https://github.com/kartevonmorgen
  */
abstract class SSAbstractImport
{
  private $_feed_url;
  private $_importtype;
  private $_feed_update_daily = false;
  private $_owner_user_id = 0;

  private $_feed_uuid;
  private $_feed_title;
  private $_error;
  private $_log = '';

  private $_raw_data;
  private $_xml_data;

	function __construct($feed)
  {
    $this->_feed = $feed;
  }

  public function get_feed()
  {
    return $this->_feed;
  }

  public function get_feed_id()
  {
    if(empty($this->get_feed()))
    {
      return 0;
    }
    return $this->get_feed()->ID;
  }

  public function is_feed_update_daily()
  {
    return $this->get_feed_meta('ss_feedupdatedaily');
  }

  public function get_feed_url()
  {
    return $this->get_feed_meta('ss_feedurl');
  }

  public function is_linkurl_valid_check_disabled()
  {
    return $this->get_feed_meta(
      'ss_disable_linkurl_valid_check');
  }

  public function get_owner_user_id()
  {
    if(empty($this->get_feed()))
    {
      return 0;
    }
    return $this->get_feed()->post_author;
  }

  public function get_feed_meta($key)
  {
    return get_post_meta($this->get_feed_id(), $key, 1);
  }

  public function get_importtype()
  {
    $importtypeid = $this->get_feed_meta('ss_feedurltype');

    $factory = SSImporterFactory::get_instance();
    return $factory->get_importtype( $importtypeid );
  }

  public function get_feed_eventids()
  {
    return explode(',', 
     $this->get_feed_meta('ss_feed_eventids'));
  }

  public abstract function is_feed_valid();

  public abstract function read_feed_uuid();

  abstract function read_feed_title();

  abstract function read_events_from_feed();

  public function import()
  {
		global $current_site; 

    $updated_event_ids = array();

		if ( ! $this->is_feed_update_daily() ) 
    {
      if( !is_user_logged_in() )
      {
        $this->set_error( 
          "No user logged in ". $this->get_feed_url() );
        return;
      }
    }

		if ( ! $this->is_feed_valid() )
		{
      return;
    }

		$this->set_feed_uuid($this->read_feed_uuid());
    if( $this->has_error() )
    {
      return;
    }

		$this->set_feed_title($this->read_feed_title());
    if( $this->has_error() )
    {
      return;
    }

    $eiEvents = $this->read_events_from_feed();
    if( $this->has_error() )
    {
      return;
    }

    $logger = new PostMetaLogger('ss_feed_updatelog',
                                 $this->get_feed_id());
    $logger->add_date();
    $logger->add_line('Update Feed (feedid=' . 
      $this->get_feed_id() . ', user=' . 
      $this->get_owner_user_id() . '): '. 
      $this->get_feed_url());
    $logger->save();

    $now = time();
    foreach ( $eiEvents as $eiEvent )
    {
      $logger->remove_prefix();
      $logger->add_newline();
      $logger->add_date();
      $logger->add_line('Update Event ' . $eiEvent->get_uid());
      $logger->add_prefix('  ');
      // Do not import events from the past
      if(strtotime($eiEvent->get_start_date()) < $now)
      {
        $logger->add_line('Event is too old (' .
          $eiEvent->get_start_date() . '), no update');
        continue;
      }

      if( !$this->is_linkurl_valid_check_disabled() )
      {
        // Checks if the feed_url has the same host
        // as the events url/link
        if( !$this->is_linkurl_valid( $eiEvent ))
        {
          $logger->add_line('the linkurl (' . 
                            $eiEvent->get_link() . ') ' .
                            'does not orginate to the ' .
                            'feedurl ('. 
                            $this->get_feed_url() . ')');
          continue;
        }
      }

      if( $this->is_backlink_enabled())
      {
        $this->add_backlink($eiEvent);
      }

      $eiEvent->set_post_status('pending');
      if( $this->is_publish_directly() )
      {
        $eiEvent->set_post_status('publish');
      }

      $eiEvent->set_owner_user_id($this->get_owner_user_id());

		  if( isset( $current_site ) )
      {
        $eiEvent->set_blog_id( $current_site->blog_id );
      }

      // Fill Lat/Lon coordinates by osm
      // so we can check if the location has been changed.
      $wpLocationHelper = new WPLocationHelper();
      $eiEventLocation = $eiEvent->get_location();
      if(!empty($eiEventLocation))
      {
        $logger->add_line('fill location (' . 
          $eiEventLocation->to_string() . ') by osm');
        $eiEventLocation = 
          $wpLocationHelper->fill_by_osm_nominatim(
            $eiEventLocation);
        $logger->add_line('  lat=' . 
          $eiEventLocation->get_lat()); 
        $logger->add_line('  lon=' . 
          $eiEventLocation->get_lon()); 
        $eiEvent->set_location($eiEventLocation);
      }

      // Check if the Event has been changed
      // only by changes we save it.
      // This prevents not needed saves and updates 
      // to the Karte von Morgen
      $eiInterface = EIInterface::get_instance();
      $oldEiEvent = $eiInterface->get_event_by_uid(
                      $eiEvent->get_uid());
      if(empty($oldEiEvent))
      {
        $logger->add_line('event does not exist'); 
      }
      else
      {
        $result = $oldEiEvent->equals_by_content($eiEvent);
        if($result->is_true())
        {
          $logger->add_line('events are equal, ' .
                            'so we do NOT update'); 
          array_push( $updated_event_ids, 
                      $oldEiEvent->get_event_id());
          continue;
        }
        $logger->add_line('event has been changed (' . 
          $result->get_message() . ') so we save it'); 
      }

      // Only save if we have changes
      $result = $eiInterface->save_event($eiEvent);
      if( $result->has_error() )
      {
        $logger->add_line('save_event gives an ERROR (' . 
          $result->get_error() . ') '); 
        $this->set_error($result->get_error());
        $logger->save();
        return;
      }

      if( !empty( $result->get_event_id() ))
      {
        array_push( $updated_event_ids, $result->get_event_id());
      }
    }

    $logger->remove_prefix();
    $logger->add_newline();
    $logger->add_line('updates finished: save the new ' .
                      'feed status');

    // Save the eventids that were already there
    $last_event_ids = $this->get_feed_eventids();

    $this->save_feed($updated_event_ids);

    // We check if some events are 
    // no longer in the feed, if so, we delete these events.
    if(empty($last_event_ids))
    {
      $logger->add_line('-- nothing to delete, ' . 
                        'update feed finished ---');
      $logger->save();
      return;
    }

    $logger->add_line('delete no longer updated events ');
    $logger->add_prefix('  ');

    foreach($last_event_ids as $last_event_id)
    {
      if(empty($last_event_id))
      {
        continue;
      }

      if(in_array($last_event_id, $updated_event_ids))
      {
        continue;
      }

      $logger->add_line('delete event (id=' . 
                        $last_event_id. ')');
      $eiInterface = EIInterface::get_instance();
      $eiInterface->delete_event_by_event_id($last_event_id);
    }
    $logger->remove_prefix();
    $logger->add_line('-- update feed finished ---');
    $logger->save();
  }
          
  // == SAVE FEED ===
  // Only save the Feed if, 
  // at least, one event have been saved.
  private function save_feed($updated_event_ids)
  {
    $feed_id = $this->get_feed_id();
    if(empty($feed_id))
    {
      $this->set_error("Feed Id is 0");
      return;
    }

    $lastupdate = get_date_from_gmt(date("Y-m-d H:i:s"));

    update_post_meta($feed_id, 'ss_feed_title', 
                     $this->get_feed_title());
    update_post_meta($feed_id, 'ss_feed_uuid', 
                     $this->get_feed_uuid());
    update_post_meta($feed_id, 'ss_feed_lastupdate', 
                     $lastupdate);
    update_post_meta($feed_id, 'ss_feed_eventids', 
                     implode(',',$updated_event_ids ));
	}

  private function is_linkurl_valid($eiEvent)
  {
    $feed_url = $this->get_feed_url();

    $feed_host = parse_url($feed_url, PHP_URL_HOST);
    $feed_host = str_replace('www.', '', $feed_host);
    $eiEvent_host = parse_url($eiEvent->get_link(), PHP_URL_HOST);
    $eiEvent_host = str_replace('www.', '', $eiEvent_host);
    return $feed_host == $eiEvent_host;
  }

  public function get_raw_data()
  {
    if( !empty( $this->_raw_data ))
    {
      return $this->_raw_data;
    }

    $req = new SimpleRequest('get', $this->get_feed_url());
    $client = new WordpressHttpClient();
    $resp = $client->send($req);
    if( $resp->getStatusCode() == 200 )
    {
      $this->_raw_data = $resp->getBody();
    }
    else
    {
      $this->set_error("GetRawData Error: An error occure while trying to read the feed from the URL (" . 
        $this->get_feed_url() . "): " .
        $resp->getReasonPhrase());
    }

    return $this->_raw_data;
  }



  public function get_xml_data()
  {
    if( !empty( $this->_xml_data ))
    {
      return $this->_xml_data;
    }
    
    try
    {
      $this->_xml_data = @simplexml_load_string( 
                                   $this->get_raw_data(), 
                                   "SimpleXMLElement", 
                                   LIBXML_NOCDATA );
    }
    catch( ErrorException $e )
    {
      $this->set_error("GetXMLData Error: An error occure while trying to read the ESS file from the URL: (" .$e. ")");
    }
    return $this->_xml_data;
  }


  public function set_feed_uuid($feed_uuid)
  {
    return $this->_feed_uuid = $feed_uuid;
  }

  public function get_feed_uuid()
  {
    return $this->_feed_uuid;
  }

  public function set_feed_title($feed_title)
  {
    return $this->_feed_title = $feed_title;
  }

  public function get_feed_title()
  {
    return $this->_feed_title;
  }
  
  public function is_backlink_enabled()
  {
    return get_option('ss_backlink_enabled', false);
  }
  
  public function is_publish_directly()
  {
    return get_option('ss_publish_directly', false);
  }
  
  public function add_backlink($eiEvent)
  {
    $backlink = $eiEvent->get_link();
    if(empty($backlink))
    {
      $backlink = $this->get_feed_url();
    }
    $backlink_html = '<p>Importiert von ';
    $backlink_html .= '<a href="';
    $backlink_html .= $backlink;
    $backlink_html .= '">';
    $backlink_html .= $backlink;
    $backlink_html .= '</a></p>';
    $eiEvent->set_description(
      $eiEvent->get_description() . $backlink_html);
  }

  public function set_error($error)
  {
    $this->_error = '['. $this->get_feed_url() . ']:' .$error;
  }

  public function get_error()
  {
    return $this->_error;
  }

  public function set_log( $log )
  {
    $this->_log = '['. $this->get_feed_url() . ']:' .$log;
  }

  public function add_log( $log )
  {
    $this->_log .= PHP_EOL;
    $this->_log .= '['. $this->get_feed_url() . ']:' . $log;
  }

  public function get_log()
  {
    return $this->_log;
  }

  public function has_error()
  {
    return !empty($this->_error);
  }

	/**
	 * Control if the URL is correctly formated (RFC 3986)
	 * An IP can also be submited as a URL.
	 *
	 * @access	public
	 * @param	String	stringDate string element to control
	 * @return	Boolean
	 */
	public function is_validURL( $url='' )
	{
		$url = trim( $url );
		$ereg = "/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i";

		return ( preg_match( $ereg, $url ) > 0 && strlen( $url ) > 10 )? TRUE : $this->is_validIP( $url );
	}

	/**
	 * 	Control if the parameter submited is a valide IP v4
	 *
	 * 	@access public
	 * 	@param	String	Value of the IP to evaluate
	 * 	@return	Boolean	If the parameter submited is a valide IP return TRUE, FALSE else.
	 */
	public function is_validIP( $ip='' )
	{
		$ip = trim( $ip );
		$regexp = '/^((1?\d{1,2}|2[0-4]\d|25[0-5])\.){3}(1?\d{1,2}|2[0-4]\d|25[0-5])$/';

		if ( preg_match( $regexp, $ip ) <= 0 )
		{
			return FALSE;
		}
		else
		{
			$a = explode( ".", $ip );

			if ( $a[0] > 255) { return FALSE; }
			if ( $a[1] > 255) { return FALSE; }
			if ( $a[2] > 255) {	return FALSE; }
			if ( $a[3] > 255) { return FALSE; }

			return TRUE;
    }
  }
}
