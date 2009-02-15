<?php
/**
 * Plugin Name: Get Free Web Designs Widget
 * Plugin URI: http://xavisys.com/2009/02/get-free-web-designs-widget/
 * Description: Shows a feed of recent <a href="http://www.getfreewebdesigns.com">Get Free Web Designs</a> templates
 * Version: 1.0.1
 * Author: Aaron D. Campbell
 * Author URI: http://xavisys.com/
 */

/*  Copyright 2006  Aaron D. Campbell  (email : wp_plugins@xavisys.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/**
 * wpGFWDFeed is the class that handles ALL of the plugin functionality.
 * It helps us avoid name collisions
 * http://codex.wordpress.org/Writing_a_Plugin#Avoiding_Function_Name_Collisions
 */
class wpGFWDFeed
{
	private $_iconcolors = array(
		'blue',
		'green',
		'grey',
		'orange',
		'purple',
		'red',
		'yellow'
	);

	/**
	 * Displays the GFWD widget, with all tweets in an unordered list.
	 * Things are classed but not styled to allow easy styling.
	 *
	 * @param array $args - Widget Settings
	 * @param array|int $widget_args - Widget Number
	 */
	public function display($args, $widget_args = 1) {
		require_once(ABSPATH . WPINC . '/rss.php');
		if ( !$rss = fetch_rss('http://feeds.feedburner.com/GFWD-Design-Feed/') ) {
			return;
		}

		extract($args, EXTR_SKIP);
		if ( is_numeric($widget_args) ) {
			$widget_args = array( 'number' => $widget_args );
		}
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract($widget_args, EXTR_SKIP);

		$options = get_option('widget_gfwd');

		if ( !isset($options[$number]) ) {
			return;
		}

		if ( isset($options[$number]['error']) && $options[$number]['error'] ) {
			return;
		}

		// Cast items to int and do sanity check.
		$options[$number]['items'] = (int) $options[$number]['items'];
		if ( $options[$number]['items'] < 1 || 20 < $options[$number]['items'] ) {
			$options[$number]['items'] = 10;
		}
		//Sanity check for icon color
		if ( !in_array($options[$number]['iconcolor'], $this->_iconcolors) ) {
			$options[$number]['iconcolor'] = 'grey';
		}
		$output			= '';

		if ( is_array( $rss->items ) && !empty( $rss->items ) ) {
			$rss->items = array_slice($rss->items, 0, $options[$number]['items']);
			// Get the diretory to store files in or list them from, make it if missing
			$upload = $this->wp_upload_dir();

			foreach ($rss->items as $item ) {
				while ( strstr($item['link'], 'http') != $item['link'] )
					$item['link'] = substr($item['link'], 1);
				$link = clean_url(strip_tags($item['link']));
				$guid = clean_url(strip_tags($item['guid']));
				$title = attribute_escape(strip_tags($item['title']));
				if ( empty($title) ) {
					$title = __('Untitled');
				}
				$linkInfo = parse_url($guid);
				$urlParams = wp_parse_args($linkInfo['query']);

				$file = path_join($upload['path'], "{$urlParams['template']}.jpg");
				//If the thumb exists on the local site, use it
				if (is_file($file) && is_readable($file)) {
					if (defined('WP_DEBUG') && WP_DEBUG) {
						echo "<!-- local:{$file} -->\r\n";
					}
					$imgPath = path_join( $upload['url'], "{$urlParams['template']}.jpg" );
				} else { // If the file didn't exist, try to create it as we go
					// If we can't create the directory, give an error
					if ( ! wp_mkdir_p( dirname( $file ) ) ) {
						$upload['error'] = sprintf( __( 'Unable to create directory %s. Is its parent directory writable by the server?' ), dirname( $file ) );
					} else {
						$imgPath = "http://www.getfreewebdesigns.com/designs/thumbs/{$urlParams['template']}.jpg";
						//Try to copy file to local site
						if (copy($imgPath, $file)) {
							if (defined('WP_DEBUG') && WP_DEBUG) {
								echo "<!-- copied:{$file} -->\r\n";
							}
							// If file was copied, then use that link
							$imgPath = path_join( $upload['url'], "{$urlParams['template']}.jpg" );
						} else {
							$upload['error'] = sprintf( __( 'Could not write file %s' ), $file );
						}
					}
				}
				// If an error was created, display it
				if (defined('WP_DEBUG') && WP_DEBUG && $upload['error'] !== false) {
					echo "<!-- {$upload['error']}: 'Unable to create thumb file!' -->\r\n";
				}

				$output .= '<li>';
				$output .= "<a href='{$link}' title='View Template'><img src='{$imgPath}' alt='View Template' class='gfwd-ss' style='width:100%;' /></a>\n";
				$output .= "<p><a href='http://www.getfreewebdesigns.com/designs/?id={$urlParams['template']}' title='Download Design Above'><img src='" . plugins_url( dirname( plugin_basename(__FILE__) ) ). "/img/download-page-{$options[$number]['iconcolor']}.gif' alt='download' /> Download Design</a></p>\n";

				$output .= '</li>';
			}
		}

		if (empty($output)) {
			return;
		} else {
			$output .= '<li><a href="http://www.getfreewebdesigns.com" title="Download free XHTML and CSS templates"><small>Get Free Web Designs</small></a></li>';
		}

		echo $before_widget;

		$title = apply_filters('widget_title', $options[$number]['title'] );
		if (empty($title)) {
			$title = __('Free Web Designs');
		}
		echo $before_title . $title . $after_title;
		echo "<ul>{$output}</ul>";
		echo $after_widget;
	}

