<?php /*

**************************************************************************

Plugin Name:  Tylr Slidr
Plugin URI:   http://tylrslidr.com
Description:  The Easiest Way to Pull Your Flickr Photos into Wordpress.
Version:      1.6
Author:       Tyler Craft
Author URI:   http://www.tylercraft.com/

**************************************************************************

Copyright (C) 2008 tylercraft.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

**************************************************************************/

class TylrSlidr {
	var $version = '0.1';
	var $settings = array();
	var $defaultsettings = array();
	var $swfobjects = array();
	var $standardcss;
	var $cssalignments;
	var $wpheadrun = FALSE;
	var $adminwarned = FALSE;

	// Class initialization
	function TylrSlidr() {
		global $wp_db_version, $wpmu_version;
		
		// Redirect the old settings page to the new one for any old links
		if ( is_admin() && 'tylr-slidr.php' == $_GET['page'] ) {
			wp_redirect( admin_url( 'options-general.php?page=tylr-slidr' ) );
			exit();
		}

		// For debugging (this is limited to localhost installs since it's not nonced)
		if ( !empty($_GET['resetalloptions']) && 'localhost' == $_SERVER['HTTP_HOST'] && is_admin() && 'tylr-slidr' == $_GET['page'] ) {
			update_option( 'tylrslidr_options', array() );
			wp_redirect( admin_url( 'options-general.php?page=tylr-slidr&defaults=true' ) );
			exit();
		}
		
		// Create default settings array
		$this->defaultsettings = apply_filters( 'ts_defaultsettings', array(
			'flickrslideshow' => array(
				'button'          => 1,
			),
			'userID'			=> '',
			'groupID'           => '',
			'transparency'		=> 0,
			'width'				=> 450,
			'height'			=> 500,
			'alignment'			=> 'left',
			'tinymceline'		=> 3,
		) );
		
		// Setup the settings by using the default as a base and then adding in any changed values
		// This allows settings arrays from old versions to be used even though they are missing values
		$usersettings = (array) get_option('tylrslidr_options');
		$this->settings = $this->defaultsettings;
		if ( $usersettings !== $this->defaultsettings ) {
			foreach ( (array) $usersettings as $key1 => $value1 ) {
				if ( is_array($value1) ) {
					foreach ( $value1 as $key2 => $value2 ) {
						$this->settings[$key1][$key2] = $value2;
					}
				} else {
					$this->settings[$key1] = $value1;
				}
			}
		}
		
		$usersettings = (array) get_option('tylrslidr_options');
		foreach ( $this->defaultsettings as $type => $setting ) {
			if ( !is_array($this->defaultsettings[$type]) ) continue;
			if ( isset($usersettings[$type]['button']) )
				unset($usersettings[$type]['button']); // Reset buttons
		}
		$usersettings['version'] = $this->version;
		update_option( 'tylrslidr_options', $usersettings );
		
		// Register general hooks
		add_action( 'admin_menu', array(&$this, 'RegisterSettingsPage') );
		add_filter( 'plugin_action_links', array(&$this, 'AddPluginActionLink'), 10, 2 );
		
		add_action( 'admin_post_tssettings', array(&$this, 'POSTHandler') );
		add_action( 'wp_head', array(&$this, 'Head') );
		add_action( 'admin_head', array(&$this, 'Head') );
		add_action( 'the_content', array(&$this, 'SWFObjectCalls'), 15 );
		add_filter( 'widget_text', 'do_shortcode', 11 ); // Videos in the text widget
		add_action( 'widget_text', array(&$this, 'SWFObjectCalls'), 15 );
		/*
		if ( 'update.php' == basename( $_SERVER['PHP_SELF'] ) && 'upgrade-plugin' == $_GET['action'] && FALSE !== strstr( $_GET['plugin'], 'vipers-video-quicktags' ) )
			add_action( 'admin_notices', array(&$this, 'AutomaticUpgradeNotice') );
		*/
		// Register editor button hooks
		add_filter( 'tiny_mce_version', array(&$this, 'tiny_mce_version') );
		add_filter( 'mce_external_plugins', array(&$this, 'mce_external_plugins') );
		add_action( 'edit_form_advanced', array(&$this, 'AddQuicktagsAndFunctions') );
		add_action( 'edit_page_form', array(&$this, 'AddQuicktagsAndFunctions') );
		if ( 1 == $this->settings['tinymceline'] )
			add_filter( 'mce_buttons', array(&$this, 'mce_buttons') );
		else
			add_filter( 'mce_buttons_' . $this->settings['tinymceline'], array(&$this, 'mce_buttons') );
		
		// Register shortcodes
		add_shortcode( 'tylr-slidr', array(&$this, 'shortcode_tylrslidr') );
		
		
		// Register scripts and styles
		wp_enqueue_script( 'swfobject', plugins_url('/tylr-slidr/resources/swfobject.v.2.2.js'), array(), '2.2' );

		if ( is_admin() ) {

			// Editor pages only
			if ( in_array( basename($_SERVER['PHP_SELF']), apply_filters( 'ts_editor_pages', array('post-new.php', 'page-new.php', 'post.php', 'page.php') ) ) ) {
				add_action( 'admin_head', array(&$this, 'EditorCSS') );
				add_action( 'admin_footer', array(&$this, 'OutputjQueryDialogDiv') );

				// If old version of jQuery UI, then replace it to fix a bug with the UI core
				if ( $wp_db_version < 8601 ) {
					wp_deregister_script( 'jquery-ui-core' );
					wp_enqueue_script( 'jquery-ui-core', plugins_url('/tylr-slidr/resources/jquery-ui/ui.core.js'), array('jquery'), '1.5.2' );
				}

				wp_enqueue_script( 'jquery-ui-draggable', plugins_url('/tylr-slidr/resources/jquery-ui/ui.draggable.js'), array('jquery-ui-core'), '1.5.2' );
				wp_enqueue_script( 'jquery-ui-resizable', plugins_url('/tylr-slidr/resources/jquery-ui/ui.resizable.js'), array('jquery-ui-core'), '1.5.2' );
				wp_enqueue_script( 'jquery-ui-dialog', plugins_url('/tylr-slidr/resources/jquery-ui/ui.dialog.js'), array('jquery-ui-core'), '1.5.2' );
				wp_enqueue_style( 'ts-jquery-ui', plugins_url('/tylr-slidr/resources/jquery-ui/ts-jquery-ui.css'), array(), $this->version, 'screen' );
			}
		}

	}
	
