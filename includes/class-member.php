<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


use \DCMM_Users\create_member_as_user;
use DCMM\Gateways\Gateway_Manager;

/**
 * Set up the Member post type
 * 
 * TODO: Need to handle the case where the email address is changed in the CPT
 * TODO: create method to check if user is member
 * TODO: this class should only be a Member object; get rid of developer helpers (post type, etc.)
 */

class DCMM_Member extends WP_User {

	/**
	 * The ID of the WP User
	 * 
	 * TODO: This should be renamed to something like $wp_user_id
	 */
	protected $member_id = null;
	
	/**
	 * The ID of the CPT post
	 * 
	 * TODO: This should be renamed to memberID
	 */
	protected $cpt_id = null;

	/**
	 * The ID of the WP User
	 */
	protected $wp_user_id = null;

	protected static $our_post_type = 'dcmm-member';
	protected static $meta_prefix = 'dcmm_';
	
	// TODO: use the $meta_prefix property instead; 
	protected static $meta_keys = array(
		'nonce_prefix' => 'dcmm_member_info_',
		'status'	=>	'dcmm_' . 'status',
		'wp_user_id' => 'dcmm_' . 'wp_user_id',
		'start_date' => 'dcmm_' . 'membership_start_date',
		'dues_payment' => 'dcmm_' . 'last_dues_payment',
		'expiration_date' => 'dcmm_' . 'expiration_date',
		'settings_hash' => 'dcmm_' . 'settings_hash',
		'first_name' => 'dcmm_' . 'first_name',
		'last_name' => 'dcmm_' . 'last_name',
		'email' => 'dcmm_' . 'email',
		'phone' => 'dcmm_' . 'phone',
		'address' => 'dcmm_' . 'mailing_address',
		'street1' => 'dcmm_' . 'street1',
		'street2' => 'dcmm_' . 'street2',
		'city' => 'dcmm_' . 'city',
		'state' => 'dcmm_' . 'state',
		'zip' => 'dcmm_' . 'zip',
		// Recurring subscription fields
		'subscription_id' => 'dcmm_' . 'subscription_id',
		'subscription_status' => 'dcmm_' . 'subscription_status',
		'subscription_interval' => 'dcmm_' . 'subscription_interval',
		'subscription_gateway' => 'dcmm_' . 'subscription_gateway',
		'subscription_created' => 'dcmm_' . 'subscription_created',
		'subscription_cancelled' => 'dcmm_' . 'subscription_cancelled',
		
	);

	static $instance;

	// Member info
	protected $start_date = null;
	protected $last_dues_payment = null;
	protected $first_name = null;
	protected $last_name = null;
	protected $email = null;
	protected $phone = null;
	protected $mailing_address = null;
	protected $membership_status = null;

	/**
	 * Create a new Member object.
	 * 
	 * If you want to access an existing Member, you can pass in the CPT Post ID.
	 * 
	 * TODO: change this to construct using post ID OR email
	 */
	public function __construct( $key = null ) {

		// if the version of the plugin is less than 1.0, use the old constructor
		if ( version_compare( DCMM_VERSION, '1.0.0', '>=' ) ) {
			$this->construct_v_1_0_0( $key );
		} else {
			$this->construct_v_0_1( $key );
		}
	}

	/**
	 * New constructor stores most meta in the CPT instead of the user
	 */
	function construct_v_1_0_0( $key = null ) {
		
		// let's see if there's a WordPress user associated
		if ( !is_null ( $key ) ) {

			// were we passed an email address or a post ID?
			if ( is_numeric( $key ) ) {

				// it's a CPT post ID, add it to the object...
				// legacy property
				$this->cpt_id = intval( $key );
				// new property
				$this->member_id = intval( $key );

				// ...and get the email address
				$email = get_post_meta( $key, self::$meta_keys['email'], true );
			} else {

				// if it's an email address, use that
				$email = $key;
			}
		}

		// load the member's meta (from the CPT) onto the Member object
		$this->load_member_meta( $this->member_id );
	
		// register meta boxes
		// TODO: move this elsewhere
		require_once( 'class-member-metaboxes.php' );
		new DCMM_metaboxes();

	}

	/**
	 * Old constructor used WP User for a lot of meta
	 */
	function construct_v_0_1( $key = null ) {
		
		// let's see if there's a WordPress user associated
		if ( !is_null ( $key ) ) {

			// were we passed an email address or a post ID?
			if ( is_numeric( $key ) ) {

				// it's a CPT post ID, add it to the object...
				$this->cpt_id = $key;

				// ...and get the email address
				$email = get_post_meta( $key, self::$meta_keys['email'], true );
			} else {

				// if it's an email address, use that
				$email = $key;
			}

			// now we can get the WP User (if it exists)
			$wordpress_user = \get_user_by( 'email', $key );

			if ( $wordpress_user ) {
				// if so, check if WP User has role of "Organizational Member"
				if ( !in_array( 'organizational_member', $wordpress_user->roles ) ) {
					// if not, add the role
					$wordpress_user->set_role( 'organizational_member' );
				}

				$this->wp_user_id = $wordpress_user->ID;

				// load the user's meta to the object
				$this->load_user_meta( $this->wp_user_id );

			} else {
				
				// if not, create a WP user, giving it a role of "Organizational Member"
				include_once( 'functions-user-role.php' );
				$wp_user_id = \DCMM_Users\create_member_as_user( $email, $this->cpt_id );

				if ( ! \is_wp_error( $wp_user_id ) ) {
					$this->wp_user_id = $wp_user_id;
				}
			}
		}
		
		// register meta boxes
		// TODO: move this elsewhere
		require_once( 'class-member-metaboxes.php' );
		new DCMM_metaboxes();

	}

	/**
	 * Check if a member exists
	 * 
	 * Checks the CPT ID, making sure it's of our post type, and that it isn't trashed, etc.
	 * 
	 * @return bool 
	 */
	function exists() {
		// Check if we have a valid member ID
		if ( empty( $this->member_id ) || ! is_numeric( $this->member_id ) ) {
			return false;
		}
		
		// Check if the post exists
		$post = get_post( $this->member_id );
		if ( ! $post ) {
			return false;
		}
		
		// Check if it's the correct post type
		if ( $post->post_type !== self::$our_post_type ) {
			return false;
		}
		
		// Check if the post is published (not trashed, etc.)
		if ( $post->post_status !== 'publish' ) {
			return false;
		}
		
		return true;
	}

	/**
	 * Gets the meta keys for the Member post type.
	 * 
	 * If a value is passed in that matches a key, the value is returned; 
	 * If no value is passed in, an array of all the keys is returned.
	 * 
	 * @param string $key 
	 * 
	 * @return string|array 
	 */
	public static function get_meta_keys( $key = null ) {

		// if a key is passed in, return that key's value
		if ( !is_null( $key ) ) {

			return self::$meta_keys[ $key ];
			
		} else {

			// if no key is passed in, return all the keys
			return self::$meta_keys;
		}
	}

	/**
	 * Gets the post type
	 * 
	 */
	public static function get_post_type() {
		return self::$our_post_type;
	}