	/**
	 * Sets up admin forms to manage widgets
	 *
	 * @param array|int $widget_args - Widget Number
	 */
	public function control($widget_args) {
		global $wp_registered_widgets;
		static $updated = false;

		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract( $widget_args, EXTR_SKIP );

		$options = get_option('widget_gfwd');
		if ( !is_array($options) )
			$options = array();

		if ( !$updated && !empty($_POST['sidebar']) ) {
			$sidebar = (string) $_POST['sidebar'];

			$sidebars_widgets = wp_get_sidebars_widgets();
			if ( isset($sidebars_widgets[$sidebar]) )
				$this_sidebar =& $sidebars_widgets[$sidebar];
			else
				$this_sidebar = array();

			foreach ( $this_sidebar as $_widget_id ) {
				if ( array($this,'display') == $wp_registered_widgets[$_widget_id]['callback'] && isset($wp_registered_widgets[$_widget_id]['params'][0]['number']) ) {
					$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
					if ( !in_array( "gfwd-$widget_number", $_POST['widget-id'] ) ) // the widget has been removed.
						unset($options[$widget_number]);
				}
			}

			foreach ( (array) $_POST['widget-gfwd'] as $widget_number => $widget_gfwd ) {
				if ( !isset($widget_gfwd['title']) && isset($options[$widget_number]) ) // user clicked cancel
					continue;
				$widget_gfwd['title'] = stripslashes($widget_gfwd['title']);
				if ( !current_user_can('unfiltered_html') ) {
					$widget_gfwd['title'] = strip_tags($widget_gfwd['title']);
				}
				$options[$widget_number] = $widget_gfwd;
			}

			update_option('widget_gfwd', $options);
			$updated = true;
		}

		if ( -1 != $number ) {
			$options[$number]['number'] = $number;
			$options[$number]['title'] = attribute_escape($options[$number]['title']);
		}
		$this->_showForm($options[$number]);
	}

	/**
	 * Registers widget in such a way as to allow multiple instances of it
	 *
	 * @see wp-includes/widgets.php
	 */
	public function register() {
		if ( !$options = get_option('widget_gfwd') )
			$options = array();
		$widget_ops = array('classname' => 'widget_gfwd', 'description' => __('Show the latest designs from getfreewebdesigns.com'));
		$control_ops = array('width' => 400, 'height' => 350, 'id_base' => 'gfwd');
		$name = __('getfreewebdesigns.com Feed');

		$id = false;
		foreach ( array_keys($options) as $o ) {
		// Old widgets can have null values for some reason
		if ( !isset($options[$o]['title']) || !isset($options[$o]['items']) )
			continue;
			$id = "gfwd-$o"; // Never never never translate an id
			wp_register_sidebar_widget($id, $name, array($this,'display'), $widget_ops, array( 'number' => $o ));
			wp_register_widget_control($id, $name, array($this,'control'), $control_ops, array( 'number' => $o ));
		}

		// If there are none, we register the widget's existance with a generic template
		if ( !$id ) {
			wp_register_sidebar_widget( 'gfwd-1', $name, array($this,'display'), $widget_ops, array( 'number' => -1 ) );
			wp_register_widget_control( 'gfwd-1', $name, array($this,'control'), $control_ops, array( 'number' => -1 ) );
		}
	}