	// Register the settings page that allows plugin configuration
	function RegisterSettingsPage() {
		add_options_page( __("Tylr Slidr Configuration", 'tylr-slidr'), __('Tylr Slidr', 'tylr-slidr'), 'manage_options', 'tylr-slidr', array(&$this, 'SettingsPage') );
	}
	
	// Add a link to the settings page to the plugins list
	function AddPluginActionLink( $links, $file ) {
		static $this_plugin;
		
		if( empty($this_plugin) ) $this_plugin = plugin_basename(__FILE__);

		if ( $file == $this_plugin ) {
			$settings_link = '<a href="' . admin_url( 'options-general.php?page=tylr-slidr' ) . '">' . __('Settings') . '</a>';
			array_unshift( $links, $settings_link );
		}

		return $links;
	}
	
	// Output the settings page
	function SettingsPage() {
		global $wpmu_version;

		$tab = ( !empty($_GET['tab']) ) ? $_GET['tab'] : 'general';

		if ( !empty($_GET['defaults']) ) : ?>
<div id="message" class="updated fade"><p><strong><?php _e('Settings for this tab reset to defaults.', 'tylr-slidr'); ?></strong></p></div>
<?php endif; ?>

<div class="wrap">

	<h2 style="position:relative">
<?php

	_e("Tylr Slidr", 'tylr-slidr');

?>
	</h2>


	<ul class="subsubsub">
<?php
		$tabs = array(
			'credits'     => __('Credits', 'tylr-slidr'),
		);
		$tabhtml = array();

		// If someone wants to remove a tab (for example on a WPMU intall)
		$tabs = apply_filters( 'ts_tabs', $tabs );

		$class = ( 'general' == $tab ) ? ' class="current"' : '';
		$tabhtml[] = '		<li><a href="' . admin_url( 'options-general.php?page=tylr-slidr' ) . '"' . $class . '>' . __('General', 'tylr-slidr') . '</a>';

		foreach ( $tabs as $stub => $title ) {
			$class = ( $stub == $tab ) ? ' class="current"' : '';
			$tabhtml[] = '		<li><a href="' . admin_url( 'options-general.php?page=tylr-slidr&amp;tab=' . $stub ) . '"' . $class . ">$title</a>";
		}

		echo implode( " |</li>\n", $tabhtml ) . '</li>';
?>

	</ul>

	<form id="tssettingsform" method="post" action="admin-post.php">

	<?php wp_nonce_field('tylr-slidr'); ?>

	<input type="hidden" name="action" value="tssettings" />

	<script type="text/javascript">
	// <![CDATA[
		jQuery(document).ready(function() {
			// Show items that need to be hidden if Javascript is disabled
			// This is needed for pre-WordPress 2.7
			jQuery(".hide-if-no-js").removeClass("hide-if-no-js");

			// Confirm pressing of the "reset tab to defaults" button
			jQuery("#ts-defaults").click(function(){
				var areyousure = confirm("<?php echo js_escape( __("Are you sure you want to reset this to the default settings?", 'tylr-slidr') ); ?>");
				if ( true != areyousure ) return false;
			});
		});
	// ]]>
	</script>

<?php
	// Figure out which tab to output
	switch ( $tab ) {
		case 'credits': ?>
	<p><?php _e('This plugin uses many scripts and packages written by others. They deserve credit too, so here they are in no particular order:', 'vipers-video-quicktags'); ?></p>

	<ul>
		
		<li><?php printf( __('<strong><a href="%1$s">Alex aka ViperBond007</a></strong> for writing <a href="%2$s">Vipers Video Quicktags</a> which was the basis for this plugin. It taught me everything i know regarding how to write a WP Plugin.', 'tyler-slider'), 'http://www.viper007bond.com/', 'http://www.viper007bond.com/wordpress-plugins/vipers-video-quicktags/' ); ?></li>
		<li><?php printf( __('The authors of and contributors to <a href="%s">jQuery</a>, the awesome Javascript package used by WordPress.', 'tylr-slidr'), 'http://jquery.com/' ); ?></li>
		<li><?php printf( __('The authors of and contributors to <a href="%s">SWF Object</a>, the best way of inserting a swf into your web page.', 'tylr-slidr'), 'http://code.google.com/p/swfobject/' ); ?></li>
		<li><?php printf( __("Everyone who's helped create <a href='%s'>WordPress</a> as without it and it's excellent API, this plugin obviously wouldn't exist.", 'tylr-slidr'), 'http://jquery.com/' ); ?></li>
		<li><?php _e('Everyone who has provided bug reports and feature suggestions for this plugin.', 'vipers-video-quicktags'); ?></li>
	</ul>

<?php
			break; // End credits

		default;
?>
	<p><?php _e('Obtain the Flickr user ID from <a href="http://www.idgettr.com">www.idgettr.com</a>.', 'tylr-slidr'); ?></p>

	<input type="hidden" name="ts-tab" value="general" />

	<table class="form-table">
		<tr valign="top">
			<th scope="row"><label for="ts-userID"><?php _e('Default User ID', 'tylr-slidr'); ?></label></th>
			<td>
				<input type="text" name="ts-userID" id="ts-width" value="<?php echo attribute_escape($this->settings['userID']); ?>" size="50" class="tswide" /><br />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="ts-groupID"><?php _e('Default Group ID', 'tylr-slidr'); ?></label></th>
			<td>
				<input type="text" name="ts-groupID" id="ts-groupID" value="<?php echo attribute_escape($this->settings['groupID']); ?>" size="50" class="tswide" /><br />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="ts-groupID"><?php _e('Transparency', 'tylr-slidr'); ?></label></th>
			<td>
				<input type="checkbox" name="ts-transparency" id="ts-transparency" value="1" <?php if(attribute_escape($this->settings['transparency']) == '1'){ echo 'checked';} ?> />
				Set the wmode of the slideshow to opaque. This will allow HTML elements (like a drop down navigation) to appear on top of the slideshow
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="ts-width"><?php _e('Default Width', 'tylr-slidr'); ?></label></th>
			<td>
				<input type="text" name="ts-width" id="ts-width" value="<?php echo attribute_escape($this->settings['width']); ?>" size="50" class="tswide" /><br />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="ts-height"><?php _e('Default Height', 'tylr-slidr'); ?></label></th>
			<td>
				<input type="text" name="ts-height" id="ts-height" value="<?php echo attribute_escape($this->settings['height']); ?>" size="50" class="tswide" /><br />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="ts-tinymceline"><?php _e('Show Button In Editor On Line Number', 'tylr-slidr'); ?></label></th>
			<td>
				<select name="ts-tinymceline" id="ts-tinymceline">
<?php
					$alignments = array(
						1 => __('1', 'tylr-slidr'),
						2 => __('2 (Kitchen Sink Toolbar)', 'tylr-slidr'),
						3 => __('3 (Default)', 'tylr-slidr'),
					);
					foreach ( $alignments as $alignment => $name ) {
						echo '					<option value="' . $alignment . '"';
						selected( $this->settings['tinymceline'], $alignment );
						echo '>' . $name . "</option>\n";
					}
?>
				</select>
			</td>
		</tr>
	</table>
<?php
			// End General tab
	}
?>

<?php if ( 'help' != $tab && 'credits' != $tab ) : ?>
	<p class="submit">
		<input type="submit" name="ts-submit" value="<?php _e('Save Changes'); ?>" />
		<input type="submit" name="ts-defaults" id="ts-defaults" value="<?php _e('Reset Tab To Defaults', 'tylr-slidr'); ?>" />
	</p>
<?php endif; ?>

	</form>
</div>

<?php

	}
	
