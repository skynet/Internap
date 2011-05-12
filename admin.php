<?php

require_once('lib/nusoap.php');
require_once('lib/class.wsdlcache.php');

add_action( 'admin_menu', 'internap_config_page' );
internap_admin_warnings();

function internap_admin_init() {
	global $wp_version;

	// all admin functions are disabled in old versions
	if ( !function_exists('is_multisite') && version_compare( $wp_version, '3.0', '<' ) ) {

		function internap_version_warning() {
			echo "<div id='internap-warning' class='updated fade'><p><strong>".sprintf(__('Internap %s requires WordPress 3.0 or higher.'), INTERNAP_VERSION) ."</strong> ".sprintf(__('Please <a href="%s">upgrade WordPress</a> to a current version, or <a href="%s">downgrade to version 0.5 of the Internap plugin</a>.'), 'http://codex.wordpress.org/Upgrading_WordPress', 'http://wordpress.org/extend/plugins/internap/download/'). "</p></div>";
		}
		add_action('admin_notices', 'internap_version_warning');

		return;
	}
}
add_action('admin_init', 'internap_admin_init');

function internap_nonce_field($action = -1) { return wp_nonce_field($action); }
$internap_nonce = 'internap-update-key';

function internap_config_page() {
	if ( function_exists('add_submenu_page') )
	add_submenu_page('plugins.php', __('Internap'), __('Internap'), 'manage_options', 'internap-configuration', 'internap_conf');
	add_submenu_page('upload.php', __('Internap CDN'), __('Internap CDN'), 'manage_options', 'internap-cdn', 'internap_cdn');
	
}

function internap_plugin_action_links( $links, $file ) {
	if ( $file == plugin_basename( dirname(__FILE__).'/internap.php' ) ) {
		$links[] = '<a href="plugins.php?page=internap-configuration">'.__('Settings').'</a>';
		$links[] = '<a href="upload.php?page=internap-cdn">'.__('Manage').'</a>';
	}

	return $links;
}

add_filter( 'plugin_action_links', 'internap_plugin_action_links', 10, 2 );

