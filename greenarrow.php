<?php
/*
Plugin Name: Green Arrow Newsletter
Description: Newsletter form for green arrow double otp-in
Version: 1.0
Author: Sonu Bamniya
Author URI: https://sbamniya.in
*/
function clean_email($address) {
	$addressArr = explode("@", $address);
	$username = $addressArr[0];
	$domain = $addressArr[1];
	$usernameWithoutPlus = explode("+", $username)[0];
	$usernameWithoutMinus = explode("-", $usernameWithoutPlus)[0];
    return implode("", explode(".", $usernameWithoutMinus))."@".$domain;
}

function add_to_list($token) {
	if (!$token) {
		return false;
	}
	$host   = get_option("ga-host");
	$apiKey   = get_option("ga-api-key");
	$listId   = get_option("ga-list-id");
	$email = json_decode(base64_decode($token))->email;

	// This is where you run the code and display the output
	 $curl = curl_init();
	 $url = rtrim($host, "/")."/ga/api/v2/mailing_lists/$listId/subscribers";
	 curl_setopt_array($curl, array(
	   CURLOPT_URL => $url,
	   CURLOPT_RETURNTRANSFER => true,
	   CURLOPT_FOLLOWLOCATION => true,
	   CURLOPT_ENCODING => "",
	   CURLOPT_MAXREDIRS => 10,
	   CURLOPT_TIMEOUT => 30,
	   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	   CURLOPT_CUSTOMREQUEST => "POST",
	   CURLOPT_HTTPHEADER => array(
		 "Authorization: Basic $apiKey",
		 "Content-Type: application/json",
		 "User-Agent: API",
		 "Accept: */*"
	   ),
	   CURLOPT_POSTFIELDS => json_encode(array(
		"subscriber" => array(
		  "email" => $email,
		  "status" => "active",
		  "subscribe_ip" => null,
		  "subscribe_time" => gmdate("Y-m-d\TH:i:s\Z"),
		),
	  ))
	 ));
	 curl_exec($curl);
	 curl_error($curl);
	 curl_close($curl);
	 echo "<div class='ga-success-message'>You have been successfully Subscribed.</div>";
	 return true;
}

function check_email_exists($email) {
	$host   = get_option("ga-host");
	$apiKey   = get_option("ga-api-key");
	$listId   = get_option("ga-list-id");

	// This is where you run the code and display the output
	 $curl = curl_init();
	 $url = rtrim($host, "/")."/ga/api/v2/mailing_lists/$listId/subscribers/".$email;
	 curl_setopt_array($curl, array(
	   CURLOPT_URL => $url,
	   CURLOPT_RETURNTRANSFER => true,
	   CURLOPT_FOLLOWLOCATION => true,
	   CURLOPT_ENCODING => "",
	   CURLOPT_MAXREDIRS => 10,
	   CURLOPT_TIMEOUT => 30,
	   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	   CURLOPT_CUSTOMREQUEST => "GET",
	   CURLOPT_HTTPHEADER => array(
		 "Authorization: Basic $apiKey",
		 "Content-Type: application/json",
		 "User-Agent: API",
		 "Accept: */*"
	   )
	 ));
	 $response = curl_exec($curl);
	
	 curl_close($curl);
	 $response = json_decode($response);
	 return $response->success;
}