	/**
	 * Output the HTML for the Member Info inputs for the Member post type
	 * 
	 * Usually used on the Edit screen for the Member post type
	 * 
	 * @uses \DCMM_Member::get_meta_keys()
	 * @uses \DCMM_Member::get()
	 * 
	 * @return void
	 */
	public function get_member_info_form() {
		 
		// get the CPT post ID
		if ( $this->cpt_id ) {
			$cpt_id = $this->cpt_id;
		} 

		// get the meta keys for the CPT
		$meta_keys = \DCMM_Member::get_meta_keys();

		$nonce_prefix = $meta_keys['nonce_prefix'];

		$first_name = $this->get( 'first_name' );
		$last_name = $this->get( 'last_name' );
		$email = $this->get( 'email' );
		$mailing_address = $this->get( 'address' );
		$phone = $this->get( 'phone' );

		// nonces for each meta field
		foreach ( $meta_keys as $meta_key ) {
			wp_nonce_field( $nonce_prefix, $meta_key . '_nonce' );
		}
		?>

		<div class="dcm-metabox">
			<h3>Name</h3>
			<div class="form-section">
				<div class="form-row">
					<!-- first name -->
					<div class="form-group half">
						<label for="<?php echo esc_attr( $meta_keys['first_name'] ); ?>">First Name:</label>
						<input type="text" name="<?php echo esc_attr( $meta_keys['first_name'] ); ?>" id="<?php echo esc_attr($meta_keys['first_name']); ?>" <?php echo !empty( $first_name ) ? ' value="' . esc_html( $first_name ) . '"' : ''; ?> />
					</div>
						
					<!-- last name -->
					<div class="form-group half">
						<label for="<?php echo esc_attr( $meta_keys['last_name'] ); ?>">Last Name:</label>
						<input type="text" name="<?php echo esc_attr( $meta_keys['last_name'] ); ?>" id="<?php echo esc_attr( $meta_keys['last_name'] ); ?>" <?php echo !empty( $last_name ) ? ' value="' . esc_html( $last_name ) . '"' : ''; ?> />
					</div>
				</div>
			</div>

			<h3>Contact Info</h3>
			<div class="form-section">
				
				<!-- Email -->
				<div class="form-row">
					<label for="<?php echo esc_attr( $meta_keys['email'] ); ?>">Email:</label>
					<input type="email" name="<?php echo esc_attr( $meta_keys['email'] ); ?>" id="<?php echo esc_attr( $meta_keys['email'] ); ?>" <?php echo !empty( $email ) ? ' value="' . esc_html( $email ) . '"' : 'placeholder="member@example.com"'; ?> required />
				</div>

				<!-- Phone -->
				<div class="form-row">
					<label for="<?php echo esc_attr( $meta_keys['phone'] ); ?>">Phone Number:</label>
					<input type="tel" name="<?php echo esc_attr( $meta_keys['phone'] ); ?>" id="<?php echo esc_attr( $meta_keys['phone'] ); ?>" <?php echo !empty( $phone ) ? ' value="' . esc_html( $phone ) . '"' : 'placeholder="Phone"'; ?> />
				</div>
			</div>


			<!-- Mailing Address -->
			<div class="form-section">
				<h4>Mailing Address</h4>
				<div class="form-row">
					<label for="<?php echo esc_attr( $meta_keys['address'] ); ?>[street1]" >Street:</label>
					<input type="text" name="<?php echo esc_attr( $meta_keys['address'] ); ?>[street1]" id="<?php echo esc_attr( $meta_keys['address'] ); ?>_street1" <?php echo !empty( $mailing_address['street1'] ) ? ' value="' . esc_html( $mailing_address["street1"] ). '"' : 'placeholder="Street"'; ?> />
					<br />
					<input type="text" name="<?php echo esc_attr( $meta_keys['address'] ); ?>[street2]" id="<?php echo esc_attr( $meta_keys['address'] ); ?>_street2" <?php echo !empty( $mailing_address['street2'] ) ? ' value="' . esc_html( $mailing_address["street2"] ) . '"' : 'placeholder=""'; ?> />
				</div>

				<div class="form-row">
					<label for="<?php echo esc_attr( $meta_keys['address'] ); ?>[city]" >City:</label>
					<input type="text" name="<?php echo esc_attr( $meta_keys['address'] ); ?>[city]" id="<?php echo esc_attr( $meta_keys['address'] ); ?>_city" <?php echo !empty( $mailing_address['city'] ) ? ' value="' . esc_html( $mailing_address["city"] ) . '"' : 'placeholder="City"'; ?> />
				</div>

				<div class="form-row">
					<div class="form-group half">
						<label for="<?php echo esc_attr( $meta_keys['address'] ); ?>[state]" >State:</label>
						<input type="text" name="<?php echo esc_attr( $meta_keys['address'] ); ?>[state]" id="<?php echo esc_attr( $meta_keys['address'] ); ?>_state" <?php echo !empty( $mailing_address['state'] ) ? ' value="' . esc_html( $mailing_address["state"] ) . '"' : 'placeholder="State"'; ?> />
					</div>

					<div class="form-group half">
						<label for="<?php echo esc_attr( $meta_keys['address'] ); ?>[zip]" >Zip:</label>
						<input type="text" name="<?php echo esc_attr( $meta_keys['address'] ); ?>[zip]" id="<?php echo esc_attr( $meta_keys['address'] ); ?>_zip" <?php echo !empty( $mailing_address['zip'] ) ? ' value="' . esc_html( $mailing_address["zip"] ) . '"' : 'placeholder="Zip"'; ?> />
					</div>
				</div>
			</div>

		</div>

		<?php
	}

	/**
	 * Gets the ID of the Member custom post type
	 * 
	 * @return int The ID of the Member post type
	 */
	function get_member_id() {
		return $this->member_id;
	}

	/**
	 * Checks if there is a WP User associated with this Member
	 * 
	 * @return bool True if there is a WP User, false if not
	 */
	function has_wp_user() {
		return !empty( $this->wp_user_id );
	}

	/**
	 * Gets the ID of the associated WP User, as stored in our DCMM_Member object
	 * 
	 * @return int The ID of the WP User | FALSE if there is no WP User
	 */
	function get_wp_user_id() {
		
		if ( ! $this->has_wp_user() ) {
			return false;
		} else {
			return $this->wp_user_id;
		}
	}

	/**
	 * Creates a WP User account for this Member, using their email address.
	 * 
	 * TODO: create more robust error handling
	 * 
	 * @uses \DCMM_Users\create_member_as_user()
	 * 
	 * @return int The ID of the WP User | WP_Error if there was an error (usually no email address)
	 */
	function create_wp_user() {

		// make sure we have an email address
		if ( empty( $this->email ) ) {
			return new WP_Error( 'no_email', 'No email address found for this member' );
		}

		// if there is no WP User, create one
		if ( ! $this->has_wp_user() ) {
			// create a WP User with the role of "Organizational Member"
			include_once( 'functions-user-role.php' );
			$user_ID = \DCMM_Users\create_member_as_user( $this->email, $this->cpt_id );

			// if user was created successfully, save the ID to the object & CPT
			if ( ! \is_wp_error( $user_ID ) ) {
				$this->save( 'wp_user_id', $user_ID );

				// and save the CPT ID to the WP User meta
				$user_meta_saved = add_user_meta( $user_ID, 'dcmm_post_id', $this->cpt_id );

			}
		} else {
			$user_ID = $this->get_wp_user_id();
			// Ensure the already-linked user has the member role (guards against role
			// being stripped after the link was established, e.g. plugin deactivation)
			include_once( 'functions-user-role.php' );
			\DCMM_Users\add_member_role( $user_ID );
		}

		return $user_ID;
	}

