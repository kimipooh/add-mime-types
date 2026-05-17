<?php
/*
Plugin Name: Add MIME Types
Plugin URI: 
Description: The plugin additionally allows the mime types and file extensions to WordPress.
Version: 3.2.0
Tested up to: 7.0
Author: Kimiya Kitani
Author URI: http://kitaney-wordpress.blogspot.jp/
Text Domain: wp-add-mime-types
License: GPL v2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
if ( ! defined( 'ABSPATH' ) ) exit;

define('WAMT_DEFAULT_VAR', '3.1.1');
define('WAMT_PLUGIN_DIR', 'wp-add-mime-types');
define('WAMT_PLUGIN_NAME', 'wp-add-mime-types');
define('WAMT_PLUGIN_BASENAME', WAMT_PLUGIN_DIR . '/' . WAMT_PLUGIN_NAME . '.php');
define('WAMT_SITEADMIN_SETTING_FILE', 'wp_add_mime_types_network_array');
define('WAMT_SETTING_FILE', 'wp_add_mime_types_array');
define('WAMT_DEBUG_OPTION', 'wp_add_mime_types_last_debug');

require_once( dirname( __FILE__  ) . '/includes/admin.php');

// Uninstall settings when the plugin is uninstalled.
function wamt_uninstaller(){
	if(is_multisite())
		delete_site_option(WAMT_SITEADMIN_SETTING_FILE);
	delete_site_option(WAMT_DEBUG_OPTION);
	delete_option(WAMT_DEBUG_OPTION);
	delete_option(WAMT_SETTING_FILE);
}
if ( function_exists('register_uninstall_hook') )
	register_uninstall_hook( __FILE__, 'wamt_uninstaller' );

// Add Setting to WordPress 'Settings' menu for Multisite.
if(is_multisite()){
	add_action('network_admin_menu', 'wamt_network_add_to_settings_menu');
	require_once( dirname( __FILE__  ) . '/includes/network-admin.php');
}
add_action('admin_menu', 'wamt_add_to_settings_menu');

// Procedure for adding the mime types and file extensions to WordPress.
function wamt_add_allow_upload_extension( $mimes ) {
	$mime_type_values = array();

	if ( ! function_exists( 'is_plugin_active_for_network' ) ) 
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

	if(is_multisite() && is_plugin_active_for_network(WAMT_PLUGIN_BASENAME))
		$settings = get_site_option(WAMT_SITEADMIN_SETTING_FILE);
	else
		$settings = get_option(WAMT_SETTING_FILE);
		
	if( !isset($settings['mime_type_values'] ) ) return $mimes;
	$mime_type_values = maybe_unserialize($settings['mime_type_values']);
	
	if(!empty($mime_type_values)){
		foreach ((array)$mime_type_values as $line){
			// Ignore to the right of '#' on a line.
			$line = substr($line, 0, strcspn($line, '#'));
			// Escape Strings
			$line = wp_strip_all_tags($line);
			// If 2 or more "=" character in the line data, it will be ignored.
			$line_value = explode("=", $line);
			if(count($line_value) != 2) continue;
			
			// If the head in each line is set to '-', then the MIME type restricts.
			// ex. -bmp = image/bmp 
			// The files which has "bmp" file extention becomes not to be able to upload.
			$line_pref_str = explode("-", $line_value[0]);
			if(count($line_pref_str) === 2): 
				if(isset($mimes[trim($line_pref_str[1])])):
					unset($mimes[trim($line_pref_str[1])]);
				endif;
			else:
				// "　" is the Japanese multi-byte space. If the character is found out, it automatically change the space. 
				$mimes[trim($line_value[0])] = trim(str_replace("　", " ", $line_value[1])); 
			endif;
		}
	}

	return $mimes;
}

// Register the Procedure process to WordPress.
add_filter( 'upload_mimes', 'wamt_add_allow_upload_extension');

// Using in add_allow_upload_extension_exception function.
function wamt_remove_underscore($filename, $filename_raw){
	return str_replace("_.", ".", $filename);
}

function wamt_update_last_mime_debug_result( $data, $file, $filename, $mimes, $real_mime = null ) {
	$wp_filetype = wp_check_filetype( $filename, $mimes );
	$fileinfo_mime = '';

	if ( is_readable( $file ) && function_exists( 'finfo_open' ) ) {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		if ( $finfo ) {
			$fileinfo_mime = finfo_file( $finfo, $file );
			finfo_close( $finfo );
		}
	}

	$debug_data = array(
		'time'            => sanitize_text_field( current_time( 'mysql' ) ),
		'timestamp'       => time(),
		'filename'        => sanitize_file_name( wp_basename( $filename ) ),
		'ext'             => isset( $data['ext'] ) && $data['ext'] ? sanitize_key( $data['ext'] ) : '',
		'type'            => isset( $data['type'] ) && $data['type'] ? sanitize_mime_type( $data['type'] ) : '',
		'proper_filename' => isset( $data['proper_filename'] ) && $data['proper_filename'] ? sanitize_file_name( $data['proper_filename'] ) : '',
		'real_mime'       => $real_mime ? sanitize_mime_type( $real_mime ) : '',
		'fileinfo_result' => $fileinfo_mime ? sanitize_mime_type( $fileinfo_mime ) : '',
		'expected_ext'    => isset( $wp_filetype['ext'] ) && $wp_filetype['ext'] ? sanitize_key( $wp_filetype['ext'] ) : '',
		'expected_mime'   => isset( $wp_filetype['type'] ) && $wp_filetype['type'] ? sanitize_mime_type( $wp_filetype['type'] ) : '',
		'result'          => array(
			'ext'             => isset( $data['ext'] ) && $data['ext'] ? sanitize_key( $data['ext'] ) : '',
			'type'            => isset( $data['type'] ) && $data['type'] ? sanitize_mime_type( $data['type'] ) : '',
			'proper_filename' => isset( $data['proper_filename'] ) && $data['proper_filename'] ? sanitize_file_name( $data['proper_filename'] ) : '',
		),
		'user_id'         => get_current_user_id(),
		'blog_id'         => is_multisite() ? get_current_blog_id() : 0,
	);

	if ( is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( WAMT_PLUGIN_BASENAME ) ) {
		update_site_option( WAMT_DEBUG_OPTION, $debug_data );
	} else {
		update_option( WAMT_DEBUG_OPTION, $debug_data );
	}
}

function wamt_get_last_mime_debug_result() {
	if ( ! function_exists( 'is_plugin_active_for_network' ) )
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

	if ( is_multisite() && is_plugin_active_for_network( WAMT_PLUGIN_BASENAME ) )
		return get_site_option( WAMT_DEBUG_OPTION );

	return get_option( WAMT_DEBUG_OPTION );
}

function wamt_render_last_mime_debug_result() {
	$debug_data = wamt_get_last_mime_debug_result();
?>
     <fieldset style="border:1px solid #777777; width: 750px; padding-left: 6px; padding-bottom: 1em;">
		<legend><h3><?php esc_html_e( 'Last MIME debug result', 'wp-add-mime-types' ); ?></h3></legend>
<?php if ( ! empty( $debug_data ) && is_array( $debug_data ) ) : ?>
		<table>
			<tbody>
				<tr><th scope="row"><?php esc_html_e( 'Time', 'wp-add-mime-types' ); ?></th><td><?php echo esc_html( $debug_data['time'] ?? '' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Filename', 'wp-add-mime-types' ); ?></th><td><?php echo esc_html( $debug_data['filename'] ?? '' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Extension', 'wp-add-mime-types' ); ?></th><td><?php echo esc_html( $debug_data['ext'] ?? '' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Type', 'wp-add-mime-types' ); ?></th><td><?php echo esc_html( $debug_data['type'] ?? '' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Proper filename', 'wp-add-mime-types' ); ?></th><td><?php echo esc_html( $debug_data['proper_filename'] ?? '' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Real MIME', 'wp-add-mime-types' ); ?></th><td><?php echo esc_html( $debug_data['real_mime'] ?? '' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Fileinfo result', 'wp-add-mime-types' ); ?></th><td><?php echo esc_html( $debug_data['fileinfo_result'] ?? '' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Expected extension', 'wp-add-mime-types' ); ?></th><td><?php echo esc_html( $debug_data['expected_ext'] ?? '' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Expected MIME', 'wp-add-mime-types' ); ?></th><td><?php echo esc_html( $debug_data['expected_mime'] ?? '' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'User ID', 'wp-add-mime-types' ); ?></th><td><?php echo esc_html( $debug_data['user_id'] ?? '' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Blog ID', 'wp-add-mime-types' ); ?></th><td><?php echo esc_html( $debug_data['blog_id'] ?? '' ); ?></td></tr>
			</tbody>
		</table>
		<p><textarea cols="100" rows="8" readonly><?php echo esc_textarea( wp_json_encode( $debug_data, JSON_PRETTY_PRINT ) ); ?></textarea></p>
<?php else : ?>
		<p><?php esc_html_e( 'No MIME debug result has been recorded yet.', 'wp-add-mime-types' ); ?></p>
<?php endif; ?>
     </fieldset>
<?php
}

// Exception for WordPress 4.7.1 file contents check system using finfo_file (wp-includes/functions.php)
// In case of custom extension in this plugins' setting, the WordPress 4.7.1 file contents check system is always true.

function wamt_add_allow_upload_extension_exception( $data, $file, $filename,$mimes,$real_mime=null) {
	$mime_type_values = false;

	if ( ! function_exists( 'is_plugin_active_for_network' ) )
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

	if(is_multisite() && is_plugin_active_for_network(WAMT_PLUGIN_BASENAME))
		$settings = get_site_option(WAMT_SITEADMIN_SETTING_FILE);
	else
		$settings = get_option(WAMT_SETTING_FILE);

	if(!isset($settings['mime_type_values']) || empty($settings['mime_type_values']))
		$mime_type_values = array();
	else
		$mime_type_values = maybe_unserialize($settings['mime_type_values']);

	if(isset($settings['security_attempt_enable']) && $settings['security_attempt_enable'] === "yes" && isset($settings['file_type_debug']) && $settings['file_type_debug'] === "yes")
		wamt_update_last_mime_debug_result( $data, $file, $filename, $mimes, $real_mime );

	$ext = $type = $proper_filename = false;
	if(isset($data['ext'])) $ext = $data['ext'];
	if(isset($data['type'])) $type = $data['type'];
	if(isset($data['proper_filename'])) $proper_filename = $data['proper_filename'];
	if($ext != false && $type != false) return $data;	

	// If file extension is 2 or more 
	$f_sp = explode(".", $filename);
	$f_exp_count  = count ($f_sp);

	// Filename type is "XXX" (There is not file extension). 
	if($f_exp_count <= 1){
		return $data;
	/* Even if the file extension is "XXX.ZZZ", "XXX.YYY.ZZZ", "AAA.XXX.YYY.ZZZ" or more, it always picks up  the tail of the extensions.
	*/
	}else{
		$f_name = $f_sp[0];
		$f_ext  = $f_sp[$f_exp_count - 1];
		// WordPress sanitizes the filename in case of 2 or more extensions. 
		// ex. XXX.YYY.ZZZ --> XXX_.YYY.ZZZ.
		// The following function fixes the sanitized extension when a file is uploaded in the media in case of allowed extensions. 
		// ex. XXX.YYY.ZZZ -- sanitized --> XXX_.YYY.ZZZ -- fixed the plugin --> XXX.YYY.ZZZ
		// In detail, please see sanitize_file_name function in "wp-includes/formatting.php".
		if(isset($settings['filename_sanitized_enable']) && $settings['filename_sanitized_enable'] === "yes"){
		}else{
			add_filter( 'sanitize_file_name', 'wamt_remove_underscore', 10, 2 );
		}
	}

	// If "security_attempt_enable" option disables (default) in the admin menu, the plugin avoids the security check regarding a file extension by WordPress core because of flexible management.
	if(isset($settings['security_attempt_enable']) && $settings['security_attempt_enable'] === "yes"){
		return $data;
	}

	if(empty($mime_type_values))
		return $data;

		
	$flag = false;
	if(!empty($mime_type_values)){
		foreach ((array)$mime_type_values as $line){
			// Ignore to the right of '#' on a line.
			$line = substr($line, 0, strcspn($line, '#'));
			// Escape Strings
			$line = wp_strip_all_tags($line);
			
			$line_value = explode("=", $line);
			if(count($line_value) != 2) continue;
			// "　" is the Japanese multi-byte space. If the character is found out, it automatically change the space. 		
			if(trim($line_value[0]) === $f_ext){
				$ext = $f_ext;
				$type = trim(str_replace("　", " ", $line_value[1])); 
				$flag = true;
				break;
			}
		}
	}

	if($flag)
	    return compact( 'ext', 'type', 'proper_filename' );
	else
		return $data;
}

// It's different arguments between WordPress 5.1 and previous versions.
global $wp_version;
if ( version_compare( $wp_version, '5.1') >= 0):
	add_filter( 'wp_check_filetype_and_ext', 'wamt_add_allow_upload_extension_exception',10,5);
else:
	add_filter( 'wp_check_filetype_and_ext', 'wamt_add_allow_upload_extension_exception',10,4);
endif;
