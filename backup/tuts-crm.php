<?php 
	/**
	* Plugin Name: Tuts+ CRM
	* Plugin URI: #
	* Version 1.0
	* Author: Tuts+
	* Author URI: http://code.tutsplus.com
	* Description: A simple CRM system for WordPress
	* License: GPL2
	*/

	class WPTutsCRM {

		/**
		* Constructor. Called when plugin is initialised
		*/
		function __construct() {
			add_action( 'init', array( $this, 'register_custom_post_type' ) );
		}

		/**
		* Registers a Meta Box on our Contact Custom Post Type, called 'Congtact Details'
		*/

		function register_meta_boxes(){
			add_meta_box( 'contact-details', 'Contact Details', array( $this, 'output_meta_box'), 'contact', 'normal', 'high' );
		}

		/**
		* Output a contact details meta box
		*
		* @param WP_Post $post WordPress Post object
		*/

		function output_meta_box($post){

			$email = get_post_meta( $post->ID, '_contact_email', true );

			// Add a nonce field so we can check for it later
			wp_nonce_field( 'save_contact', 'contacts_nonce' );

			//Output label and feild
			echo ( '<label for="contact_email">' . __( 'Email Address', 'tuts-crm' ) . '</label>' );
			echo ( '<input type="text" name="contact_email" id="contact_email" value="' . esc_attr( $email ) . '" />' );
		}

		/**
		* Saves the meta box fiels data
		*
		* @param int $post_id Post ID
		*/

		function save_meta_boxes( $post_id ){
			//check if our nonce is set
			if( ! isset( $_POST['contacts_nonce'] ) ){
				return $post_id;
			}

			//verify that the nonce is valid
			if ( ! wp_verify_nonce( $_POST['contacts_nonce'], 'save_contact' ) ){
				return $post_id;
			}

			// Check if this is the Contact custom post type
			if ( 'contact' != $_POST['post_type'] ){
				return $post_id;
			}
			// Check the logged in user has permission to edit this post
			if( ! current_user_can( 'edit_post', $post_id ) ){
				return $post_id;
			}
			// OK to save meta data
			$email = sanitize_text_field( $_POST['contact_email'] );
			update_post_meta( $post_id, '_contact_email', $email );

		}

		/**
		* Registers a Custom Post Type called contact
		*/
		function register_custom_post_type() {
			register_post_type( 'contact', array( 'labels' => array(
					'name'=>_x( 'Contacts', 'post type general name', 'tuts-crm' ),
					'singular_name'=>_x( 'Contact', 'post type singular name', 'tuts-crm' ),
					'menu_name'=>_x( 'Contacts', 'admin menu', 'tuts-crm' ),
					'name_admin_bar'=>_x( 'Contact', 'add new on admin bar', 'tuts-crm' ),
					'add_new'=>_x( 'Add New', 'contact', 'tuts-crm' ),
					'add_new_item'=>__( 'Add New Contact', 'tuts-crm' ),
					'new_item'=>__( 'New Contact', 'tuts-crm' ),
					'edit_item'=>__( 'Edit Contact', 'tuts-crm' ),
					'view_item'=>__( 'View Contact', 'tuts-crm' ),
					'all_items'=>__( 'All Contacts', 'tuts-crm' ),
					'search_items'=>__( 'Search Contacts', 'tuts-crm' ),
					'parent_item_colon'=>__( 'Parent Contacts', 'tuts-crm' ),
					'not_found'=>__( 'No contacts found', 'tuts-crm' ),
					'not_found_in_trash'=>__( 'No contac found in trash', 'tuts-crm' ),
			),
			
			// Frontend
			'has_archive'=>false,
			'public'=>false,
			'publicly_queryable'=>false,

			// Admin
			'capability_type'=>'post',
			'menu_icon'=>'dashicons-businessman',
			'menu_position'=>10,
			'query_var'=>true,
			'show_in_menu'=>true,
			'show_ui'=>true,
			'supports'=>array(
				'title',
				'author',
				'comments',
			),
			) );
		}
	}

	$wpTutsCRM = new WPTutsCRM;
 ?>