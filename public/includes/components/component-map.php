<?php

if (!function_exists('aesop_map_shortcode')) {
	function aesop_map_shortcode($atts, $content = null) {

		$defaults = array(
			'height' 	=> 500,
			'sticky'	=> 'off'
		);

		wp_enqueue_script('aesop-map-script',AI_CORE_URL.'/public/includes/libs/leaflet/leaflet.js');
		wp_enqueue_style('aesop-map-style',AI_CORE_URL.'/public/includes/libs/leaflet/leaflet.css', AI_CORE_VERSION, true);

		$atts = apply_filters('aesop_map_defaults',shortcode_atts($defaults, $atts));

		// sticky maps class
		$sticky = 'off' !== $atts['sticky'] ? sprintf('aesop-sticky-map-%s', esc_attr( $atts['sticky'] ) ) : null;
		
		//clean height
		$get_height = 'off' == $atts['sticky'] ? preg_replace('/[^0-9]/','',$atts['height'] ) : null;
		$height = $get_height ? sprintf('style="height:%spx;"',$get_height ) : null;

		// custom classes
		$classes = function_exists('aesop_component_classes') ? aesop_component_classes( 'map', '' ) : null;
		
		// get markers - since 1.3
		$markers 	= get_post_meta( get_the_ID(), 'ase_map_component_locations', false);

		// filterable map marker waypoint offset - since 1.3
		// 50% means when the id hits 50% from the top the waypoint will fire
		$marker_waypoint_offset = apply_filters('aesop_map_waypoint_offset', '50%');

		ob_start();

		/**
		*
		* 	if sticky and we have markers do scroll waypoints
		*
		* 	@since 1.3
		*/
		if ( 'off' !== $atts['sticky'] && $markers ):

			?>
			<!-- Aesop Sticky Maps -->
			<script>
				jQuery(document).ready(function(){

					jQuery('body').addClass('aesop-sticky-map <?php echo esc_attr($sticky);?>');

					map.invalidateSize();

					<?php
					$i = 0;

					foreach( $markers as $key => $marker ): $i++;

						$loc 	= sprintf('%s,%s',$marker['lat'],$marker['lng']);

						?>
						jQuery('#aesop-map-marker-<?php echo absint($i);?>').waypoint({
							offset: '<?php echo esc_attr($marker_waypoint_offset);?>',
							handler: function(direction){
								map.panTo(new L.LatLng(<?php echo esc_attr($loc);?>));
							}
						});
						<?php

					endforeach;
					?>
				});
			</script><?php

		endif;

		do_action('aesop_map_before');
			?><div id="aesop-map-component" class="aesop-component aesop-map-component <?php echo sanitize_html_class($classes);?> " <?php echo $height;?>></div><?php
		do_action('aesop_map_before');

		return ob_get_clean();
	}

}

class AesopMapComponent {

	function __construct(){
		add_action('wp_footer', array($this,'aesop_map_loader'),20);

		// map marker shortcode
		add_shortcode('aesop_map_marker', array($this,'aesop_map_marker_sc'));

	}

