<?php
/*
* This class includes the admin UI components and metaboxes, and the supporting methods they require.
*/
class GO_Term_Tracker_Admin
{
	public function __construct()
	{
		// change the columns shown in the dashboard list of posts
		// http://core.trac.wordpress.org/browser/tags/3.5.1/wp-admin/includes/class-wp-posts-list-table.php#L0
		add_action( 'manage_' . go_termtracker()->post_type_name . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_' . go_termtracker()->post_type_name . '_posts_columns', array( $this, 'columns' ), 11 );

		// add any CSS needed for the dashboard
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
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
		add_meta_box( $this->get_field_id( 'terms' ), 'Terms', array( $this, 'metabox_terms' ), go_termtracker()->post_type_name, 'normal', 'default' );
	}//END metaboxes

	/**
	 * Get the data for this post and pass it to a list table view
	 */
	public function metabox_terms( $post )
	{
		$terms = wp_get_object_terms( $post->ID, go_termtracker()->config( 'taxonomies_to_track' ) );

		if ( ! class_exists( 'GO_Term_Tracker_Table' ) )
		{
			require( __DIR__ . '/class-go-termtracker-table.php' );
		}//end if
		$table = new GO_Term_Tracker_Table;
		$table->prepare_items( $terms );
		$table->display();
	}//END metabox_terms

	/**
	 * hooked to the manage_%POSTTYPE_posts_custom_column action
	 */
	public function column( $column, $post_id )
	{
		if ( $this->column_name() != $column )
		{
			return;
		}//end if

		// escaped upstream, contains HTML
		echo $this->column_terms( $post_id );
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
			$this->column_name() => 'Terms',
		);

		return $columns;
	}//END columns

	private function column_name()
	{
		return go_termtracker()->id_base . '_terms';
	}// end column_name

	private function column_terms( $post_id )
	{
		$terms = wp_get_object_terms( $post_id, go_termtracker()->config( 'taxonomies_to_track' ) );
		return $this->get_admin_dashboard_terms( $terms );
	}//END column_terms

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