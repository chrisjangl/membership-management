<?php
/**
 * Contact class - describes a person in our system
 * 
 */

 class DC_Contact extends WP_User {

   protected $status = 'contact';
   protected $first_name = null;
   protected $last_name = null;
   protected $email_address = null;
   protected $wp_user_id = null;

   function __construct( $user_email ) {

      // check if WP User exists for the email address

      // if not, create one

      // then load NAP data into the Contact object
         
   }

   /**
    * registers the passed email address as a WordPress user,
    * if it doesn't belong to an existing WP user.
    * 
    * @uses email_exists()
    * @uses wp_insert_user()
    * @uses is_wp_error()
    * 
    * @param string $user_email    The email to register
    * 
    * @return bool|int $contact_ID The user ID belonging to the WP user whether it already exists
    *                              or if created
    * 
    */
   function register_wp_user( $user_email ) {

      // check if email is registered to WP user
      if ( email_exists( $user_email ) ) {

         $contact_ID = email_exists( $user_email );

         // @todo: probably want to make sure the WordPress user has role of 'contact'

      } else {

         // otherwise, create a user
         // @todo: can't re-use user names based on first last name
         $contact_ID = wp_insert_user(array(
            'user_login' 	=>	$user_email, 
            'user_email' 	=>	$user_email,
            'role' 			=> 'contact'
         ));

         // were we able to create the user account?
         if ( is_wp_error( $contact_ID ) ) {

            return false;
            
         } 
      } 

      return $contact_ID;

   }

   /**
    * Gets the first name of the WordPress user
    *
    * @return string $user_first_name  First name of the WordPress user
    */
   function get_first_name() {
      
   }

   /**
    * Sets the first name of the WordPress user
    *
    * @return 
    */
   function set_first_name() {

   }

   /**
    * Loads the first name of the WordPress user into our Contact object
    *
    * @return 
    */
   function load_first_name() {

   }

   /**
    * Gets the last name of the WordPress user
    *
    * @return string $user_last_name  Last name of the WordPress user
    */
   function get_last_name() {
      
   }

   /**
    * Sets the last name of the WordPress user
    *
    * @return 
    */
   function set_last_name() {

   }

   /**
    * Loads the last name of the WordPress user into our Contact object
    *
    * @return 
    */
   function load_last_name() {

   }

   /**
    * Gets the email address of the WordPress user
    *
    * @return string $user_email  Email address of the WordPress user
    */
   function get_email_address() {
      
   }

   /**
    * Sets the email address of the WordPress user
    *
    * @return 
    */
   function set_email_address() {

   }

   /**
    * Loads the email address of the WordPress user into our Contact object
    *
    * @return 
    */
   function load_email_address() {

   }
 }