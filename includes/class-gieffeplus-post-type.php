<?php
defined('ABSPATH') || die('Not allowed');

if (!class_exists('Gieffeplus_Post_Type')) {

	class Gieffeplus_Post_Type
	{
		public static function register(): void
		{
			$labels = array(
				'name'                     => esc_html__('Rivenditori', 'gieffeplus'),
				'singular_name'            => esc_html__('Rivenditore', 'gieffeplus'),
				'menu_name'                => esc_html__('Rivenditori', 'gieffeplus'),
				'all_items'                => esc_html__('Tutti i Rivenditori', 'gieffeplus'),
				'add_new'                  => esc_html__('Aggiungi Nuovo', 'gieffeplus'),
				'add_new_item'             => esc_html__('Aggiungi Nuovo Rivenditore', 'gieffeplus'),
				'edit_item'                => esc_html__('Modifica Rivenditore', 'gieffeplus'),
				'new_item'                 => esc_html__('Nuovo Rivenditore', 'gieffeplus'),
				'view_item'                => esc_html__('Visualizza Rivenditore', 'gieffeplus'),
				'view_items'               => esc_html__('Visualizza Rivenditori', 'gieffeplus'),
				'search_items'             => esc_html__('Cerca Rivenditori', 'gieffeplus'),
				'not_found'                => esc_html__('Nessun Rivenditore Trovato', 'gieffeplus'),
				'not_found_in_trash'       => esc_html__('Nessun Rivenditore Trovato nel Cestino', 'gieffeplus'),
				'archives'                 => esc_html__('Archivio Rivenditori', 'gieffeplus'),
				'filter_items_list'        => esc_html__('Filtra lista rivenditori', 'gieffeplus'),
				'items_list_navigation'    => esc_html__('Navigazione lista rivenditori', 'gieffeplus'),
				'items_list'               => esc_html__('Lista rivenditori', 'gieffeplus'),
				'attributes'               => esc_html__('Attributi rivenditore', 'gieffeplus'),
				'name_admin_bar'           => esc_html__('Rivenditore', 'gieffeplus'),
			);

			$args = array(
				'label'                 => esc_html__('Rivenditori', 'gieffeplus'),
				'labels'                => $labels,
				'description'           => esc_html__('Punti vendita rivenditori mostrati nella mappa di ricerca.', 'gieffeplus'),
				'public'                => false,
				'publicly_queryable'    => false,
				'show_ui'               => true,
				// Enabled (was false) so the CPT and its meta play nicely with the
				// block editor, WPBakery's data sources and any future REST consumers.
				'show_in_rest'          => true,
				'rest_base'             => 'rivenditori',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
				'has_archive'           => false,
				'show_in_menu'          => true,
				'menu_icon'             => 'dashicons-store',
				'show_in_nav_menus'     => false,
				'delete_with_user'      => false,
				'exclude_from_search'   => true,
				'capability_type'       => 'post',
				'map_meta_cap'          => true,
				'hierarchical'          => false,
				'can_export'            => true,
				'rewrite'               => false,
				'query_var'             => false,
				'supports'              => array('title'),
			);

			register_post_type('rivenditore', $args);
		}
	}
}
