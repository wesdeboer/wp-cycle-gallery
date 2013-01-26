<?php
/*
Plugin Name: WP-Cycle-Gallery
Plugin URI: http://wesdeboer.com/wp-cycle-gallery
Version: 0.1
Author: Wes DeBoer
Author URI: http://wesdeboer.com
License: GPLv2 or later
*/

define( 'JQUERY_CYCLE_FILE', 'jquery.cycle.all.js' );
define( 'JQUERY_CYCLE_VER', '2.9999.81' );

class WP_Cycle_Gallery {
	var $selectors = array();

	function WP_Cycle_Gallery() {
		add_filter( 'post_gallery', array( &$this, 'wp_cycle_gallery_post_gallery' ), 10, 2 );
		add_action( 'wp_print_scripts', array( &$this, 'wp_cycle_gallery_scripts' ) ); 
		add_action( 'wp_footer', array( &$this, 'wp_cycle_gallery_footer_scripts' ), 100 );
	}

	function wp_cycle_gallery_post_gallery($output, $attr) {
		// return to default gallery if cycle is explicity defined as false
		if ( $attr['cycle'] == 'false' ) {
			return '';
		}

		// override the user supplied columns to 1
		$attr['columns'] = 1;

		// use the default gallery style output with adjustments as necessary below
		$post = get_post();

		static $instance = 0;
		$instance++;

		// We're trusting author input, so let's at least make sure it looks like a valid orderby statement
		if ( isset( $attr['orderby'] ) ) {
			$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
			if ( !$attr['orderby'] )
				unset( $attr['orderby'] );
		}

		extract(shortcode_atts(array(
			'order'      => 'ASC',
			'orderby'    => 'menu_order ID',
			'id'         => $post->ID,
			'itemtag'    => 'dl',
			'icontag'    => 'dt',
			'captiontag' => 'dd',
			'columns'    => 1,
			'size'       => 'thumbnail',
			'include'    => '',
			'exclude'    => '',
			'cycle'      => true, // probably not necessary yet but for future use
			'fx'         => 'fade',
			'speed'      => 1000,
			'timeout'    => 4000,
		), $attr));

		$id = intval($id);
		if ( 'RAND' == $order )
			$orderby = 'none';

		if ( !empty($include) ) {
			$_attachments = get_posts( array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );

			$attachments = array();
			foreach ( $_attachments as $key => $val ) {
				$attachments[$val->ID] = $_attachments[$key];
			}
		} elseif ( !empty($exclude) ) {
			$attachments = get_children( array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
		} else {
			$attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
		}

		if ( empty($attachments) )
			return '';

		if ( is_feed() ) {
			$output = "\n";
			foreach ( $attachments as $att_id => $attachment )
				$output .= wp_get_attachment_link($att_id, $size, true) . "\n";
			return $output;
		}

		$currentheight = 0;
		$currentwidth = 0;
		foreach ( $attachments as $val ) {
				// Get the list of widths/heights to define the gallery width/height at the same as the largest image
				$meta = wp_get_attachment_metadata( $val->ID );
				$currentwidth = $meta['sizes'][$size]['width'];
				if ( $currentwidth > $imagewidth ) {
					$imagewidth = $currentwidth;
				}
				$currentheight = $meta['sizes'][$size]['height'];
				if ( $currentheight > $imageheight ) {
					// height includes the 3% padding added to the image
					$imageheight = $currentheight + (($currentwidth * .03)*2);
				}
		}

		$itemtag = tag_escape($itemtag);
		$captiontag = tag_escape($captiontag);
		$icontag = tag_escape($icontag);
		$valid_tags = wp_kses_allowed_html( 'post' );
		if ( ! isset( $valid_tags[ $itemtag ] ) )
			$itemtag = 'dl';
		if ( ! isset( $valid_tags[ $captiontag ] ) )
			$captiontag = 'dd';
		if ( ! isset( $valid_tags[ $icontag ] ) )
			$icontag = 'dt';

		// We don't need columns as it's a single column cycle
		//$columns = intval($columns);
		//$itemwidth = $columns > 0 ? floor(100/$columns) : 100;
		// We don't need to float as it's a cycle plugin
		//$float = is_rtl() ? 'right' : 'left';

		// rename the selector to prevent it colliding with the default selector
		$selector = "gallerycycle-{$instance}";
		$this->selectors[$selector]['fx'] = $fx;
		$this->selectors[$selector]['speed'] = $speed;
		$this->selectors[$selector]['timeout'] = $timeout;

		$gallery_style = $gallery_div = '';
		if ( apply_filters( 'use_default_gallery_style', true ) )
			// Make some css adjustments to define the width and height of the element based on image size
			$gallery_style = "
			<style type='text/css'>
				#{$selector} {
					height: {$imageheight}px;
					width: {$imagewidth}px;
					margin: 0 auto;
				}
				#{$selector} .gallery-item {
					text-align: center;
					width: {$imagewidth}px;
				}
				#{$selector} img {
					border: 2px solid #cfcfcf;
					background: #ffffff;
				}
				#{$selector} .gallery-caption {
					margin-left: 0;
				}
			</style>
			<!-- see gallery_shortcode() in wp-includes/media.php -->";
		$size_class = sanitize_html_class( $size );
		$gallery_div = "<div id='$selector' class='gallery galleryid-{$id} gallery-columns-{$columns} gallery-size-{$size_class}'>";
		$output = apply_filters( 'gallery_style', $gallery_style . "\n\t\t" . $gallery_div );

		$i = 0;
		foreach ( $attachments as $id => $attachment ) {
			$link = isset($attr['link']) && 'file' == $attr['link'] ? wp_get_attachment_link($id, $size, false, false) : wp_get_attachment_link($id, $size, true, false);

			$output .= "<{$itemtag} class='gallery-item'>";
			$output .= "
				<{$icontag} class='gallery-icon'>
					$link
				</{$icontag}>";
			if ( $captiontag && trim($attachment->post_excerpt) ) {
				$output .= "
					<{$captiontag} class='wp-caption-text gallery-caption'>
					" . wptexturize($attachment->post_excerpt) . "
					</{$captiontag}>";
			}
			$output .= "</{$itemtag}>";
			// remove the <br> for the cycle
			/*
			if ( $columns > 0 && ++$i % $columns == 0 )
				$output .= '<br style="clear: both" />';
			 */
		}

		// Remove this extra <br> output
		$output .= "
			</div>\n";

		return $output;
	}

	function wp_cycle_gallery_footer_scripts() {
		foreach ( $this->selectors as $k => $v ) {
			echo "
<script>
(function($) {
jQuery('#{$k}').cycle({
	fx: '{$v['fx']}',
	speed: '{$v['speed']}',
	timeout: '{$v['timeout']}',
	containerResize: false,
	slideResize: false
});
})(jQuery);
</script>
				";
		}
	}

	function wp_cycle_gallery_scripts() {
		if ( !is_admin() ) {
			wp_enqueue_script( 'cycle', plugins_url( 'js/' . JQUERY_CYCLE_FILE, __FILE__ ), array('jquery'), JQUERY_CYCLE_VER, true );
		}
	}
}

$wp_cycle_gallery = new WP_Cycle_Gallery();