		// Handle the submits from the settings page
	function POSTHandler() {
		global $wpmu_version;

		// Capability check
		if ( !current_user_can('manage_options') )
			wp_die( __('Cheatin&#8217; uh?') );

		// Form nonce check
		check_admin_referer('tylr-slidr');

		$usersettings = (array) get_option('tylrslidr_options');
		$defaults = FALSE;

		switch ( $_POST['ts-tab'] ) {
			case 'general':
				// Check for the defaults button
				if ( !empty($_POST['ts-defaults']) ) {
					unset( $usersettings['transparency'], $usersettings['userID'],$usersettings['groupID'],$usersettings['width'], $usersettings['height'], $usersettings['tinymceline'] ); // Custom CSS is skipped
					$defaults = TRUE;
					break;
				}
				
				if(isset($_POST['ts-transparency'])){
					$usersettings['transparency'] = 1;
				}else{
					$usersettings['transparency'] = 0;
				}
				
				$usersettings['userID']             = $_POST['ts-userID'];
				$usersettings['groupID']            = $_POST['ts-groupID'];
				$usersettings['width']              = (int) $_POST['ts-width'];
				$usersettings['height']             = (int) $_POST['ts-height'];
				$usersettings['tinymceline']        = (int) $_POST['ts-tinymceline'];

				break;
		}

		update_option( 'tylrslidr_options', $usersettings );

		// Redirect back to the place we came from
		$url = admin_url( 'options-general.php?page=tylr-slidr&tab=' . urlencode($_POST['ts-tab']) );
		if ( TRUE == $defaults )
			$redirectto = add_query_arg( 'defaults', 'true', $url );
		else
			$redirectto = add_query_arg( 'updated', 'true', $url );

		wp_redirect( $redirectto );
		
	}
	
