<?php
/**
 * Checks if wp_mail() works.
 *
 * @package Site Health Tools
 */

namespace SiteHealthTools;

// Make sure the file is not directly accessible.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you can not directly access this file.' );
}

/**
 * Class Mail Check
 */
class Mail_Check extends Site_Health_Tool {

	/**
	 * The error object for the mail check.
	 *
	 * @var \WP_Error
	 */
	private $mail_error = null;

	public function __construct() {
		$this->label       = \__( 'Mail Check', 'site-health-tools' );
		$this->description = \__( 'The Mail Check will invoke the <code>wp_mail()</code> function and check if it succeeds. We will use the E-mail address you have set up, but you can change it below if you like.', 'site-health-tools' );

		\add_action( 'wp_ajax_site-health-mail-check', array( $this, 'run_mail_check' ) );

		parent::__construct();
	}

	/**
	 * Checks if wp_mail() works.
	 *
	 * @uses sanitize_email()
	 * @uses wp_mail()
	 * @uses wp_send_json_success()
	 * @uses wp_die()
	 *
	 * @return void
	 */
	public function run_mail_check() {
		\check_ajax_referer( 'site-health-mail-check' );

		if ( ! \current_user_can( 'view_site_health_checks' ) ) {
			\wp_send_json_error();
		}

		add_action( 'wp_mail_failed', array( $this, 'mail_failed' ) );

		$output        = '';
		$sendmail      = false;
		$email         = \sanitize_email( $_POST['email'] );
		$email_message = \sanitize_text_field( $_POST['email_message'] );
		$wp_address    = \get_bloginfo( 'url' );
		$wp_name       = \get_bloginfo( 'name' );
		$date          = \date_i18n( \get_option( 'date_format' ), current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$time          = \date_i18n( \get_option( 'time_format' ), current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		// translators: %s: website url.
		$email_subject = sprintf( \esc_html__( 'Site Health Tools – Test Message from %s', 'site-health-tools' ), $wp_address );

		$email_body = sprintf(
		// translators: %1$s: website name. %2$s: website url. %3$s: The date the message was sent. %4$s: The time the message was sent.
			\__( 'Hi! This test message was sent by the Site Health Tools plugin from %1$s (%2$s) on %3$s at %4$s. Since you’re reading this, it obviously works.', 'site-health-tools' ),
			$wp_name,
			$wp_address,
			$date,
			$time,
			$email_message
		);

		if ( ! empty( $email_message ) ) {
			$email_body .= "\n\n" . sprintf(
				// translators: %s: The custom message that may be included with the email.
				__( 'Additional message from admin: %s', 'site-health-tools' ),
				$email_message
			);
		}

		// Store the time before we send the email.
		$pre_send_timer = microtime( true );

		$sendmail = \wp_mail( $email, $email_subject, $email_body );

		// Store the time after we send the email.
		$post_send_timer = microtime( true );

		if ( ! empty( $sendmail ) ) {
			$output .= '<div class="notice notice-success inline"><p>';
			$output .= \__( 'We have just sent an e-mail using <code>wp_mail()</code> and it seems to work. Please check your inbox and spam folder to see if you received it.', 'site-health-tools' );
			$output .= '</p></div>';

			// Check how long the `wp_mail` function took, if it exceeds 3 seconds, it may indicate that something is not working correctly.
			if ( ( $post_send_timer - $pre_send_timer ) > 3 ) {
				$output .= '<div class="notice notice-warning inline"><p>';
				$output .= \__( 'The e-mail took a while to send; this may indicate that your server is really busy, or that the sending of emails may be experiencing other unexpected issues. If you experience continued issues, consider reaching out to your hosting provider.', 'site-health-tools' );
				$output .= '</p></div>';
			}
		} else {
			$output .= '<div class="notice notice-error inline"><p>';
			$output .= \esc_html__( 'It seems there was a problem sending the e-mail.', 'site-health-tools' );
			$output .= '</p><p>' . $this->mail_error->get_error_message();
			$output .= '</p></div>';
		}

		$response = array(
			'message' => $output,
		);

		\wp_send_json_success( $response );
	}

	/**
	 * Capture errors when sending emails from WordPress.
	 *
	 * @param \WP_Error $error A WP_Error object containing the PHPMailer error.
	 *
	 * @return void
	 */
	public function mail_failed( $error ) {
		$this->mail_error = $error;
	}

	/**
	 * Add the Mail Checker to the tools tab.
	 *
	 * @return void
	 */
	public function tab_content() : void {
		?>
		<form action="#" id="site-health-mail-check" method="POST">
			<table class="widefat tools-email-table">
				<tr>
					<td>
						<p>
							<?php
							$current_user = \wp_get_current_user();
							?>
							<label for="email"><?php \esc_html_e( 'Email', 'site-health-tools' ); ?></label>
							<input type="text" name="email" id="email" value="<?php echo \esc_attr( $current_user->user_email ); ?>">
						</p>
					</td>
					<td>
						<p>
							<label for="email_message"><?php \esc_html_e( 'Additional message', 'site-health-tools' ); ?></label>
							<input type="text" name="email_message" id="email_message" value="">
						</p>
					</td>
				</tr>
			</table>
			<input type="submit" class="button button-primary" value="<?php \esc_html_e( 'Send test mail', 'site-health-tools' ); ?>">
		</form>

		<div id="tools-mail-check-response-holder">
			<span class="spinner"></span>
		</div>
		<?php
	}
}

new Mail_Check();