function deliver_mail($location) {

	if (!isset($_POST[$location.'-nsf-submitted'] ) ) {
		return false;
	}
	// sanitize form values
	$email   = sanitize_email( $_POST[$location."-nsf-email"] );
	if ( empty( $email ) ) {
		echo "<div class='ga-error-message'>Please enter email.</div>";

		return false;
	} 
	
	$email = clean_email($email);
	$isDoubleOptIn = get_option("ga-double-opt");
	$isGoogleRecaptchaEnabled = get_option("ga-google-captcha") == 1;
	if ($isGoogleRecaptchaEnabled) {
		$recaptcha = $_POST['g-recaptcha-response'];
		$secret_key = get_option("ga-google-site-secret");
		// Hitting request to the URL, Google will
		// respond with success or error scenario
		$url = 'https://www.google.com/recaptcha/api/siteverify?secret='
			. $secret_key . '&response=' . $recaptcha;
	
		// Making request to verify captcha
		$response = file_get_contents($url);
	
		// Response return by google is in
		// JSON format, so we have to parse
		// that json
		$response = json_decode($response);
		
		// Checking, if response is true or not
		if ($response->success != true) {
			echo "<div class='ga-error-message'>Please verify captcha.</div>";
			return false;
		}
	}
	$isExists = check_email_exists($email);
	if ($isExists == "1") {
		echo "<div class='ga-error-message'>You are already a subscriber.</div>";
		return false;
	}
	$token = base64_encode(json_encode(["email" => $email]));

	if ($isDoubleOptIn == "1") {
		$homeURL = get_home_url();
		$subject = "Thank you for subscribing to our newsletter";
		$body = '<div>One last step, to successfully subscribe to our newsletter. Please tap on "Confirm Your Subscription" to complete the signup process.</div><div><a href="'.$homeURL.esc_url( $_SERVER['REQUEST_URI']).'?ga-confirmation-token=$token">Confirm Subscription</a></div>';
		
		// $headers = array('Content-Type: text/html; charset=UTF-8');
		// if (!wp_mail($email, $subject,$body, $headers)) {
		// 	echo "<div class='ga-success-message'>You have successfully subscribed to our newsletter. We have sent you a confirmation link on the email.</div>";
		// 	return;
		// } 
		
		// This is where you run the code and display the output
		$curl = curl_init();
		$url = "https://theamericanretiree.herokuapp.com/send-email";
		$domainArr = parse_url($homeURL);
		$domain = $domainArr["host"];
		curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_HTTPHEADER => array(
			"Content-Type: application/json",
			"User-Agent: API",
			"Accept: */*"
		),
		CURLOPT_POSTFIELDS => json_encode(array(
					"to" => $email,
					"from" => get_bloginfo("name"),
					"subject" => $subject,
					"message" => $body,
					"email" => "noreply@$domain"
				)
			)
		));
		curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);
		if ($code === 200) {
			echo '<h4>One Last Step:</h4><div class="ga-success-message">Simply click the link in your email to confirm your subscription, please be sure to check your <b>spam</b> or <b>junk</b> folder.</div>';
			return true;
		}
		echo "<div class='ga-error-message'>An error occurred while sending email.</div>";
		
		return false;
	} 

	return add_to_list($token);
}

function html_form_code($location) {
    $isGoogleRecaptchaEnabled = get_option("ga-google-captcha") == 1 ? true : false;
	if ($isGoogleRecaptchaEnabled) {
		?>
			<script src="https://www.google.com/recaptcha/api.js" async defer></script>
		<?php
	}
	?>
    <div>
		<?php 
			$isSuccess = deliver_mail($location); 
			if (isset($_GET['ga-confirmation-token'])) {
				add_to_list($_GET['ga-confirmation-token']);
				wp_redirect(home_url(), '302' );
			}
	 	if($isSuccess == false){
		?>
		<p>Subscribe to our daily newsletter</p>
		
		<form action="<?=esc_url($_SERVER['REQUEST_URI'])?>" method="post" class="ga-newsletter-form">
			<label class="label">Email</label>
			<input type="email" required id="nsf-email" name="<?=$location?>-nsf-email" value="<?=isset( $_POST[$location."-nsf-email"] ) ? esc_attr( $_POST[$location."-nsf-email"] ) : '' ?>" size="40" class="email-input" />
			<?php
			if ($isGoogleRecaptchaEnabled) {
				?>
					<div class="g-recaptcha" data-sitekey="<?=get_option("ga-google-site-key")?>" style="margin-top: 10px"></div>
					<br/>
				<?php
			}
			?>
			<p><input type="checkbox" required name="nsf-confirm" value="1"> By continuing, you accept the privacy policy</p>
			<p><input type="submit" name="<?=$location?>-nsf-submitted" value="Subscribe"></p>
		</form>
		<?php } ?>
	</div>
	<style>
		.ga-success-message {
			padding: 10px 20px;
			background-color: #afdde8;
			border-radius: 7px;
			margin-bottom: 10px;
		}
		.ga-error-message{
			padding: 10px 20px;
			background-color: #e8afaf;
			border-radius: 7px;
			margin-bottom: 10px;
		}
	</style>
	<?php
}


function cf_shortcode( $atts ) {
    $attributes = shortcode_atts(array(
        'location' => "footer",
    ), $atts);

	ob_start();
	html_form_code($attributes['location']);

	return ob_get_clean();
}

add_shortcode( 'greenarrow_newsletter', 'cf_shortcode' );


function save_greenarrow_configuration() {
	if (isset($_POST['ga-save-settings'])) {
		// sanitize form values
		$host   = $_POST["ga-host"];
		$apiKey   = $_POST["ga-api-key"];
		$listId   = $_POST["ga-list-id"];
		$isDoubleOptIn   = isset($_POST["ga-double-opt"]) ? 1 : 0;
		$googleCaptchaEnabled   = isset($_POST["ga-google-captcha"]) ? 1 : 0;
		$googleSiteKey   = $_POST["ga-google-site-key"];
		$googleSiteSecret = $_POST["ga-google-site-secret"];

		update_option("ga-host", $host);
		update_option("ga-api-key", $apiKey);
		update_option("ga-list-id", $listId);
		update_option("ga-double-opt", $isDoubleOptIn);
		update_option("ga-google-captcha", $googleCaptchaEnabled);
		update_option("ga-google-site-key", $googleSiteKey);
		update_option("ga-google-site-secret", $googleSiteSecret);
	}
}