	/**
	 * AJAX handler to create a WP User account for a Member
	 * 
	 * Linked to the 'dcmm_create_wp_user_account' AJAX action
	 * 
	 * TODO: check if there's an email address before trying to create the WP User
	 * TODO: create more robust error handling
	 * 
	 * @uses \DCMM_Member::ajax_create_wp_user_account()
	 * 
	 * @return void
	 */
	public static function ajax_create_wp_user_account() {
		
		// check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'dcmm_create_wp_user_account' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		}

		// get the CPT ID
		if ( ! isset( $_POST['cpt_id'] ) || ! is_numeric( $_POST['cpt_id'] ) ) {
			wp_send_json_error( array( 'message' => 'Invalid CPT ID' ) );
		}

		$cpt_id = intval( $_POST['cpt_id'] );

		// create a new Member object
		$member = new DCMM_Member( $cpt_id );

		// if there's no email address, return error
		if ( empty( $member->email ) ) {
			wp_send_json_error( array( 'message' => 'No email address found for this member' ) );
		}

		// create the WP User
		$member->create_wp_user();

		if ( $member->has_wp_user() ) {
			wp_send_json_success( array( 'message' => 'WP User created successfully', 'user_id' => $member->get_wp_user_id() ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to create WP User' ) );
		}
	}

	/**
	 * Loads the user's meta into the object
	 * 
	 * TODO: Look into whether I need to include member status in this?
	 * TODO: make this more efficient by allowing to load only specific meta
	 * 
	 * @param int $cpt_id The CPT post ID of the Member
	 * 
	 * @uses \DCMM_Member::get_meta_keys()
	 * @uses \DCMM_Member::load_first_name_onto_member_object()
	 * @uses \DCMM_Member::load_last_name_onto_member_object()
	 * @uses \DCMM_Member::load_email_onto_member_object()
	 * @uses \DCMM_Member::load_phone_onto_member_object()
	 * @uses \DCMM_Member::load_mailing_address_onto_member_object()
	 */
	protected function load_member_meta( $cpt_id = null ) {

		if ( is_null( $cpt_id ) ) {
			$cpt_id = $this->cpt_id;
		}

		// get the meta keys for the CPT
		$meta_keys = \DCMM_Member::get_meta_keys();

		// load member's WP User ID
		$this->load_wp_user_id_onto_member_object( $cpt_id );

		// load member's start date
		$this->load_start_date_onto_member_object( $cpt_id );

		// load member's dues payment
		$this->load_dues_payment_onto_member_object( $cpt_id );

		// load member's first name
		$this->load_first_name_onto_member_object( $cpt_id );

		// load member's last name
		$this->load_last_name_onto_member_object( $cpt_id );

		// load member's email
		$this->load_email_onto_member_object( $cpt_id );

		// load member's phone
		$this->load_phone_onto_member_object( $cpt_id );

		// load member's mailing address
		$this->load_mailing_address_onto_member_object( $cpt_id );

	}

	/**
	 * Loads the user's WP User ID into the Member object
	 * 
	 * @param int $cpt_id The ID of the Member post type
	 * 
	 * @uses \DCMM_Member::get_meta_keys()
	 * 
	 * @return void
	 */
	protected function load_wp_user_id_onto_member_object( $cpt_id = null ) {
		if ( is_null( $cpt_id ) ) {
			$cpt_id = $this->cpt_id;
		}

		// get the meta keys for the CPT
		$meta_keys = \DCMM_Member::get_meta_keys();

		// get the user's WP User ID...
		$raw_value = get_post_meta( $cpt_id, $meta_keys['wp_user_id'], true );
		
		// if we get anything, make sure it's an integer
		if ( is_numeric( $raw_value ) && $raw_value > 0 ) {
			$this->wp_user_id = intval( $raw_value );
		} else {
			// if we didn't get a valid WP User ID, set it to null
			$this->wp_user_id = null;
		}
	}

	/**
	 * Loads the user's start date into the Member object
	 * 
	 * @param int $cpt_id The ID of the Member post type
	 * 
	 * @uses \DCMM_Member::get_meta_keys()
	 * 
	 * @return void
	 */
	protected function load_start_date_onto_member_object( $cpt_id = null ) {
		if ( is_null( $cpt_id ) ) {
			$cpt_id = $this->cpt_id;
		}

		// get the meta keys for the CPT
		$meta_keys = \DCMM_Member::get_meta_keys();

		// get the user's WP User ID
		$this->start_date = get_post_meta( $cpt_id, $meta_keys['start_date'], true );
	}

	/**
	 * Loads the user's dues payment into the Member object
	 * 
	 * @param int $cpt_id The ID of the Member post type
	 * 
	 * @uses \DCMM_Member::get_meta_keys()
	 * 
	 * @return void
	 */
	protected function load_dues_payment_onto_member_object( $cpt_id = null ) {
		if ( is_null( $cpt_id ) ) {
			$cpt_id = $this->cpt_id;
		}

		// get the meta keys for the CPT
		$meta_keys = \DCMM_Member::get_meta_keys();

		// get the user's WP User ID
		$this->last_dues_payment = get_post_meta( $cpt_id, $meta_keys['dues_payment'], true );
	}

	/**
	 * Loads the user's first name into the Member object
	 * 
	 * @param int $cpt_id The ID of the Member post type
	 * 
	 * @uses \DCMM_Member::get_meta_keys()
	 * 
	 * @return void
	 */
	protected function load_first_name_onto_member_object( $cpt_id = null ) {

		if ( is_null( $cpt_id ) ) {
			$cpt_id = $this->cpt_id;
		}

		// get the meta keys for the CPT
		$meta_keys = \DCMM_Member::get_meta_keys();

		// get the user's first name
		$this->first_name = get_post_meta( $cpt_id, $meta_keys['first_name'], true );
	}

	/**
	 * Loads the user's last name into the Member object
	 * 
	 * @param int $cpt_id The ID of the Member post type
	 * 
	 * @uses \DCMM_Member::get_meta_keys()
	 * 
	 * @return void
	 */
	protected function load_last_name_onto_member_object( $cpt_id = null ) {

		if ( is_null( $cpt_id ) ) {
			$cpt_id = $this->cpt_id;
		}

		// get the meta keys for the CPT
		$meta_keys = \DCMM_Member::get_meta_keys();

		// get the user's last name
		$this->last_name = get_post_meta( $cpt_id, $meta_keys['last_name'], true );
	}

	/**
	 * Loads the user's membership status into the Member object
	 * 
	 * @param int $member_ID The ID of the Member post type
	 * 
	 * @uses \DCMM_Member::get_meta_keys()
	 * 
	 * @return void
	 */
	protected function load_membership_status_onto_member_object( $cpt_id = null ) {

		if ( is_null( $cpt_id ) ) {
			$cpt_id = $this->cpt_id;
		}

		// get the meta keys for the CPT
		$meta_keys = \DCMM_Member::get_meta_keys();

		// get the user's last name
		$this->membership_status = get_post_meta( $cpt_id, $meta_keys['status'], true );
	}

	/**
	 * Loads the user's email into the Member object
	 * 
	 * @param int $cpt_id The ID of the Member post type
	 * 
	 * @uses \DCMM_Member::get_meta_keys()
	 * 
	 * @return void
	 */
	protected function load_email_onto_member_object( $cpt_id = null ) {

		// if we weren't passed a CPT ID, use the object's CPT ID
		if ( is_null( $cpt_id ) ) {
			$cpt_id = $this->cpt_id;
		}

		// get the meta keys for the CPT
		$meta_keys = \DCMM_Member::get_meta_keys();

		// get the member's email
		$email = get_post_meta( $cpt_id, $meta_keys['email'], true );

		// if $email isn't a WP, set it to the object
		if ( ! is_wp_error( $email ) ) {
			$this->email = $email;
		}	
	}

	/**
	 * Loads the user's phone into the Member object
	 * 
	 * @param int $cpt_id The ID of the Member post type
	 * 
	 * @uses \DCMM_Member::get_meta_keys()
	 * 
	 * @return void
	 */
	protected function load_phone_onto_member_object( $cpt_id = null ) {

		if ( is_null( $cpt_id ) ) {

			// TODO: decide do i want to use cpt_id or cpt_id?
			$cpt_id = $this->cpt_id;

		}

		// get the meta keys for the CPT
		$meta_keys = \DCMM_Member::get_meta_keys();

		// get the member's phone
		$phone = get_post_meta( $cpt_id, $meta_keys['phone'], true );

		// if $phone isn't a WP, set it to the object
		if ( ! is_wp_error( $phone ) ) {
			$this->phone = $phone;
		}
	}

	/**
	 * Loads the user's mailing address into the Member object
	 * 
	 * @param int $cpt_id The ID of the Member post type
	 * 
	 * @uses \DCMM_Member::get_meta_keys()
	 * 
	 * @return void
	 */
	protected function load_mailing_address_onto_member_object( $cpt_id = null ) {

		if ( is_null( $cpt_id ) ) {
			$cpt_id = $this->cpt_id;
		}

		// get the meta keys for the CPT
		$meta_keys = \DCMM_Member::get_meta_keys();

		// get the member's mailing address
		$mailing_address = get_post_meta( $cpt_id, $meta_keys['address'], true );

		// if $mailing_address isn't a WP, set it to the object
		if ( ! is_wp_error( $mailing_address ) ) {
			$this->mailing_address = $mailing_address;
		}
	}

	/**
	 * Save the Member's info to CPT post meta
	 * 
	 * TODO: make this more efficient by only updating the relevant property after saving the meta
	 * 
	 * @param string $key The meta key to save. Use the key from \DCMM_Member::get_meta_keys()
	 * @param mixed $value The value to save
	 * 
	 * @uses \DCMM_Member::get_meta_keys()
	 * 
	 * @return bool True if the meta was saved, false if not
	 */
	public function save( $key, $value ) {

		// get the meta keys for the CPT
		$meta_keys = \DCMM_Member::get_meta_keys();

		// check if the $key passed in is a valid meta key
		if ( !isset( $meta_keys[$key] ) ) {
			return false;
		}

		// get current value of the meta key
		$current_value = get_post_meta( $this->cpt_id, $meta_keys[$key], true );
		
		// Track status changes for hooks
		$status_changed = false;
		$old_status = null;
		$new_status = null;
		
		if ( $key === 'status' && $value !== $current_value ) {
			$status_changed = true;
			$old_status = $current_value;
			$new_status = $value;
		}

		// if new meta was added, and there was no previous value, add it
		if ( $value && '' == $current_value ) {
			add_post_meta( $this->cpt_id, $meta_keys[$key], $value, true );
		}

		// if there was a previous value, but it doesn't match new value, update it
		elseif ( $value && $value != $current_value ) {
			update_post_meta( $this->cpt_id, $meta_keys[$key], $value );
		}

		// if there is no new value, but there was a previous value, delete it
		elseif ( '' == $value && $current_value ) {
			delete_post_meta( $this->cpt_id, $meta_keys[$key], $current_value );
		}

		// and then make sure to update the Member object
		// TODO: make this more efficient by only updating the relevant property
		$this->load_member_meta( $this->cpt_id );
		
		// Fire status change hook if status was updated
		if ( $status_changed ) {
			// Log if debugging enabled
			// error_log("DCMM: Firing member status change hook - Member: {$this->cpt_id}, Old: {$old_status}, New: {$new_status}");
			do_action( 'dcmm_member_status_changed', $this->cpt_id, $old_status, $new_status );
		}

		// Trigger auto-sync to WordPress user if enabled and relevant field changed
		if ( $this->is_personal_info_sync_enabled() ) {
			$sync_fields = array( 'first_name', 'last_name', 'email' );
			if ( in_array( $key, $sync_fields ) && $value !== $current_value ) {
				$this->sync_to_wp_user();
			}
		}

		return true;
	}

	/**
	 * Get member info from the Member post type
	 * 
	 * @param string $key The meta key to get
	 * 
	 * @uses \DCMM_Member::get_meta_keys()
	 * 
	 * @return mixed The value of the meta key
	 */
	public function get( $key ) {

		// get the meta keys for the CPT
		$meta_keys = $this::get_meta_keys();

		// check if the $key passed in is a valid meta key
		if ( !isset( $meta_keys[$key] ) ) {
			return false;
		}

		// get the meta value
		$meta_value = get_post_meta( $this->cpt_id, $meta_keys[$key], true );

		// if $meta_value isn't a WP error, return it
		if ( !is_wp_error( $meta_value ) ) {
			return $meta_value;
		} else {
			return false;
		}
	}

	/**
	 * Maybe charge for renewal dues
	 * 
	 * If dues are enabled, this will start the payment flow.
	 * If dues are not enabled, it will renew the membership without charging.
	 * 
	 * @param string $payment_type Optional payment type: 'one_time' or 'subscription'
	 * @param string $interval Optional billing interval for subscriptions: 'monthly', 'yearly', 'seasonal'
	 * @return mixed Returns a payment object or a WP_Error if dues are enabled but no amount is set.
	 */
	public function maybe_charge_for_renewal( $payment_type = 'one_time', $interval = null ) {
		
		// check if dues are enabled
		if ( ! \DCMM_Settings\are_dues_enabled() ) {
			return $this->renew_membership( 'manual:no_dues' );
		}

		// If dues are enabled, get the amount
		$amount = \DCMM_Settings\get_dues_amount();
		if ( ! $amount || ! is_numeric( $amount) ) {
			return new \WP_Error( 'invalid_dues_amount', 'Dues are enabled, but no valid amount is set.' );
		}

		// Load active gateway
		$gateway = Gateway_Manager::get_default_gateway();

		// Handle subscription vs one-time payment
		if ( $payment_type === 'subscription' ) {
			return $this->start_subscription_renewal( $gateway, floatval( $amount ), $interval );
		} else {
			return $this->start_one_time_renewal( $gateway, floatval( $amount ) );
		}
	}

	/**
	 * Start one-time payment renewal process
	 * 
	 * @param object $gateway Payment gateway instance
	 * @param float $amount Payment amount
	 * @return mixed Payment result or WP_Error
	 */
	private function start_one_time_renewal( $gateway, $amount ) {
		// Standard one-time payment flow
		return $gateway->start_payment( $this, $amount );
	}

	/**
	 * Start subscription renewal process
	 * 
	 * @param object $gateway Payment gateway instance
	 * @param float $amount Subscription amount
	 * @param string $interval Billing interval
	 * @return mixed Subscription result or WP_Error
	 */
	private function start_subscription_renewal( $gateway, $amount, $interval ) {
		// Check if gateway supports subscriptions
		if ( ! $gateway->supports_subscriptions() ) {
			return new \WP_Error( 
				'subscription_not_supported', 
				'The selected payment gateway does not support recurring subscriptions.' 
			);
		}

		// Check if member already has an active subscription
		if ( $this->has_active_subscription() ) {
			return new \WP_Error( 
				'existing_subscription', 
				'You already have an active subscription. Please cancel your current subscription before creating a new one.' 
			);
		}

		// If no interval provided, get it from membership term length
		if ( is_null( $interval ) ) {
			$term_length = \DCMM_Settings\get_settings( 'dcmm_membership_term_length' );
			$interval = $this->map_term_to_subscription_interval( $term_length );
		}

		// Create the subscription
		return $gateway->create_subscription( $this->get_member_id(), $amount, $interval );
	}
	
	/**
	 * Map membership term length to subscription billing interval
	 * 
	 * @param string $term_length Membership term length
	 * @return string Subscription interval
	 */
	private function map_term_to_subscription_interval( $term_length ) {
		switch ( $term_length ) {
			case 'yearly':
				return 'yearly';
			case 'monthly':
				return 'monthly';
			case 'seasonal':
				return 'semi_annually'; // 6 months
			default:
				return 'monthly'; // Default fallback
		}
	}

	/**
	 * Get available renewal options for this member
	 * 
	 * @return array Available renewal options
	 */
	public function get_renewal_options() {
		$options = [];
		
		// Always include one-time payment option
		$amount = \DCMM_Settings\get_dues_amount();
		$options['one_time'] = [
			'type' => 'one_time',
			'label' => 'One-time Payment',
			'description' => 'Pay $' . number_format( $amount, 2 ) . ' for membership renewal',
			'amount' => $amount,
			'available' => true,
		];

		// Add subscription options if gateway supports them
		$gateway = Gateway_Manager::get_default_gateway();
		if ( $gateway && $gateway->supports_subscriptions() ) {
			
			// Check if member already has active subscription
			$has_active_subscription = $this->has_active_subscription();
			
			// Get the actual membership term length from settings
			$term_length = \DCMM_Settings\get_settings( 'dcmm_membership_term_length' );
			if ( ! $term_length ) {
				$term_length = 'monthly'; // Default fallback
			}
			
			// Create subscription option that matches membership term
			$subscription_option = $this->create_subscription_option( $term_length, $amount, $has_active_subscription );
			if ( $subscription_option ) {
				$options['subscription'] = $subscription_option;
			}

			$options['subscription_available'] = true;
			$options['subscription_interval'] = $term_length; // Use actual term for UI display
		} else {
			$options['subscription_available'] = false;
		}

		// Add common data for the UI
		$options['amount'] = $amount;
		$options['currency'] = '$'; // TODO: make this configurable

		return apply_filters( 'dcmm_member_renewal_options', $options, $this );
	}
	
	/**
	 * Create subscription option based on membership term length
	 * 
	 * @param string $term_length Membership term length
	 * @param float $amount Base amount
	 * @param bool $has_active_subscription Whether member has active subscription
	 * @return array|null Subscription option array or null if term not supported
	 */
	private function create_subscription_option( $term_length, $amount, $has_active_subscription ) {
		switch ( $term_length ) {
			case 'yearly':
				return [
					'type' => 'subscription',
					'interval' => 'yearly',
					'label' => 'Yearly Subscription',
					'description' => 'Automatically pay $' . number_format( $amount, 2 ) . ' every year',
					'amount' => $amount,
					'available' => !$has_active_subscription,
					'disabled_reason' => $has_active_subscription ? 'You already have an active subscription' : null,
				];
				
			case 'monthly':
				return [
					'type' => 'subscription',
					'interval' => 'monthly',
					'label' => 'Monthly Subscription',
					'description' => 'Automatically pay $' . number_format( $amount, 2 ) . ' every month',
					'amount' => $amount,
					'available' => !$has_active_subscription,
					'disabled_reason' => $has_active_subscription ? 'You already have an active subscription' : null,
				];
				
			case 'seasonal':
				// Map seasonal to 6-month billing for subscription purposes
				$seasonal_amount = $amount; // Keep same amount but bill twice per year
				return [
					'type' => 'subscription',
					'interval' => 'semi_annually', // 6 months
					'label' => 'Seasonal Subscription',
					'description' => 'Automatically pay $' . number_format( $seasonal_amount, 2 ) . ' every 6 months',
					'amount' => $seasonal_amount,
					'available' => !$has_active_subscription,
					'disabled_reason' => $has_active_subscription ? 'You already have an active subscription' : null,
				];
				
			default:
				// Unsupported term length for subscriptions
				return null;
		}
	}

	/**
	 * Get member's current subscription status for display
	 * 
	 * @return array|false Subscription status info or false if no subscription
	 */
	public function get_subscription_status_info() {
		if ( ! $this->has_active_subscription() ) {
			return false;
		}

		$subscription_data = $this->get_subscription_data();
		if ( ! $subscription_data ) {
			return false;
		}

		// Get real-time status from gateway if possible
		$gateway = Gateway_Manager::get_gateway( $subscription_data['gateway'] );
		if ( $gateway && $gateway->supports_subscriptions() ) {
			$gateway_status = $gateway->get_subscription_status( $subscription_data['id'] );
			if ( ! is_wp_error( $gateway_status ) ) {
				$subscription_data = array_merge( $subscription_data, $gateway_status );
			}
		}

		return [
			'id' => $subscription_data['id'],
			'status' => $subscription_data['status'],
			'interval' => $subscription_data['interval'],
			'gateway' => $subscription_data['gateway'],
			'next_billing' => $subscription_data['next_billing_time'] ?? null,
			'last_payment_amount' => $subscription_data['last_payment_amount'] ?? null,
			'created' => $subscription_data['created'],
			'can_cancel' => in_array( $subscription_data['status'], ['active', 'trialing'] ),
		];
	}

	/**
	 * Subscribe a member to the organization
	 * 
	 * Fires `dcmm_member_subscribed` action upon completion.
	 * 
	 * TODO: standardize how we're storing date/times
	 * TODO: fix start date being overridden 
	 * TODO: add support for different contexts (e.g. 'renewal', 'signup')
	 * TODO: Fix that we don't always have a dues payment
	 * 
	 * @param $cpt_id (optional) ID of the cpt Member to subscribe. If none passed, uses the curent object
	 * @param $context (optional) Default: 'signup'. 
	 * 
	 */
	private function subscribe_to_membership( $context = 'signup' ) {

		$cpt_id = $this->get_member_id();

		$today = current_time( 'Y-m-d H:i:s' );

		// store initial signup date, if doesn't already exist
		$start_date_result = $this->save( 'start_date', $today );
		
		// record the dues payment
		$dues_result = $this->save( 'dues_payment', $today );

		// set member as active
		$status_result = $this->save( 'status', 'active' );
		
		// Calculate and store expiration date
		$this->get_expiration_date();

		// Fire payment received hook
		$payment_data = array(
			'amount' => \DCMM_Settings\get_dues_amount(),
			'date' => $today,
			'context' => $context,
			'method' => 'subscription'
		);
		do_action( 'dcmm_member_payment_received', $cpt_id, $payment_data );

		do_action( 'dcmm_member_subscribed', $cpt_id, $context );

		return true;

	}

	/**
	 * Renew a member's membership
	 * 
	 */
	function renew_membership( $context = 'manual' ) {

		$today = current_time( 'Y-m-d H:i:s' );

		$this->log( 'renew_membership', $context );

		$result = $this->subscribe_to_membership( "renew:$context" );
		
		// Force recalculation of expiration date after renewal
		$this->save( 'settings_hash', '' );
		$this->get_expiration_date();

		return $result;

	}

	/**
	 * Get the member's membership expiration date
	 * 
	 * Uses hybrid approach: stores calculated date in database for performance,
	 * but recalculates when membership settings change.
	 * 
	 * @return string|null Expiration date in Y-m-d format or null if no expiration
	 */
	public function get_expiration_date() {

		// Check if we have a stored expiration date and settings hash
		$stored_date = $this->get( 'expiration_date' );
		$stored_hash = $this->get( 'settings_hash' );
		$current_hash = $this->get_settings_hash();
		
		// Don't recalculate expiration for inactive members - they have no future expiration
		if ( $this->get( 'status' ) === 'inactive' ) {
			return $stored_date; // Return empty string or existing stored date
		}
		
		// If no stored date or settings have changed, recalculate
		if ( ! $stored_date || $stored_hash !== $current_hash ) {
			$calculated_date = $this->calculate_expiration_date();
			
			if ( $calculated_date ) {
				$this->save( 'expiration_date', $calculated_date );
				$this->save( 'settings_hash', $current_hash );
			}
			
			return $calculated_date;
		}
		
		return $stored_date;
	}

	/**
	 * Calculate expiration date based on membership settings and start date
	 * 
	 * TODO: Fix anchor date being considered when join policy is 'rolling'
	 * TODO: Need to consider the renewal window
	 * 
	 * @return string|null Expiration date in Y-m-d format or null if no expiration
	 */
	private function calculate_expiration_date() {

		$start_date = $this->get( 'start_date' );
		if ( ! $start_date ) {
			return null;
		}
		
		// Get membership settings
		$join_policy = DCMM_Settings\get_settings( 'dcmm_join_policy' );
		$term_length = DCMM_Settings\get_settings( 'dcmm_membership_term_length' );
		$anchor_date = DCMM_Settings\get_settings( 'dcmm_anchor_date' );
		
		// Default to rolling monthly if no settings
		if ( ! $join_policy ) $join_policy = 'rolling';
		if ( ! $term_length ) $term_length = 'monthly';
		
		$start_timestamp = strtotime( $start_date );
		
		switch ( $join_policy ) {
			case 'rolling':
				return $this->calculate_rolling_expiration( $start_timestamp, $term_length );
				
			case 'fixed_term':
				return $this->calculate_fixed_term_expiration( $start_timestamp, $term_length );
				
			case 'anchored_full_term':
				return $this->calculate_anchored_expiration( $start_timestamp, $term_length, $anchor_date );
				
			default:
				return $this->calculate_rolling_expiration( $start_timestamp, $term_length );
		}
	}

	/**
	 * Calculate rolling (anniversary-based) expiration
	 * 
	 * @param int $start_timestamp Start date timestamp
	 * @param string $term_length Term length (yearly, monthly, seasonal)
	 * @return string Expiration date in Y-m-d format
	 */
	private function calculate_rolling_expiration( $start_timestamp, $term_length ) {
		switch ( $term_length ) {
			case 'yearly':
				return date( 'Y-m-d', strtotime( '+1 year', $start_timestamp ) );
				
			case 'monthly':
				return date( 'Y-m-d', strtotime( '+1 month', $start_timestamp ) );
				
			case 'seasonal':
				// For seasonal, expire at end of season (assuming 6 months)
				return date( 'Y-m-d', strtotime( '+6 months', $start_timestamp ) );
				
			default:
				return date( 'Y-m-d', strtotime( '+1 month', $start_timestamp ) );
		}
	}

	/**
	 * Calculate fixed term expiration (everyone expires on same date)
	 * 
	 * @param int $start_timestamp Start date timestamp
	 * @param string $term_length Term length (yearly, monthly, seasonal)
	 * @return string Expiration date in Y-m-d format
	 */
	private function calculate_fixed_term_expiration( $start_timestamp, $term_length ) {
		$current_year = date( 'Y' );
		
		switch ( $term_length ) {
			case 'yearly':
				// Everyone expires December 31st
				return $current_year . '-12-31';
				
			case 'monthly':
				// Everyone expires at end of current month
				return date( 'Y-m-t' );
				
			case 'seasonal':
				// Everyone expires at end of season (June 30th or December 31st)
				$current_month = date( 'n' );
				if ( $current_month <= 6 ) {
					return $current_year . '-06-30';
				} else {
					return $current_year . '-12-31';
				}
				
			default:
				return date( 'Y-m-t' );
		}
	}

	/**
	 * Calculate anchored full term expiration
	 * 
	 * For renewals, advances the current expiration by the full membership duration.
	 * For initial signups, calculates the next anchor occurrence from signup date.
	 * 
	 * @param int $start_timestamp Start date timestamp
	 * @param string $term_length Term length (yearly, monthly, seasonal)
	 * @param string $anchor_date Anchor date setting (MM-DD for yearly/seasonal, DD for monthly)
	 * @return string Expiration date in Y-m-d format
	 */
	private function calculate_anchored_expiration( $start_timestamp, $term_length, $anchor_date ) {
		if ( ! $anchor_date ) {
			// Fallback to fixed term if no anchor date set
			return $this->calculate_fixed_term_expiration( $start_timestamp, $term_length );
		}
		
		// Check if this is a renewal (existing expiration date exists)
		$current_expiration = $this->get( 'expiration_date' );
		if ( $current_expiration && $current_expiration !== '' ) {
			// This is a renewal - advance the current expiration by the membership duration
			return $this->advance_expiration_by_duration( $current_expiration, $term_length );
		}
		
		// This is initial signup - calculate next anchor occurrence
		return $this->calculate_next_anchor_occurrence( $start_timestamp, $term_length, $anchor_date );
	}

	/**
	 * Advance an expiration date by the membership duration
	 * 
	 * @param string $current_expiration Current expiration date (Y-m-d format)
	 * @param string $term_length Term length (yearly, monthly, seasonal)
	 * @return string New expiration date in Y-m-d format
	 */
	private function advance_expiration_by_duration( $current_expiration, $term_length ) {
		$expiration_timestamp = strtotime( $current_expiration );
		
		switch ( $term_length ) {
			case 'yearly':
				return date( 'Y-m-d', strtotime( '+1 year', $expiration_timestamp ) );
				
			case 'seasonal':
				return date( 'Y-m-d', strtotime( '+6 months', $expiration_timestamp ) );
				
			case 'monthly':
				return date( 'Y-m-d', strtotime( '+1 month', $expiration_timestamp ) );
				
			default:
				return date( 'Y-m-d', strtotime( '+1 month', $expiration_timestamp ) );
		}
	}
	
	/**
	 * Calculate next anchor occurrence from signup date
	 * 
	 * @param int $start_timestamp Start date timestamp
	 * @param string $term_length Term length (yearly, monthly, seasonal)
	 * @param string $anchor_date Anchor date setting
	 * @return string Expiration date in Y-m-d format
	 */
	private function calculate_next_anchor_occurrence( $start_timestamp, $term_length, $anchor_date ) {
		$start_date = date( 'Y-m-d', $start_timestamp );
		
		switch ( $term_length ) {
			case 'yearly':
			case 'seasonal':
				return $this->calculate_next_yearly_anchor( $start_date, $anchor_date, $term_length );
				
			case 'monthly':
				return $this->calculate_next_monthly_anchor( $start_date, $anchor_date );
				
			default:
				return $this->calculate_next_monthly_anchor( $start_date, $anchor_date );
		}
	}
	
	/**
	 * Calculate next yearly/seasonal anchor occurrence
	 * 
	 * @param string $start_date Start date (Y-m-d format)
	 * @param string $anchor_date Anchor date (MM-DD format or full date for backward compatibility)
	 * @param string $term_length Term length for calculating multiple periods if needed
	 * @return string Expiration date in Y-m-d format
	 */
	private function calculate_next_yearly_anchor( $start_date, $anchor_date, $term_length ) {
		// Handle backward compatibility with full dates (YYYY-MM-DD)
		if ( strlen( $anchor_date ) === 10 && strpos( $anchor_date, '-' ) !== false ) {
			// Extract MM-DD from full date
			$anchor_date = date( 'm-d', strtotime( $anchor_date ) );
		}
		
		// Ensure MM-DD format
		if ( ! preg_match( '/^\d{2}-\d{2}$/', $anchor_date ) ) {
			// Invalid format, fallback to fixed term
			return $this->calculate_fixed_term_expiration( strtotime( $start_date ), $term_length );
		}
		
		$start_year = date( 'Y', strtotime( $start_date ) );
		$anchor_this_year = $start_year . '-' . $anchor_date;

		// calculate the renewal window by including the grace period
		$grace_period_days = DCMM_Settings\get_settings( 'grace_period_days' );
		$renewal_window_end_timestamp = strtotime( '+' . $grace_period_days . ' days', strtotime( $anchor_this_year ) );
		$renewal_window_end = date( 'Y-m-d', $renewal_window_end_timestamp );
		
		// If signup is before this year's anchor date, use this year's anchor
		// making sure to consider the grace period
		if ( strtotime( $start_date ) <= strtotime( $renewal_window_end ) ) {
			return $anchor_this_year;
		}
		
		// Otherwise, use next year's anchor
		return ( $start_year + 1 ) . '-' . $anchor_date;
	}
	
	/**
	 * Calculate next monthly anchor occurrence
	 * 
	 * @param string $start_date Start date (Y-m-d format)
	 * @param string $anchor_date Anchor day (DD format or MM-DD for backward compatibility)
	 * @return string Expiration date in Y-m-d format
	 */
	private function calculate_next_monthly_anchor( $start_date, $anchor_date ) {
		// Handle backward compatibility - extract day from MM-DD or YYYY-MM-DD
		if ( strpos( $anchor_date, '-' ) !== false ) {
			$parts = explode( '-', $anchor_date );
			$anchor_day = end( $parts ); // Get the last part (day)
		} else {
			$anchor_day = $anchor_date;
		}
		
		// Validate day format
		if ( ! is_numeric( $anchor_day ) || $anchor_day < 1 || $anchor_day > 31 ) {
			// Invalid day, fallback to fixed term
			return $this->calculate_fixed_term_expiration( strtotime( $start_date ), 'monthly' );
		}
		
		$start_year = date( 'Y', strtotime( $start_date ) );
		$start_month = date( 'm', strtotime( $start_date ) );
		$start_day = date( 'd', strtotime( $start_date ) );
		
		// Try this month's anchor day first
		$days_in_month = date( 't', mktime( 0, 0, 0, $start_month, 1, $start_year ) );
		$effective_day = min( $anchor_day, $days_in_month ); // Handle months with fewer days
		
		$anchor_this_month = sprintf( '%04d-%02d-%02d', $start_year, $start_month, $effective_day );
		
		// If signup is before this month's anchor day, use this month
		if ( $start_day <= $effective_day ) {
			return $anchor_this_month;
		}
		
		// Otherwise, use next month's anchor day
		$next_month_timestamp = mktime( 0, 0, 0, $start_month + 1, 1, $start_year );
		$next_year = date( 'Y', $next_month_timestamp );
		$next_month = date( 'm', $next_month_timestamp );
		$days_in_next_month = date( 't', $next_month_timestamp );
		$effective_next_day = min( $anchor_day, $days_in_next_month );
		
		return sprintf( '%04d-%02d-%02d', $next_year, $next_month, $effective_next_day );
	}
	
	/**
	 * Get valid membership statuses
	 * 
	 * @return array Valid status values
	 */
	public static function get_valid_statuses() {
		return apply_filters( 'dcmm_valid_statuses', [ 'active', 'inactive' ] );
	}
	
	/**
	 * Validate membership status
	 * 
	 * @param string $status Status to validate
	 * @return string Valid status (defaults to 'active' if invalid)
	 */
	public static function validate_status( $status ) {
		$valid_statuses = static::get_valid_statuses();
		return in_array( $status, $valid_statuses, true ) ? $status : 'active';
	}
	
	/**
	 * Set member status with validation
	 * 
	 * @param string $status New status value
	 * @return mixed Result of save operation
	 */
	public function set_status( $status ) {
		$old_status = $this->get( 'status' );
		$validated_status = static::validate_status( $status );
		
		$result = $this->save( 'status', $validated_status );
		
		// Fire status change hook if status actually changed
		if ( $old_status !== $validated_status ) {
			do_action( 'dcmm_member_status_changed', $this->get_member_id(), $old_status, $validated_status );
		}
		
		return $result;
	}
	
	/**
	 * Check if member is active
	 * 
	 * @return bool True if member status is active
	 */
	public function is_active() {
		return $this->get( 'status' ) === 'active';
	}
	
	/**
	 * Check if member is inactive
	 * 
	 * @return bool True if member status is inactive
	 */
	public function is_inactive() {
		return $this->get( 'status' ) === 'inactive';
	}

	/**
	 * Generate hash of relevant membership settings
	 * 
	 * @return string Settings hash
	 */
	private function get_settings_hash() {
		$settings = array(
			'join_policy' => DCMM_Settings\get_settings( 'dcmm_join_policy' ),
			'term_length' => DCMM_Settings\get_settings( 'dcmm_membership_term_length' ),
			'anchor_date' => DCMM_Settings\get_settings( 'dcmm_anchor_date' ),
		);
		
		return md5( serialize( $settings ) );
	}

	/**
	 * Check if membership is expired
	 * 
	 * @return bool True if expired, false otherwise
	 */
	public function is_expired() {
		$expiration_date = $this->get_expiration_date();
		
		if ( ! $expiration_date ) {
			return false; // No expiration date means not expired
		}
		
		return strtotime( $expiration_date ) < time();
	}

	/**
	 * Check if member is in renewal window (can renew)
	 * 
	 * @return bool True if in renewal window, false otherwise
	 */
	public function is_in_renewal_window() {
		$expiration_date = $this->get_expiration_date();
		
		if ( ! $expiration_date ) {
			return false; // No expiration date means no renewal window
		}
		
		$renewal_window_days = $this->get_renewal_window_days();
		$renewal_window_start = strtotime( $expiration_date . ' -' . $renewal_window_days . ' days' );
		$grace_period_days = $this->get_grace_period_days();
		$grace_period_end = strtotime( $expiration_date . ' +' . $grace_period_days . ' days' );
		
		$current_time = time();
		
		// Can renew from window start until end of grace period
		return $current_time >= $renewal_window_start && $current_time <= $grace_period_end;
	}

	/**
	 * Get renewal window start date
	 * 
	 * @return string|false Renewal window start date (Y-m-d format) or false if no expiration
	 */
	public function get_renewal_window_start() {
		$expiration_date = $this->get_expiration_date();
		
		if ( ! $expiration_date ) {
			return false;
		}
		
		$renewal_window_days = $this->get_renewal_window_days();
		return date( 'Y-m-d', strtotime( $expiration_date . ' -' . $renewal_window_days . ' days' ) );
	}

	/**
	 * Get current renewal status
	 * 
	 * @return string Renewal status: 'too_early', 'available', 'grace', 'suspended'
	 */
	public function get_renewal_status() {
		$expiration_date = $this->get_expiration_date();
		
		if ( ! $expiration_date ) {
			return 'no_expiration'; // No expiration date set
		}
		
		$current_time = time();
		$expiration_timestamp = strtotime( $expiration_date );
		$renewal_window_days = $this->get_renewal_window_days();
		$grace_period_days = $this->get_grace_period_days();
		
		$renewal_window_start = strtotime( $expiration_date . ' -' . $renewal_window_days . ' days' );
		$grace_period_end = strtotime( $expiration_date . ' +' . $grace_period_days . ' days' );
		
		if ( $current_time < $renewal_window_start ) {
			return 'too_early';
		} elseif ( $current_time >= $renewal_window_start && $current_time <= $expiration_timestamp ) {
			return 'available';
		} elseif ( $current_time > $expiration_timestamp && $current_time <= $grace_period_end ) {
			return 'grace';
		} else {
			return 'suspended';
		}
	}

	/**
	 * Get days until renewal window opens
	 * 
	 * @return int Days until renewal window opens (negative if already open/past)
	 */
	public function get_days_until_renewal_window() {
		$renewal_window_start = $this->get_renewal_window_start();
		
		if ( ! $renewal_window_start ) {
			return 0;
		}
		
		return ceil( ( strtotime( $renewal_window_start ) - time() ) / ( 24 * 60 * 60 ) );
	}

	/**
	 * Checks whether Member is set to renew automatically (subscription)
	 *
	 * @return string|false 'Subscription' if member has active subscription, 'Manual' if not, false if not an active member
	 */
	public function get_renewal_method() {
		if ( $this->has_active_subscription() ) {
			return 'Subscription';
		}

		// if not an active member, return false
		if ( $this->get( 'status' ) !== 'active' ) {
			return false;
		} else {

			// if active member but no subscription, return 'Manual'
			return 'Manual';
		}
	}

	/**
	 * Get renewal window days setting
	 * 
	 * @return int Number of days before expiration that renewal becomes available
	 */
	private function get_renewal_window_days() {
		$options = get_option( 'dcmm_settings', array() );
		return isset( $options['renewal_window_days'] ) ? intval( $options['renewal_window_days'] ) : 30;
	}

	/**
	 * Get grace period days setting
	 * 
	 * @return int Number of days after expiration that renewal is still allowed
	 */
	private function get_grace_period_days() {
		$options = get_option( 'dcmm_settings', array() );
		return isset( $options['grace_period_days'] ) ? intval( $options['grace_period_days'] ) : 30;
	}

	/**
	 * Log an action for the Member
	 * 
	 * @param string $action The action to log
	 * @param string $context (optional) The context of the action
	 * @param string $notes (optional) Additional notes about the action
	 * 
	 * @return void
	 */
	public function log( $action, $context = '', $notes = '' ) {
		$logs = get_post_meta( $this->get_member_id(), 'dcmm_log', true );

		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$logs[] = array(
			'time'    => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
			'action'  => $action,
			'context' => $context,
			'notes'   => $notes,
		);

		update_post_meta( $this->get_member_id(), 'dcmm_log', $logs );
	}
	
	/**
	 * Get member's email address
	 * 
	 * @return string Email address
	 */
	public function get_email() {
		return $this->get( 'email' );
	}
	
	/**
	 * Get member's first name
	 * 
	 * @return string First name
	 */
	public function get_first_name() {
		return $this->get( 'first_name' );
	}
	
	/**
	 * Get member's last name
	 * 
	 * @return string Last name
	 */
	public function get_last_name() {
		return $this->get( 'last_name' );
	}
	
	/**
	 * Get member's status
	 * 
	 * @return string Membership status
	 */
	public function get_status() {
		return $this->get( 'status' );
	}
	
	/**
	 * Get member's CPT ID
	 * 
	 * @return int Member CPT ID
	 */
	public function get_id() {
		return $this->get_member_id();
	}

	/**
	 * Check if member has an active recurring subscription
	 * 
	 * @return bool True if member has active subscription
	 */
	public function has_active_subscription() {
		$subscription_status = $this->get( 'subscription_status' );
		return in_array( $subscription_status, [ 'active', 'trialing' ] );
	}

	/**
	 * Get member's subscription data
	 * 
	 * @return array|false Subscription data or false if no subscription
	 */
	public function get_subscription_data() {
		$subscription_id = $this->get( 'subscription_id' );
		if ( ! $subscription_id ) {
			return false;
		}

		return [
			'id' => $subscription_id,
			'status' => $this->get( 'subscription_status' ),
			'interval' => $this->get( 'subscription_interval' ),
			'gateway' => $this->get( 'subscription_gateway' ),
			'created' => $this->get( 'subscription_created' ),
			'cancelled' => $this->get( 'subscription_cancelled' ),
		];
	}

	/**
	 * Create a recurring subscription for this member
	 * 
	 * @param string $interval Billing interval (monthly, yearly, etc)
	 * @param float  $amount   Optional amount override
	 * @return array|WP_Error Subscription data on success, WP_Error on failure
	 */
	public function create_subscription( $interval = 'monthly', $amount = null ) {
		// Check if member already has active subscription
		if ( $this->has_active_subscription() ) {
			return new \WP_Error( 'existing_subscription', 'Member already has an active subscription' );
		}

		// Get amount from settings if not provided
		if ( is_null( $amount ) ) {
			$amount = \DCMM_Settings\get_dues_amount();
			if ( ! $amount || ! is_numeric( $amount ) ) {
				return new \WP_Error( 'invalid_amount', 'No valid dues amount configured' );
			}
		}

		// Get the default gateway
		$gateway = Gateway_Manager::get_default_gateway();
		if ( ! $gateway ) {
			return new \WP_Error( 'no_gateway', 'No payment gateway configured' );
		}

		// Create subscription through gateway
		$result = $gateway->create_subscription( $this->get_member_id(), floatval( $amount ), $interval );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Store subscription data
		$this->save( 'subscription_id', $result['subscription_id'] );
		$this->save( 'subscription_status', $result['status'] );
		$this->save( 'subscription_interval', $interval );
		$this->save( 'subscription_gateway', $gateway->get_name() );
		$this->save( 'subscription_created', current_time( 'mysql' ) );

		// Log the subscription creation
		$this->log( 'subscription_created', 'recurring', "Subscription ID: {$result['subscription_id']}" );

		return $result;
	}

	/**
	 * Cancel the member's recurring subscription
	 * 
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function cancel_subscription() {
		$subscription_data = $this->get_subscription_data();
		if ( ! $subscription_data ) {
			return new \WP_Error( 'no_subscription', 'Member has no subscription to cancel' );
		}

		// Get the gateway that created the subscription
		$gateway = Gateway_Manager::get_gateway( $subscription_data['gateway'] );
		if ( ! $gateway ) {
			return new \WP_Error( 'gateway_not_found', 'Original payment gateway not available' );
		}

		// Cancel through gateway
		$result = $gateway->cancel_subscription( $this->get_member_id(), $subscription_data['id'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update subscription status
		$this->save( 'subscription_status', 'cancelled' );
		$this->save( 'subscription_cancelled', current_time( 'mysql' ) );

		// Log the cancellation
		$this->log( 'subscription_cancelled', 'recurring', "Subscription ID: {$subscription_data['id']}" );

		return true;
	}

	/**
	 * Handle a successful recurring payment
	 * 
	 * @param array $payment_data Payment data from gateway
	 * @return bool Success status
	 */
	public function process_recurring_payment( $payment_data ) {
		// Renew the membership
		$result = $this->renew_membership( 'recurring' );

		// Fire recurring payment hook
		$payment_data['member_id'] = $this->get_member_id();
		$payment_data['context'] = 'recurring';
		do_action( 'dcmm_recurring_payment_received', $this->get_member_id(), $payment_data );

		// Log the payment
		$amount = isset( $payment_data['amount'] ) ? $payment_data['amount'] : 'unknown';
		$this->log( 'recurring_payment', 'automatic', "Amount: $amount" );

		return $result;
	}

	/**
	 * Initialize member-related hooks
	 * 
	 * @since 1.1.1
	 */
	public static function init_hook() {
		// Disable author pages for member users
		add_action( 'template_redirect', array( __CLASS__, 'disable_member_author_pages' ) );
	}

	/**
	 * Disable author pages for users associated with member CPTs
	 * 
	 * @since 1.1.1
	 */
	public static function disable_member_author_pages() {
		if ( is_author() ) {
			$author_id = get_queried_object_id();
			
			// Check if this user is associated with a member CPT
			$member_posts = get_posts( array(
				'post_type' => self::$our_post_type,
				'meta_key' => self::$meta_keys['wp_user_id'],
				'meta_value' => $author_id,
				'posts_per_page' => 1
			) );
			
			if ( ! empty( $member_posts ) ) {
				// This is a member user, show 404
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
				get_template_part( 404 );
				exit;
			}
		}
	}

	/**
	 * Check if auto-sync to WordPress users is enabled
	 *
	 * @return bool True if auto-sync is enabled
	 */
	private function is_personal_info_sync_enabled() {
		$settings = get_option( 'dcmm_settings', array() );
		return isset( $settings['auto_sync_wp_users'] ) ? (bool) $settings['auto_sync_wp_users'] : true; // Default: enabled
	}

	/**
	 * Sync member data to associated WordPress user
	 *
	 * Updates WP user's first_name, last_name, user_email, and display_name
	 * based on member CPT data.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	private function sync_to_wp_user() {
		if ( ! $this->has_wp_user() ) {
			return false; // No WP user to sync to
		}

		$wp_user_id = $this->get_wp_user_id();
		if ( ! $wp_user_id ) {
			return false;
		}

		// Prepare sync data
		$first_name = $this->get( 'first_name' ) ?: '';
		$last_name = $this->get( 'last_name' ) ?: '';
		$email = $this->get( 'email' ) ?: '';

		// Create display name from first and last name
		$display_name = trim( $first_name . ' ' . $last_name );
		if ( empty( $display_name ) && ! empty( $email ) ) {
			// Fallback to email if no name available
			$display_name = $email;
		}

		$sync_data = array(
			'ID' => $wp_user_id,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'user_email' => $email,
			'display_name' => $display_name
		);

		// Remove empty email to avoid WP validation errors
		if ( empty( $email ) ) {
			unset( $sync_data['user_email'] );
		}

		// Update WP user data
		$result = wp_update_user( $sync_data );

		if ( is_wp_error( $result ) ) {
			error_log( 'DCMM: Failed to sync member ' . $this->get_member_id() . ' to WP user ' . $wp_user_id . ': ' . $result->get_error_message() );
			return $result;
		}

		// Log successful sync
		$this->log( 'sync_to_wp_user', 'auto', 'Synced to WP user ID: ' . $wp_user_id );

		return true;
	}

	/**
	 * Sync WordPress user data back to member
	 *
	 * Only fills in empty member fields - doesn't overwrite existing data.
	 * Called when WP user is edited directly.
	 *
	 * @param int $wp_user_id WordPress user ID
	 * @return bool True on success, false on failure
	 */
	public function sync_from_wp_user( $wp_user_id ) {
		if ( $this->get_wp_user_id() !== $wp_user_id ) {
			return false; // Not our user
		}

		if ( ! $this->is_personal_info_sync_enabled() ) {
			return false; // Auto-sync disabled
		}

		$wp_user = get_userdata( $wp_user_id );
		if ( ! $wp_user ) {
			return false;
		}

		$synced_fields = array();

		// Only sync if CPT data is empty (don't overwrite existing data)
		if ( empty( $this->get( 'first_name' ) ) && ! empty( $wp_user->first_name ) ) {
			$this->save( 'first_name', $wp_user->first_name );
			$synced_fields[] = 'first_name';
		}

		if ( empty( $this->get( 'last_name' ) ) && ! empty( $wp_user->last_name ) ) {
			$this->save( 'last_name', $wp_user->last_name );
			$synced_fields[] = 'last_name';
		}

		if ( empty( $this->get( 'email' ) ) && ! empty( $wp_user->user_email ) ) {
			$this->save( 'email', $wp_user->user_email );
			$synced_fields[] = 'email';
		}

		// Log sync if any fields were updated
		if ( ! empty( $synced_fields ) ) {
			$this->log( 'sync_from_wp_user', 'auto', 'Synced fields: ' . implode( ', ', $synced_fields ) );
		}

		return true;
	}
}

// Initialize the class hooks
DCMM_Member::init_hook();
