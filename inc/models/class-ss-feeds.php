<?php


class SSFeeds 
{
  private static $instance = null;

  private function __construct() 
  {
  }

  public function start()
  {
    add_action('init', 
               array($this,'create_post_type'));
    add_action('manage_ssfeed_posts_columns',
               array($this,'columns'),10,2);
    add_action('manage_ssfeed_posts_custom_column',
               array($this,'column_data'),11,2);
    
    add_action('admin_menu', 
               array($this, 'submenus'));
    add_filter('post_row_actions', 
               array($this, 'update_feedlink'), 10, 2);

    add_filter('posts_join', 
               array($this, 'join'),10,1);
    add_filter('posts_orderby',
               array($this, 'set_default_sort'),20,2);



  }

  public function create_post_type() 
  {
    $labels = array(
      'name'               => 'Event Feeds',
      'singular_name'      => 'Event Feed',
      'menu_name'          => 'Event Feeds',
      'name_admin_bar'     => 'Event Feed',
      'add_new'            => 'Add New',
      'add_new_item'       => 'Add New Feed',
      'new_item'           => 'Neue Feed',
      'edit_item'          => 'Edit Feed',
      'view_item'          => 'View Feed',
      'all_items'          => 'All Feeds',
      'search_items'       => 'Search Feeds',
      'parent_item_colon'  => 'Parent Feed',
      'not_found'          => 'No Feeds Found',
      'not_found_in_trash' => 'No Feeds Found in Trash'
      );

    $args = array(
      'labels'              => $labels,
      'public'              => true,
      'exclude_from_search' => true,
      'publicly_queryable'  => false,
      'show_ui'             => true,
      'show_in_nav_menus'   => true,
      'show_in_menu'        => true,
      'show_in_admin_bar'   => true,
      'menu_position'       => 5,
      'menu_icon'           => 'dashicons-admin-appearance',
      'hierarchical'        => false,
      'supports'            => array( 'title', 
                                      'author'),
      'has_archive'         => false,
      'rewrite'             => array( 'slug' => 'feeds' ),
      'query_var'           => false);

    // Feed Details
    $ui_metabox = new UIMetabox('ssfeed_metabox',
                                'Feed Details',
                                'ssfeed');
    $ui_metabox->add_textfield('ss_feedurl', 'Feed URL');
    $field = $ui_metabox->add_dropdownfield(
                            'ss_feedurltype',
                            'Feed URL Type');
    $factory = SSImporterFactory::get_instance();
    foreach($factory->get_importtypes()
            as $importtype)
    {
       $field->add_value(
         $importtype->get_id(),
         $importtype->get_name());
    }
    $ui_metabox->add_checkbox('ss_feedupdatedaily', 
                              'Update daily');
    $ui_metabox->register();

    // Feed Info's
    $ui_metabox = new UIMetabox('ssfeed_metabox_info',
                                'Feed Informationen',
                                'ssfeed');
    $field = $ui_metabox->add_textfield('ss_feed_title', 
                                        'Feed Title');
    $field->set_disabled(true);
    $field = $ui_metabox->add_textfield('ss_feed_uuid', 
                                        'Feed UUID');
    $field->set_disabled(true);
    $field = $ui_metabox->add_textfield('ss_feed_lastupdate', 
                                        'Letzte Update');
    $field->set_disabled(true);
    $field = $ui_metabox->add_textfield('ss_feed_eventids', 
                                        'Event Ids');
    $field->set_disabled(true);
    $field = $ui_metabox->add_textarea('ss_feed_updatelog', 
                                        'Update Log');
    $field->set_disabled(true);

    $ui_metabox->register();

    register_post_type( 'ssfeed', $args );  
  }

  /** 
   * The object is created from within the class itself
   * only if the class has no instance.
   */
  public static function get_instance()
  {
    if (self::$instance == null)
    {
      self::$instance = new SSFeeds();
    }
    return self::$instance;
  }

  function columns($columns) 
  {
    unset($columns['date']);
    return array_merge(
      $columns,
      array(
        'ss_feed_lastupdate' => 'Letzte Update',
        'ss_feedurl' => 'Feed URL',
        'ss_feedurltype' => 'Feed Type',
        'ss_feedupdatedaily' => 'Update daily'
      ));
  }

