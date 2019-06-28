<?php
/**
 * Plugin Name:       SQRL Login
 * Description:       Login and Register your users using SQRL
 * Version:           0.2.0
 * Author:            Daniel Persson
 * Author URI:        http://danielpersson.dev
 * Text Domain:       sqrl
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * GitHub Plugin URI: http://github.com/kalaspuffar/wordpress-sqrl
 */

class SQRLLogin{

    /**
     * SQRLLogin constructor.
     */
    public function __construct() {
		add_action('login_form', array($this, 'addToLoginForm'));

		add_action( 'admin_post_sqrl_login', array($this, 'loginCallback'));
        add_action( 'admin_post_nopriv_sqrl_login', array($this, 'loginCallback'));
        add_action( 'admin_post_sqrl_auth', array($this, 'apiCallback'));
        add_action( 'admin_post_nopriv_sqrl_auth', array($this, 'apiCallback'));

		add_action( 'admin_post_sqrl_check_login', array($this, 'checkIfLoggedInAjax'));
		add_action( 'admin_post_nopriv_sqrl_check_login', array($this, 'checkIfLoggedInAjax'));

		add_action( 'edit_user_profile', array($this, 'associateSQRL') );
		add_action( 'show_user_profile', array($this, 'associateSQRL') );

		add_action( 'admin_post_sqrl_disassociate', array($this, 'disAssociateUser') );
	}

	function associateSQRL($user) {
		$adminPostPath = parse_url(admin_url('admin-post.php'), PHP_URL_PATH);

		?>
		<h3>Associate SQRL to profile</h3>
		<?php
		if(get_user_meta($user->id, 'idk', true)) {
			?>
			<table class="form-table">
				<tr>
					<th>
					</th>
					<td>
						<div class="sqrl-form">
							<a href="<?php echo $adminPostPath ?>?action=sqrl_disassociate">Disassociate SQRL identity</a>
						</div>
					</td>
				</tr>
			</table>
			<?php
		} else {
			?>
			<table class="form-table">
				<tr>
					<th>
					</th>
					<td>
						<div class="sqrl-form">
							<?php $this->addToLoginForm($user); ?>
						</div>
					</td>
				</tr>
			</table>
			<?php
		}
	}


	function checkIfLoggedInAjax() {
		$siteURL = "https://uhash.com";
		header("Access-Control-Allow-Origin: {$siteURL}");
		header('Access-Control-Allow-Credentials: true');
		header('Access-Control-Max-Age: 1');    // cache for 1 day
		header("Access-Control-Allow-Methods: GET, OPTIONS");

		$wp_users = get_users(array(
			'meta_key'     => 'sqrl_session',
			'meta_value'   => sanitize_text_field($_GET['session']),
			'number'       => 1,
			'count_total'  => false,
			'fields'       => 'id',
		));

		if($wp_users[0]) {
			echo "true";
		} else {
			echo "false";
		}
	}

	function generateRandomString($length = 16) {
		return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
	}

