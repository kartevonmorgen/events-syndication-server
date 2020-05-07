<?php
/**
  * View UIFeedAdminPage
  * Container of all the structure of the admin page to manage the ESS settings.
  *
  * @author  	  Brice Pissard, Sjoerd Takken
  * @copyright 	No Copyright.
  * @license   	GNU/GPLv2, see http://www.gnu.org/licenses/gpl-2.0.html
  * @link		    https://github.com/kartevonmorgen
  */
final class UIFeedAdminPage extends UIPage
{
  private $_feedadmincontrol; 

  public function set_feedadmincontrol($feedadmincontrol)
  {
    $this->_feedadmincontrol = $feedadmincontrol;
  }

  public function get_capability()
  {
    // can be shown for every user
    return 'manage_event_feeds';
  }

  public function admin_init()
  {
  }

  public function show_page()
  {

    //SS_Control_admin::control_requirement();
    $this->_feedadmincontrol->control_import();
    $this->_feedadmincontrol->control_nav_actions();

    wp_enqueue_script('jquery');

    /* wp_enqueue_style('admin', EVENTS_SS_PATH . 'assets/css/admin.css',                FALSE, EVENTS_SS_VERSION, TRUE);*/

//    wp_enqueue_script('settings', EM_DIR_URI  . 'includes/js/admin-settings.js', FALSE, EVENTS_SS_VERSION, TRUE );
    wp_enqueue_script('toggles', EVENTS_SS_PATH . 'assets/js/jquery.toggles.min.js', FALSE, EVENTS_SS_VERSION, TRUE );
    wp_enqueue_script('maphilight', EVENTS_SS_PATH . 'assets/js/jquery.maphilight.js', FALSE, EVENTS_SS_VERSION, TRUE );
    wp_enqueue_script('timezone', EVENTS_SS_PATH . 'assets/js/jquery.timezone-picker.js', FALSE, EVENTS_SS_VERSION, TRUE );
    wp_enqueue_script('admin', EVENTS_SS_PATH . 'assets/js/admin.js', FALSE, EVENTS_SS_VERSION, TRUE);

		?><style  type="text/css" charset="utf-8"><?php include( EVENTS_SS_DIR.'/assets/css/admin.css'); ?></style>

		<div id="loader" style="display:none;">
			<p style="background-image:url('<?php echo EVENTS_SS_URL;?>/assets/img/loader_orange.gif');"></p>
			<div><?php _e( 'please wait...', 'events-ss' ); ?></div>
		</div>
		<div class="wrap">
			<div id="icon-options-general" class="icon32" style="background:url('<?php echo EVENTS_SS_URL;?>/assets/img/SS_icon_32x32.png') 0 0 no-repeat;"><br/></div>
			<form id="em-options-form" method="post"  enctype='multipart/form-data' target="_self" onsubmit="admin.loader();"><?php
				echo SSNotices::get_instance();
                //SSDatabase::update_feeds_daily();
				$this->show_feed_form();
				$this->show_import_page();
			?></form>
    </div><?php
  }

	private function show_feed_form()
	{
    $notices = SSNotices::get_instance();

    ?><div id="add_feed_form" class="highlight" style="display:block;">
			<?php $this->get_explain_block(
				"This section control the feeds imported to this websites.". 
				"<br/>".
				"<b>Import an feed to get the events, coming from another website, presented and updated in this website.</b>"
				//"You can specify if the events aggregated have to be updated daily (to always have the latest information coming from the original websites)."
			);?>
			<div id="titlediv">
				<input type="text" id="title" name="feed_url" autocomplete="off" value="<?php echo (( isset( $_REQUEST['feed_url'] ) && @$notices->get_errors()>0 )? $_REQUEST['feed_url']:SS_IO::HTTPS); ?>" onclick="admin.control_import_field(jQuery(this));" />
				<div class="iphone <?php echo ( ( isset( $_REQUEST['feed_mode'] ) )? ( ( $_REQUEST['feed_mode'] == 'on' )? 'on' : 'off' ) : 'off' );?>" id="mode" data-checkbox="feed_mode_checkbox" data-ontext="<?php _e('Update Daily','events-ss'); ?>" data-offtext="<?php _e('Import Once','events-ss'); ?>"><?php _e('Update Daily','events-ss'); ?></div>
				<input type="checkbox" class=":w
        feed_mode_checkbox" id="feed_mode" name="feed_mode" <?php echo( ( isset( $_REQUEST['feed_mode'] ) )? ( ( $_REQUEST['feed_mode'] == 'on' )? "checked='checked'" : '' ) : '' );?> />
				<input type="submit" value="<?php _e('ADD','events-ss'); ?>" class="button-primary" id="bt_add_feed" />
			</div>
		</div><?php
	}

