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
  private $_feed_update_daily = false;
  private $_owner_user_id = 0;

  private $_feed_uuid;
  private $_feed_title;
  private $_error;

  private $_raw_data;
  private $_xml_data;

	function __construct($feed_url)
  {
    $this->_feed_url = strtr( urldecode( esc_html( $feed_url ) ), array(
		    "&lt;"   => "<",
		    "&gt;"   => ">",
		    "&quot;" => '""',
		    "&apos;" => "'",
		    "&amp;"  => "&"
		) );

  }

  public abstract function is_feed_valid();

  public abstract function read_feed_uuid();

  abstract function read_feed_title();

  abstract function read_events_from_feed();

  public function import()
  {
		global $current_site; 

    $updated_event_ids = array();

		if ( !is_user_logged_in() && ! $this->is_feed_update_daily() ) 
    {
      return;
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

    if( empty($eiEvents) )
    {
      $this->set_error( "No events found in the ESS Feed:");
      return;
    }

    $stored_feed = $this->get_stored_feed($this->get_feed_uuid());

    foreach ( $eiEvents as $eiEvent )
    {
      // Checks if the feed_url has the same host
      // as the events url/link
      if( !$this->is_linkurl_valid( $eiEvent ))
      {
        continue;
      }

      $eiEvent->set_owner_user_id($this->get_owner_user_id());
      if ( $eiEvent->get_owner_user_id() === 0 )
      {
        $eiEvent->set_owner_user_id( get_current_user_id() );
      }

		  if( isset( $current_site ) )
      {
        $eiEvent->set_blog_id( $current_site->blog_id );
      }

      $eiInterface = EIInterface::get_instance();
      $result = $eiInterface->save_event($eiEvent);
      if( $result->has_error() )
      {
        $this->set_error($result->get_error());
        return;
      }

      if( !empty( $result->get_event_id() ))
      {
        array_push( $updated_event_ids, $result->get_event_id());
      }
    }

    $this->save_stored_feed($stored_feed, $updated_event_ids);

    // If the feed exists already, we check if some events are 
    // no longer in the feed, if so, we delete these events.
    if(!empty( $stored_feed))
    {
      $last_event_ids = explode(',',$stored_feed->feed_event_ids);
    }

    if(empty($last_event_ids))
    {
      return;
    }

    foreach($last_event_ids as $last_event_id)
    {
      if(in_array($last_event_id, $updated_event_ids))
      {
        continue;
      }

      $eiInterface = EIInterface::get_instance();
      $eiInterface->delete_event_by_event_id($last_event_id);
    }

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

    $this->set_owner_user_id( $stored_feed->feed_owner );
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

    if ( empty( $updated_event_ids ))
    {
      return;
    }
    
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

    $success = $db->add( array(
      'feed_id' => $feed_id,
      'feed_uuid'	=> $this->get_feed_uuid(),
      'feed_owner' => intval( $user_owner_id ),
      'feed_event_ids' 	=> implode(',',$updated_event_ids ),
      'feed_title'		=> $this->get_feed_title(), 
      'feed_host'			=> $dom_[ 'host' ],
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