	/**
	 * Displays the actualy for that populates the widget options box in the
	 * admin section
	 *
	 * @param array $args - Current widget settings and widget number, gets combind with defaults
	 */
	private function _showForm($args) {

		$defaultArgs = array(	'title'			=> __('Free Web Designs'),
								'items'			=> 10,
								'iconcolor'			=> 'grey',
								'number'		=> '%i%' );
		$args = wp_parse_args( $args, $defaultArgs );
		extract( $args );
?>
			<p>
				<label for="gfwd-title-<?php echo $number; ?>"><?php _e('Title (optional):'); ?></label>
				<input class="widefat" id="gfwd-title-<?php echo $number; ?>" name="widget-gfwd[<?php echo $number; ?>][title]" type="text" value="<?php echo $title; ?>" />
			</p>
			<p>
				<label for="gfwd-items-<?php echo $number; ?>"><?php _e('How many items would you like to display?'); ?></label>
				<select id="gfwd-items-<?php echo $number; ?>" name="widget-gfwd[<?php echo $number; ?>][items]">
					<?php
						for ( $i = 1; $i <= 20; ++$i )
							echo "<option value='$i' " . ( $items == $i ? "selected='selected'" : '' ) . ">$i</option>";
					?>
				</select>
			</p>
			<p>
				<label for="gfwd-iconcolor-<?php echo $number; ?>"><?php _e('What color download icon do you want?'); ?></label>
				<select id="gfwd-iconcolor-<?php echo $number; ?>" name="widget-gfwd[<?php echo $number; ?>][iconcolor]">
					<?php
						foreach ($this->_iconcolors as $color) {
							echo "<option value='$color' " . ( $iconcolor == $color ? "selected='selected'" : '' ) . ">$color</option>";
						}
					?>
				</select>
			</p>


<?php
	}

	/**
	 * Returns an array containing the current upload directory's path and url,
	 * or an error message.
	 *
	 * @return array
	 */
	public function wp_upload_dir() {
		/** WordPress Administration File API */
		require_once(ABSPATH . 'wp-admin/includes/file.php');

		$siteurl = get_option( 'siteurl' );
		$upload_path = get_option( 'upload_path' );
		if ( trim($upload_path) === '' )
			$upload_path = WP_CONTENT_DIR . '/uploads';
		$dir = $upload_path;

		// $dir is absolute, $path is (maybe) relative to ABSPATH
		$dir = path_join( ABSPATH, $upload_path );
		$path = str_replace( ABSPATH, '', trim( $upload_path ) );

		if ( !$url = get_option( 'upload_url_path' ) )
			$url = trailingslashit( $siteurl ) . $path;

		if ( defined('UPLOADS') ) {
			$dir = ABSPATH . UPLOADS;
			$url = trailingslashit( $siteurl ) . UPLOADS;
		}

		$subdir = '/gfwd';

		$dir .= $subdir;
		$url .= $subdir;

		// Make sure we have an uploads dir
		if ( ! wp_mkdir_p( $dir ) ) {
			$message = sprintf( __( 'Unable to create directory %s. Is its parent directory writable by the server?' ), $dir );
			return array( 'error' => $message );
		}

		$thumbs = list_files($dir);
		if ( count($thumbs) > 20 ) {
			rsort($thumbs);
			$thumbs = array_slice($thumbs,20);
			foreach ($thumbs as $remove) {
				unlink($remove);
			}
		}

		$uploads = array( 'path' => $dir, 'url' => $url, 'subdir' => $subdir, 'error' => false );
		return apply_filters( 'upload_dir', $uploads );
	}

	/**
	 * On plugin activation, try to create the upload directory
	 */
	public function activate() {
		$this->wp_upload_dir();
	}
}
// Instantiate our class
$wpGFWDFeed = new wpGFWDFeed();

/**
 * Add filters and actions
 */
add_action('widgets_init', array($wpGFWDFeed, 'register'));
register_activation_hook(__FILE__, array($wpGFWDFeed,'activate'));
