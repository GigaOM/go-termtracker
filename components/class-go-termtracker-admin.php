<?php
/*
* This class includes the admin UI components and metaboxes, and the supporting methods they require.
*/
class GO_Term_Tracker_Admin
{
	private $show_popular = FALSE;

	public function __construct()
	{
		// change the columns shown in the dashboard list of posts
		// http://core.trac.wordpress.org/browser/tags/3.5.1/wp-admin/includes/class-wp-posts-list-table.php#L0
		add_action( 'manage_' . go_termtracker()->post_type_name . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_' . go_termtracker()->post_type_name . '_posts_columns', array( $this, 'columns' ), 11 );

		// add any CSS needed for the dashboard
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// if the go-popular plugin exists, enable additional "popular" data about terms
		$this->show_popular = function_exists( 'go_popular' );
	}//END __construct

	/**
	 * register and enqueue any scripts needed for the dashboard
	 */
	public function admin_enqueue_scripts()
	{
		wp_register_style( 'go-termtracker-admin', go_termtracker()->plugin_url . '/css/go-termtracker-admin.css', array(), go_termtracker()->version );
		wp_enqueue_style( 'go-termtracker-admin' );
	}//end admin_enqueue_scripts

	/**
	 * register our metaboxes
	 */
	public function metaboxes()
	{
		if ( ! class_exists( 'GO_Term_Tracker_Table' ) )
		{
			require( __DIR__ . '/class-go-termtracker-table.php' );
		}//end if

		add_meta_box( $this->get_field_id( 'terms' ), 'New Terms', array( $this, 'metabox_terms' ), go_termtracker()->post_type_name, 'normal', 'default' );

		if ( $this->show_popular )
		{
			add_meta_box( $this->get_field_id( 'popular' ), 'Popular Terms', array( $this, 'metabox_popular_terms' ), go_termtracker()->post_type_name, 'normal', 'default' );
			add_meta_box( $this->get_field_id( 'emergent' ), 'Emergent Terms', array( $this, 'metabox_emergent_terms' ), go_termtracker()->post_type_name, 'normal', 'default' );
		}//end if
	}//END metaboxes

	/**
	 * Get the data for this post and pass it to a list table view
	 */
	public function metabox_terms( $post )
	{
		$terms = $this->get_terms( $post );

		$table = new GO_Term_Tracker_Table;
		$table->prepare_items( $terms );
		$table->display();
	}//END metabox_terms

	/**
	 * Get the popular terms for this post and pass it to a list table view
	 */
	public function metabox_popular_terms( $post )
	{
		$terms = $this->get_popular_terms( $post );

		$table = new GO_Term_Tracker_Table;

		$table->columns['popularity'] = 'Popularity';
		$table->default_order_by = 'popularity';
		$table->default_order = 'desc';

		$table->prepare_items( $terms );
		$table->display();
	}//END metabox_popular_terms

	/**
	 * Get the emergent terms for this post and pass it to a list table view
	 */
	public function metabox_emergent_terms( $post )
	{
		$terms = $this->get_emergent_terms( $post );

		$table = new GO_Term_Tracker_Table;

		$table->columns['emergent_score'] = 'Emergent Score';
		$table->default_order_by = 'emergent_score';
		$table->default_order = 'desc';

		$table->prepare_items( $terms );
		$table->display();
	}//END metabox_popular_terms

	/**
	 * hooked to the manage_%POSTTYPE_posts_custom_column action
	 */
	public function column( $column, $post_id )
	{
		if ( $this->column_name( 'popular' ) == $column && $this->show_popular )
		{
			// escaped upstream, contains HTML
			$this->column_popular( $post_id );
			return;
		}//end if

		if ( $this->column_name( 'emergent' ) == $column && $this->show_popular )
		{
			// escaped upstream, contains HTML
			$this->column_emergent( $post_id );
			return;
		}//end if

		if ( $this->column_name( 'terms' ) == $column )
		{
			// escaped upstream, contains HTML
			$this->column_terms( $post_id );
			return;
		}//end if
	}//END column

	/**
	 * hooked to the manage_%POSTTYPE_posts_columns action
	 * Forces this post type to only show checkbox, title, and terms
	 */
	public function columns( $columns )
	{
		$columns = array(
			'cb' => $columns['cb'],
			'title' => $columns['title'],
			$this->column_name( 'terms' ) => 'New Terms',
		);

		if ( $this->show_popular )
		{
			$columns[ $this->column_name( 'popular' ) ] = 'Popular Terms';
			$columns[ $this->column_name( 'emergent' ) ] = 'Emergent Terms';
		}//end if

		return $columns;
	}//END columns

	private function column_name( $column )
	{
		return go_termtracker()->id_base . '_' . $column;
	}// end column_name

	private function get_terms( $post )
	{
		if ( ! is_object( $post ) )
		{
			$post = get_post( $post );
		}//end if

		return wp_get_object_terms( $post->ID, go_termtracker()->config( 'taxonomies_to_track' ) );
	}//end get_terms

	private function column_terms( $post_id )
	{
		$terms = $this->get_terms( $post_id );
		echo $this->get_admin_dashboard_terms( $terms );
	}//END column_terms

	private function column_popular( $post_id )
	{
		$terms = $this->get_popular_terms( $post_id );
		echo $this->get_admin_dashboard_terms( $terms );
	}//end column_go_popular

	private function column_emergent( $post_id )
	{
		$terms = $this->get_emergent_terms( $post_id );
		echo $this->get_admin_dashboard_terms( $terms );
	}//end column_go_popular

	private function get_popular_terms( $post )
	{
		if ( ! is_object( $post ) )
		{
			$post = get_post( $post );
		}//end if

		$args = array(
			'count' => -1,
		);

		$args['from'] = date( 'Y-m-d', strtotime( $post->post_date ) );
		$args['to'] = $args['from'] . ' 23:59:59';

		return go_popular()->get_popular_terms(  go_termtracker()->config( 'taxonomies_to_track' ), $args );
	}//end get_popular_terms

	private function get_emergent_terms( $post )
	{
		if ( ! is_object( $post ) )
		{
			$post = get_post( $post );
		}//end if

		$args = array(
			'count' => -1,
		);

		$args['from'] = date( 'Y-m-d', strtotime( $post->post_date ) );
		$args['to'] = $args['from'] . ' 23:59:59';

		return go_popular()->get_emergent_terms(  go_termtracker()->config( 'taxonomies_to_track' ), $args );
	}//end get_emergent_terms

	/**
	 * generate simple comma-separated list
	 */
	private function get_admin_dashboard_terms( $terms )
	{
		$terms_html = array();
		foreach ( $terms as $term )
		{
			$terms_html[] = esc_html( $term->taxonomy . ': ' . $term->name );
		}// end foreach

		return '<span>' . implode( '</span>, <span>', $terms_html ) . '</span>';
	}//END get_admin_dashboard_terms

	private function get_field_name( $field_name )
	{
		return go_termtracker()->id_base . '[' . $field_name . ']';
	}//END get_field_name

	private function get_field_id( $field_name )
	{
		return go_termtracker()->id_base . '-' . $field_name;
	}//END get_field_id
}//END GO_Term_Tracker_Admin