function internap_conf() {
	global $internap_nonce, $wpcom_api_key;

	if ( isset($_POST['submit']) ) {
		if ( function_exists('current_user_can') && !current_user_can('manage_options') ) die(__('Cheatin&#8217; uh?'));

		check_admin_referer( $internap_nonce );
		$home_url = parse_url( get_bloginfo('url') );
		if ( empty($home_url['host']) ) $ms[] = 'bad_home_url';

		$auth_domain = trim(strip_tags($_POST['auth_domain']));
		$auth_user = trim(strip_tags($_POST['auth_user']));
		$auth_key = trim(strip_tags($_POST['auth_key']));

		if (empty($auth_domain)) {
			$ms[] = 'new_auth_empty';
			delete_option('internap_domain');
		} elseif (empty($auth_user)) {
			$ms[] = 'new_auth_empty';
			delete_option('internap_user');
		} elseif (empty($auth_key)) {
			$ms[] = 'new_auth_empty';
			delete_option('internap_key');
		} else {
			$auth_status = internap_verify_auth($auth_domain, $auth_user, $auth_key);
		}

		if ( $auth_status == 'valid' ) {
			update_option('internap_domain', $auth_domain);
			update_option('internap_user', $auth_user);
			update_option('internap_key', $auth_key);
			$ms[] = 'new_auth_valid';
		} else if ( $auth_status == 'invalid' ) {
			$ms[] = 'new_auth_invalid';
		} else if ( $auth_status == 'failed' ) {
			$ms[] = 'new_auth_failed';
		}

	} elseif ( isset($_POST['check']) ) {
		internap_get_server_connectivity(0);
	}

	if ( empty( $auth_status) ||  $auth_status != 'valid' ) {
		$auth_domain = get_option('internap_domain');
		$auth_user = get_option('internap_user');
		$auth_key = get_option('internap_key');
		if ( empty( $auth_domain ) OR empty( $auth_user ) OR empty( $auth_key )) {
			if ( empty( $auth_status ) || $auth_status != 'failed' ) {
				if ( internap_get_server_connectivity(0) == '' )
				$ms[] = 'no_connection';
				else
				$ms[] = 'auth_empty';
			}
			$auth_status = 'empty';
		} else {
			$auth_status = internap_verify_auth($auth_domain, $auth_user, $auth_key);
		}
		if ( $auth_status == 'valid' ) {
			$ms[] = 'auth_valid';
		} else if ( $auth_status == 'invalid' ) {
			delete_option('internap_key');
			$ms[] = 'auth_empty';
		} else if ( !empty($auth_key) && $auth_status == 'failed' ) {
			$ms[] = 'auth_failed';
		}
	}

	$messages = array(
		'new_auth_empty' => array('color' => '#aa0', 'text' => __('Your Web Services authentication has been cleared.')),
		'new_auth_valid' => array('color' => '#4AB915', 'text' => __('Your Web Services authentication has been verified.')),
		'new_auth_invalid' => array('color' => '#888', 'text' => __('The Web Services authentication you entered is invalid.')),
		'new_auth_failed' => array('color' => '#888', 'text' => __('The Web Services authentication you entered could not be verified because a connection to Internap Web Services could not be established. Please check your server configuration.')),
		'no_connection' => array('color' => '#888', 'text' => __('There was a problem connecting to the Internap Web Service. Please check your server configuration.')),
		'auth_empty' => array('color' => '#aa0', 'text' => sprintf(__('Please enter your Web Service authentication. (<a href="%s" style="color:#fff">Get your Web Services authentication.</a>)'), 'http://mediaconsole.internapcdn.com/')),
		'auth_valid' => array('color' => '#4AB915', 'text' => __('This Web Services authentication is valid.')),
		'auth_failed' => array('color' => '#aa0', 'text' => __('The Web Services authentication below was previously validated but a connection to Internap\'s Web Service can not be established at this time. Please check your server configuration.')),
		'bad_home_url' => array('color' => '#888', 'text' => sprintf( __('Your WordPress home URL %s is invalid.  Please fix the <a href="%s">home option</a>.'), esc_html( get_bloginfo('url') ), admin_url('options.php#home') ) ),
	);
	?>
	<?php if ( !empty($_POST['submit'] ) ) : ?>
<div id="message" class="updated fade">
<p><strong><?php _e('Options saved.') ?></strong></p>
</div>
	<?php endif; ?>
<div class="wrap">
<h2><?php _e('Internap Configuration'); ?></h2>
<div class="narrow">
<form action="" method="post" id="internap-configuration" style="margin: auto;">
<p><?php printf(__('<a href="%1$s">Internap\'s CDN</a> provides a scalable, high-performance content delivery solution to optimize the delivery of your Website content to users across the world. If you don\'t have an Internap account yet, you can contact Sales at <a href="%2$s">Internap.com</a>.'), 'http://www.internap.com/', 'http://www.internap.com/'); ?></p>
<h3><?php _e('Internap Web Services Authentication'); ?></h3>
	<?php foreach ( $ms as $m ) : ?>
<p style="padding: .5em; background-color: <?php echo $messages[$m]['color']; ?>; color: #fff; font-weight: bold;"><?php echo $messages[$m]['text']; ?></p>
	<?php endforeach; ?>
<p><?php _e('Domain Name'); ?><br />
<input id="auth_domain" name="auth_domain" type="text" size="50" value="<?php echo get_option('internap_domain') ?>" /></p>
<p><?php _e('User Name'); ?><br />
<input id="auth_user" name="auth_user" type="text" size="50" value="<?php echo get_option('internap_user') ?>" /></p>
<p><?php _e('MD5 Hash'); ?><br />
<input id="auth_key" name="auth_key" type="text" size="50" value="<?php echo get_option('internap_key') ?>" /></p>
	<?php internap_nonce_field($internap_nonce) ?>
<p class="submit"><input type="submit" name="submit" value="<?php _e('Update options &raquo;'); ?>" /></p>
</form>
<form action="" method="post" id="internap-connectivity" style="margin: auto;">
<h3><?php _e('Server Connectivity'); ?></h3>
	<?php
	$servers = internap_get_server_connectivity();
	$fail_count = count($servers) - count( array_filter($servers) );
	if ( is_array($servers) && count($servers) > 0 ) {
		// some connections work, some fail
		if ( $fail_count > 0 && $fail_count < count($servers) ) { ?>
<p style="padding: .5em; background-color: #aa0; color: #fff; font-weight: bold;"><?php _e('Unable to reach some Internap servers.'); ?></p>
<p><?php echo sprintf( __('A network problem or firewall is blocking some connections from your web server to Internap.com.  Internap plugin is working but this may cause problems during times of network congestion.  Please contact your web host or firewall administrator and give them <a href="%s" target="_blank">this information about Internap and firewalls</a>.'), 'http://internap.wordpress.com/web-services'); ?></p>
		<?php
		// all connections fail
		} elseif ( $fail_count > 0 ) { ?>
<p style="padding: .5em; background-color: #888; color: #fff; font-weight: bold;"><?php _e('Unable to reach any Internap servers.'); ?></p>
<p><?php echo sprintf( __('A network problem or firewall is blocking all connections from your web server to Internap\'s Web Services.  <strong>Internap plugin cannot work correctly until this is fixed.</strong>  Please contact your web host or firewall administrator and give them <a href="%s" target="_blank">this information about Internap and firewalls</a>.'), 'http://internap.wordpress.com/web-services'); ?></p>
		<?php
		// all connections work
		} else { ?>
<p style="padding: .5em; background-color: #4AB915; color: #fff; font-weight: bold;"><?php  _e('All Internap servers are available.'); ?></p>
<p><?php _e('Internap Web Services are working correctly.  All servers are accessible.'); ?></p>
		<?php
		}
	} else {
		?>
<p style="padding: .5em; background-color: #888; color: #fff; font-weight: bold;"><?php _e('Unable to find Internap servers.'); ?></p>
<p><?php echo sprintf( __('A DNS problem or firewall is preventing all access from your web server to Internap\'s Web Services.  <strong>Internap plugin cannot work correctly until this is fixed.</strong>  Please contact your web host or firewall administrator and give them <a href="%s" target="_blank">this information about Internap and firewalls</a>.'), 'http://internap.wordpress.com/web-services'); ?></p>
		<?php
	}

	if ( !empty($servers) ) {
		?>
<table style="width: 66%;">
	<thead>
		<th><?php _e('Internap server'); ?></th>
		<th><?php _e('Network Status'); ?></th>
	</thead>
	<tbody>
	<?php
	asort($servers);
	foreach ( $servers as $ip => $status ) {
		$color = ( $status ? '#4AB915' : '#888');
		?>
		<tr>
			<td><?php echo htmlspecialchars($ip); ?></td>
			<td style="padding: 0 .5em; font-weight:bold; color: #fff; background-color: <?php echo $color; ?>"><?php echo ($status ? __('Accessible') : __('Re-trying') ); ?></td>
			<?php
	}
	}
	?>
	
	</tbody>
</table>
<p><?php if ( get_option('internap_connectivity_time') ) echo sprintf( __('Last checked %s ago.'), human_time_diff( get_option('internap_connectivity_time') ) ); ?></p>
<p class="submit"><input type="submit" name="check" value="<?php _e('Check network status &raquo;'); ?>" /></p>
<p><?php printf( __('<a href="%s" target="_blank">Click here</a> to confirm that <a href="%s" target="_blank">Internap.com is up</a>.'), 'http://status.automattic.com/9931/136079/Internap-API', 'http://status.automattic.com/9931/136079/Internap-API' ); ?></p>
</form>
</div>
</div>
	<?php
}

