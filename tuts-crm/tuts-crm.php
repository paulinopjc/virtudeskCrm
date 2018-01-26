<?php
/**
* Plugin Name: Tuts+ CRM
* Plugin URI: #
* Version: 1.0
* Author: Tuts+
* Author URI: http://code.tutsplus.com
* Description: A simple CRM system for WordPress
* License: GPL2
*/

include_once( 'advanced-custom-fields/acf.php' );
define( 'ACF_LITE', true );

class WPTutsCRM {
	/**
	* Constructor. Called when plugin is initialised
	*/
	function __construct() {
		add_action( 'init', array( &$this, 'register_custom_post_type' ) );
		add_action( 'plugins_loaded', array( $this, 'acf_fields' ) );
		add_filter( 'manage_edit-contact_columns', array( $this, 'add_table_columns' ) );
		add_action( 'manage_contact_posts_custom_column', array( $this, 'output_table_columns_data' ), 10, 2 );
		add_filter( 'manage_edit-contact_sortable_columns', array( $this, 'define_sortable_table_columns' ) );

		if ( is_admin() ) {
			add_filter( 'request', array( $this, 'orderby_sortable_table_columns' ) );
			add_filter( 'posts_join', array( $this, 'search_meta_data_join' ) );
			add_filter( 'posts_where', array( $this, 'search_meta_data_where' ) );
		}

	}

	/**
	*Activation hook to register a new Role and assign it our contact capabilities
	*/
	function plugin_activation(){
		//define our custom capabilities
		$customCaps = array(
			'edit_others_contacts'=>true,
			'delete_others_contacts'=>true,
			'delete_private_contacts'=>true,
			'edit_private_contacts'=>true,
			'read_private_contacts'=>true,
			'edit_published_contacts'=>true,
			'publish_contacts'=>true,
			'delete_published_contacts'=>true,
			'edit_contacts'=>true,
			'delete_contacts'=>true,
			'edit_contact'=>true,
			'read_contact'=>true,
			'delete_contact'=>true,
			'read'=>true,
		);

		//Create our CRM role and assign the custom capabilities to it
		add_role( 'crm', __( 'CRM', 'tuts-crm' ), $customCaps );

		//add custom capabilities to the admin and editor roles
		$roles = array( 'administrator', 'editor' );
		foreach ( $roles as $rolename ) {
			//Get Role
			$role = get_role( $roleName );

			//check role exists
			if ( is_null( $role ) ){
				continue;
			}

			//iterate through our custom capabilities, adding them to this role if they are enabled
			foreach ( $customCaps as $capability => $enabled ){
				if ( $enabled ){
					//add capability
					$role->add_cap( $capability );
				}
			}
		}

		//add some of our capabilities to the author role
		$role = get_role( 'author' );
		$role->add_cap( 'edit_contact' );
		$role->add_cap( 'edit_contacts' );
		$role->add_cap( 'publish_contacts' );
		$role->add_cap( 'read_contact' );
		$role->add_cap( 'delete_contact' );
		unset( $role );
	}

	/**
	*deactivation hook to unregister our existing contacts role
	*/
	function plugin_deactivation(){
		remove_role( 'crm' );
	}

	/**
	* Adds a where clause to the WordPress meta table for license key searches in the WordPress Administration
	*
	* @param string $where SQL WHERE clause(s)
	* @return string SQL WHERE clauses
	*/
	function search_meta_data_where($where) {
	    global $wpdb;
	 
	    // Only join the post meta table if we are performing a search
	    if ( empty ( get_query_var( 's' ) ) ) {
	            return $where;
	        }
	     
	        // Only join the post meta table if we are on the Contacts Custom Post Type
	    if ( 'contact' != get_query_var( 'post_type' ) ) {
	        return $where;
	    }
	     
	    // Get the start of the query, which is ' AND ((', and the rest of the query
	    $startOfQuery = substr( $where, 0, 7 );
	    $restOfQuery = substr( $where ,7 );
	     
	    // Inject our WHERE clause in between the start of the query and the rest of the query
	    $where = $startOfQuery . 
	            "(" . $wpdb->postmeta . ".meta_value LIKE '%" . get_query_var( 's' ) . "%') OR " . $restOfQuery .
	            "GROUP BY " . $wpdb->posts . ".id";
	     
	    // Return revised WHERE clause
	    return $where;
	}