    public function addToLoginForm($user = false) {
        if (get_option( 'users_can_register' )) {
            $button_label = __('Login or Register with SQRL', 'sqrl');
        } else {
            $button_label = __('Login with SQRL', 'sqrl');
        }

		$adminPostPath = parse_url(admin_url('admin-post.php'), PHP_URL_PATH);

		$siteUrl = explode("://", get_site_url());
		$domainName = $siteUrl[0];
		if(count($siteUrl) == 2) {
			$domainName = $siteUrl[1];
		}

		$session = $this->generateRandomString();
		$nut = $this->generateRandomString();
		$sqrlURL = 'sqrl://' . $domainName . $adminPostPath . '?action=sqrl_auth&nut=' . $nut . '-' . $session;

		if($user) {
			set_transient($session, $user->id, 15 * 60);
		}

		$html .= '<div class="sqrl-login-wrapper">';
		$html .= '	<div class="sqrl-login-row">';
		$html .= '		<a id="sqrl" href="' . $sqrlURL . '" onclick="sqrlLinkClick(this);return true;" encoded-sqrl-url="' . $this->base64url_encode($sqrlURL) . '" tabindex="-1">';
		$html .= '			<img src="' . plugins_url( 'images/sqrl-button.png', __FILE__ ) . '"/>';
		$html .= '		</a>';
		$html .= '	</div>';
		$html .= '	<div class="sqrl-login-row">';
		$html .= '		<img src="https://chart.googleapis.com/chart?chs=150x150&cht=qr&chld=M|0&chl=' . urlencode($sqrlURL) . '"/>';
		$html .= '		<div>';
		$html .= '			You may also login with SQRL using';
		$html .= '			any SQRL-equipped smartphone by';
		$html .= '			scanning this QR code.';
		$html .= '		</div>';
		$html .= '	</div>';
		$html .= '	<div class="sqrl-login-row">';
		$html .= '		<span id="reloadDisplay"></span>';
		$html .= '	</div>';
		$html .= '	<div class="sqrl-login-row">';
		$html .= '	    <a href="https://play.google.com/store/apps/details?id=org.ea.sqrl">';
		$html .= '		   <img src="' . plugins_url( 'images/en_badge_web_generic.png', __FILE__ ) . '" alt="Get it on Google Play" height="60" />';
		$html .= '	    </a>';
		$html .= '	    <a href="https://www.grc.com/files/sqrl.exe">';
		$html .= '		   <img src="' . plugins_url( 'images/microsoft.png', __FILE__ ) . '" alt="Get it for Windows" height="42" />';
		$html .= '	    </a>';
		$html .= '	</div>';
		$html .= '</div>';

		wp_enqueue_script('pagesync', plugin_dir_url(__FILE__).'pagesync.js');
		wp_enqueue_script('reload', plugin_dir_url(__FILE__).'reload.js');

		wp_localize_script('reload', 'sqrlReload', array(
			'adminURL' => admin_url('admin-post.php'),
			'session' => plugins_url(),
		));

		wp_enqueue_style('style', plugin_dir_url(__FILE__).'style.css');

		echo $html;
	}

	public function loginCallback() {
		$session = $_GET['session'];
		if(empty($session)) {
			$nutSession = explode('-', $_GET["nut"]);
			$session = $nutSession[1];
		}

		$wp_users = get_users(array(
			'meta_key'     => 'sqrl_session',
			'meta_value'   => sanitize_text_field($session),
			'number'       => 1,
			'count_total'  => false,
			'fields'       => 'id',
		));

		delete_user_meta( $wp_users[0], 'sqrl_session');
		wp_set_auth_cookie( $wp_users[0] );

		header("Location: " . get_site_url(), true);
	}

	public function apiCallback() {
		$clientStr = explode("\r\n", $this->base64url_decode($_POST["client"]));

		$client = array();
		foreach ($clientStr as $k => $v) {
			$p = explode("=", $v);
			$client[$p[0]] = $p[1];
		}

		$adminPostPath = parse_url(admin_url('admin-post.php'), PHP_URL_PATH);

		$result = sodium_crypto_sign_verify_detached ($this->base64url_decode($_POST["ids"]), $_POST["client"] . $_POST["server"] , $this->base64url_decode($client["idk"]) );

		$serverStr = explode("\r\n", $this->base64url_decode($_POST["server"]));
		if(count($serverStr) == 1) {
			foreach (explode("&", $serverStr[0]) as $k => $v) {
				$p = explode("=", $v);
				$server[$p[0]] = $p[1];
			}
		} else {
			$server = array();
			foreach ($serverStr as $k => $v) {
				$p = explode("=", $v);
				$server[$p[0]] = $p[1];
			}
		}

		$nutSession = explode('-', $server["nut"]);
		$nutSession[0] = $this->generateRandomString();

		$options = array();
		foreach (explode("~", $client["opt"]) as $v) {
			$options[$v] = true;
		}

		$retVal = $options["noiptest"] ? 0 : 4;

		$response = array();

		$response[] = "ver=1";
		$response[] = "nut=" . $nutSession[0] . '-' . $nutSession[1];
		$response[] = "qry=" . $adminPostPath . "?action=sqrl_auth&nut=" . $nutSession[0] . '-' . $nutSession[1];
		if($client['cmd'] == 'query') {
			if($this->accountPresent($client)) {
				$retVal += 1;
				if($options["suk"]) {
					$response[] = "suk=" . $this->getServerUnlockKey($client);
				}
			}
		} else if($client['cmd'] == 'ident') {
			if(!$this->accountPresent($client)) {
				$retVal += 1;
				$user = get_transient($nutSession[1]);
				delete_transient($nutSession[1]);

				if($user) {
					$this->associateUser($user, $client, $nutSession[1]);
				} else {
					$this->createUser($client, $nutSession[1]);
				}
			}

			$this->addUserSession($client, $server);
			if(strpos($client['opt'], 'cps') !== false) {
				$response[] = "url=" . get_site_url() . $adminPostPath . "?action=sqrl_login&nut=" . $nutSession[0] . '-' . $nutSession[1];
				$response[] = "can=" . get_site_url() . "?q=canceled";
			}
		} else {
			error_log(print_r($client, true));
		}

		$response[] = "tif=" . $retVal;
		$response[] = "sin=0";

		header('Content-Type: application/x-www-form-urlencoded');

        echo $this->base64url_encode(implode("\r\n", $response));
    }

