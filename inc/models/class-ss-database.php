<?php
/**
  * Model SSDatabase
  * Container of the interface with the database
  * Here the feeds stored which are synchronised 
  * from another website.
  *
  * @author     Brice Pissard, Sjoerd Takken
  * @copyright 	No Copyright.
  * @license    GNU/GPLv2, see http://www.gnu.org/licenses/gpl-2.0.html
  * @link    	  https://github.com/kartevonmorgen/
  */
final class SSDatabase
{
  private static $instance = null;

	private $_wpdb = NULL;
	private $_table = "";

	const FEED_TABLE_NAME		= 'events_ss_feeds';

	// -- Syndication Status
	const EVENT_STATUS_DRAFT	= 'draft';
	const EVENT_STATUS_PUBLISH	= 'publish';

	// -- Feed Status
	const FEED_STATUS_DELETED 	= 'DELETED';
	const FEED_STATUS_ACTIVE	= 'ACTIVE';

	// -- Feed Modes
	const FEED_MODE_STANDALONE 	= 'STANDALONE';
	const FEED_MODE_CRON		= 'CRON';

	// -- Default Feed Attributes
  // ISO 4217 language code (2 chars), default 'en'
	const DEFAULT_LANGUAGE		= 'en';		 
	const DEFAULT_CURRENCY		= 'EUR';
	const DEFAULT_CATEGORY_TYPE = 'general';

	var $feed_id;
	var $feed_host;
	var $feed_url;
	var $feed_status;
	var $feed_mode;
	var $feed_timestamp;


	private function __construct()
	{
		$this->init();
	}

  /** 
   * The object is created from within the class itself
   * only if the class has no instance.
   */
  public static function get_instance()
  {
    if (self::$instance == null)
    {
      self::$instance = new SSDatabase();
    }
    return self::$instance;
  }

	public function init()
	{
		if ( strlen( $this->get_table() ) > 0 )
		{
      return;
    }
			
    global $wpdb;
		if ( !isset( $wpdb ) ) 
    {
      $wpdb = $GLOBALS[ 'wpdb' ];
    }

		$this->_wpdb = $wpdb;
    $this->get_db()->show_errors();

    if ( is_multisite() )	
    {
      $prefix = $this->get_db()->base_prefix;
    }
    else 				
    {
      $prefix = $this->get_db()->prefix;
    }

    $this->_table = $prefix . SSDatabase::FEED_TABLE_NAME;
	}

	public function createTable()
	{
    $db = $this->get_db();
		if ( @count( $db->get_results( "SHOW TABLES LIKE '". $this->get_table()."';" ) ) >= 1 )
    {
			return;
    }

		$sql = "CREATE TABLE " . $this->get_table() . " (
			feed_id     bigint( 20 ) 	UNSIGNED NOT NULL AUTO_INCREMENT,
			feed_owner  bigint( 20 ) 	UNSIGNED NOT NULL,
			feed_uuid   VARCHAR( 128 ) 	CHARACTER SET utf8 COLLATE utf8_unicode_ci 	NOT NULL,
			feed_event_ids VARCHAR( 128 ) 	CHARACTER SET utf8 COLLATE utf8_unicode_ci 	NOT NULL,
			feed_post_ids  VARCHAR( 128 ) 	CHARACTER SET utf8 COLLATE utf8_unicode_ci 	NOT NULL,
			feed_title  VARCHAR( 256 ) 	CHARACTER SET utf8 COLLATE utf8_unicode_ci 	NOT NULL,
			feed_host   VARCHAR( 128 ) 	CHARACTER SET utf8 COLLATE utf8_unicode_ci 	NOT NULL,
			feed_type   VARCHAR( 128 ) 	CHARACTER SET utf8 COLLATE utf8_unicode_ci 	NOT NULL,
			feed_url    VARCHAR( 4096 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci 	NOT NULL,
			feed_status ENUM('".SSDatabase::FEED_STATUS_ACTIVE."','".SSDatabase::FEED_STATUS_DELETED."') 	CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '".SSDatabase::FEED_STATUS_ACTIVE."',
			feed_mode		ENUM('".SSDatabase::FEED_MODE_STANDALONE."','".SSDatabase::FEED_MODE_CRON."') 		CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '".SSDatabase::FEED_MODE_STANDALONE."',
			feed_timestamp DATETIME NOT NULL,
			PRIMARY KEY (feed_id),
			UNIQUE  KEY `feed_uuid`  (`feed_uuid`),
      KEY `feed_owner` (`feed_owner`)
		) CHARACTER SET utf8 COLLATE utf8_unicode_ci;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		dbDelta( $sql );
	}

