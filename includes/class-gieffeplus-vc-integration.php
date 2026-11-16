<?php
defined('ABSPATH') || die('Not allowed');

if (!class_exists('Gieffeplus_Vc_Integration')) {

	/**
	 * Exposes [rivenditori] as a drag-and-drop WPBakery element. Rendering is
	 * still handled entirely by Gieffeplus_Shortcode::render() — vc_map() just
	 * maps the same shortcode tag onto a friendly params UI, so behaviour is
	 * identical whether the shortcode is typed manually or added via WPBakery.
	 */
	class Gieffeplus_Vc_Integration
	{
		public static function map(): void
		{
			if (!function_exists('vc_map') || !defined('WPB_VC_VERSION')) {
				return;
			}

			vc_map(array(
				'name'        => __('Reseller Map', 'gieffeplus'),
				'base'        => 'rivenditori',
				'description' => __('Mappa di ricerca "trova il rivenditore più vicino a te".', 'gieffeplus'),
				'category'    => __('Content', 'gieffeplus'),
				'icon'        => 'icon-wpb-google-maps',
				'params'      => array(
					array(
						'type'        => 'textfield',
						'heading'     => __('Titolo', 'gieffeplus'),
						'param_name'  => 'title',
						'value'       => __('Trova il rivenditore più vicino a te', 'gieffeplus'),
					),
					array(
						'type'        => 'textarea',
						'heading'     => __('Sottotitolo', 'gieffeplus'),
						'param_name'  => 'subtitle',
						'value'       => __('Inserisci il tuo indirizzo per iniziare la ricerca', 'gieffeplus'),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __('Placeholder campo indirizzo', 'gieffeplus'),
						'param_name'  => 'placeholder',
						'value'       => __('CAP, città o indirizzo', 'gieffeplus'),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __('Testo pulsante', 'gieffeplus'),
						'param_name'  => 'button_text',
						'value'       => __('Cerca', 'gieffeplus'),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __('Titolo elenco risultati', 'gieffeplus'),
						'param_name'  => 'results_title',
						'value'       => __('Gli store nelle tue vicinanze', 'gieffeplus'),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __('Numero massimo risultati', 'gieffeplus'),
						'param_name'  => 'results_count',
						'value'       => '6',
						'description' => __('Da 1 a 20.', 'gieffeplus'),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __('Altezza mappa (px)', 'gieffeplus'),
						'param_name'  => 'map_height',
						'value'       => '420',
					),
					array(
						'type'        => 'textfield',
						'heading'     => __('Zoom iniziale', 'gieffeplus'),
						'param_name'  => 'zoom',
						'value'       => '10',
					),
					array(
						'type'        => 'textfield',
						'heading'     => __('Centro mappa - Latitudine', 'gieffeplus'),
						'param_name'  => 'center_lat',
						'value'       => '41.8719',
					),
					array(
						'type'        => 'textfield',
						'heading'     => __('Centro mappa - Longitudine', 'gieffeplus'),
						'param_name'  => 'center_lng',
						'value'       => '12.5674',
					),
				),
			));
		}
	}
}