	private function show_nav_action()
	{
		$view = strtolower( ( isset( $_REQUEST['view'] ) )? $_REQUEST['view'] : 'all' );

		?><div class="tablenav top">
			<div class="alignleft actions">
				<select name="action">
					<option value="-1" selected="selected"><?php _e('Bulk Actions','events-ss'); ?></option>
					<option value="active"><?php echo (( empty( $view ) || $view == 'all' )?__('Move to Active','events-ss'):__('Restore','events-ss') );?></option>
					<option <?php echo (( $view == 'trash' )?'value="full_deleted">Delete Permanently':'value="deleted">'.__('Move to Trash','events-ss') );?></option>
					<?php if ( empty( $view ) || $view == 'all' ) : ?>
					<option value="update_cron"><?php _e('Save Daily Updates','events-ss'); ?></option>
					<?php endif; ?>
				</select>
				<input class="button action" type="submit" value="<?php _e('Apply','events-ss'); ?>" name="apply_table_filter" />
			</div>
		</div><?php
	}

	// === SYNDICATION TAB ===
	private function show_import_page()
	{
    $db = SSDatabase::get_instance();
		$view = strtolower( ( isset( $_GET['view'] ) )? $_GET['view'] : 'all' );
		$active_count = $db->count(array('feed_status'=>SSDatabase::FEED_STATUS_ACTIVE));
		$trash_count = $db->count(array('feed_status'=>SSDatabase::FEED_STATUS_DELETED));
		
    $efi_query = array();
    $efi_query['feed_status'] = ( $view == 'trash' ) ? 
      SSDatabase::FEED_STATUS_DELETED : SSDatabase::FEED_STATUS_ACTIVE ;
    
    $current_user = wp_get_current_user();
    if( !user_can( $current_user, 'manage_other_event_feeds' ))
    {
      $efi_query['feed_owner'] = $current_user->ID;
    }

    $efi = $db->get( $efi_query );

		$url_all_events 	= em_add_get_params( $_SERVER['REQUEST_URI'], array('view'=>'all'   ) )."#import";
		$url_trash_events 	= em_add_get_params( $_SERVER['REQUEST_URI'], array('view'=>'trash' ) )."#import";

		if ( $view == 'trash' && $trash_count <= 0 )
		{
			@wp_redirect( $url_all_events );
			exit;
		}
		//d( $efi );

		?><div class="em-menu-import em-menu-group" style="display:block;">

			<!-- PAGES NAV -->
			<div class="subsubsub">
				<a href='<?php echo $url_all_events; ?>' <?php echo ( empty( $view ) || $view == 'all' )? 'class="current"':''; ?>><?php _e ( 'All', 'events-ss' ); ?> <span class="count">(<?php echo $active_count; ?>)</span></a>
				<?php if ($trash_count>0):?>&nbsp;|&nbsp;
				<a href='<?php echo $url_trash_events; ?>' <?php echo ( $view == 'trash' )? 'class="current"':''; ?>><?php _e ( 'Trash', 'events-ss' ); ?> <span class="count">(<?php echo $trash_count;   ?>)</span></a>
				<?php endif ?>
			</div><?php

      // ACTIONS NAV
      $this->show_nav_action();

      $rowno = 0;

      $next_cron = NULL;
      foreach( get_option( 'cron' ) as $timestamp => $date_ )
      {
        if ( isset( $date_[ SS_IO::CRON_EVENT_HOOK ] ) )
        {
          $next_cron = $timestamp;
          break;
        }
      }

      ?><!-- LIST -->
      <input type="hidden" value="" id="selected_event_id" name="selected_event_id" />
      <table class='widefat'>
        <thead>
          <tr>
            <th class='manage-column column-cb check-column' scope='col'><input type='checkbox' class='select-all' value='1'/></th>
            <th width="18"></th>
            <th><?php _e('Title', 'events-ss') ?></th>
            <th><?php _e('Nb Events', 'events-ss') ?></th>
            <th><?php _e('Owner', 'events-ss') ?></th>
            <th width="90"><?php  _e('Update Daily','events-ss') ?></th>
            <th width="100"><?php _e('Last Update', 'events-ss') ?></th>
            <th width="100"><?php _e('Next Update', 'events-ss') ?></th>
            <th width="45"><?php  _e('Update', 		'events-ss') ?></th>
            <th width="50"><?php  _e('View',		'events-ss') ?></th>
          </tr>
        </thead>
        <tfoot>
          <tr>
            <th class='manage-column column-cb check-column' scope='col'><input type='checkbox' class='select-all' value='1'/></th>
            <th></th>
            <th><?php _e('Title', 'events-ss') ?></th>
            <th><?php _e('Nb Events', 'events-ss') ?></th>
            <th><?php _e('Owner', 'events-ss') ?></th>
            <th><?php _e('Update Daily','events-ss') ?></th>
            <th><?php _e('Last Update', 'events-ss') ?></th>
            <th><?php _e('Next Update', 'events-ss') ?></th>
            <th><?php _e('Update', 'events-ss') ?></th>
            <th><?php _e('View', 'events-ss') ?></th>
          </tr>
        </tfoot>
        <tbody><?php

          if ( count( $efi ) > 0 )
          {
            foreach ( $efi as $feed )
            {
              $user = get_user_by( 'id', $feed->feed_owner );
              $owner = ((isset($user))?$user->user_login:'');
              $event_ids = explode(',', $feed->feed_event_ids );
              $class = ($rowno % 2) ? 'alternate' : '';
              $rowno++;

            ?><tr class="<?php echo $class; ?>">
              <th class="check-column" scope="row">
                <label class="screen-reader-text" for="cb-select-<?php echo $feed->feed_id; ?>">Select My first event</label>
                <input type='checkbox' class='row-selector' id="cb-select-<?php echo $feed->feed_id; ?>" value='<?php echo $feed->feed_id; ?>' name='feeds[]'/>
                <div class="locked-indicator"></div>
              </th>
              <td align="center">
                <img src="http://www.google.com/s2/favicons?domain=http://<?php echo $feed->feed_host; ?>" width="16" height="16" alt="<?php echo $feed->feed_host; ?>" />
              </td>
              <td>
                <strong class="row-title"><?php echo $feed->feed_title; ?></strong>
              </td>
              <td align="left">
                <?php echo count( $event_ids ); ?>
              </td>
              <td class="author column-author">
                <a><?php echo $owner; ?></a>
              </td>
              <td>
                <?php $this->button_checkbox( array(
                  'checked' => (( $feed->feed_mode == SSDatabase::FEED_MODE_CRON )? TRUE : FALSE ),
                  'on' => __('ON','events-ss'),
                  'off' => __( 'OFF', 'events-ss' ),
                  'id' => 'feed_mode_'.$feed->feed_id,
                  'onchecked' => "jQuery('#cb-select-".$feed->feed_id."').prop('checked',true);",
                  'onunchecked'	=> "jQuery('#cb-select-".$feed->feed_id."').prop('checked',true);"
                ));?>
              </td>
              <td>
                <h4><?php echo sprintf( __("%s ago",'events-ss'), human_time_diff( strtotime( $feed->feed_timestamp ) ) ); ?></h4>
              </td>
              <td>
                <h4><?php echo ( ( $feed->feed_mode == SSDatabase::FEED_MODE_CRON && $next_cron != NULL )? sprintf( __("in %s",'events-ss'), human_time_diff( $next_cron ) ) : '-' ); ?></h4>
              </td>
              <td>
                <button title="<?php _e( "Reimport the feed to update the event.", 'events-ss' );?>" onmousedown="admin.set_event_id('<?php echo $feed->feed_id; ?>');" class="button-primary reload_box" name="update_once" value="<?php echo urlencode( $feed->feed_url );?>" style="background-image:url('<?php echo EVENTS_SS_URL;?>/assets/img/reload_icon_24x24.png');background-position:7px 2px;background-repeat:no-repeat;"></button>
              </td>
              <td>
                <a href="<?php echo $feed->feed_url; ?>" target="_blank" title="<?php _e( "Download the Feed.",'events-ss'); ?>">
                  <div class="button-primary arrow_cont">
                    <div class="arrow_box"></div>
                  </div>
                </a>
              </td>
            </tr><?php
            }
          }
          else
          {
            ?><tr>
              <th colspan="8" style="text-align:left;"><?php echo _e( "No Feeds Found",'events-ss'); ?></th>
            </tr><?php
          }
        ?></tbody>
      </table>

    </div><?php
  }

