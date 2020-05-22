<?php
/**
  * This instance is used to create an importer,
  * depending on the Feed type
  *
  * @author     Sjoerd Takken 
  * @copyright  No Copyright.
  * @license   	GNU/GPLv2, see http://www.gnu.org/licenses/gpl-2.0.html
  * @link       https://github.com/kartevonmorgen
  */
final class SSImporterFactory
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
      self::$instance = new SSImporterFactory();
    }
    return self::$instance;
  }

  public function create_importer($feed_url)
  {
    $contains = 'em_ess=1';
    if( strpos($feed_url, $contains) !== FALSE)
    {
      return new SSESSImport($feed_url);
    }

    $contains = 'ical=1';
    if( strpos($feed_url, $contains) !== FALSE)
    {
      return new SSICalImport($feed_url);
    }
    return null;
  }

}