	// Output the <div> used to display the dialog box
	function OutputjQueryDialogDiv() { ?>
<div class="hidden">
	<div id="ts-dialog">
		<div class="ts-dialog-content">
			<div id="ts-dialog-message">
				<h3>Follow these steps to have a Flickr Slideshow displayed within the blog:</h3>
				<div id="ts-step1">
					<p>If you wish to display a slideshow from a users pool or a groups pool of photos, you must obtain the Flickr user or group ID from <a href="http://idgettr.com/">www.idgettr.com</a>. If you are displaying a users set of photos, then you can skip this step. </p>
					<p><label id="userIDLabel">User ID:</label><input type="text" id="ts-dialog-userID" class="ts-dialog-dim" style="width:200px" /> <label id="groupIDLabel">or Group ID:</label><input type="text" id="ts-dialog-groupID" class="ts-dialog-dim" style="width:200px" /></p>
				</div>	
				<div id="ts-step2">
					<p>Or enter the URL of the flickr pool or set that you'd like to have in your slideshow, for example: <br/>http://www.flickr.com/photos/library_of_congress/sets/72157603624867509/</p>
					<p><label>URL:</label><input type="text" id="ts-dialog-url" style="width:600px" /></p>
				</div>
			</div>
			<input type="hidden" id="ts-dialog-tag" />
		</div>
		<div id="ts-dialog-slide-header" class="ts-dialog-slide ui-dialog-titlebar"><?php _e('Dimensions', 'tylr-slidr'); ?></div>
		<div id="ts-dialog-slide" class="ts-dialog-slide ts-dialog-content">
			<p><?php printf( __("The default dimensions for this slideshow can be set on this plugin's <a href='%s'>settings page</a>. However, you can set custom dimensions for this one particular slideshow here:", 'tylr-slidr'), admin_url('options-general.php?page=tylr-slidr') ); ?></p>
			<p><input type="text" id="ts-dialog-width" class="ts-dialog-dim" style="width:50px" /> &#215; <input type="text" id="ts-dialog-height" class="ts-dialog-dim" style="width:50px" /> pixels</p>
		</div>
		</div>
	</div>
</div>
<div id="ts-precacher">
	<img src="<?php echo plugins_url('/tylr-slidr/resources/jquery-ui/images/333333_7x7_arrow_right.gif'); ?>" alt="" />
	<img src="<?php echo plugins_url('/tylr-slidr/resources/jquery-ui/images/333333_7x7_arrow_down.gif'); ?>" alt="" />
</div>
<?php
	}
	