	public function button_checkbox( Array $DATA_=NULL )
	{
		if(empty($DATA_))return;
		?><div
			class			= "toggle hide-if-no-js iphone <?php echo ( ( $DATA_['checked'] == true )? 'on' : 'off' ); ?>"
			id				= "<?php echo $DATA_['id']; ?>-div"
			data-checkbox	= "<?php echo $DATA_['id']; ?>-checkbox"
			data-ontext		= "<?php echo $DATA_['on']; ?>"
			data-offtext	= "<?php echo $DATA_['off']; ?>">
			<?php echo ( ( $DATA_['checked'] == true )? $DATA_['on'] : $DATA_['off'] );	?>
		</div>
		<input
			<?php if(isset($DATA_['onchecked'])||isset($DATA_['onunchecked'])) : ?>
				onchange = "if(jQuery(this).is(':checked')){<?php echo $DATA_['onchecked'];?>}else{<?php echo $DATA_['onunchecked']; ?>};"
			<?php endif; ?>
			type	= "checkbox"
			class	= "<?php echo $DATA_['id']; ?>-checkbox input-checkbox"
			name	= "<?php echo $DATA_['id']; ?>"
			<?php echo( ( $DATA_['checked'] == true )? "checked='checked'" : '' );?>
		/>
    	<?php
	}

	public function get_explain_block( $txt='' )
	{
		?><div class="ss_explain">
			<img src="<?php echo EVENTS_SS_URL. '/assets/img/info_icon_30x30.png'; ?>" alt="<?php _e('Info','events-ss');?>" />
			<p><?php _e( $txt, "events-ss" ); ?></p>
		</div><?php
	}

  public function get_ahref( $url )
  {
    return "<a href='".$url."' target='_blank'>" .$url ."</a>";
  }


}
