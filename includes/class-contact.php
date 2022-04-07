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


   }
 }