	// Hide TinyMCE buttons the user doesn't want to see + some misc editor CSS
	function EditorCSS() {
		echo "<style type='text/css'>\n	#ts-precacher { display: none; }\n";

		// Attempt to match the dialog box to the admin colors
		$color = ( 'classic' == get_user_option('admin_color', $user_id) ) ? '#CFEBF7' : '#EAF3FA';
		$color = apply_filters( 'ts_titlebarcolor', $color ); // Use this hook for custom admin colors
		echo "	.ui-dialog-titlebar { background: $color; }\n";

		echo "</style>\n";
	}
	
		// Output the head stuff
	function Head() {
		$this->wpheadrun = TRUE;

		echo "<!-- Tylr Slidr v" . $this->version . " | http://www.tylercraft.com/portfolio/tylr-slidr/ http://tylerslidr.com-->\n<style type=\"text/css\">\n";
		$aligncss = str_replace( '\n', ' ', $this->cssalignments[$this->settings['alignment']] );
		
		$standardcss = $this->StringShrink( $this->standardcss );
		echo strip_tags( str_replace( '/* alignment CSS placeholder */', $aligncss, $standardcss ) );

		// WPMU can't use this to avoid them messing with the theme
		if ( empty($wpmu_version) ) echo ' ' . strip_tags( $this->StringShrink( $this->settings['customcss'] ) );

		echo "\n</style>\n";
		
		?>
<script type="text/javascript">
// <![CDATA[
	var tsexpressinstall = "<?php echo plugins_url('/tylr-slidr/resources/expressInstall.swf'); ?>";
// ]]>
</script>
<?php
	}
	
	// Output the SWFObject calls that replace all of the placeholders created by the shortcode handlers with the Flash videos (this is per post now)
	function SWFObjectCalls( $content ) {
		global $wpmu_version;
		
		// 		$this->swfobjects[$objectid] = array( 'width' => $atts['width'], 'height' => $atts['height'], 'url' => $atts['url']);
		if ( is_feed() || empty($this->swfobjects) ) return $content;

		$content .= "\n<script type=\"text/javascript\">\n";

		foreach ( $this->swfobjects as $objectid => $embed ) {
			
			// $embed['url']
			$patterns[0] = '/http:\/\/flickr.com/';
			$patterns[1] = '/http:\/\/www.flickr.com/';
			
			$returnURL = preg_replace($patterns, "", $embed['url']);
			
			// Strip off /show/ from URL if it's there
			$show = substr($returnURL,-5);
			
			if($show == "show/"){
				$returnURL = substr($returnURL, 0, -6);
			}elseif($show == "/show"){	
				$returnURL = substr($returnURL, 0, -5);
			}elseif(substr($returnURL, -1, 1) == "/"){
				$returnURL = substr($returnURL, 0, -1);
			}

			// Get Tag
			$tags = explode("/tags/", $returnURL);
			if(sizeOf($tags)){
				$tags = explode("/", $tags[1]);
				$tag = $tags[0];			
			}else{
				$tag = "";
			}
			
			// Get Set
			$pieces = explode("/sets/", $returnURL);
			
			if(sizeOf($pieces)){
				$pieces = explode("/", $pieces[1]);
				$setID = $pieces[0];			
			}else{
				$setID = "";
			}

			$groupID = $embed['groupID'] ;
			$userID = $embed['userID'] ;
			
			$wmode = 'wmode: "opaque"';
			
			if((int)$this->settings['transparency'] == 1){

				$wmode = 'wmode: "opaque"';
			}

			$content .= '	swfobject.embedSWF("'
								. 'http://www.flickr.com/apps/slideshow/show.swf?v=71649&offsite=true", '
								.'"' . $objectid 
								. '", "' . $embed['width'] 
								. '", "' . $embed['height'] 
								. '", "9.0.28"'
								. ', tsexpressinstall'
								. ', { offsite: "true", intl_lang: "en-us", tags: "'.$tag.'",page_show_url: "' . $returnURL . '/show/", page_show_back_url: "' . $returnURL . '/", set_id: "' . $setID . '", user_id: "'. $userID .'", group_id: "'. $groupID .'" }'
								. ', { '.$wmode.', bgcolor:"#000000", quality:"high", allowScriptAccess:"always", allowFullScreen:"true"}'
								. ', {});';
		}

		$content .= "</script>\n";

		// Clear outputted calls
		$this->swfobjects = array();

		return $content;
	}
	
