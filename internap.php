<?php
/*
Plugin Name: Internap CDN
Plugin URI: https://github.com/skynet/Internap
Description: Internap provides The Ultimate Online Experience through Internet Connectivity, Colocation, Managed Hosting and Content Delivery Network services.
Author: Ionel Roiban
Author URI: http://www.usphp.com/
Version: 1.0.1
Network: true
*/

/*
 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
	exit;
}

define('INTERNAP_VERSION', '1.0');
define('INTERNAP_PLUGIN_URL', plugin_dir_url( __FILE__ ));
define('INTERNAP_TEST_MODE', false);

/** If you hardcode the Internap WS Info here, all auth config screens will be hidden */
$internap_auth = array(
	'domain' => '',
	'username' => '',
	'key' => '',
);

if ( is_admin() )
require_once dirname( __FILE__ ) . '/admin.php';

function internap_init() {
	// nothing
}
add_action('init', 'internap_init');

function internap_get_auth() {
	global $internap_auth;
	if ( !empty($internap_auth['key']) )
	return $internap_auth;
	return get_option('internap_auth');
}

function internap_verify_auth($auth_domain, $auth_user, $auth_key) {
	return true;
}

// return a comma-separated list of role names for the given user
function internap_get_user_roles( $user_id ) {
	$roles = false;

	if ( !class_exists('WP_User') )
	return false;

	if ( $user_id > 0 ) {
		$comment_user = new WP_User($user_id);
		if ( isset($comment_user->roles) )
		$roles = join(',', $comment_user->roles);
	}

	if ( is_multisite() && is_super_admin( $user_id ) ) {
		if ( empty( $roles ) ) {
			$roles = 'super_admin';
		} else {
			$comment_user->roles[] = 'super_admin';
			$roles = join( ',', $comment_user->roles );
		}
	}

	return $roles;
}

function internap_http_post($request, $host, $path, $port = 80, $ip=null) {
	global $wp_version;

	$internap_ua = "WordPress/{$wp_version} | ";
	$internap_ua .= 'Internap Plugin/' . constant( 'INTERNAP_VERSION' );

}