	public function aesop_map_loader(){

		global $post;

		$id         = isset( $post ) ? $post->ID : null;

		$mapboxid 	= get_option('ase_mapbox_id','aesopinteractive.hkoag9o3');
		$markers 	= isset( $post ) ? get_post_meta( $id, 'ase_map_component_locations', false) : false;
		$tiles 		= isset( $post ) ? get_post_meta( $id, 'aesop_map_tiles', true) : false;
		$start 		= isset( $post ) && self::get_map_meta( $id, 'ase_map_component_start') ? self::get_map_meta( $id, 'ase_map_component_start' ) : self::start_fallback( $markers );
		$zoom 		= isset( $post ) && self::get_map_meta( $id, 'ase_map_component_zoom') ? self::get_map_meta( $id, 'ase_map_component_zoom' ) : 12;

		$default_location 	= is_single();
		$location 			= apply_filters( 'aesop_map_component_appears', $default_location );
	
		if ( function_exists('aesop_component_exists') && aesop_component_exists('map') && ( $location ) )  { ?>
			<!-- Aesop Locations -->
			<script>
				<?php
				if ( $markers ): ?>
					var map = L.map('aesop-map-component',{
						scrollWheelZoom: false,
						zoom: <?php echo wp_filter_nohtml_kses( round( $zoom ) );?>,
						center: [<?php echo $start;?>]
					});
				<?php if ( 'hydda' == $tiles ) { ?>
					L.tileLayer('http://{s}.tile.openstreetmap.se/hydda/full/{z}/{x}/{y}.png', {
						minZoom: 0,
						maxZoom: 18,
						attribution: 'Tiles <a href="http://openstreetmap.se/" target="_blank">OSM Sweden</a> &mdash; Data &copy; <a href="http://openstreetmap.org">OSM</a>'
					}).addTo(map);
				<?php } elseif ( 'mq-sat' == $tiles ) { ?>
					L.tileLayer('http://oatile{s}.mqcdn.com/tiles/1.0.0/sat/{z}/{x}/{y}.jpg', {
						attribution: 'Tiles <a href="http://www.mapquest.com/">MapQuest</a>, NASA/JPL-Caltech, US Dept. of Ag.',
						subdomains: '1234'
					}).addTo(map);
					L.tileLayer('http://{s}.tile.stamen.com/toner-hybrid/{z}/{x}/{y}.png', {
						attribution: 'Tiles <a href="http://stamen.com">Stamen Design</a> &mdash; Data &copy; <a href="http://openstreetmap.org">OSM</a>',
						subdomains: 'abcd',
						minZoom: 0,
						maxZoom: 20
					}).addTo(map);
				<?php } elseif ( 'mq-sat-c' == $tiles ) { ?>
					L.tileLayer('http://oatile{s}.mqcdn.com/tiles/1.0.0/sat/{z}/{x}/{y}.jpg', {
						attribution: 'Tiles <a href="http://www.mapquest.com/">MapQuest</a>, NASA/JPL-Caltech, US Dept. of Ag.',
						subdomains: '1234'
					}).addTo(map);
				<?php } elseif ( 'acetate' == $tiles ) { ?>
					L.tileLayer('http://a{s}.acetate.geoiq.com/tiles/acetate-hillshading/{z}/{x}/{y}.png', {
						attribution: '&copy; Esri & Stamen, Data OSM & Natural Earth',
						subdomains: '0123',
						minZoom: 2,
						maxZoom: 18
					}).addTo(map);
				<?php } elseif ( 'stamen-tonerlite' == $tiles ) { ?>
					L.tileLayer('http://{s}.tile.stamen.com/toner-lite/{z}/{x}/{y}.png', {
						attribution: 'Tiles <a href="http://stamen.com">Stamen Design</a> &mdash; Data &copy; <a href="http://openstreetmap.org">OSM</a>',
						subdomains: 'abcd',
						minZoom: 0,
						maxZoom: 20
					}).addTo(map);
				<?php } elseif ( 'stamen-toner' == $tiles ) { ?>
					L.tileLayer('http://{s}.tile.stamen.com/toner/{z}/{x}/{y}.png', {
						attribution: 'Tiles <a href="http://stamen.com">Stamen Design</a> &mdash; Data &copy; <a href="http://openstreetmap.org">OSM</a>',
						subdomains: 'abcd',
						minZoom: 0,
						maxZoom: 20
					}).addTo(map);
				<?php } elseif ( 'stamen-w' == $tiles ) { ?>
					L.tileLayer('http://{s}.tile.stamen.com/watercolor/{z}/{x}/{y}.png', {
						attribution: 'Tiles <a href="http://stamen.com">Stamen Design</a> &mdash; Data &copy; <a href="http://openstreetmap.org">OSM</a>',
						subdomains: 'abcd',
						minZoom: 1,
						maxZoom: 16
					}).addTo(map);
					L.tileLayer('http://{s}.tile.stamen.com/toner-hybrid/{z}/{x}/{y}.png', {
						attribution: 'Tiles <a href="http://stamen.com">Stamen Design</a> &mdash; Data &copy; <a href="http://openstreetmap.org">OSM</a>',
						subdomains: 'abcd',
						minZoom: 0,
						maxZoom: 20
					}).addTo(map);
				<?php } elseif ( 'stamen-w-c' == $tiles ) { ?>
					L.tileLayer('http://{s}.tile.stamen.com/watercolor/{z}/{x}/{y}.png', {
						attribution: 'Tiles <a href="http://stamen.com">Stamen Design</a> &mdash; Data &copy; <a href="http://openstreetmap.org">OSM</a>',
						subdomains: 'abcd',
						minZoom: 1,
						maxZoom: 16
					}).addTo(map);
				<?php } elseif ( 'openc' == $tiles ) { ?>
					L.tileLayer('http://{s}.tile.thunderforest.com/cycle/{z}/{x}/{y}.png', {
						attribution: '&copy; <a href="http://www.opencyclemap.org">OpenCycleMap</a>, &copy; <a href="http://openstreetmap.org">OSM</a>'
					}).addTo(map);
				<?php } elseif ( 'greenglobe' == $tiles) { ?>
					L.tileLayer('http://tile.mtbmap.cz/mtbmap_tiles/{z}/{x}/{y}.png').addTo(map);
					L.tileLayer('http://{s}.tile.stamen.com/toner-hybrid/{z}/{x}/{y}.png', {
						attribution: 'Tiles <a href="http://stamen.com">Stamen Design</a>, <a href="http://openstreetmap.org">OSM</a>',
						subdomains: 'abcd',
						minZoom: 0,
						maxZoom: 20
					}).addTo(map);
				<?php } elseif ( 'mapbox' == $tiles) { ?>
					L.tileLayer('//{s}.tiles.mapbox.com/v3/<?php echo esc_attr($mapboxid);?>/{z}/{x}/{y}.png', {
						maxZoom: 20
					}).addTo(map);
				<?php } else { ?>
					L.tileLayer('//{s}.tiles.mapbox.com/v3/<?php echo esc_attr($mapboxid);?>/{z}/{x}/{y}.png', {
						maxZoom: 20
					}).addTo(map);
				<?php }
					foreach( $markers as $marker ):

						$lat 	= $marker['lat'];
						$long 	= $marker['lng'];
						$text 	= $marker['title'] ? $marker['title'] : null;

						$loc 	= sprintf('%s,%s',esc_attr($lat),esc_attr($long));

						// if market content is set run a popup
						if ( $text ) { ?>

							L.marker([<?php echo $loc;?>]).addTo(map).bindPopup('<?php echo aesop_component_media_filter($text);?>').openPopup();

						<?php } else { ?>

							L.marker([<?php echo $loc;?>]).addTo(map);

						<?php }

					endforeach;

				else:

					if ( is_user_logged_in() ) {
						$url 		= admin_url( 'post.php?post='.$id.'&action=edit' );
						$editlink 	= sprintf('<a href="%s">here</a>',$url );

						?>jQuery('#aesop-map-component').append('<div class="aesop-error aesop-content"><?php echo __("Your map appears to be empty! Setup and configure your map markers in this post {$editlink}.","aesop-core");?></div>');<?php

					}

				endif;
				?>
			</script>

		<?php }
	}