	public function deleteTable()
	{
    $db = $this->get_db();
		$sql = 'DROP TABLE ' . $this->get_table();

		return (( $db->query( $sql ) === FALSE )? 
      FALSE : TRUE );
	}

	public function get( Array $DATA_=NULL )
	{
		$result = FALSE;
    $db = $this->get_db();

		if ( @count( $DATA_ ) > 0 )
		{
			$sql =
				" SELECT * " .
				" FROM " . $this->get_table() .
				" WHERE " .
				( ( isset( $DATA_['feed_status'] 	) )? "feed_status = ".$db->prepare( "%s", $DATA_['feed_status'] ) : " ( feed_status = '".SSDatabase::FEED_STATUS_ACTIVE."' OR feed_status = '".SSDatabase::FEED_STATUS_DELETED."')" ) .
				( ( isset( $DATA_['feed_owner'] 	) )? " AND feed_owner = ".$db->prepare( "%d", $DATA_['feed_owner'] ) : "" ).
				( ( isset( $DATA_['feed_uuid']	 	) )? " AND 	feed_uuid 		= ".$db->prepare( "%s", $DATA_['feed_uuid'] ) : "" ) .
				( ( isset( $DATA_['feed_event_ids'] ) )? " AND 	feed_event_ids = ".$db->prepare( "%s", $DATA_['feed_event_ids'] ) : "" ) .
				( ( isset( $DATA_['feed_post_ids'] 	) )? " AND 	feed_post_ids	= ".$db->prepare( "%s", $DATA_['feed_post_ids']  ) : "" ) .
				( ( isset( $DATA_['feed_title'] 	) )? " AND 	feed_title		= ".$db->prepare( "%s", $DATA_['feed_title'] 	 ) : "" ) .
				( ( isset( $DATA_['feed_host'] 		) )? " AND 	feed_host		= ".$db->prepare( "%s", $DATA_['feed_host'] 	 ) : "" ) .
				( ( isset( $DATA_['feed_type'] 		) )? " AND 	feed_type		= ".$db->prepare( "%s", $DATA_['feed_type'] 	 ) : "" ) .
				( ( isset( $DATA_['feed_url'] 		) )? " AND 	feed_url		= ".$db->prepare( "%s", $DATA_['feed_url'] 		 ) : "" ) .
				( ( isset( $DATA_['feed_mode'] 		) )? " AND 	feed_mode		= ".$db->prepare( "%s", $DATA_['feed_mode'] 	 ) : "" );

			$result = $db->get_results( $sql, OBJECT_K );
		}
		return $result;
	}

