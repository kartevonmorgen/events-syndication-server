<?php
/**
  * Model SS_IO (Input/Output)
  * Manage the I/O between user, local server and web-services
  *
  * @author  	Brice Pissard, Sjoerd Takken
  * @copyright 	No Copyright.
  * @license   	GNU/GPLv2, see http://www.gnu.org/licenses/gpl-2.0.html
  * @link		https://github.com/kartevonmorgen/
  */
final class SS_IO
{
	const EM_ESS_ARGUMENT 	= 'em_ess';

	const HTTPS				= 'https://';
	const SS_WEBSITE	= 'https://github.com/kartevonmorgen/';
	const PLUGIN_WEBSITE	= 'http://wp-events-plugin.com';
	const CURL_LIB_URL		= 'http://php.net/manual/en/book.curl.php';

	const CRON_EVENT_HOOK	= 'SS_daily_event_hook';

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
      self::$instance = new SS_IO();
    }
    return self::$instance;
  }

	public function start()
	{
		add_filter( 'rewrite_rules_array', 
                array( $this, 'get_rewrite_rules_array'));
		add_filter( 'query_vars', 
                array( $this,	'get_query_vars'));
	}

	public function set_activation()
	{
		flush_rewrite_rules();

		if ( !current_user_can( 'activate_plugins' ) ) 
    {
      return;
    }

    $plugin = isset( $_REQUEST[ 'plugin' ] ) ? $_REQUEST[ 'plugin' ] : EventsSyndicationServer::NAME;
    check_admin_referer( "activate-plugin_{$plugin}" );

    $db = SSDatabase::get_instance();
    $db->createTable();

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

	public function set_deactivation()
	{
		if ( !current_user_can( 'activate_plugins' ) ) 
    {
      return;
    }

    $plugin = isset( $_REQUEST[ 'plugin' ] ) ? $_REQUEST[ 'plugin' ] : EventsSyndicationServer::NAME;
    check_admin_referer( "deactivate-plugin_{$plugin}" );

		// DEBUG: remove DB while desactivating the plugin
		//if( !EM_MS_GLOBAL || (EM_MS_GLOBAL && is_main_blog()) )
		//	ESSDatabase::deteteTable();

		// -- Remove Schedule Hook (CRON tasks)
		wp_clear_scheduled_hook( SS_IO::CRON_EVENT_HOOK );
	}

	public function set_uninstall()
  {
    if ( ! current_user_can( 'activate_plugins' ) ) 
    {
      return;
    }

    check_admin_referer( 'bulk-plugins' );

    $db = SSDatabase::get_instance();
    $db->deleteTable();

		// -- Remove Schedule Hook (CRON tasks)
		wp_clear_scheduled_hook( SS_IO::CRON_EVENT_HOOK );

		// Important: 
    //   Check if the file is the one that was registered 
    //   during the uninstall hook.
    if ( __FILE__ != WP_UNINSTALL_PLUGIN ) 
    {
      return;
    }
  }

	public function get_rewrite_rules_array( $rules )
	{
		return $rules + array( "/ss/?$"=>'index.php?'. SS_IO::EM_ESS_ARGUMENT . '=1' );
	}

	public function get_query_vars( $vars )
	{
		array_push( $vars, SS_IO::EM_ESS_ARGUMENT );
		return $vars;
	}

	public function get_curl_result( $target_url, $feed_url='' )
	{
		$ch = @curl_init();

		$post_data = array(
			'REMOTE_ADDR' => (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR']:''),
			'SERVER_ADMIN' => (isset($_SERVER['SERVER_ADMIN']) ? $_SERVER['SERVER_ADMIN' ]:''),
			'HTTP_HOST' => (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST']:''),
			'REQUEST_URI'	=> (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI']:''),
			'PROTOCOL' => ( ( EVENTS_SS_SECURE )? 'https://' : 'http://' ),
			'feed'			=> urlencode( $feed_url ),
		);

		if ( $ch != FALSE )
		{
			curl_setopt( $ch, CURLOPT_URL, $target_url );
			curl_setopt( $ch, CURLOPT_COOKIEJAR, $this->tmp() . '/cookies' );
			curl_setopt( $ch, CURLOPT_REFERER, (isset($_SERVER[ 'REQUEST_URI' ]) ? $_SERVER[ 'REQUEST_URI' ] : '') );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_data );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt( $ch, CURLOPT_VERBOSE, 1);
			curl_setopt( $ch, CURLOPT_FAILONERROR, 1);
			curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0);

			$result = curl_exec( $ch );

			//d( $target_url, $feed_url, urlencode( $feed_url ), $result );

			return $result;
		}
		else
		{
			if ( ini_get( 'allow_url_fopen' ) )
			{
				$opts = array(
					'http' => array(
						'method'  => 'POST',
						'header'  => "Content-Type: application/x-www-form-urlencoded",
						'content' => http_build_query( $post_data ),
						'timeout' => (60*20)
					)
				);

				$result = @file_get_contents( $target_url, FALSE, stream_context_create( $opts ), -1, 40000 );

				return $result;
			}
			else
			{
				$file = $target_url . "?";

				foreach ( $post_data as $att => $value )
        {
					$file .= $att . "=" . urlencode( $value ) . "&";
        }

				$result = @exec( "wget -q \"" . $file . "\"" );

				if ( $result == FALSE )
				{
          $notices = SSNotices::get_instance();
					$notices->add_error(
						__( "PHP cURL must be installed on your server or PHP parameter 'allow_url_fopen' must be set to TRUE: ", 'ss-events' ).
						$this->get_curl_lib_link()
					);
				}
				else
        {
					return $result;
        }
			}
		}
		return FALSE;
	}

  private function get_curl_lib_link()
  {
    return "<a href='".SS_IO::CURL_LIB_URL."' target='_blank'>" .
      __( "Client URL Library", 'ss-events' ) .
    "</a>";
  }

	/**
     * Get a usable temp directory
     *
     * Adapted from Solar/Dir.php
     * @author Paul M. Jones <pmjones@solarphp.com>
     * @license http://opensource.org/licenses/bsd-license.php BSD
     * @link http://solarphp.com/trac/core/browser/trunk/Solar/Dir.php
     *
     * @return string
     */
    private function tmp()
    {
        static $tmp = null;

        if ( !$tmp )
        {
            $tmp = function_exists( 'sys_get_temp_dir' )? sys_get_temp_dir() : $this->_tmp();
			$tmp = rtrim( $tmp, DIRECTORY_SEPARATOR );
        }
        return $tmp;
    }

    /**
     * Returns the OS-specific directory for temporary files
     *
     * @author Paul M. Jones <pmjones@solarphp.com>
     * @license http://opensource.org/licenses/bsd-license.php BSD
     * @link http://solarphp.com/trac/core/browser/trunk/Solar/Dir.php
     *
     * @return string
     */
    private function _tmp()
    {
        // non-Windows system?
        if ( strtolower( substr( PHP_OS, 0, 3 ) ) != 'win' )
        {
            $tmp = empty($_ENV['TMPDIR']) ? getenv( 'TMPDIR' ) : $_ENV['TMPDIR'];
            return ($tmp)? $tmp : '/tmp';
        }

        // Windows 'TEMP'
        $tmp = empty($_ENV['TEMP']) ? getenv('TEMP') : $_ENV['TEMP'];
        if ($tmp) {return $tmp;}

        // Windows 'TMP'
        $tmp = empty($_ENV['TMP']) ? getenv('TMP') : $_ENV['TMP'];
        if ($tmp) {return $tmp;}

       	// Windows 'windir'
        $tmp = empty($_ENV['windir']) ? getenv('windir') : $_ENV['windir'];
        if ($tmp) {return $tmp;}

        // final fallback for Windows
        return getenv('SystemRoot') . '\\temp';
    }

}
