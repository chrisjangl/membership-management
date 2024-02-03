<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


use \DCMM_Users\create_member_as_user;

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
	 */
	protected $member_id = null;
	
	/**
	 * The ID of the CPT post
	 */
	protected $cpt_id = null;
	protected static $our_post_type = 'dcmm-member';
	protected static $meta_prefix = 'dcmm_';
	
	// TODO: use the $meta_prefix property instead; 
	protected static $meta_keys = array(
		'nonce_prefix' => 'dcmm_member_info_',
		'status'	=>	'dcmm_' . 'status',
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
	// protected static $db_table_name = 'custom_lms_exam_strations';
	static $instance;

	// Member info
	protected $first_name = null;
	protected $last_name = null;
	protected $email = null;
	protected $phone = null;
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
				$this->cpt_id = $key;

				// ...and get the email address
				$email = get_post_meta( $key, self::$meta_keys['email'], true );
			} else {

				// if it's an email address, use that
				$email = $key;
			}
		}

		// load the member's meta (from the CPT) onto the Member object
		$this->load_member_meta( $this->cpt_id );
	
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

				$this->member_id = $wordpress_user->ID;

				// load the user's meta to the object
				$this->load_user_meta( $this->member_id );

			} else {
				
				// if not, create a WP user, giving it a role of "Organizational Member"
				include_once( 'functions-user-role.php' );
				$member_ID = \DCMM_Users\create_member_as_user( $email );

				if ( ! \is_wp_error( $member_ID ) ) {
					$this->member_id = $member_ID;
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
	 * Gets the ID of the associated WP User, as stored in our DCMM_Member object
	 * 
	 * @return int The ID of the WP User
	 * 
	 */
	function get_wp_user_id() {
		return $this->member_id;
	}

	/**
	 * Loads the user's meta into the object
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
		$mailing_address = get_post_meta( $cpt_id, $meta_keys['mailing_address'], true );

		// if $mailing_address isn't a WP, set it to the object
		if ( ! is_wp_error( $mailing_address ) ) {
			$this->mailing_address = $mailing_address;
		}
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

		// make sure we have a WP User ID
		if ( is_null( $this->wp_user_id ) ) {
			return false;
		}

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
}