	public function add( Array $DATA_=NULL )
	{
		if ( empty( $DATA_ ) )
		{
		  return FALSE;
    }

    $db = $this->get_db();
		$sql = "INSERT INTO " . $this->get_table() . //IGNORE
			" ( ".
				(( intval( @$DATA_['feed_id'] ) > 0 ) ? "feed_id," 		: "" ) .
				(( intval( @$DATA_['feed_owner'] ) > 0 ) ? "feed_owner," 		: "" ) .
				(( isset( $DATA_['feed_uuid'] )) ? "feed_uuid," : "" ) .
				(( isset(  $DATA_['feed_event_ids'] )) ? "feed_event_ids," 	: "" ) .
				(( isset(  $DATA_['feed_post_ids'] )) ? "feed_post_ids," 	: "" ) .
				(( isset(  $DATA_['feed_title'] )) ? "feed_title," : "" ) .
				(( isset(  $DATA_['feed_host'] )) ? "feed_host," : "" ) .
				(( isset(  $DATA_['feed_type'] )) ? "feed_type," : "" ) .
				(( isset(  $DATA_['feed_url'] )) ? "feed_url," 		: "" ) .
				(( isset(  $DATA_['feed_status'] )) ? "feed_status," : "" ) .
				(( isset(  $DATA_['feed_mode'] )) ? "feed_mode," : "" ) .
												 		 	 	 "feed_timestamp".
			" ) VALUES ( " .
				( ( intval( @$DATA_['feed_id']) > 0 )? $db->prepare( "%d", $DATA_['feed_id'] 			) . "," : "" ) .
				( ( intval( @$DATA_['feed_owner'] 	) > 0 )? $db->prepare( "%d", $DATA_['feed_owner'] 	 	) . "," : "" ) .
				( ( isset(  $DATA_['feed_uuid'] 		) )? $db->prepare( "%s", $DATA_['feed_uuid'] 		) . "," : "" ) .
				( ( isset(  $DATA_['feed_event_ids']  	) )? $db->prepare( "%s", $DATA_['feed_event_ids'] 	) . "," : "" ) .
				( ( isset(  $DATA_['feed_post_ids'] 	) )? $db->prepare( "%s", $DATA_['feed_post_ids']  	) . "," : "" ) .
				( ( isset(  $DATA_['feed_title'] 		) )? $db->prepare( "%s", $DATA_['feed_title'] 	 	) . "," : "" ) .
				( ( isset(  $DATA_['feed_host'] 		) )? $db->prepare( "%s", $DATA_['feed_host'] 	 	) . "," : "" ) .
				( ( isset(  $DATA_['feed_type'] 		) )? $db->prepare( "%s", $DATA_['feed_type'] 	 	) . "," : "" ) .
				( ( isset(  $DATA_['feed_url'] 		 	) )? $db->prepare( "%s", $DATA_['feed_url'] 		) . "," : "" ) .
				( ( isset(  $DATA_['feed_status'] 	 	) )? $db->prepare( "%s", $DATA_['feed_status'] 		) . "," : "" ) .
				( ( isset(  $DATA_['feed_mode'] 		) )? $db->prepare( "%s", $DATA_['feed_mode'] 	 	) . "," : "" ) .
														  "'".date( "Y-m-d H:i:s" )."' " .
			" ) " .
			(( intval( @$DATA_['feed_id'] ) > 0 )?
				" ON DUPLICATE KEY UPDATE ".
														 " feed_id			= " . $db->prepare( "%d", $DATA_['feed_id'] 		) .
				(( isset( $DATA_['feed_owner'] 		) )? ",feed_owner		= " . $db->prepare( "%d", $DATA_['feed_owner'] 		) : "" ) .
				(( isset( $DATA_['feed_uuid']	 	) )? ",feed_uuid 		= " . $db->prepare( "%s", $DATA_['feed_uuid'] 		) : "" ) .
				(( isset( $DATA_['feed_event_ids'] 	) )? ",feed_event_ids	= " . $db->prepare( "%s", $DATA_['feed_event_ids'] 	) : "" ) .
				(( isset( $DATA_['feed_post_ids'] 	) )? ",feed_post_ids	= " . $db->prepare( "%s", $DATA_['feed_post_ids'] 	) : "" ) .
				(( isset( $DATA_['feed_title'] 		) )? ",feed_title		= " . $db->prepare( "%s", $DATA_['feed_title'] 		) : "" ) .
				(( isset( $DATA_['feed_host'] 		) )? ",feed_host		= " . $db->prepare( "%s", $DATA_['feed_host'] 		) : "" ) .
				(( isset( $DATA_['feed_type'] 		) )? ",feed_type		= " . $db->prepare( "%s", $DATA_['feed_type'] 		) : "" ) .
				(( isset( $DATA_['feed_url'] 		) )? ",feed_url			= " . $db->prepare( "%s", $DATA_['feed_url'] 		) : "" ) .
				(( isset( $DATA_['feed_status'] 	) )? ",feed_status		= " . $db->prepare( "%s", $DATA_['feed_status'] 	) : "" ) .
				(( isset( $DATA_['feed_mode'] 		) )? ",feed_mode		= " . $db->prepare( "%s", $DATA_['feed_mode'] 		) : "" ) .
														 ",feed_timestamp	= '". date("Y-m-d H:i:s")."' "
				:
				""
			);

    return ( ( $db->query( $sql ) === FALSE )? FALSE : TRUE );
  }

