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

  private $_raw_data;
  private $_lines_data;
  private $_xml_data;

	function __construct($importtype, $feed_url)
  {
    $this->_feed_url = strtr( urldecode( esc_html( $feed_url ) ), array(
		    "&lt;"   => "<",
		    "&gt;"   => ">",
		    "&quot;" => '""',
		    "&apos;" => "'",
		    "&amp;"  => "&"
		) );

    $this->_importtype = $importtype;
  }

  public function get_importtype()
  {
    return $this->_importtype;
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

    $stored_feed = $this->get_stored_feed(
                               $this->get_feed_uuid());
    if(empty($stored_feed))
    {
      $this->set_owner_user_id( get_current_user_id() );
    }
    else
    {
      $this->set_owner_user_id( $stored_feed->feed_owner );
    }

    $logger = new UserMetaLogger('feed_update_log',
                                 $this->get_owner_user_id());
    $logger->add_date();
    $logger->add_line('Update Feed (user' . 
      $this->get_owner_user_id() . '): '. 
      $this->get_feed_url());

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
        $logger->add_line('Event is too old, no update');
        continue;
      }

      // Checks if the feed_url has the same host
      // as the events url/link
      if( !$this->is_linkurl_valid( $eiEvent ))
      {
        $logger->add_line('the feed_url is invalid');
        continue;
      }

      if( $this->is_backlink_enabled())
      {
        $this->add_backlink($eiEvent);
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
      }
      $logger->add_line('event has been changed (' . 
        $result->get_message() . ') so we save it'); 

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

    $this->save_stored_feed($stored_feed, $updated_event_ids);

    // If the feed exists already, we check if some events are 
    // no longer in the feed, if so, we delete these events.
    if(!empty( $stored_feed))
    {
      $last_event_ids = explode(',',$stored_feed->feed_event_ids);
    }

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
          
  private function get_stored_feed($feed_uuid)
  {
    $db = SSDatabase::get_instance();

    $feeds = $db->get(array('feed_uuid'=>$feed_uuid));
    if(empty($feeds))
    {
      return null;
    }
    $stored_feed = reset( $feeds );

    if( $stored_feed->feed_mode === SSDatabase::FEED_MODE_CRON )
    {
      $this->set_feed_update_daily(true);
    }
    return $stored_feed;
  }

  // == SAVE FEED ===
  // Only save the ESS Feed if, at least, one event have been saved.
  private function save_stored_feed($stored_feed, $updated_event_ids)
  {
    $feed_id = 0;

    $dom_ = parse_url( $this->get_feed_url() );
    if(!empty($stored_feed))
    {
      $feed_id = $stored_feed->feed_id;
    }
    $db = SSDatabase::get_instance();

    $user_owner_id = $this->get_owner_user_id();
    if($user_owner_id === 0)
    {
      $user_owner_id = get_current_user_id();
    }

    $importtype = $this->get_importtype();
    $feedtypeid = $importtype->get_id();

    $success = $db->add( array(
      'feed_id' => $feed_id,
      'feed_uuid'	=> $this->get_feed_uuid(),
      'feed_owner' => intval( $user_owner_id ),
      'feed_event_ids' 	=> implode(',',$updated_event_ids ),
      'feed_title'		=> $this->get_feed_title(), 
      'feed_host'			=> $dom_[ 'host' ],
      'feed_type'			=> $feedtypeid,
      'feed_url'			=> $this->get_feed_url(),
      'feed_status'		=> SSDatabase::FEED_STATUS_ACTIVE,
      'feed_mode'			=> ( $this->is_feed_update_daily() ? SSDatabase::FEED_MODE_CRON : SSDatabase::FEED_MODE_STANDALONE )));

    if( !$success )
    {
      $this->set_error( 
        "Impossible to insert the feed in the Database ". 
        $this->get_feed_url() );
    }
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
      $this->set_error("GetRawData Error: An error occure while trying to read the ESS file from the URL (" . 
        $this->get_feed_url() . "): " .
        $resp->getReasonPhrase());
    }

    return $this->_raw_data;
  }

  public function get_lines_data()
  {
    if( !empty( $this->_lines_data ))
    {
      return $this->_lines_data;
    }

    $this->_lines_data = explode(PHP_EOL, $this->get_raw_data());
    return $this->_lines_data;
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

  public function set_feed_update_daily($update_daily)
  {
    $this->_feed_update_daily = $update_daily;
  }

  public function is_feed_update_daily()
  {
    return $this->_feed_update_daily;
  }

  public function get_feed_url()
  {
    return $this->_feed_url;
  }

  public function set_owner_user_id($owner_user_id)
  {
    $this->_owner_user_id = $owner_user_id;
  }

  public function get_owner_user_id()
  {
    return $this->_owner_user_id;
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
  
  public function add_backlink($eiEvent)
  {
    $backlink_html = '<p>Importiert von ';
    $backlink_html .= '<a href="';
    $backlink_html .= $eiEvent->get_link();
    $backlink_html .= '">';
    $backlink_html .= $eiEvent->get_link();
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

  public function has_error()
  {
    return !empty($this->_error);
  }
}
