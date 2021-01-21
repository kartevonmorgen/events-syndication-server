<?php
/**
  * Controller SSICalImport
  * Control the import of ESS feed
  *
  * @author     Sjoerd Takken
  * @copyright 	No Copyright.
  * @license   	GNU/GPLv2, see http://www.gnu.org/licenses/gpl-2.0.html
  * @link		    https://github.com/kartevonmorgen
  */
class SSICalImport extends SSAbstractImport
{
  private $vCalendars = null;
  private $_ical_lines_data;

  public function get_ical_lines_data()
  {
    if( !empty( $this->_ical_lines_data ))
    {
      return $this->_ical_lines_data;
    }

    $data = $this->get_raw_data();
    $data = str_replace("\r\n ","",$data);

    $this->_ical_lines_data = explode(PHP_EOL, 
                                      $data);
    return $this->_ical_lines_data;
  }

	/**
	 * Simple test to check if the URL targets to an existing file.
	 *
	 * @param 	String	feed_url	URL of the ess feed file to test
	 * @return	Boolean	result		return a boolean value.
	 */
	public function is_feed_valid()
	{
    if( empty($this->get_vcalendar()))
    {
      return false;
    }
    return true;
	}

  public function read_feed_uuid()
  {
    $vCal = $this->get_vcalendar();
    if( empty( $vCal->get_prodid()))
    {
      $this->set_error('No PRODID found for ical feed ');
      return null;
    }
    return $vCal->get_prodid();
  }

  public function read_feed_title()
  {
    $vCal = $this->get_vcalendar();
    if( empty( $vCal->get_name()))
    {
      return $vCal->get_prodid();
    }
    return $vCal->get_name();
  }

  public function read_events_from_feed()
  {
    $vCal = $this->get_vcalendar();

    $eiEvents = array();
    foreach ( $vCal->get_events() as $vEvent )
		{
      array_push($eiEvents, $vEvent->get_ei_event());
    }
    return $eiEvents;
  }

  private function get_vcalendar()
  {
    if(empty( $this->get_vcalendars()))
    {
      return null;
    }
    return reset($this->get_vcalendars());
  }

  private function get_vcalendars()
  {
    if(!empty($this->vCalendars))
    {
      return $this->vCalendars;
    }

    $vCals = array();
    $vCal = null;
    foreach($this->get_ical_lines_data() as $line)
    {
      if ($this->is_element($line, 'BEGIN:VCALENDAR'))
      {
        $vCal = new VCalendar();
        continue;
      }

      if ($this->is_element($line, 'END:VCALENDAR'))
      {
        array_push($vCals, $vCal);
        $vCal = null;
        continue;
      }

      if ($this->is_element($line, 'BEGIN:VEVENT'))
      {
        $vEvent = new VEvent($this);
        continue;
      }

      if ($this->is_element($line, 'END:VEVENT'))
      {
        $vCal->add_event($vEvent);
        $vEvent = null;
        continue;
      }

      if(empty($vCal))
      {
        continue;
      }

      if(empty($vEvent))
      {
        $vCal->set_value($this->get_key($line), 
                         $this->get_value($line));
        continue;
      }

      $vEvent->set_value( $this->get_key($line), 
                          $this->get_value($line));
    }

    $this->vCalendars = $vCals;
    return $this->vCalendars;
  }

  private function is_element($line, $element)
  {
    return stristr($line, $element) !== false;
  }

  private function get_key($line)
  {
    return strstr($line, ':', true);
  }

  private function get_value($line)
  {
    $value = strstr($line, ':'); 
    return substr($value, 1);
  }
}

class VCalendar
{
  private $vEvents;
  private $link;
  private $name;
  private $prodid;
  
  function __construct()
  {
    $this->vEvents = array();
    $this->prodid = null;
    $this->name = null;
    $this->link = null;
  }

  function set_value($key, $value)
  {
    switch ($key) 
    {
      case 'X-ORGINAL_URL':
        $this->set_link($value);
        break;
      case 'X-WR-CALNAME':
        $this->set_name($value);
        break;
      case 'PRODID':
        $this->set_prodid($value);
        break;
    }
  }

  function add_event($vEvent)
  {
    array_push($this->vEvents, $vEvent);
  }

  function get_events()
  {
    return $this->vEvents;
  }

  function set_name($name)
  {
    $this->name = $name;
  }

  function get_name()
  {
    return $this->name;
  }

  function set_link($link)
  {
    $this->link = $link;
  }

  function get_link()
  {
    return $this->link;
  }

  function set_prodid($prodid)
  {
    $this->prodid = $prodid;
  }

  function get_prodid()
  {
    return $this->prodid;
  }
}

class VEvent
{
  private $eiEvent;
  private $importer;

  function __construct($importer)
  {
    $this->importer = $importer;
    $this->eiEvent = new EiCalendarEvent();
  }

  function get_ei_event()
  {
    return $this->eiEvent;
  }

  function get_importer()
  {
    return $this->importer;
  }

  function log($log)
  {
    $this->get_importer()->add_log($log);
  }