	/**
	* Adds a join to the WordPress meta table for license key searches in the WordPress Administration
	*
	* @param string $join SQL JOIN statement
	* @return string SQL JOIN statement
	*/
	function search_meta_data_join($join) {
	    global $wpdb;
	         
	    // Only join the post meta table if we are performing a search
	    if ( empty ( get_query_var( 's' ) ) ) {
	        return $join;
	    }
	         
	    // Only join the post meta table if we are on the Contacts Custom Post Type
	    if ( 'contact' != get_query_var( 'post_type' ) ) {
	        return $join;
	    }
	         
	    // Join the post meta table
	    $join .= " LEFT JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id ";
	         
	    return $join;
	}

	/**
	*Inspect the request to see if we are on the contacts WP_LIST_Table and attempting to sort by email address or phone number. if so, ammend the posts query to sort by that custom meta key
	*
	*@param array $vars Requesr Variables
	*@return array New Request Variables
	*/
	function orderby_sortable_table_columns( $vars ){
		//Don't do anything if we are not ion the contact custom post type
		if ( 'contact' != $vars['post_type'] ) return $vars;

		//Don't do anything if no orderby parameter is set
		if ( ! isset( $vars['orderby'] ) ) return $vars;

		//Check if the orderby parameter matches one of our sortable columns
		if ( $vars['orderby'] == 'email_address' OR $vars['orderby'] == 'phone_number' ){
			//add orderby meta_value and meta_key parameters to the query
			$vars = array_merge( $vars, array( 'meta_key'=>$vars['orderby'], 'orderby'=>'meta_value', 
		) );
		}
		return $vars;
	}

	/**
	*Define which contact columns are sortable
	*
	*@param array $columns Existing sortable columns
	*@param array new sortable columns
	*/
	function define_sortable_table_columns( $columns ){
		$columns['email_address'] = 'email_address';
		$columns['phone_number'] = 'phone_number';

		return $columns;
	}

	/**
	* outputs our contact custom field data, based on the column requested
	*
	*@param string $columnName Column Key Name
	*@param int $post_id POST ID
	*/
	function output_table_columns_data( $columnName, $post_id ){
		//Field
		$field = get_field( $columnName, $post_id );

		if( 'photo' == $columnName ){
			echo '<img src="' . $field['sizes']['thumbnail'].'" width= "'.$field['sizes']['thumbnail-width'] . '" height= "' . $field['sizes']['thumbnail-height'] . '" />';
		}else{
			//Output field
			echo $field;
		}
	}

	/**
	* Adss table columns to the contacts WP_LIST_Table
	*
	*@param arra $columns existing columns
	*@return array new columns
	*/
	function add_table_columns( $columns ) {
		$columns['email_address'] = __( 'Email Address', 'tuts-crm' );
		$columns['phone_number'] = __('Phone Number', 'tuts-crm');
		$columns['photo'] = __( 'Photo', 'tuts-crm' );

		return $columns;
	}
	