	// Break the browser cache of TinyMCE
	function tiny_mce_version( $version ) {
		return $version . '-ts' . $this->version . 'line' . $this->settings['tinymceline'];
	}
	
	// Load the custom TinyMCE plugin
	function mce_external_plugins( $plugins ) {
		$plugins['tylrslidr'] = plugins_url('/tylr-slidr/resources/tinymce3/editor_plugin.js');
		return $plugins;
	}


	// Add the custom TinyMCE buttons
	function mce_buttons( $buttons ) {
		array_push( $buttons, 'tsFlickrSlideshow');
		return $buttons;
	}
	
	// Add the old style buttons to the non-TinyMCE editor views and output all of the JS for the button function + dialog box
	function AddQuicktagsAndFunctions() {
		global $wp_version;
		
		$types = array(
			'tylr-slidr'     => array(
				__('Flickr Slideshow', 'tylr-slidr'),
				__('Embed a Flickr Slideshow', 'tylr-slidr')
			),
		);

		$buttonhtml = $datajs = '';
		foreach ( $types as $type => $strings ) {
			// HTML for quicktag button
			if ( 1 == $this->settings[$type]['button'] )
				$buttonshtml .= '<input type="button" class="ed_button" onclick="TSButtonClick(\'' . $type . '\')" title="' . $strings[1] . '" value="' . $strings[0] . '" />';

			// Create the data array
			$datajs .= "	TSData['$type'] = {\n";
			$datajs .= '		title: "' . $this->js_escape( ucwords( $strings[1] ) ) . '"';
			$datajs .= ",\n".'		userID: "' . $this->settings['userID'] . '"';
			$datajs .= ",\n".'	groupID: "' . $this->settings['groupID'] . '"';
			
			if ( !empty($this->settings['width']) && !empty($this->settings['height']) ) {
				$datajs .= ",\n		width: " . $this->settings['width'] . ",\n";
				$datajs .= '		height: ' . $this->settings['height'];
			}
			$datajs .= "\n	};\n";
		}
		?>
<script type="text/javascript">
// <![CDATA[
	// Video data
	var TSData = {};
<?php echo $datajs; ?>

	<?php
		if($wp_version > 2.6){
			echo 'var TSDialogDefaultHeight = 303;';
		}else{
			echo 'var TSDialogDefaultHeight = 306;';
		}
	?>
	var TSDialogDefaultExtraHeight = 108;
	
	// This function is run when a button is clicked. It creates a dialog box for the user to input the data.
	function TSButtonClick( tag ) {

		// Close any existing copies of the dialog
		TSDialogClose();

		// Calculate the height/maxHeight (i.e. add some height for Blip.tv)
		TSDialogHeight = TSDialogDefaultHeight;
		TSDialogMaxHeight = TSDialogDefaultHeight + TSDialogDefaultExtraHeight;
		
		// Open the dialog while setting the width, height, title, buttons, etc. of it
		var buttons = { "<?php echo js_escape('Okay', 'tylr-slidr'); ?>": TSButtonOkay, "<?php echo js_escape('Cancel', 'tylr-slidr'); ?>": TSDialogClose };
		var title = '<img src="<?php echo plugins_url('/tylr-slidr/buttons/'); ?>' + tag + '.png" alt="' + tag + '" width="20" height="20" /> ' + TSData[tag]["title"];
		jQuery("#ts-dialog").dialog({ autoOpen: false, width: 750, minWidth: 750, height: TSDialogHeight, minHeight: TSDialogHeight, maxHeight: TSDialogMaxHeight, title: title, buttons: buttons, resize: TSDialogResizing });

		// Reset the dialog box incase it's been used before
		jQuery("#ts-dialog-slide-header").removeClass("selected");
		jQuery("#ts-dialog-input").val("");
		jQuery("#ts-dialog-tag").val(tag);

		// Style the jQuery-generated buttons by adding CSS classes and add second CSS class to the "Okay" button
		jQuery(".ui-dialog button").addClass("button").each(function(){
			if ( "<?php echo js_escape('Okay', 'tylr-slidr'); ?>" == jQuery(this).html() ) jQuery(this).addClass("button-highlighted");
		});

		jQuery(".ts-dialog-slide").removeClass("hidden");
		jQuery("#ts-dialog-width").val(TSData[tag]["width"]);
		jQuery("#ts-dialog-height").val(TSData[tag]["height"]);
		jQuery("#ts-dialog-userID").val(TSData[tag]["userID"]);
		jQuery("#ts-dialog-groupID").val(TSData[tag]["groupID"]);
		jQuery("#ts-dialog-set").val("");

		// Do some hackery on any links in the message -- jQuery(this).click() works weird with the dialogs, so we can't use it
		jQuery("#ts-dialog-message a").each(function(){
			jQuery(this).attr("onclick", 'window.open( "' + jQuery(this).attr("href") + '", "_blank" );return false;' );
		});

		// Show the dialog now that it's done being manipulated
		jQuery("#ts-dialog").dialog("open");

		// Focus the input field
		jQuery("#ts-dialog-userID").focus();
	}

	// Close + reset
	function TSDialogClose() {
		jQuery(".ui-dialog").height(TSDialogDefaultHeight);
		jQuery("#ts-dialog").dialog("close");
	}

	// Callback function for the "Okay" button
	function TSButtonOkay() {

		var tag = jQuery("#ts-dialog-tag").val();
		
		var userID = jQuery("#ts-dialog-userID").val();
		var groupID = jQuery("#ts-dialog-groupID").val();
		var width = jQuery("#ts-dialog-width").val();
		var height = jQuery("#ts-dialog-height").val();
		
		var url = jQuery("#ts-dialog-url").val();

		var text = url;
		
		if ( !tag || !text ) return TSDialogClose();

		if ( width && height && ( width != TSData[tag]["width"] || height != TSData[tag]["height"] ) ){
			tylrSlidrTag = "[" + tag + ' width="' + width + '" height="' + height + '"';
		}else{
			tylrSlidrTag = "[" + tag;
		}
		
		tylrSlidrTag += ' userID="' + userID + '"';
		tylrSlidrTag += ' groupID="' + groupID + '"';
		
		tylrSlidrTag += ']' + text + "[/" + tag + "]";

		if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.activeEditor ) && !ed.isHidden() ) {
			ed.focus();
			if (tinymce.isIE)
				ed.selection.moveToBookmark(tinymce.EditorManager.activeEditor.windowManager.bookmark);

			ed.execCommand('mceInsertContent', false, tylrSlidrTag);
		} else
			edInsertContent(edCanvas, tylrSlidrTag);