	/**
	*
	*	Retrieve meta settings for map component
	*
	*	@param $post_id int
	*   @param $key string -meta key
	* 	@return starting coordinate
	* 	@since 1.1
	*/
	private function get_map_meta($post_id = 0, $key = ''){

		// bail if no post id set or no key
		if ( empty( $post_id ) || empty( $key ) )
			return;

  		$meta = get_post_meta( $post_id, $key, true );

  		return empty( $meta ) ? null : $meta;

	}

	/**
	*
	*	If the user has not entered a starting view coordinate,
	*	then fallback to the first coordinate entered if present.
	*
	*	@param $markers - array - gps coordinates entered aspost meta within respective post
	*	@return first gps marker found
	* 	@since 1.1
	*
	*/
	private function start_fallback( $markers ) {

		// bail if no markers found
		if( empty( $markers ) )
			return;

		$i = 0;

		foreach ( $markers as $marker ) { $i++;

			$lat 	= sanitize_text_field($marker['lat']);
			$long 	= sanitize_text_field($marker['lng']);

			$mark 	= sprintf('%s,%s',$lat,$long);

			if ( $i == 1 );
				break;

		}

		return $mark;

	}

	/**
	*
	*	Add a shortcode that lets users decide trigger points in map component
	*	Note: this is ONLY used when maps is in sticky mode, considered an internal but public function
	*
	*
	*/
	function aesop_map_marker_sc($atts, $content = null) {

		$defaults = array('title' => '','hidden' => '');

		$atts = shortcode_atts( $defaults, $atts );

		// let this be used multiple times
		static $instance = 0;
		$instance++;

		$out = sprintf('<h2 id="aesop-map-marker-%s" class="aesop-map-marker">%s</h2>', $instance, esc_html( $atts[ 'title'] ) );

		return apply_filters('aesop_map_marker_output', $out);
	}
}
new AesopMapComponent;