function internap_admin_warnings() {
	if ( !get_option('internap_domain') && !get_option('internap_user') && !get_option('internap_key') && !isset($_POST['submit']) ) {
		function internap_warning() {
			echo "
			<div id='internap-warning' class='updated fade'><p><strong>".__('Internap is almost ready.')."</strong> ".sprintf(__('You must <a href="%1$s">enter your Internap Webservice Information</a> for it to work.'), "plugins.php?page=internap-configuration")."</p></div>
			";
		}
		add_action('admin_notices', 'internap_warning');
		return;
	}
}

// Check connectivity between the WordPress blog and Internap's servers.
// Returns an associative array of server IP addresses, where the key is the IP address, and value is true (available) or false (unable to connect).
function internap_check_server_connectivity() {
	global $internap_api_host, $internap_api_port, $wpcom_api_key;

	$test_host = 'publicws.internapcdn.com';

	// Some web hosts may disable one or both functions
	if ( !function_exists('fsockopen') || !function_exists('gethostbynamel') )
	return array();

	$ips = gethostbynamel($test_host);
	if ( !$ips || !is_array($ips) || !count($ips) )
	return array();

	$servers = array();
	foreach ( $ips as $ip ) {
		$response = 'valid';
		// we have connectivity
		if ( $response == 'valid' || $response == 'invalid' )
		$servers[$ip] = true;
		else
		$servers[$ip] = false;
	}

	return $servers;
}