		TSDialogClose();
	}

	// This function is called while the dialog box is being resized.
	function TSDialogResizing( test ) {
		if ( jQuery(".ui-dialog").height() > TSDialogHeight ) {
			jQuery("#ts-dialog-slide-header").addClass("selected");
		} else {
			jQuery("#ts-dialog-slide-header").removeClass("selected");
		}
	}

	// On page load...
	jQuery(document).ready(function(){
		// Add the buttons to the HTML view
		jQuery("#ed_toolbar").append('<?php echo $this->js_escape( $buttonshtml ); ?>');

		// Make the "Dimensions" bar adjust the dialog box height
		jQuery("#ts-dialog-slide-header").click(function(){
			if ( jQuery(this).hasClass("selected") ) {
				jQuery(this).removeClass("selected");
				jQuery(this).parents(".ui-dialog").animate({ height: TSDialogHeight });
			} else {
				jQuery(this).addClass("selected");
				jQuery(this).parents(".ui-dialog").animate({ height: TSDialogMaxHeight });
			}
		});

		// If the Enter key is pressed inside an input in the dialog, do the "Okay" button event
		jQuery("#ts-dialog :input").keyup(function(event){
			if ( 13 == event.keyCode ) // 13 == Enter
				TSButtonOkay();
		});

		// Make help links open in a new window to avoid loosing the post contents
		jQuery("#ts-dialog-slide a").each(function(){
			jQuery(this).click(function(){
				window.open( jQuery(this).attr("href"), "_blank" );
				return false;
			});
		});
	});
