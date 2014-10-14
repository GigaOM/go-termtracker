<?php
class GO_Term_Tracker
{
	public $id_base = 'go-termtracker';
	public $version = 1;
	public $plugin_url;
	public $post_type_name = 'go-termtracker';
	public $post_meta_key  = 'go-termtracker';

	private $admin; // the admin object
	private $config;

	public function __construct()
	{
		$this->plugin_url = untrailingslashit( plugin_dir_url( __FILE__ ) );

		add_action( 'init', array( $this, 'init' ), 12 );
		add_action( 'created_term', array( $this, 'created_term' ), 10, 2 );
	} // END __construct

	/**
	 * an object accessor for the admin object
	 */
	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once __DIR__ . '/class-go-termtracker-admin.php';
			$this->admin = new GO_Term_Tracker_Admin();
		}// end if

		return $this->admin;
	} // END admin

	/**
	 * Loads config settings
	 */
	public function config( $key = NULL )
	{
		if ( ! $this->config )
		{
			$defaults = array(
				'taxonomies_to_track' => array(),
			);

			$this->config = apply_filters( 'go_config', $defaults, 'go-termtracker' );
		}//end if

		if ( $key )
		{
			return isset( $this->config[ $key ] ) ? $this->config[ $key ] : NULL;
		}//end if

		return $this->config;
	}//end config

	/**
	 * register this post type, as well as any taxonomies that go with it
	 */
	public function init()
	{
		if ( is_admin() )
		{
			$this->admin();
		}//end if

		$args = array(
			'label'   => 'Term Tracker',
			'labels'  => array(
				'name'               => 'Term Journals',
				'singular_name'      => 'Term Journal',
				'add_new'            => 'Add New',
				'add_new_item'       => 'Add New Journal Entry',
				'edit_item'          => 'Edit Journal',
				'new_item'           => 'New Journal',
				'all_items'          => 'All Journals',
				'view_item'          => 'View Journal',
				'search_items'       => 'Search Journal',
				'not_found'          => 'No journal found',
				'not_found_in_trash' => 'No journal found in Trash',
				'parent_item_colon'  => '',
				'menu_name'          => 'Term Tracker',
			),
			'register_meta_box_cb' => array( $this, 'metaboxes' ),
			'public'               => FALSE,
			'show_ui'              => current_user_can( 'manage_categories' ) ? TRUE : FALSE,
			'show_in_nav_menus'    => current_user_can( 'manage_categories' ) ? TRUE : FALSE,
			'taxonomies'           => array_keys( $this->config( 'taxonomies_to_track' ) ),
			'supports'             => FALSE,
		);
		register_post_type( $this->post_type_name, $args );
	} // END init

	/**
	 * wrapper method for metaboxes in the admin object, allows lazy loading
	 */
	public function metaboxes()
	{
		$this->admin()->metaboxes();
	} // END metaboxes

	/**
	 * Handle the insertion of new terms:
	 *  - following the creation of new terms, this handler will be called:
	 *    - if it's a term in a taxonomy we're tracking, and if no go-termtracker post exists for that day, create one.
	 *
	 * @uses 'created_term' hook with the term id and taxonomy id as parameters.
	 */
	public function created_term( $unused_term_id, $tt_id = null )
	{
		// check if the term is in one of the taxonomies in the config:
		$term = $this->get_term_by_ttid( $tt_id );

		if ( ! in_array( $term->taxonomy, $this->config( 'taxonomies_to_track' ) ) )
		{
			return;
		}//end if

		// check if any go-termtracker posts exist per current timespan rule:
		if ( ! $post_id = $this->get_journal_post_id() )
		{
			return;
		}//end if

		// add the new term to the list of terms associated with that post:
		wp_set_object_terms( $post_id, (int) $term->term_id, (string) $term->taxonomy, TRUE );
	}//end created_term

	/**
	 * Check if this term has already been created within the specified timespan for the given taxonomy.
	 * If not go ahead and create a new journal entry for it:
	 */
	public function get_journal_post_id()
	{
		$post_id = FALSE;

		// check if go-termtracker posts exist for the timespan:
		$date_query = $this->get_date_query();

		$posts = get_posts(
			array(
				'post_type'   => $this->post_type_name,
				'post_status' => 'publish',
				'fields'      => 'ids',
				'date_query'  => array(
					$date_query,
				),
			)
		);

		if ( ! isset( $posts[0] ) )
		{
			// none yet in this timespan, create new go-termtracker post:
			$posts[0] = $this->create_journal_post();
			if ( ! $posts[0] )
			{
				return FALSE;
			}//end if
		}//end if

		// we'll update the existing post:
		return $posts[0];
	} // END get_journal_post_id

	/**
	 * Create new post for this term:
	 */
	public function create_journal_post()
	{
		$post_arg = array(
			'post_type'   => $this->post_type_name,
			'post_title'  => date( 'Y-m-d' ),
			'post_status' => 'publish',
			'post_date'   => $this->get_quantized_date()->format( 'Y-m-d' ),
		);

		return (int) wp_insert_post( $post_arg );
	} // END create_journal_post

	/**
	 * This next function is from here: https://github.com/misterbisson/scriblio-authority/blob/master/components/class-authority-posttype.php . . .
	 * The comment from that file is also reproduced here:
	 *   I'm pretty sure the only reason why terms aren't fetchable by TTID has to do with the history of WPMU and sitewide terms.
	 *   In this case, we need a UI that accepts terms from multiple taxonomies, so we use the TTID to represent the term in the form element,
	 *   and we need this function to translate those TTIDs into real terms for storage when the form is submitted.
	 */
	public function get_term_by_ttid( $tt_id )
	{
		global $wpdb;

		$sql = "SELECT term_id, taxonomy FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = %d LIMIT 1";
		$term_id_and_tax = $wpdb->get_row( $wpdb->prepare( $sql, $tt_id ), OBJECT );

		if ( ! $term_id_and_tax )
		{
			$error = new WP_Error( 'invalid_ttid', 'Invalid term taxonomy ID' );
			return $error;
		}//end if

		return get_term( (int) $term_id_and_tax->term_id, $term_id_and_tax->taxonomy );
	}//end get_term_by_ttid

	/**
	 * Get array representing the timerange for which we're registering journal entries
	 * (currently: one day)
	 * returns array ready for WP_Query's date parameters
	 */
	private function get_date_query()
	{
		$date = $this->get_quantized_date();
		$date_query = array(
			'year'   => ( int ) $date->format( 'Y' ),
			'month'  => ( int ) $date->format( 'm' ),
			'day'    => ( int ) $date->format( 'd' ),
			'hour'   => 0,
			'minute' => 0,
			'second' => 0,
		);

		return $date_query;
	} // END get_date_query

	/**
	 * Return quantized DateTime object (seeded at now() via the DateTime constructor) for use as 'post_date' in the insert of the go-termtracker post
	 * (rule: round up to start of the current day)
	 * e.g., if now() is Friday 6 December at 3:41 PM and 53 seconds in PDT, return a datetime representing start of that time unit
	 * i.e., return Friday 6 December at 0:00 AM and 00 seconds in PDT
	 */
	private function get_quantized_date()
	{
		$timezone = get_option( 'timezone_string' );
		// if timezone is set, use it, otherwise default to UTC:
		$this->is_valid_timezone_id( $timezone ) ? $local = new DateTimeZone( $timezone ) : $local = new DateTimeZone( 'UTC' );

		$date = new DateTime();
		$date->setTimezone( $local );
		$quantized_date = new DateTime( $date->format( 'Y-m-d e' ) );
		$quantized_date->setTimezone( $local );

		return $quantized_date;
	} // END get_quantized_date

	/**
	 * This list function is reported to return less than the expected number of zone constants
	 * ( ref: http://stackoverflow.com/questions/5816960/how-to-check-is-timezone-identifier-valid-from-code )
	 */
	private function is_valid_timezone_id( $timezone_id )
	{
		$zones = timezone_identifiers_list(); // list of (all) valid timezones
		return in_array( $timezone_id, $zones );
	}//end is_valid_timezone_id
}// end class

/**
 * Singleton
 */
function go_termtracker()
{
	global $go_termtracker;

	if ( ! $go_termtracker )
	{
		$go_termtracker = new GO_Term_Tracker();
	}// end if

	return $go_termtracker;
} // END go_termtracker
