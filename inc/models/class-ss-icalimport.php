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
class SSICalImport extends SSAbstractImport implements ICalLogger
{
  private $vCalendars = null;
  private $_ical_lines_data;

  public function get_ical_lines_data()
  {
    $stringUtil = new PHPStringUtil();

    if( !empty( $this->_ical_lines_data ))
    {
      return $this->_ical_lines_data;
    }

    $data = $this->get_raw_data();

    // First remove the linebreaks in the text
    $data = str_replace("\r\n ","",$data);
    
    $linesdata2 = array();
    $linesdata = explode(PHP_EOL, $data);
    foreach($linesdata as $linedata)
    {
      if($stringUtil->endsWith($linedata, "\r"))
      {
        $linedata = substr($linedata, 0, -1);
      }
      array_push($linesdata2, $linedata);
    }

    $this->_ical_lines_data = $linesdata2;
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
      $index = 0;
      if($vEvent->is_recurring())
      {
        $this->add_log('STARTDATE: ' . date("Y-m-d | h:i:sa", $vEvent->get_dt_startdate()));
        foreach( $vEvent->get_recurring_dates() as $date )
        {
          $this->add_log('RDATE: ' . date("Y-m-d | h:i:sa", $date));
          array_push($eiEvents, $this->read_event($vEvent, $date, $index));
          $index = $index + 1;
        }
      }
      else
      {
        array_push($eiEvents, $this->read_event($vEvent, $vEvent->get_dt_startdate(), $index));
      }
    }
    return $eiEvents;
  }

  private function read_event($vEvent, $startdate, $index)
  {
    $eiEvent = new EiCalendarEvent();

    $uid = $vEvent->get_uid();
    if($index > 0 )
    {
      $uid = $uid . '__' . $index;
    }
    $enddate = $startdate + ($vEvent->get_dt_enddate() - $vEvent->get_dt_startdate());
    $eiEvent->set_uid(sanitize_title($uid));
    $eiEvent->set_slug(sanitize_title($uid));
    $eiEvent->set_title($vEvent->get_summary());
    $eiEvent->set_description($vEvent->get_description());
    $eiEvent->set_link($vEvent->get_url());

    $eiEvent->set_start_date($startdate);
    $eiEvent->set_end_date($enddate);
    $eiEvent->set_all_day($vEvent->is_dt_allday());

    $eiEvent->set_published_date($vEvent->get_created());
    $eiEvent->set_updated_date($vEvent->get_lastmodified());

    $location = $vEvent->get_location();
    $length = strlen( 'http' );
    if (substr( $location, 0, $length ) === 'http')
    {
      // For the Heinrich Boll Stiftung gab es
      // Online Veranstaltungen wo bei LOCATION
      // der Link eingegeben war, wir erlauben
      // das zu übernehmen wenn der noch nicht durch
      // URL eingegeben ist.
      if(empty($eiEvent->get_link()))
      {
        $eiEvent->set_link($location);
      }
    }
    else
    {
      $wpLocH = new WPLocationHelper();
      $loc = $wpLocH->create_from_free_text_format($location);
      if($wpLocH->is_valid($loc))
      {
        $eiEvent->set_location($loc);
      }
    }

    $eiEvent->set_contact_name($vEvent->get_organizer_name());
    $eiEvent->set_contact_email($vEvent->get_organizer_email());
    return $eiEvent;
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
        $vCal = new ICalVCalendar($this);
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
        $vEvent = new ICalVEvent($this);
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
        $vCal->parse_value($this->get_key($line), 
                           $this->get_value($line));
        continue;
      }

      $vEvent->parse_value( $this->get_key($line), 
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
    $value = substr($value, 1);
    return $value;
  }
}