// ]]>
</script>
<?php
	}
	
	// Handle TylrSlidr shortcodes
	function shortcode_tylrslidr( $atts, $content = '' ) {
		
		$content = $this->wpuntexturize( $content );
		
		// Handle WordPress.com shortcode format
		if ( isset($atts[0]) ) {
			$atts = $this->attributefix( $atts );
			$content = $atts[0];
			unset($atts[0]);
		}

		if ( empty($content) ) return $this->error( sprintf( __('No user or group ID was passed to the %s BBCode', 'tyler-slider'), __('tylr-slidr') ) );

		if ( is_feed() ) return $this->postlink();

		// Set any missing $atts items to the defaults
		$atts = shortcode_atts(array(
			'width'    => $this->settings['width'],
			'height'   => $this->settings['height'],
			'userid'   => "",
			'groupid'   => ""
		), $atts);

		// Allow other plugins to modify these values (for example based on conditionals)
		$atts = apply_filters( 'ts_shortcodeatts', $atts, 'tylr-slidr' );
		
		$objectid = uniqid('ts');

		// KEEP BACKWARDS COMPATIBLE WITH V1.0
		$oldContent = split('user_id=', $content);

		if(sizeOf($oldContent) > 1){
			$fallbacklink = 'http://www.flickr.com/slideShow/index.gne?' . $content;	
			return '<iframe src="'.$fallbacklink.'" frameBorder="0" width="'.$atts['width'].'" height="'.$atts['height'].'" scrolling="no"></iframe>';
		}
				
		// At the moment, swfobject can't embed Flickr Slideshows, so must use iframes
		$this->swfobjects[$objectid] = array( 'width' => $atts['width'], 'height' => $atts['height'], 'userID' => $atts['userid'], 'groupID' => $atts['groupid'], 'url' => $content);
		
		return '<span class="tsbox tsflash" style="width:' . $atts['width'] . 'px;height:' . $atts['height'] . 'px;"><span id="' . $objectid . '"><em>' . sprintf( __('Please <a href="%1$s">enable Javascript</a> and <a href="%2$s">Flash</a> to view this %3$s video.', 'tylr-slidr'), 'http://www.google.com/support/bin/answer.py?answer=23852', 'http://www.adobe.com/shockwave/download/download.cgi?P1_Prod_Version=ShockwaveFlash', __('Flash') ) . '</em></span></span>';
	}
	
	// Return a link to the post for use in the feed
	function postlink() {
		global $post;

		if ( empty($post->ID) ) return ''; // This should never happen (I hope)

		$text = ( !empty($this->settings['customfeedtext']) ) ? $this->settings['customfeedtext'] : '<em>' . __( 'Click here to view the embedded slideshow.', 'tylr-slidr' ) . '</em>';

		return apply_filters( 'ts_feedoutput', '<a href="' . get_permalink( $post->ID ) . '">' . $text . '</a>' );
	}
	
	// WordPress' js_escape() won't allow <, >, or " -- instead it converts it to an HTML entity. This is a "fixed" function that's used when needed.
	function js_escape($text) {
		$safe_text = addslashes($text);
		$safe_text = preg_replace('/&#(x)?0*(?(1)27|39);?/i', "'", stripslashes($safe_text));
		$safe_text = preg_replace("/\r?\n/", "\\n", addslashes($safe_text));
		$safe_text = str_replace('\\\n', '\n', $safe_text);
		return apply_filters('js_escape', $safe_text, $text);
	}
	
	// Replaces tabs, new lines, etc. to decrease the characters
	function StringShrink( $string ) {
		if ( empty($string) ) return $string;
		return preg_replace( "/\r?\n/", ' ', str_replace( "\t", '', $string ) );
	}
	
	// Reverse the parts we care about (and probably some we don't) of wptexturize() which gets applied before shortcodes
	function wpuntexturize( $text ) {
		$find = array( '&#8211;', '&#8212;', '&#215;', '&#8230;', '&#8220;', '&#8217;s', '&#8221;', '&#038;' );
		$replace = array( '--', '---', 'x', '...', '``', '\'s', '\'\'', '&' );
		return str_replace( $find, $replace, $text );
	}

}

// Start this plugin once all other plugins are fully loaded
add_action( 'plugins_loaded', 'TylrSlidr' ); function TylrSlidr() { global $TylrSlidr; $TylrSlidr = new TylrSlidr(); }

?>