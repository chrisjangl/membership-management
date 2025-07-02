<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


use \DCMM_Users\create_member_as_user;
use DCMM\Gateways\Gateway_Manager;

/**
 * Set up the Member post type
 * 
 * TODO: Need to convert this from a singleton-eque class to a regular class
 * TODO: Need to handle the case where the email address is changed in the CPT
 * 
 * TODO: create method to check if user is member
 * TODO: this class should only be a Member object; get rid of developer helpers (post type, etc.)
 * 
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
			// if there is already a WP User, do nothing
			$user_ID = $this->get_wp_user_id();
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
	 * @return mixed Returns a payment object or a WP_Error if dues are enabled but no amount is set.
	 */
	public function maybe_charge_for_renewal() {
		
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

		// start the payment flow
		return $gateway->start_payment( $this, floatval( $amount) );

	}

	/**
	 * Subscribe a member to the organization
	 * 
	 * Fires `dcmm_member_subscribed` action upon completion.
	 * 
	 * TODO: standardize how we're storing date/times
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

		return $this->subscribe_to_membership( "renew:$context" );

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
}