// Check the server connectivity and store the results in an option.
// Cached results will be used if not older than the specified timeout in seconds; use $cache_timeout = 0 to force an update.
// Returns the same associative array as internap_check_server_connectivity()
function internap_get_server_connectivity( $cache_timeout = 86400 ) {
	$servers = get_option('internap_available_servers');
	if ( (time() - get_option('internap_connectivity_time') < $cache_timeout) && $servers !== false )
	return $servers;

	// There's a race condition here but the effect is harmless.
	$servers = internap_check_server_connectivity();
	update_option('internap_available_servers', $servers);
	update_option('internap_connectivity_time', time());
	return $servers;
}

// Returns true if server connectivity was OK at the last check, false if there was a problem that needs to be fixed.
function internap_server_connectivity_ok() {
	$servers = internap_get_server_connectivity();
	return !( empty($servers) || !count($servers) || count( array_filter($servers) ) < count($servers) );
}

function internap_cdn() {
	global $internap_nonce, $wpcom_api_key;
	
	$purgePath = '';
	if ( isset($_POST['submit']) ) {
		if ( function_exists('current_user_can') && !current_user_can('manage_options') ) die(); // un-authorized
		check_admin_referer( $internap_nonce );
		$purgePath = trim($_POST['purgePath']);
		$purge_response = internap_call('Purge', $purgePath);
	}

	if ( empty( $auth_status) ||  $auth_status != 'valid' ) {
		$auth_domain = get_option('internap_domain');
		$auth_user = get_option('internap_user');
		$auth_key = get_option('internap_key');
		if ( empty( $auth_domain ) OR empty( $auth_user ) OR empty( $auth_key )) {
			if ( empty( $auth_status ) || $auth_status != 'failed' ) {
				if ( internap_get_server_connectivity(0) == '' )
				$ms[] = 'no_connection';
				else
				$ms[] = 'auth_empty';
			}
			$auth_status = 'empty';
		} else {
			$auth_status = internap_verify_auth($auth_domain, $auth_user, $auth_key);
		}
		if ( $auth_status == 'valid' ) {
			$ms[] = 'auth_valid';
		} else if ( $auth_status == 'invalid' ) {
			delete_option('internap_key');
			$ms[] = 'auth_empty';
		} else if ( !empty($auth_key) && $auth_status == 'failed' ) {
			$ms[] = 'auth_failed';
		}
	}
	$url_mappings = internap_call('ListUrlMappings');
	if (empty($url_mappings)) die('Empty Result');
	$url_mappings = $url_mappings['ListUrlMappingsResult'];
	if ($url_mappings['Status'] !== 'SUCCESS') die('Unsuccesful');
	$url_mappings = $url_mappings['Mappings']['UrlMapping'];
	if (empty($url_mappings)) die('Empty Result'); //print_r($url_mappings);
	foreach ($url_mappings as $row) {
	    $s[]  = $row['MatchedUrl'].$row['MappedUrl'].$row['MediaType'];
	}
	array_multisort($s, $url_mappings);
	?>
	<div class="wrap">
	<h2>URL Mapping</h2>
	<table class="wp-list-table widefat pages" cellspacing="0">
		<thead>
			<tr>
				<th>MatchedUrl</th>
				<th>MappedUrl</th>
				<th>MediaType</th>
				<th>IsSecure</th>
				<th>IsFullPath</th>
				<th>IsOriginPull</th>
			</tr>
		</thead>
	<?php
	$i = 0;
	foreach($url_mappings as $m) {
		$c = $i++&1 ? ' class="alt"' : '';
	?><tr<?php print $c; ?>>
		<td><?php print $m['MatchedUrl'] ?></td>
		<td><?php print $m['MappedUrl'] ?></td>
		<td><?php print $m['MediaType'] ?></td>
		<td><?php print $m['IsSecure'] ?></td>
		<td><?php print $m['IsFullPath'] ?></td>
		<td><?php print $m['IsOriginPull'] ?></td>
	</tr><?php
	}
	?>
	</table>
	<h2>Purge cached version of one origin pulled file</h2>
	<form action="" method="post" id="internap-cdn" style="margin: auto;">
		<?php internap_nonce_field($internap_nonce) ?>
		Purge Path (URL): <input id="purgePath" name="purgePath" type="text" size="100" value="<?php print ($purgePath);?>"/>
		<input type="submit" name="submit" value="<?php _e('Purge Now &raquo;'); ?>" />
	</form>
	</div>
	<?php 
}