	public function count( Array $DATA_=NULL )
	{
    $db = $this->get_db();
		$sql =
			" SELECT COUNT(*) " .
			" FROM ". $this->get_table() .
			" WHERE " .
			(( isset( $DATA_['feed_status'] )) ? "feed_status	= " . $db->prepare( "%s", $DATA_['feed_status'] 	) : "feed_status='".SSDatabase::FEED_STATUS_ACTIVE."'" ) .
			(( isset( $DATA_['feed_owner'])) ? " AND feed_owner = " . $db->prepare( "%d", $DATA_['feed_owner'] ) : "" ) .
			(( isset( $DATA_['feed_uuid']	)) ? " AND 	feed_uuid = " . $db->prepare( "%s", $DATA_['feed_uuid'] 		) : "" ) .
			(( isset( $DATA_['feed_event_ids'] 	)) ? " AND 	feed_event_ids	= " . $db->prepare( "%s", $DATA_['feed_event_ids']	) : "" ) .
			(( isset( $DATA_['feed_post_ids'] 	) )? " AND 	feed_post_ids	= " . $db->prepare( "%s", $DATA_['feed_post_ids']) : "" ) .
			(( isset( $DATA_['feed_title'] 		) )? " AND 	feed_title		= " . $db->prepare( "%s", $DATA_['feed_title'] 		) : "" ) .
			(( isset( $DATA_['feed_host'] 		) )? " AND 	feed_host		= " . $db->prepare( "%s", $DATA_['feed_host'] 		) : "" ) .
			(( isset( $DATA_['feed_type'] 		) )? " AND 	feed_type		= " . $db->prepare( "%s", $DATA_['feed_type'] 		) : "" ) .
			(( isset( $DATA_['feed_url'] 		) )? " AND 	feed_url		= " . $db->prepare( "%s", $DATA_['feed_url'] 		) : "" ) .
			(( isset( $DATA_['feed_mode'] 		) )? " AND 	feed_mode		= " . $db->prepare( "%s", $DATA_['feed_mode'] 		) : "" );

		return $db->get_var( $sql );
	}

	public function delete( Array $DATA_=NULL )
	{
    $db = $this->get_db();

		$sql =
			" DELETE " .
			" FROM ". $this->get_table() .
			" WHERE " .
			(( isset( $DATA_['feed_status'] ) )? " 		feed_status	= " . $db->prepare( "%s", $DATA_['feed_status'] ) : " feed_status='".SSDatabase::FEED_STATUS_ACTIVE."'" ) .
			(( isset( $DATA_['feed_id'] 	) )? " AND	feed_id	= " . $db->prepare( "%d", $DATA_['feed_id'] 	) : "" ) .
			(( isset( $DATA_['feed_owner'] 	) )? " AND 	feed_owner = " . $db->prepare( "%d", $DATA_['feed_owner']	) : "" ) .
			(( isset( $DATA_['feed_uuid']	) )? " AND 	feed_uuid	= " . $db->prepare( "%s", $DATA_['feed_uuid'] 	) : "" ) .
			(( isset( $DATA_['feed_title'] 	) )? " AND 	feed_title = " . $db->prepare( "%s", $DATA_['feed_title']  ) : "" ) .
			(( isset( $DATA_['feed_host'] 	) )? " AND 	feed_host	= " . $db->prepare( "%s", $DATA_['feed_host'] 	) : "" ) .
			(( isset( $DATA_['feed_mode'] 	) )? " AND 	feed_mode	= " . $db->prepare( "%s", $DATA_['feed_mode'] 	) : "" );

		return ( ( $db->query( $sql ) === FALSE )? FALSE : TRUE );
	}

	public function set_default_values()
	{
		$user = wp_get_current_user();

		$l = get_bloginfo( 'language' );
		$language = strtolower( $l{0}.$l{1} );

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

	public function update_feeds_daily()
	{
		$feeds = $this->get( 
      array( 'feed_mode' => SSDatabase::FEED_MODE_CRON ));

    $instance = SSImporterFactory::get_instance();
		if ( empty( $feeds ))
		{
      return;
    }

    foreach ( $feeds as $feed )
    {
      if ( $feed && property_exists( $feed, 'feed_url' ) == TRUE )
			{
        $feed_type = $feed->feed_type;
        $importer = $instance->create_importer($feed_type, $feed->feed_url);
        if(!empty($importer))
        {
          $importer->import();
        }
			}
		}
	}

  private function get_table()
  {
    return $this->_table;
  }

  private function get_db()
  {
    return $this->_wpdb;
  }
}