function green_arrow_newsletter_configuration() {
		settings_fields( 'green_arrow_newsletter_configuration' );
		do_settings_sections( 'green_arrow_newsletter_configuration' );
    ?>
    <h2>Configure Green Arrow Newsletter</h2>
	
	<?php save_greenarrow_configuration(); ?>

	<form action="<?=esc_url( $_SERVER['REQUEST_URI'])?>" method="post">
	<table class="form-table" role="presentation">
		<tbody>
		<tr>
			<th scope="row"><label for="ga-host">Host</label></th>
			<td>
			<input
				name="ga-host"
				type="text"
				id="ga-host"
				value="<?=get_option("ga-host")?>"
				class="regular-text"
				aria-describedby="ga-host-description"
			/>
			<p class="description" id="ga-host-description">
				URL of the api, where green arrow is deployed
			</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ga-api-key">API Key</label></th>
			<td>
			<input
				name="ga-api-key"
				type="text"
				id="ga-api-key"
				value="<?=get_option("ga-api-key")?>"
				class="regular-text"
				aria-describedby="ga-api-key-description"
			/>
			<p class="description" id="ga-api-key-description">
				API Key provided by green arrow
			</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><label for="ga-list-id">List ID</label></th>
			<td>
			<input
				name="ga-list-id"
				type="text"
				id="ga-list-id"
				aria-describedby="ga-list-id-description"
				value="<?=get_option("ga-list-id")?>"
				class="regular-text"
			/>
			<p class="description" id="ga-list-id-description">
				List you want the email to be pushed in.
			</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><label for="ga-double-opt">Double Opt-in</label></th>
			<td>
			<?php 
			if (get_option("ga-double-opt") == 1) {
				?>
				<input
					name="ga-double-opt"
					type="checkbox"
					id="ga-double-opt"
					aria-describedby="ga-double-opt-description"
					value="1"
					class="regular-text"
					checked
				/>
				<?php
			} else {
				?>
				<input
					name="ga-double-opt"
					type="checkbox"
					id="ga-double-opt"
					aria-describedby="ga-double-opt-description"
					value="1"
					class="regular-text"
				/>
				<?php
			}
			?>
			<p class="description" id="ga-double-opt-description">
				If it's checked, it will send an email before the user is added to the list.
			</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ga-double-opt">Enable Google re-Captcha</label></th>
			<td>
			<?php 
			if (get_option("ga-google-captcha") == 1) {
			?>
				<input
					name="ga-google-captcha"
					type="checkbox"
					id="ga-google-captcha"
					aria-describedby="ga-google-captcha-description"
					value="1"
					class="regular-text"
					checked
				/>
				<?php
			} else {
			?>
				<input
					name="ga-google-captcha"
					type="checkbox"
					id="ga-google-captcha"
					aria-describedby="ga-google-captcha-description"
					value="1"
					class="regular-text"
				/>
				<?php
			}
			?>
			<p class="description" id="ga-google-captcha-description">
				If you want to have google captcha verification on the website.
			</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ga-list-id">Google API Key</label></th>
			<td>
			<input
				name="ga-google-site-key"
				type="text"
				id="ga-google-site-key"
				aria-describedby="ga-google-site-key-description"
				value="<?=get_option("ga-google-site-key")?>"
				class="regular-text"
			/>
			<p class="description" id="ga-google-site-key-description">
				Public key provided by google.
			</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ga-list-id">Google Secret</label></th>
			<td>
			<input
				name="ga-google-site-secret"
				type="text"
				id="ga-google-site-secret"
				aria-describedby="ga-google-site-secret-description"
				value="<?=get_option("ga-google-site-secret")?>"
				class="regular-text"
			/>
			<p class="description" id="ga-google-site-secret-description">
				Secret provided by google. Required for verification.
			</p>
			</td>
		</tr>
		<tr>
			<td>
			<input
				name="ga-save-settings"
				type="submit"
				value="Save"
				class="button button-primary"
			/>
			</td>
		</tr>
		</tbody>
	</table>
	</form>

    <?php
}


function greearrow_settings_page() {
	add_options_page( 'Newsletter plugin page', 'Green Arrow Configuration', 'manage_options', 'greenarrow-newsletter', 'green_arrow_newsletter_configuration' );
}
add_action( 'admin_menu', 'greearrow_settings_page' );
?>