  function column_data($column,$post_id) 
  {
    switch($column) 
    {
      case 'ss_feed_lastupdate' :
        echo get_post_meta($post_id,'ss_feed_lastupdate',1);
        break;
      case 'ss_feedurl' :
        echo get_post_meta($post_id,'ss_feedurl',1);
        break;
      case 'ss_feedurltype' :
        echo get_post_meta($post_id,'ss_feedurltype',1);
        break;
      case 'ss_feedupdatedaily' :
        $value = get_post_meta($post_id,
                               'ss_feedupdatedaily',1);
        if((bool)$value)
        {
          echo 'JA';
        }
        else
        {
          echo 'NEIN';
        }
        break;
     }
  }

  function update_feedlink($actions, $post)
  {
    if($post->post_type === 'ssfeed')
    {
      $update_link = admin_url( 'edit.php?post_type=ssfeed&amp;post=' . $post->ID . '&amp;page=update_feed');
      $actions['update_feed'] = '<a href="'. $update_link . '" title="Update Feed" rel="permalink">Update Feed</a>';
    }
    return $actions;
  }

  function submenus()
  {
    add_submenu_page('edit.php?post_type=ssfeed',
                   'Update Feed', 'Update Feed', 
                   'publish_posts', 'update_feed',
                   array($this, 'update_feed'));
  }

  function update_feed()
  {
    if(!isset($_GET['action']) && 
       $_GET['action']== 'update_feed')
    {
      return;
    }

    $feed_id = $_GET['post'];
    $feed = get_post($feed_id);

    $instance = SSImporterFactory::get_instance();
    $importer = $instance->create_importer($feed);
    if(empty($importer))
    { 
      echo 'No Importer found for feed id' . $feed_id;
      return;
    }
    $importer->import();
    
    if($importer->has_error())
    {
      echo 'ERROR:' . $importer->get_error();
    }
    else
    {
      echo 'Import done succesfully';
    }
    echo '</br>';
  }

  function join($wp_join) 
  {
    return $wp_join;
  }

  function set_default_sort($orderby,$query) 
  {
    return $orderby;
  }

	public function update_feeds_daily()
	{
    $cron_message = '';
    $cron_message .= 'Start update_feeds_daily ' . get_date_from_gmt(date("Y-m-d H:i:s"));
    $cron_message .= PHP_EOL;
    update_option('ss_cron_message', $cron_message );

    $args = array( 'post_type' => 'ssfeed',
                   'numberposts' => -1);
    $feeds = get_posts($args);

    $instance = SSImporterFactory::get_instance();
		if ( empty( $feeds ))
		{
      $cron_message .= 'No feeds found';
      $cron_message .= PHP_EOL;
      update_option('ss_cron_message', $cron_message );
      return;
    }

    foreach ( $feeds as $feed )
    {
      if ( empty( $feed ))
			{
        $cron_message .= 'Feed is empty';
        $cron_message .= PHP_EOL;
        continue;
      }

      $feed_id = $feed->ID;

      $feed_updatedaily = get_post_meta($feed_id,
                                        'ss_feedupdatedaily',
                                        1);
      $feed_url = get_post_meta($feed_id,'ss_feedurl',1);
      $feed_type = get_post_meta($feed_id,'ss_feedurltype',1);
      $feed_user = $feed->post_author;

      $cron_message .= 'Update Feed ' . get_date_from_gmt( date("Y-m-d H:i:s"));
      $cron_message .= PHP_EOL;
      $cron_message .= 'Feed (' . $feed_type . '): ' . 
                                  $feed_url; 
      $cron_message .= PHP_EOL;
      $cron_message .= 'Feed-Owner: ' . $feed_user;
      $cron_message .= PHP_EOL;

      if ( ! ((bool) $feed_updatedaily) )
			{
        $cron_message .= 'Feed update daily is OFF';
        $cron_message .= PHP_EOL;
        continue;
      }

      if ( empty($feed_url) || empty($feed_type) )
			{
        $cron_message .= 'Feed URL or Type is empty';
        $cron_message .= PHP_EOL;
        continue;
      }

      $importer = $instance->create_importer($feed);
      if(empty($importer))
      {
        $cron_message .= 'Importer could not be created';
        $cron_message .= PHP_EOL;
        continue;
      }
      wp_set_current_user($feed_user);
      $importer->import();
      if( $importer->has_error() )
      {
        $cron_message .= $importer->get_error();
        $cron_message .= PHP_EOL;
      }
      else
      {
        $cron_message .= 'Import done sucessfully';
        $cron_message .= PHP_EOL;
      }
      wp_set_current_user(0);
      update_option('ss_cron_message', $cron_message );
		}
    $cron_message .= 'Cronjob finished';
    $cron_message .= PHP_EOL;
    update_option('ss_cron_message', $cron_message );
	}
}