	private function createUser($client, $session) {
		$new_user = wp_create_user($this->get_random_unique_username('user_'), wp_generate_password(), 'nobody@localhost');
		associateUser($new_user);
	}

	private function associateUser($user, $client, $session) {
		update_user_meta( $new_user, 'idk', sanitize_text_field($client['idk']) );
		update_user_meta( $new_user, 'suk', sanitize_text_field($client['suk']) );
		update_user_meta( $new_user, 'vuk', sanitize_text_field($client['vuk']) );

		update_user_meta( $new_user, 'sqrl_session', sanitize_text_field($session) );
	}

	public function disAssociateUser() {
		$user = wp_get_current_user();

		delete_user_meta( $user->id, 'idk');
		delete_user_meta( $user->id, 'suk');
		delete_user_meta( $user->id, 'vuk');
		delete_user_meta( $user->id, 'sqrl_session');

		header("Location: " . admin_url('profile.php'), true);
	}

	private function addUserSession($client, $server) {
		$wp_users = get_users(array(
			'meta_key'     => 'idk',
			'meta_value'   => sanitize_text_field($client['idk']),
			'number'       => 1,
			'count_total'  => false,
			'fields'       => 'id',
		));

		$nutSession = explode('-', $server["nut"]);

		update_user_meta( $wp_users[0], 'sqrl_session', $nutSession[1] );
	}

	private function getServerUnlockKey($client) {
		$wp_users = get_users(array(
			'meta_key'     => 'idk',
			'meta_value'   => sanitize_text_field($client['idk']),
			'number'       => 1,
			'count_total'  => false,
			'fields'       => 'id',
		));

		return get_user_meta($wp_users[0], "suk", true);
	}


	private function accountPresent($client) {
		$wp_users = get_users(array(
			'meta_key'     => 'idk',
			'meta_value'   => sanitize_text_field($client['idk']),
			'number'       => 1,
			'count_total'  => false,
			'fields'       => 'id',
		));

		if(empty($wp_users[0])) {
			return false;
		}
		return true;
	}

	function get_random_unique_username( $prefix = '' ){
		$user_exists = 1;
		do {
		   $rnd_str = sprintf("%0d", mt_rand(1, 99999999999999));
		   $user_exists = username_exists( $prefix . $rnd_str );
	   } while( $user_exists > 0 );
	   return $prefix . $rnd_str;
	}

	function base64url_encode($data, $pad = null) {
		$data = str_replace(array('+', '/'), array('-', '_'), base64_encode($data));
		if (!$pad) {
			$data = rtrim($data, '=');
		}
		return $data;
	}
	function base64url_decode($data) {
		return base64_decode(str_replace(array('-', '_'), array('+', '/'), $data));
	}

}

new SQRLLogin();
