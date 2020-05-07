<?php
/**
  * Controller SSFeedAdminControl
  * Feeds page of the Syndication Server.
  *
  * @author  	Sjoerd Takken
  * @copyright 	No Copyright.
  * @license   	GNU/GPLv2, see https://www.gnu.org/licenses/gpl-2.0.html
  */
class SSFeedAdminControl 
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
      self::$instance = new SSFeedAdminControl();
    }
    return self::$instance;
  }

  public function start() 
  {
    $page = new UIFeedAdminPage('events-syndication-server-feeds', 
                                'Events Feeds');
    //$page->set_submenu_page(true, 'events-interface-options-menu');
    $page->set_feedadmincontrol($this);
    $page->register();
  }

	public function control_import()
	{
    $selected_feed_id = ( isset( $_REQUEST[ 'selected_event_id' ] ))? intval( @$_REQUEST['selected_event_id']) : '';

    if( $selected_feed_id > 0)
    {
      // It is a new feed
      $feed_url = (isset($_REQUEST[ 'update_once' ]	)) ? 
        urldecode( @$_REQUEST[ 'update_once' ] ) : '';
    }
    else
    {
      // Feed exists already
      $feed_url = ( isset( $_REQUEST[ 'feed_url' ] )) ? 
        urldecode( @$_REQUEST[ 'feed_url' ] ) : '';
    }

    if (empty( $feed_url ))
    {
      return;
    }

    if ($feed_url == SS_IO::HTTPS)
    {
      return;
    }

    if ( !$this->isValidURL( $feed_url ) )
    {
      return;
    }

    $notices = SSNotices::get_instance();
    $db = SSDatabase::get_instance();
    $feeds = $db->get( array('feed_url'=> $feed_url));
    if( (!($selected_feed_id > 0)) && !empty($feeds))
    {
      $stored_feed = reset( $feeds );
      $notices->add_error( __( 
        "Feed URL exist already for User-ID (".
        $stored_feed->feed_owner. '), can also be in Trash ', 
        'events-ss' ). 
        $feed_url );
      return;
    }


    $instance = SSImporterFactory::get_instance();
    $importer = $instance->create_importer($feed_url);
    if(empty($importer))
    {
      //echo 'No Importer instance found for feed url' . $feed_url;
      $notices->add_error( __( 
        "No importer found for import url ", 'events-ss' ). 
        $feed_url );
      return;
    }

    
		if ( ! $importer->is_feed_valid() )
    {
      $notices->add_error( __( 
        'ERROR: '.$importer->get_error() . ' ', 'events-ss' ). 
        $feed_url );
      return;
    }

    $feed_uuid = $importer->read_feed_uuid();
    $feeds = $db->get( array('feed_uuid'=> $feed_uuid));
    if( (!($selected_feed_id > 0)) && !empty($feeds))
    {
      $stored_feed = reset( $feeds );
      $notices->add_error( __( 
        "Feed UUID exist already for User-ID (".
        $stored_feed->feed_owner. '), can also be in Trash ', 
        'events-ss' ). 
        $feed_url );
      return;
    }

    $importer->import();
    if( $importer->has_error() )
    {
      $notices->add_error( __( 
        'ERROR: '.$importer->get_error() . ' ', 'events-ss' ). 
        $feed_url );
      return;
    }

    $notices->add_confirm( __( 
        'Feed succesfully imported or updated ', 'events-ss' ). 
        $feed_url );
	}

	public function control_nav_actions()
	{
    if ( !isset( $_REQUEST[ 'action' ] ) 
         && !isset( $_REQUEST[ 'feeds' ] )) 
    {
      return;
    }

		if(count((isset($_REQUEST['feeds']) ? $_REQUEST['feeds'] : array())) > 0
      && strlen((isset($_REQUEST['action']) ? $_REQUEST['action'] : '')) > 0
      )
    {
      $count_action = 0;
      $action = "";

      foreach ( @$_REQUEST['feeds'] as $feed_id )
			{
        if ( empty( $feed_id ) )
        {
          continue;
        }
        
        $count_action++;

        switch ( $_REQUEST['action'] )
        {
          default:  
            $action = __( 'have been updated','events-ss' ); 
            break;
          case 'active': 
            $action = __( 'have been activated','events-ss' ); 
            break;
          case 'deleted': 
            $action = __( 'have been deleted', 'events-ess' ); 
            break;
          case 'full_deleted': 
            $action = __( 'have been definitively removed', 'events-ess' ); 
            break;
          case 'update_cron': 
            $action = __( 'have its daily update reactualized', 
              'events-ess' ); 
            break;
        }

        $db = SSDatabase::get_instance();
        if ( $_REQUEST['action'] == 'active' ||
						 $_REQUEST['action'] == 'deleted')
        {
          $db->add( array(
            'feed_id'		=> $feed_id,
            'feed_status'	=> strtoupper( $_REQUEST[ 'action' ] )));
        }
        else if ( $_REQUEST['action'] == 'full_deleted' )
        {
          $db->delete( array(
            'feed_status' => SSDatabase::FEED_STATUS_DELETED,
            'feed_id' => $feed_id ));
        }
        else if ( $_REQUEST['action'] == 'update_cron' )
        {
          $feed_mode = ( ( @$_REQUEST[ 'feed_mode_'.$feed_id ] == 'on' )?
            SSDatabase::FEED_MODE_CRON
            :
            SSDatabase::FEED_MODE_STANDALONE
						);

          $db->add( array(
            'feed_id'	=> $feed_id,
            'feed_mode'	=> $feed_mode));
				}
			}
      $notices = SSNotices::get_instance();
			$notices->add_confirm( 
        sprintf( __( "%d rows %s.",'events-ess'), 
          $count_action,  $action ));
    }
  }

	/**
	 * Control if the URL is correctly formated (RFC 3986)
	 * An IP can also be submited as a URL.
	 *
	 * @access	public
	 * @param	String	stringDate string element to control
	 * @return	Boolean
	 */
	public function isValidURL( $url='' )
	{
		$url = trim( $url );
		$ereg = "/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i";

		return ( preg_match( $ereg, $url ) > 0 && strlen( $url ) > 10 )? TRUE : $this->isValidIP( $url );
	}

	/**
	 * 	Control if the parameter submited is a valide IP v4
	 *
	 * 	@access public
	 * 	@param	String	Value of the IP to evaluate
	 * 	@return	Boolean	If the parameter submited is a valide IP return TRUE, FALSE else.
	 */
	public function isValidIP( $ip='' )
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
