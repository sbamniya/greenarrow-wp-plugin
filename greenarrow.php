<?php
/*
Plugin Name: Green Arrow Newsletter
Description: Newsletter form for green arrow double otp-in
Version: 1.0
Author: Sonu Bamniya
Author URI: https://sbamniya.in
*/

function add_to_list($token) {
	if (!$token) {
		return;
	}
	$host   = get_option("ga-host");
	$apiKey   = get_option("ga-api-key");
	$listId   = get_option("ga-list-id");
	$email = json_decode(base64_decode($token))->email;

	// This is where you run the code and display the output
	 $curl = curl_init();
	 $url = "$host/ga/api/v2/mailing_lists/$listId/subscribers";
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

	 echo "<div class='ga-success-message'>Subscribed.</div>";
}

function deliver_mail() {

	// if the submit button is clicked, send the email
	if ( isset($_POST['nsf-submitted'] ) ) {

		// sanitize form values
		$email   = sanitize_email( $_POST["nsf-email"] );
		if ( empty( $email ) ) {
			echo "<div class='ga-error-message'>Please enter email.</div>";
		} else {
			$isDoubleOptIn = get_option("ga-double-opt");
			$token = base64_encode(json_encode(["email" => $email]));
			if ($isDoubleOptIn == "1") {
				$subject = "Thank you for subscribing to our newsletter";
				$body = "<div>You have successfully subscribed to our newsletter. Please click on the link to confirm your subscription.</div><div><a href='".get_home_url()."?ga-confirmation-token=$token'>Confirm Subscription</a></div>";
				$headers = array('Content-Type: text/html; charset=UTF-8');
				if (wp_mail($email, $subject,$body, $headers)) {
					echo "<div class='ga-error-message'>You have successfully subscribed to our newsletter. We have sent you a confirmation link on the email.</div>";
				} else {
					echo "<div class='ga-success-message'>An error occurred while sending email.</div>";
				}
			} else {
				add_to_list($token);
			}
		}

	}
}
function html_form_code() {
	?>
    <div>
		<p>Subscribe to our daily newsletter</p>
		<?php 
			deliver_mail(); 
			if (isset($_GET['ga-confirmation-token'])) {
				add_to_list($_GET['ga-confirmation-token']);
				wp_redirect(home_url(), '302' );
			}
		?>
		
		<form action="<?=esc_url($_SERVER['REQUEST_URI'])?>" method="post" class="ga-newsletter-form">
			<label class="label">Email</label>
			<input type="email" required id="nsf-email" name="nsf-email" value="<?=isset( $_POST["cf-email"] ) ? esc_attr( $_POST["nsf-email"] ) : '' ?>" size="40" class="email-input" />
			<p><input type="checkbox" required name="nsf-confirm" value="1"> By continuing, you accept the privacy policy</p>
			<p><input type="submit" name="nsf-submitted" value="Subscribe"></p>
			
		</form>
	</div>

	<?php
}


function cf_shortcode() {
	ob_start();
	html_form_code();

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

		update_option("ga-host", $host);
		update_option("ga-api-key", $apiKey);
		update_option("ga-list-id", $listId);
		update_option("ga-double-opt", $isDoubleOptIn);
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