  function set_value($key, $value)
  {
    $eiEvent = $this->get_ei_event();
    $keys = explode(';', $key);
    if(empty($keys))
    {
      return;
    }

    $firstpartofkey = reset($keys);
    switch ($firstpartofkey) 
    {
      case 'DTSTART':
        $vEventDate = new VEventDate($this->get_importer(), $key, $value);
        $vEventDate->parse();
        $eiEvent->set_start_date($vEventDate->getTimestamp());
        $eiEvent->set_all_day($vEventDate->isDate());
        break;
      case 'DTEND':
        $vEventDate = new VEventDate($this->get_importer(), $key, $value);
        $vEventDate->parse();
        $eiEvent->set_end_date($vEventDate->getTimestamp());
        $eiEvent->set_all_day($vEventDate->isDate());
        break;
      case 'LAST_MODIFIED':
        $vEventDate = new VEventDate($this->get_importer(), $key, $value);
        $vEventDate->parse();
        $eiEvent->set_updated_date($vEventDate->getTimestamp());
        break;
      case 'CREATED':
        $vEventDate = new VEventDate($this->get_importer(), $key, $value);
        $vEventDate->parse();
        $eiEvent->set_published_date($vEventDate->getTimestamp());
        break;
      case 'UID':
        //$this->log( 'UID ' . $value );
        $eiEvent->set_uid($value);
        break;
      case 'SUMMARY':
        $eiEvent->set_title($value);
        break;
      case 'DESCRIPTION':
        $text = new VEventText($this->get_importer(), $value);
        $text->parse();
        $eiEvent->set_description($text->getResult());
        break;
      case 'URL':
        $eiEvent->set_link($value);
        break;
      case 'LOCATION':
        $length = strlen( 'http' );
        if (substr( $value, 0, $length ) === 'http')
        {
          // For the Heinrich Boll Stiftung gab es
          // Online Veranstaltungen wo bei LOCATION
          // der Link eingegeben war, wir erlauben
          // das zu übernehmen wenn der noch nicht durch
          // URL eingegeben ist.
          if(empty($eiEvent->get_link()))
          {
            $eiEvent->set_link($value);
          }
        }
        else
        {
          $wpLocH = new WPLocationHelper();
          $loc = $wpLocH->create_from_free_text_format($value);
          if($wpLocH->is_valid($loc))
          {
            $eiEvent->set_location($loc);
          }
        }
        break;
    }
  }

}

class VEventDate
{
  private $dateString;
  private $isDate;
  private $timestamp;
  private $importer;

  public function __construct($importer, $key, $dateStr)
  {
    $this->importer = $importer;
    $this->key = $key;
    $this->dateString = $dateStr;
  }

  public function get_importer()
  {
    return $this->importer;
  }

  public function log($log)
  {
    $this->get_importer()->add_log($log);
  }

  public function getKey()
  {
    return $this->key;
  }

  public function getDateString()
  {
    return $this->dateString;
  }

  private function setTimestamp($timestamp)
  {
    $this->timestamp = $timestamp;
  }

  public function getTimestamp()
  {
    return $this->timestamp;
  }

  private function setDate($isDate)
  {
    $this->isDate = $isDate;
  }

  public function isDate()
  {
    return $this->isDate;
  }

  public function parse()
  {
    $key = $this->getKey();
    $value = $this->getDateString();

    if(strpos($key, 'VALUE=DATE-TIME') !== false)
    {
      $this->setDate(false);
    }
    else if(strpos($key, 'VALUE=DATE') !== false)
    {
      $value = $value . 'T000000Z';
      $this->setDate(true);
    }
    else
    {
      $this->setDate(false);
    }

    $ts = $this->iCalDateTimeToUnixTimestamp($value);
    $this->setTimestamp($ts);
  }

  /** 
   * Return Unix timestamp from ical date time format 
   * 
   * @param {string} $icalDate A Date in the format YYYYMMDD[T]HHMMSS[Z] or
   *                           YYYYMMDD[T]HHMMSS
   *
   * @return {int} 
   */ 
  public function iCalDateTimeToUnixTimestamp($icalDate) 
  { 
    $icalDate = str_replace('T', '', $icalDate); 
    $icalDate = str_replace('Z', '', $icalDate); 

    $pattern  = '/([0-9]{4})';   // 1: YYYY
    $pattern .= '([0-9]{2})';    // 2: MM
    $pattern .= '([0-9]{2})';    // 3: DD
    $pattern .= '([0-9]{0,2})';  // 4: HH
    $pattern .= '([0-9]{0,2})';  // 5: MM
    $pattern .= '([0-9]{0,2})/'; // 6: SS
    preg_match($pattern, $icalDate, $date); 

    // Unix timestamp can't represent dates before 1970
    if ($date[1] <= 1970) 
    {
      return false;
    } 
    // Unix timestamps after 03:14:07 UTC 2038-01-19 might cause an overflow
    // if 32 bit integers are used.
    $timestamp = mktime((int)$date[4], 
                        (int)$date[5], 
                        (int)$date[6], 
                        (int)$date[2],
                        (int)$date[3], 
                        (int)$date[1]);
    return  $timestamp;
  } 

}

class VEventText
{
  private $importer;
  private $value;
  private $result;

  public function __construct($importer, $value)
  {
    $this->importer = $importer;
    $this->value = $value;
  }

  private function getValue()
  {
    return $this->value;
  }

  private function get_importer()
  {
    return $this->importer;
  }

  private function setResult($result)
  {
    $this->result = $result;
  }

  public function getResult()
  {
    return $this->result;
  }

  public function parse()
  {
    $value = $this->getValue();
    $value = str_replace("\\n", "<br>", $value);
    $this->setResult($value);
  }


}

