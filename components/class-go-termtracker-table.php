<?php

class GO_Term_Tracker_Table extends WP_List_Table
{
	public function __construct()
	{
		parent::__construct( array(
			'singular'  => 'term',
			'plural'    => 'terms',
			'ajax'      => FALSE,    // does this table support ajax?
		) );
	}//end __construct

	public function column_default( $item, $unused_column_name )
	{
		return print_r( $item, TRUE );
	}//END column_default

	public function column_name( $item )
	{
		// Reference: /wordpress/wp-admin/edit-tags.php:
		$location = 'edit-tags.php?action=edit&taxonomy=' . $item->taxonomy . '&tag_ID=' . $item->term_id . '&post_type=post';
		return '<a href="' . esc_url( admin_url( $location ) ) . '">' . esc_html( $item->name ) . '</a>';
	}//END column_name

	public function column_slug( $item )
	{
		return $item->slug;
	}//END column_slug

	public function column_taxonomy( $item )
	{
		return $item->taxonomy;
	}//END column_taxonomy

	public function column_count( $item )
	{
		return $item->count;
	}//END column_count

	public function get_columns()
	{
		$columns = array(
			'name'     => 'Name',
			'slug'     => 'Slug',
			'taxonomy' => 'Taxonomy',
			'count'    => 'Posts',
		);
		return $columns;
	}//END get_columns

	public function get_sortable_columns()
	{
		$sortable_columns = array(
			'name'     => array( 'name', false ),
			'slug'     => array( 'slug', false ),
			'taxonomy' => array( 'taxonomy', false ),
			'count'    => array( 'count', false )
		);
  		return $sortable_columns;
	}//END get_sortable_columns

	public function prepare_items( $terms )
	{
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->items = array();
		foreach ( $terms as $term )
		{
			$this->items[] = $term;
		}//END foreach
		usort( $this->items, array( &$this, 'usort_reorder' ) );
	}//END prepare_items

	public function usort_reorder( $a, $b )
	{
		// If no sort, default to name
		$orderby = empty( $_GET['orderby'] ) ? 'name' : $_GET['orderby'];
		$orderby = isset( $a->$orderby ) ? $orderby : 'name';

		// If no order, default to asc
		$order = empty( $_GET['order'] ) ? 'asc' : $_GET['order'];

		// Determine sort order
		$result = strcmp( $a->$orderby, $b->$orderby );

		// Send final sort direction to usort
		return ( $order === 'asc' ) ? $result : -$result;
	}//END usort_reorder
}//END GO_Term_Tracker_Table