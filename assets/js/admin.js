var admin =
{
	init : function( e )
	{
		admin.set_button_actions();
		admin.set_radio_buttons();
		admin.handle_owner_block();
		admin.handle_tab_selected( e );
		admin.handle_timezone();
	},

	set_button_actions : function()
	{
		jQuery( '#btAddESS' ).click( function( e )
		{
			jQuery( '.em-warning' ).hide();
			jQuery( '#add_feed_form' ).slideToggle();
		});

		jQuery('#btViewErrors').click( function( e )
		{
			jQuery( '.em-warning' ).slideToggle();
		});
	},

	blink_bt_error : function()
	{
		jQuery('#btViewErrors').show();

		for( var i = 0 ; i < 4 ; i++ )
		{
			jQuery('#btViewErrors').fadeTo( 'slow', 0 ).fadeTo( 'slow', 1.0 );
	  	}
	},

	hide_warning : function()
	{
		var w = jQuery('.em-warning'),
			cll =  w.attr('class'), el;

		if (cll == undefined || !cll) return;

		//alert( cll.match(/error/gi).length );

		if ( w.css('display') != 'none' && cll )
		{
			el = cll.match(/error/gi);

			if (el && el.length > 0)
			{
				setTimeout( function() {
					w.slideToggle();
					admin.blink_bt_error();
				}, 10000 );
			}
		}
	},

	set_radio_buttons : function()
	{
		if (jQuery('#mode').length > 0)
		{
			jQuery('#mode').toggles({
		    	clickable	: true,
		        dragable	: true,
		        click		: undefined,
		        on			: !jQuery('#mode').hasClass('off'),
		        checkbox	: (jQuery('#mode').data('checkbox')) ? jQuery('.'+jQuery('#mode').data('checkbox')) : undefined,
		        ontext		: jQuery('#mode').data('ontext'),
		        offtext		: jQuery('#mode').data('offtext')
		    });
		    jQuery('.'+jQuery('#mode').data('checkbox')).hide();

			jQuery('.toggle').each(function()
			{
				var el =  jQuery(this);

				if (el)
				{
				    el.toggles({
				    	clickable	: true,
				        dragable	: true,
				        click		: undefined,
				        on			: !el.hasClass('off'),
				        checkbox	: (el.data('checkbox')) ? jQuery('.' + el.data('checkbox')) : undefined,
				        ontext		: el.data('ontext') || 'ON',
				        offtext		: el.data('offtext') || 'OFF'
				    });
				    jQuery('.' + el.data('checkbox') ).hide();
				}
			});
		}
	},

	handle_owner_block : function()
	{
		jQuery('#owner_activate-div').click(function(){
	    	jQuery('#block_owner').css({'opacity':(jQuery('.owner_activate-checkbox').attr('checked')!=undefined)?1:0.3});
	    });
	},

	handle_tab_selected : function( $ )
	{
		$( '.nav-tab-wrapper .nav-tab' ).off();
		$( '.nav-tab-wrapper .nav-tab' ).on( 'click', function( e )
		{
			$( '.nav-tab-wrapper .nav-tab' ).removeClass( 'nav-tab-active');
			el = $(this);
			elid = el.prop( 'id' );
			$( '.em-menu-group' ).hide();
			$( '.'+ elid ).show();
			el.addClass( 'nav-tab-active' );
			admin.hide_warning();

			switch( elid )
			{
				default :
				case 'em-menu-import' :
					$( '#btAddESS' ).show();
					break;

				case 'em-menu-export' :
					$( '#btAddESS' ).hide();
					if ( $( '#add_feed_form' ).is( ':visible' ) == true )
						$( '#add_feed_form').slideToggle();
					break;
			}
		});

		var nav_url = document.location.toString(),nav_tab;

		if ( nav_url.match( '#' ) )
		{
			nav_tab = nav_url.split( '#' )[ 1 ].split( ':' );
			$('a#em-menu-' + nav_tab[ 0 ] ).trigger( 'click' );
		}
	},

	handle_timezone : function()
	{
		jQuery('#timezone-image').timezonePicker({
			target		: '#edit-date-default-timezone',
			timezone	: jQuery('#edit-date-default-timezone').val(),
			pin			: '.timezone-pin'
		});

		jQuery('#edit-date-default-timezone').css({opacity:0});

		jQuery('#timezone-detect').click(function() {
			jQuery('#timezone-image').timezonePicker('detectLocation');
		});
   },

	control_import_field : function(el)
	{
		if ( el.val() != 'http://' ) {
			el.select();
		}
	},

	loader : function()
	{
		jQuery('#loader').show();
	},

	set_event_id : function( event_id )
	{
		jQuery('#selected_event_id').val( event_id );
	}

};

jQuery( document ).ready( function( e ) { admin.init( e ); });