function internap_call($method = '', $args) {
	if ( function_exists('current_user_can') && !current_user_can('manage_options') ) die(); // un-authorized
	$wsdl_url = 'http://publicws.internapcdn.com/UrlMapperWS/UrlMapper.asmx?wsdl';
	$useCURL = in_array('curl', get_loaded_extensions()); // use CURL is extension installed/loaded
	$cache = new wsdlcache('cache', 3600); // 1 hour
	$wsdl = $cache->get($wsdl_url);
	if (is_null($wsdl)) {
		$wsdl = new wsdl($wsdl_url, '', '', '', '', 0, 30, null, $useCURL);
		$err = $wsdl->getError();
		if ($err) {
			echo '<h2>WSDL Constructor error (Expect - 404 Not Found)</h2><pre>' . $err . '</pre>';
			echo '<h2>Debug</h2><pre>' . htmlspecialchars($wsdl->getDebug(), ENT_QUOTES) . '</pre>';
			exit();
		}
		$cache->put($wsdl);
	} else {
		$wsdl->clearDebug();
		$wsdl->debug('Retrieved from cache');
	}
	
	$client = new nusoap_client($wsdl, 'wsdl');
	
	$err = $client->getError();
	
	if ($err) {
		echo '<h2>Constructor error</h2><pre>' . $err . '</pre>';
	}
	
	$client->soap_defencoding = 'UTF-8';
	
	$params = array(
		'domain' => get_option('internap_domain'),
		'username' => get_option('internap_user'),
		'key' => get_option('internap_key')
	);
	
	if($method == 'Purge') $params['purgePath'] = $purgePath;
	
	$result = $client->call($method, $params);
	// Check for a fault
	if ($client->fault) {
		echo '<h2>Fault</h2><pre>';
		print_r($result);
		echo '</pre>';
	} else {
		// Check for errors
		$err = $client->getError();
		if ($err) {
			// Display the error
			echo '<h2>Error</h2><pre>' . $err . '</pre>';
		} else {
			return $result;
		}
	}
}
