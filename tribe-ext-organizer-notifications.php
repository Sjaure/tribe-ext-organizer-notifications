<?php
/**
 * Plugin Name:       Event Tickets Extension: Organizer Notifications
 * Description:       This extension sends a notification to organizers when an attendee registers for their event.
 * Version:           1.0.1
 * Plugin URI:        https://theeventscalendar.com/extensions/organizer-notification-email/
 * GitHub Plugin URI: https://github.com/mt-support/tribe-ext-organizer-notifications
 * Extension Class:   Tribe__Extension__Organizer_Notifications
 * Author:            The Events Calendar
 * Author URI:        https://evnt.is/1971
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tec-labs-organizer-notifications
 */

// Do not load unless Tribe Common is fully loaded.
if ( class_exists( 'Tribe__Extension' ) ) {
	/**
	 * Extension main class, class begins loading on init.
	 */
	class Tribe__Extension__Organizer_Notifications extends Tribe__Extension {

		/**
		 * Setup the Extension's properties.
		 */
		public function construct() {
			$this->add_required_plugin( 'Tribe__Tickets__Main', '4.11.1' );
		}

		/**
		 * Extension initialization and hooks.
		 */
		public function init() {
			// RSVP
			add_action( 'event_tickets_rsvp_tickets_generated', [ $this, 'trigger_email_RSVP' ], 10, 2 );

			// WooCommerce
			add_action( 'event_ticket_woo_attendee_created', [ $this, 'trigger_email_WooCommerce' ], 10, 3 );

			// Tribe Commerce
			add_action( 'event_tickets_tpp_tickets_generated', [ $this, 'trigger_email_Tribe_Commerce' ], 10, 2 );

			// EDD
			add_action( 'event_ticket_edd_attendee_created', [ $this, 'trigger_email_EDD' ], 10, 3 );

			// Tickets Commerce
			add_action( 'tec_tickets_commerce_flag_action_generated_attendees', [ $this, 'trigger_email_ticket_commerce' ], 10, 7 );	
		}

		
		/**
		 * Generate organizer email from ticket
		 *
		 * @param $other
		 * @param $ticket
		 */
		public function trigger_email_ticket_commerce( $attendees, $ticket, $order, $new_status, $old_status ) {

			// Get the Event ID the ticket is for
			$event_id = $ticket->get_event_id();
			$attendee_details = array( //for now we send an empty attendee name
				"Attendee_name" => '',
			);
			$this->generate_email($attendee_details, $event_id);
		}

		/**
		 * Trigger organizer email from RSVP
		 *
		 * @param $data the order ID
		 * @param $event_id
		 * @param $data2
		 */
		public function trigger_email_RSVP( $order_id = null, $event_id = null ) {
			global $wpdb;
			$sql = "SELECT post_id FROM wp_postmeta WHERE meta_key = '_tribe_rsvp_order' and meta_value = '".$order_id."'";
			$result = $wpdb->get_results($sql);
			$post_id = intval($result[0]->post_id);
			$attendee_name = get_post_meta($post_id,'_tribe_rsvp_full_name', true);
			$attendee_details = array(
				"Attendee_name" => $attendee_name,
			);
			$this->generate_email($attendee_details, $event_id);
		}

		/**
		 * Trigger organizer email from Woocommerce
		 *
		 * @param $data 
		 * @param $event_id
		 * @param $data2
		 */
		public function trigger_email_WooCommerce( $data = null, $event_id = null, $order = null ) {

			$order = wc_get_order( $order->id );
			$formated_data = sprintf( '<p>%s</a>', esc_html__( $order, 'tec-labs-organizer-notifications' ) );
			$attendee_details = array( //for now we send an empty attendee name
				"Attendee_name" => '',
			);
			$this->generate_email($attendee_details, $event_id);
		}

		/**
		 * Trigger organizer email from Tribe Commerce
		 *
		 * @param $data 
		 * @param $event_id
		 */
		public function trigger_email_Tribe_Commerce( $order_id = null, $event_id = null ) {
			$attendee_details = array( //for now we send an empty attendee name
				"Attendee_name" => '',
			);
			$this->generate_email($attendee_details, $event_id);
		}

		/**
		 * Trigger organizer email from Easy Digital Downloads
		 *
		 * @param $data 
		 * @param $event_id
		 * @param $data2
		 */
		public function trigger_email_EDD( $data = null, $event_id = null, $order_id = null ) {
			$attendee_details = array( //for now we send an empty attendee name
				"Attendee_name" => '',
			);
			$this->generate_email($attendee_details, $event_id);
		}

		/**
		 * Generate organizer email.
		 *
		 * @param $order_id
		 * @param $event_id
		 */
		public function generate_email( $attendee_details = null, $event_id = null ) {

			// Get the organizer email address.
			$to = $this->get_recipient( $event_id );

			// Bail if there's not a valid email for the organizer.
			if ( sizeof($to) == 0) { //validate the size of the returned array instead of the previous '' === $to
				echo "No organizers for this event";
				return;
			}

			// Get the email subject.
			$subject = $this->get_subject( $event_id );

			// Get the email content.
			$content = $this->get_content($attendee_details, $event_id );

			// Generate notification email.
			wp_mail( $to, $subject, $content, [ 'Content-type: text/html' ] );
		}

		/**
		 * Get all organizers' email addresses.
		 *
		 * @param $post_id
		 *
		 * @return array
		 */
		private function get_recipient( $post_id ) {

			// Get all organizers associated to the post.
			$organizer_ids = tribe_get_organizer_ids( $post_id );

			$to = [];

			// Get the email for each organizer.
			foreach ( $organizer_ids as $organizer_id ) {
				$organizer_email = tribe_get_organizer_email( $organizer_id, false );

				// Make sure it's a valid email.
				if ( is_email( $organizer_email ) ) {
					$to[] = $organizer_email;
				}
			}

			if ( empty( $to ) ) {
				return [];
			}

			return $to;
		}

		/**
		 * Get email subject.
		 *
		 * @param $post_id
		 *
		 * @return string
		 */
		private function get_subject( $post_id ) {

			// Filter to allow users to modify the email subject.
			$subject = apply_filters( 'tribe-ext-organizer-notifications-subject', 'Your event %1$s has new attendee(s) - %2$s' );

			// Return the subject with event and site names injected.
			return sprintf( __( $subject, 'tec-labs-organizer-notifications' ), get_the_title( $post_id ), get_bloginfo( 'name' ) );
		}

		/**
		 * Get link to attendees list.
		 *
		 * @param $post_id
		 *
		 * @return string
		 */
		private function get_content( $attendee_details, $post_id ) {

			// The url to the attendee page.
			$url = admin_url( 'edit.php?post_type=tribe_events&page=tickets-attendees&event_id=' . $post_id );

			// Default link text
			$default_link_text = "View the event's attendee list";

			// Filter to allow users to modify the link text.
			$link_text = apply_filters( 'tribe-ext-organizer-notifications-link-text', $default_link_text );

			//count of available tickets
			$tickets_available = tribe_events_count_available_tickets($post_id);

			// Define the output markup.
			$output = sprintf('<a> Attendee Name: %s </a>', esc_html__($attendee_details['Attendee_name'], 'tec-labs-organizer-notifications') );
			$output.= sprintf('<br><a> Available tickets: %s </a>', esc_html__($tickets_available, 'tec-labs-organizer-notifications') );
			$output.= sprintf( '<br><a href="%s">%s</a>', esc_url( $url ), esc_html__( $link_text, 'tec-labs-organizer-notifications' ) );
			// Return link markup.
			return apply_filters( 'tribe-ext-organizer-notifications-content', $output, $attendee_details, $post_id );
		}

	} // class
} // class_exists