	/**
	* Registers a Custom Post Type called contact
	*/
	function register_custom_post_type() {
		register_post_type( 'contact', array(
            'labels' => array(
				'name'               => _x( 'Contacts', 'post type general name', 'tuts-crm' ),
				'singular_name'      => _x( 'Contact', 'post type singular name', 'tuts-crm' ),
				'menu_name'          => _x( 'Contacts', 'admin menu', 'tuts-crm' ),
				'name_admin_bar'     => _x( 'Contact', 'add new on admin bar', 'tuts-crm' ),
				'add_new'            => _x( 'Add New', 'contact', 'tuts-crm' ),
				'add_new_item'       => __( 'Add New Contact', 'tuts-crm' ),
				'new_item'           => __( 'New Contact', 'tuts-crm' ),
				'edit_item'          => __( 'Edit Contact', 'tuts-crm' ),
				'view_item'          => __( 'View Contact', 'tuts-crm' ),
				'all_items'          => __( 'All Contacts', 'tuts-crm' ),
				'search_items'       => __( 'Search Contacts', 'tuts-crm' ),
				'parent_item_colon'  => __( 'Parent Contacts:', 'tuts-crm' ),
				'not_found'          => __( 'No contacts found.', 'tuts-crm' ),
				'not_found_in_trash' => __( 'No contacts found in Trash.', 'tuts-crm' ),
			),
            
            // Frontend
            'has_archive' => false,
            'public' => false,
            'publicly_queryable' => false,
            
            // Admin
            'capabilities' => array(
            	'edit_others_posts'=>'edit_others_contacts',
            	'delete_others_posts'=>'delete_others_contacts',
            	'delete_private_posts'=>'delete_private_contacts',
            	'edit_private_posts'=>'edit_private_contacts',
            	'read_private_posts'=>'read_private_contacts',
            	'edit_published_posts'=>'edit_published_contacts',
            	'publish_posts'=>'publish_contacts',
            	'delete_published_posts'=>'delete_published_contacts',
            	'edit_posts'=>'edit_contacts',
            	'delete_posts'=>'delete_contacts',
            	'edit_post'=>'edit_contact',
            	'read_post'=>'read_contact',
            	'delete_post'=>'delete_contact',
            ),
            'map_meta_cap'=>true,
            'menu_icon' => 'dashicons-businessman',
            'menu_position' => 10,
            'query_var' => true,
            'show_in_menu' => true,
            'show_ui' => true,
            'supports' => array(
            	'title',
            	'author',
            	'comments',	
            ),
        ) );	
	}

	/**
	* Register ACF field groups and fields
	*/
	function acf_fields() {
		if(function_exists("register_field_group"))
		{
			register_field_group(array (
				'id' => 'acf_contact-details',
				'title' => 'Contact Details',
				'fields' => array (
					array (
						'key' => 'field_5a60bb4cde13f',
						'label' => 'Email Address',
						'name' => 'email_address',
						'type' => 'email',
						'required' => 1,
						'default_value' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
					),
					array (
						'key' => 'field_5a60bd46d2255',
						'label' => 'Phone Number',
						'name' => 'phone_number',
						'type' => 'number',
						'required' => 1,
						'default_value' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'min' => '',
						'max' => '',
						'step' => '',
					),
					array (
						'key' => 'field_5a60bd801fa9b',
						'label' => 'Photo',
						'name' => 'photo',
						'type' => 'image',
						'save_format' => 'object',
						'preview_size' => 'thumbnail',
						'library' => 'all',
					),
					array (
						'key' => 'field_5a60bda047bae',
						'label' => 'Type',
						'name' => 'type',
						'type' => 'select',
						'required' => 1,
						'choices' => array (
							'Prospect: Prospect' => 'Prospect: Prospect',
							'Customer: Customer' => 'Customer: Customer',
						),
						'default_value' => '',
						'allow_null' => 0,
						'multiple' => 0,
					),
				),
				'location' => array (
					array (
						array (
							'param' => 'post_type',
							'operator' => '==',
							'value' => 'contact',
							'order_no' => 0,
							'group_no' => 0,
						),
					),
				),
				'options' => array (
					'position' => 'normal',
					'layout' => 'default',
					'hide_on_screen' => array (
						0 => 'permalink',
						1 => 'excerpt',
						2 => 'custom_fields',
						3 => 'discussion',
						4 => 'comments',
						5 => 'revisions',
						6 => 'slug',
						7 => 'author',
						8 => 'format',
						9 => 'featured_image',
						10 => 'categories',
						11 => 'tags',
						12 => 'send-trackbacks',
					),
				),
				'menu_order' => 1,
			));
		}

	}
}

$wpTutsCRM = new WPTutsCRM;
register_activation_hook( __FILE__, array( $wpTutsCRM, 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( $wpTutsCRM, 'plugin_deactivation' ) );
?>