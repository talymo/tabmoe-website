<?php 

if(!class_exists('KT_Toolkit_Aq_Resize')) {
    class KT_Tool_Aq_Exception extends Exception {}

    class KT_Toolkit_Aq_Resize
    {
        /**
         * The singleton instance
         */
        static private $instance = null;

        /**
         * Should an Aq_Exception be thrown on error?
         * If false (default), then the error will just be logged.
         */
        public $throwOnError = false;

        /**
         * No initialization allowed
         */
        private function __construct() {}

        /**
         * No cloning allowed
         */
        private function __clone() {}

        static public function getInstance() {
            if(self::$instance == null) {
                self::$instance = new self;
            }

            return self::$instance;
        }

        /**
         * Run, forest.
         */
        public function process( $url, $width = null, $height = null, $crop = null, $single = true, $upscale = false ) {
            try {
                // Validate inputs.
                if (!$url)
                    throw new KT_Tool_Aq_Exception('$url parameter is required');

                // Caipt'n, ready to hook.
                if ( true === $upscale ) add_filter( 'image_resize_dimensions', array($this, 'kt_tool_aq_upscale'), 10, 6 );

                // Define upload path & dir.
                $upload_info = wp_upload_dir();
                $upload_dir = $upload_info['basedir'];
                $upload_url = $upload_info['baseurl'];
                
                $http_prefix = "http://";
                $https_prefix = "https://";
                $relative_prefix = "//"; // The protocol-relative URL
                
                /* if the $url scheme differs from $upload_url scheme, make them match 
                   if the schemes differe, images don't show up. */
                if(!strncmp($url,$https_prefix,strlen($https_prefix))){ //if url begins with https:// make $upload_url begin with https:// as well
                    $upload_url = str_replace($http_prefix,$https_prefix,$upload_url);
                }
                elseif(!strncmp($url,$http_prefix,strlen($http_prefix))){ //if url begins with http:// make $upload_url begin with http:// as well
                    $upload_url = str_replace($https_prefix,$http_prefix,$upload_url);      
                }
                elseif(!strncmp($url,$relative_prefix,strlen($relative_prefix))){ //if url begins with // make $upload_url begin with // as well
                    $upload_url = str_replace(array( 0 => "$http_prefix", 1 => "$https_prefix"),$relative_prefix,$upload_url);
                }
                

                // Check if $img_url is local.
                if ( false === strpos( $url, $upload_url ) )
                    throw new KT_Tool_Aq_Exception('Image must be local: ' . $url);

                // Define path of image.
                $rel_path = str_replace( $upload_url, '', $url );
                $img_path = $upload_dir . $rel_path;

                // Check if img path exists, and is an image indeed.
                if ( ! file_exists( $img_path ) or ! getimagesize( $img_path ) )
                    throw new KT_Tool_Aq_Exception('Image file does not exist (or is not an image): ' . $img_path);

                // Get image info.
                $info = pathinfo( $img_path );
                $ext = $info['extension'];
                list( $orig_w, $orig_h ) = getimagesize( $img_path );

                // Get image size after cropping.
                $dims = image_resize_dimensions( $orig_w, $orig_h, $width, $height, $crop );
                $dst_w = $dims[4];
                $dst_h = $dims[5];

                // Return the original image only if it exactly fits the needed measures.
                if ( ! $dims && ( ( ( null === $height && $orig_w == $width ) xor ( null === $width && $orig_h == $height ) ) xor ( $height == $orig_h && $width == $orig_w ) ) ) {
                    $img_url = $url;
                    $dst_w = $orig_w;
                    $dst_h = $orig_h;
                } else {
                    // Use this to check if cropped image already exists, so we can return that instead.
                    $suffix = "{$dst_w}x{$dst_h}";
                    $dst_rel_path = str_replace( '.' . $ext, '', $rel_path );
                    $destfilename = "{$upload_dir}{$dst_rel_path}-{$suffix}.{$ext}";

                    if ( ! $dims || ( true == $crop && false == $upscale && ( $dst_w < $width || $dst_h < $height ) ) ) {
                        // Can't resize, so return false saying that the action to do could not be processed as planned.
                        throw new KT_Tool_Aq_Exception('Unable to resize image because image_resize_dimensions() failed');
                    }
                    // Else check if cache exists.
                    elseif ( file_exists( $destfilename ) && getimagesize( $destfilename ) ) {
                        $img_url = "{$upload_url}{$dst_rel_path}-{$suffix}.{$ext}";
                    }
                    // Else, we resize the image and return the new resized image url.
                    else {

                        $editor = wp_get_image_editor( $img_path );

                        if ( is_wp_error( $editor ) || is_wp_error( $editor->resize( $width, $height, $crop ) ) ) {
                            throw new KT_Tool_Aq_Exception('Unable to get WP_Image_Editor: (is GD or ImageMagick installed?)');
                        }

                        $resized_file = $editor->save();

                        if ( ! is_wp_error( $resized_file ) ) {
                            $resized_rel_path = str_replace( $upload_dir, '', $resized_file['path'] );
                            $img_url = $upload_url . $resized_rel_path;
                        } else {
                                throw new KT_Tool_Aq_Exception('Unable to save resized image file.');
                        }

                    }
                }

                // Okay, leave the ship.
                if ( true === $upscale ) remove_filter( 'image_resize_dimensions', array( $this, 'kt_tool_aq_upscale' ) );

                // Return the output.
                if ( $single ) {
                    // str return.
                    $image = $img_url;
                } else {
                    // array return.
                    $image = array (
                        0 => $img_url,
                        1 => $dst_w,
                        2 => $dst_h
                    );
                }

                // RETINA Support ---------------------------------------------------------------> 
                    if ( apply_filters( 'kadence_retina_support', true ) ) : 
	                    $retina_w = $dst_w*2;
	                    $retina_h = $dst_h*2;
	                    
	                    //get image size after cropping
	                    $dims_x2 = image_resize_dimensions($orig_w, $orig_h, $retina_w, $retina_h, $crop);
	                    $dst_x2_w = $dims_x2[4];
	                    $dst_x2_h = $dims_x2[5];
	                    
	                    // If possible lets make the @2x image
	                    if($dst_x2_h) {

	                        if (true == $crop && ( $dst_x2_w < $retina_w || $dst_x2_h < $retina_h ) ) {
	                            // do nothing
	                        } else {
	                    
	                            $x2suffix = "{$dst_x2_w}x{$dst_x2_h}";
	                            //@2x image url
	                            $destfilename = "{$upload_dir}{$dst_rel_path}-{$x2suffix}.{$ext}";
	                            
	                            //check if retina image exists
	                            if(file_exists($destfilename) && getimagesize($destfilename)) { 
	                                // already exists, do nothing
	                            } else {
	                                // doesnt exist, lets create it
	                                $editor = wp_get_image_editor($img_path);
	                                if ( ! is_wp_error( $editor ) ) {
	                                	$editor->resize( $retina_w, $retina_h, true );
	                                    $editor = $editor->save(); 
	                                }
	                            }
	                        }
	                    
	                    }
                    endif;

                return $image;
            }
            catch (KT_Tool_Aq_Exception $ex) {
                //error_log('Aq_Resize.process() error: ' . $ex->getMessage());

                if ($this->throwOnError) {
                    // Bubble up exception.
                    throw $ex;
                }
                else {
                    // Return false, so that this patch is backwards-compatible.
                    return false;
                }
            }
        }

        /**
         * Callback to overwrite WP computing of thumbnail measures
         */
        function kt_tool_aq_upscale( $default, $orig_w, $orig_h, $dest_w, $dest_h, $crop ) {
            if ( ! $crop ) return null; // Let the wordpress default function handle this.

            // Here is the point we allow to use larger image size than the original one.
            $aspect_ratio = $orig_w / $orig_h;
            $new_w = $dest_w;
            $new_h = $dest_h;

            if ( ! $new_w ) {
                $new_w = intval( $new_h * $aspect_ratio );
            }

            if ( ! $new_h ) {
                $new_h = intval( $new_w / $aspect_ratio );
            }

            $size_ratio = max( $new_w / $orig_w, $new_h / $orig_h );

            $crop_w = round( $new_w / $size_ratio );
            $crop_h = round( $new_h / $size_ratio );

            $s_x = floor( ( $orig_w - $crop_w ) / 2 );
            $s_y = floor( ( $orig_h - $crop_h ) / 2 );

            return array( 0, 0, (int) $s_x, (int) $s_y, (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h );
        }
    }
}





if(!function_exists('kt_toolkit_aq_resize')) {
    function kt_toolkit_aq_resize( $url, $width = null, $height = null, $crop = null, $single = true, $upscale = false, $id = null ) {
        if( class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'photon' ) ) {
                            if(empty($height) ) {
                                $args = array( 'w' => $width );
                                if(!empty($id)) {
                                    $image_attributes = wp_get_attachment_image_src ( $id, 'full' );
                                    $sizes = image_resize_dimensions($image_attributes[1], $image_attributes[2], $width, null, false );
                                    $height = $sizes[5];
                                } else {
                                    $height = null;
                                }
                            } else if(empty($width) ) {
                                $args = array( 'h' => $height );
                                if(!empty($id)) {
                                    $image_attributes = wp_get_attachment_image_src ( $id, 'full' );
                                    $sizes = image_resize_dimensions($image_attributes[1], $image_attributes[2], null, $height, false );
                                    $width = $sizes[4];
                                } else {
                                    $width = null;
                                }
                            } else {
                                $args = array( 'resize' => $width . ',' . $height );
                            }
                             if ( $single ) {
                                    // str return.
                                    $image = jetpack_photon_url( $url, $args );
                                } else {
                                    // array return.
                                    $image = array (
                                        0 => jetpack_photon_url( $url, $args ),
                                        1 => $width,
                                        2 => $height
                                    );
                                }
                                return $image;
        } else {
            $kt_tool_aq_resize = KT_Toolkit_Aq_Resize::getInstance();
            return $kt_tool_aq_resize->process( $url, $width, $height, $crop, $single, $upscale );
        }
    }
}
function kt_toolkit_get_srcset($width,$height,$url,$id) {
  if(empty($id) || empty($url)) {
    return;
  }
  
  $image_meta = get_post_meta( $id, '_wp_attachment_metadata', true );
  if(empty($image_meta['file'])){
    return;
  }
  // If possible add in our images on the fly sizes
  $ext = substr($image_meta['file'], strrpos($image_meta['file'], "."));
  	$pathflyfilename = str_replace($ext,'-'.$width.'x'.$height.'' . $ext, $image_meta['file']);
  	$retina_w = $width*2;
	$retina_h = $height*2;
  	$pathretinaflyfilename = str_replace($ext, '-'.$retina_w.'x'.$retina_h.'' . $ext, $image_meta['file']);
  	$flyfilename = basename($image_meta['file'], $ext) . '-'.$width.'x'.$height.'' . $ext;
  	$retinaflyfilename = basename($image_meta['file'], $ext) . '-'.$retina_w.'x'.$retina_h.'' . $ext;

  $upload_info = wp_upload_dir();
  $upload_dir = $upload_info['basedir'];

  $flyfile = trailingslashit($upload_dir).$pathflyfilename;
  	$retinafile = trailingslashit($upload_dir).$pathretinaflyfilename;
  if(empty($image_meta['sizes']) ){ $image_meta['sizes'] = array();}
    if (file_exists($flyfile)) {
      $kt_add_imagesize = array(
        'kt_on_fly' => array( 
          'file'=> $flyfilename,
          'width' => $width,
          'height' => $height,
          'mime-type' => isset($image_meta['sizes']['thumbnail']) ? $image_meta['sizes']['thumbnail']['mime-type'] : '',
          )
      );
      $image_meta['sizes'] = array_merge($image_meta['sizes'], $kt_add_imagesize);
    }
    if (file_exists($retinafile)) {
  		$kt_add_imagesize_retina = array(
    			'kt_on_fly_retina' => array( 
	          	'file'=> $retinaflyfilename,
	          	'width' => $retina_w,
	          	'height' => $retina_h,
	          	'mime-type' => isset($image_meta['sizes']['thumbnail']) ? $image_meta['sizes']['thumbnail']['mime-type'] : '', 
	          	)
    		);
    	$image_meta['sizes'] = array_merge($image_meta['sizes'], $kt_add_imagesize_retina);
	}
    if(function_exists ( 'wp_calculate_image_srcset') ){
      $output = wp_calculate_image_srcset(array( $width, $height), $url, $image_meta, $id);
    } else {
      $output = '';
    }
    return $output;
}
function kt_toolkit_get_srcset_output($width,$height,$url,$id) {
    $img_srcset = kt_toolkit_get_srcset( $width, $height, $url, $id);
    if(!empty($img_srcset) ) {
      $output = 'srcset="'.esc_attr($img_srcset).'" sizes="(max-width: '.esc_attr($width).'px) 100vw, '.esc_attr($width).'px"';
    } else {
      $output = '';
    }
    return $output;
}
/**
 *
 * Re-create the [gallery] shortcode and use thumbnails styling from kadencethemes
 *
 */
function kadence_shortcode_gallery($attr) {
  $post = get_post();
  static $instance = 0;
  $instance++;

  if (!empty($attr['ids'])) {
    if (empty($attr['orderby'])) {
      $attr['orderby'] = 'post__in';
    }
    $attr['include'] = $attr['ids'];
  }

  $output = apply_filters('post_gallery', '', $attr);

  if ($output != '') {
    return $output;
  }

  if (isset($attr['orderby'])) {
    $attr['orderby'] = sanitize_sql_orderby($attr['orderby']);
    if (!$attr['orderby']) {
      unset($attr['orderby']);
    }
  }

  extract(shortcode_atts(array(
    'order'      => 'ASC',
    'orderby'    => 'menu_order ID',
    'id'         => $post->ID,
    'itemtag'    => '',
    'icontag'    => '',
    'captiontag' => '',
    'columns'    => 3,
    'link'      => 'file',
    'size'       => 'full',
    'include'    => '',
    'attachment_page' => 'false',
    'use_image_alt' => 'false',
    'gallery_id'  => (rand(10,100)),
    'lightboxsize' => 'full',
    'exclude'    => ''
  ), $attr));

  $id = intval($id);

  if ($order === 'RAND') {
    $orderby = 'none';
  }

  $gallery_rn = (rand(10,100));

  if (!empty($include)) {
    $_attachments = get_posts(array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby));

    $attachments = array();
    foreach ($_attachments as $key => $val) {
      $attachments[$val->ID] = $_attachments[$key];
    }
  } elseif (!empty($exclude)) {
    $attachments = get_children(array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby));
  } else {
    $attachments = get_children(array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby));
  }

  if (empty($attachments)) {
    return '';
  }

  if (is_feed()) {
    $output = "\n";
    foreach ($attachments as $att_id => $attachment) {
      $output .= wp_get_attachment_link($att_id, $size, true) . "\n";
    }
    return $output;
  }

  if ($columns == '2') {
    $itemsize = 'tcol-lg-6 tcol-md-6 tcol-sm-6 tcol-xs-12 tcol-ss-12'; $imgsize = 600;
  } else if ($columns == '1') {
    $itemsize = 'tcol-lg-12 tcol-md-12 tcol-sm-12 tcol-xs-12 tcol-ss-12'; $imgsize = 1200;
  } else if ($columns == '3'){
    $itemsize = 'tcol-lg-4 tcol-md-4 tcol-sm-4 tcol-xs-6 tcol-ss-12'; $imgsize = 400;
  } else if ($columns == '6'){
    $itemsize = 'tcol-lg-2 tcol-md-2 tcol-sm-3 tcol-xs-4 tcol-ss-6'; $imgsize = 300;
  } else if ($columns == '8' || $columns == '9' || $columns == '7'){ 
    $itemsize = 'tcol-lg-2 tcol-md-2 tcol-sm-3 tcol-xs-4 tcol-ss-4'; $imgsize = 260;
  } else if ($columns == '12' || $columns == '11'){ 
    $itemsize = 'tcol-lg-1 tcol-md-1 tcol-sm-2 tcol-xs-2 tcol-ss-3'; $imgsize = 240;
  } else if ($columns == '5'){ 
    $itemsize = 'tcol-lg-25 tcol-md-25 tcol-sm-3 tcol-xs-4 tcol-ss-6'; $imgsize = 300;
  } else {
    $itemsize = 'tcol-lg-3 tcol-md-3 tcol-sm-4 tcol-xs-6 tcol-ss-12'; $imgsize = 300;
  }

  $output .= '<div id="kad-wp-gallery'.esc_attr($gallery_rn).'" class="kad-wp-gallery kad-light-wp-gallery clearfix kt-gallery-column-'.esc_attr($columns).' rowtight">'; 
      
  $i = 0;
  foreach ($attachments as $id => $attachment) {
    $attachment_url = wp_get_attachment_url($id);
    $image = kt_toolkit_aq_resize($attachment_url, $imgsize, $imgsize, true, false, false, $id);
    if(empty($image[0])) {$image = array($attachment_url,$imgsize,$imgsize);} 
    $img_srcset_output = kt_toolkit_get_srcset_output( $image[1], $image[2], $attachment_url, $id);
    if($lightboxsize != 'full') {
            $attachment_url = wp_get_attachment_image_src( $id, $lightboxsize);
            $attachment_url = $attachment_url[0];
    }
    $lightbox_data = 'data-rel="lightbox"';
    if($link == 'attachment_page' || $attachment_page == 'true') {
      $attachment_url = get_permalink($id);
      $lightbox_data = '';
    }
    if($use_image_alt == 'true') {
      $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
    } else {
      $alt = $attachment->post_excerpt;
    }

    $output .= '<div class="'.esc_attr($itemsize).' g_item"><div class="grid_item kad_gallery_fade_in gallery_item"><a href="'.esc_url($attachment_url).'" '.$lightbox_data.' class="lightboxhover">';
    $output .= '<img src="'.esc_url($image[0]).'" width="'.esc_attr($image[1]).'" height="'.esc_attr($image[2]).'" alt="'.esc_attr($alt).'" '.$img_srcset_output.' class="light-dropshaddow"/>';
     $output .= '</a>';
    $output .= '</div></div>';
  }
  $output .= '</div>';
  
  return $output;
}
add_action('init', 'kt_tool_gallery_setup_init');
function kt_tool_gallery_setup_init() {
	$pinnacle = get_option( 'pinnacle' );
	$virtue = get_option( 'virtue' );
	if(! function_exists( 'kadence_gallery' ) ) {
		if( (isset($pinnacle['pinnacle_gallery']) && $pinnacle['pinnacle_gallery'] == '1') ||  (isset($virtue['virtue_gallery']) && $virtue['virtue_gallery'] == '1') )  {
		  	remove_shortcode('gallery');
		  	add_shortcode('gallery', 'kadence_shortcode_gallery');
		} 
	}
}
function kt_toolkit_shortcode_gallery($attr) {
	$post = get_post();
	static $instance = 0;
	$instance++;

	if (!empty($attr['ids'])) {
		if (empty($attr['orderby'])) {
		  	$attr['orderby'] = 'post__in';
		}
		$attr['include'] = $attr['ids'];
	}

	$output = apply_filters('post_gallery', '', $attr);

	if ($output != '') {
		return $output;
	}

	if (isset($attr['orderby'])) {
		$attr['orderby'] = sanitize_sql_orderby($attr['orderby']);
		if (!$attr['orderby']) {
	  		unset($attr['orderby']);
		}
	}
	if(!isset($post)) {
    	$post_id = null;
  	} else {
    	$post_id = $post->ID;
  	}

  	extract(shortcode_atts(array(
	    'order'      		=> 'ASC',
	    'orderby'    		=> 'menu_order ID',
	    'id'         		=> $post_id,
	    'columns'    		=> 3,
	    'link'      		=> 'file',
	    'size'       		=> 'full',
	    'include'    		=> '',
	    'use_image_alt' 	=> 'true',
	    'gallery_id'  		=> (rand(10,100)),
	    'lightboxsize'	 	=> 'full',
	    'exclude'    		=> ''
  	), $attr));

  	$id = intval($id);

  	if ($order === 'RAND') {
    	$orderby = 'none';
  	}
  	$caption = 'false';
  	if (!empty($include)) {
    	$_attachments = get_posts(array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby));
	    $attachments = array();
	    foreach ($_attachments as $key => $val) {
	      	$attachments[$val->ID] = $_attachments[$key];
	    }
  	} elseif (!empty($exclude)) {
    	$attachments = get_children(array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby));
  	} else {
    	$attachments = get_children(array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby));
  	}
  	if (empty($attachments)) {
    	return '';
  	}
  	if (is_feed()) {
    	$output = "\n";
    	foreach ($attachments as $att_id => $attachment) {
      		$output .= wp_get_attachment_link($att_id, $size, true) . "\n";
    	}
    	return $output;
  	}
  	$output .= '<div id="kad-wp-gallery'.esc_attr($gallery_id).'" class="kad-wp-gallery kt-gallery-column-'.esc_attr($columns).' kad-light-gallery clearfix row-margin-small">';
    if ($columns == '1') {
    	$itemsize = 'col-xxl-12 col-xl-12 col-lg-12 col-md-12 col-sm-12 col-xs-12 col-ss-12'; 
    	$imgsize = 1140;
    } else if ($columns == '2') {
    	$itemsize = 'col-xxl-3 col-xl-4 col-lg-6 col-md-6 col-sm-6 col-xs-12 col-ss-12'; 
    	$imgsize = 600;
    } else if ($columns == '3'){
    	$itemsize = 'col-xxl-25 col-xl-3 col-lg-4 col-md-4 col-sm-4 col-xs-6 col-ss-12'; 
    	$imgsize = 400;
    } else if ($columns == '4'){ 
    	$itemsize = 'col-xxl-2 col-xl-25 col-lg-3 col-md-3 col-sm-4 col-xs-6 col-ss-12'; 
    	$imgsize = 300;
    } else if ($columns == '5'){ 
    	$itemsize = 'col-xxl-2 col-xl-2 col-lg-25 col-md-25 col-sm-3 col-xs-4 col-ss-6'; 
    	$imgsize = 240;
    } else if ($columns == '6'){ 
    	$itemsize = 'col-xxl-15 col-xl-2 col-lg-2 col-md-2 col-sm-3 col-xs-4 col-ss-6'; 
    	$imgsize = 240;
    } else { 
    	$itemsize = 'col-xxl-1 col-xl-15 col-lg-2 col-md-2 col-sm-3 col-xs-4 col-ss-4'; 
    	$imgsize = 240;
    }
      
  	$i = 0;
  	foreach ($attachments as $id => $attachment) {
	    $attachment_src = wp_get_attachment_image_src($id, 'full');
    	$attachment_url = $attachment_src[0];

	    $image = kt_toolkit_aq_resize($attachment_src[0], $imgsize, $imgsize, true, false, false, $id);
	    
	    if(empty($image[0])) {
	    	$image = $attachment_src[0];
	    } 
	    $img_srcset_output = kt_toolkit_get_srcset_output( $image[1], $image[2], $attachment_url, $id);
	    if($lightboxsize != 'full') {
	            $attachment_lb = wp_get_attachment_image_src( $id, $lightboxsize);
	      		$attachment_url = $attachment_lb[0];
	    }
	    $lightbox_data = 'data-rel="lightbox"';
	    if($link == 'attachment_page') {
	      	$attachment_url = get_permalink($id);
	      	$lightbox_data = '';
	    }
	    // Get alt or caption for alt
	    if($use_image_alt == 'true') {
	      	$alt = get_post_meta($id, '_wp_attachment_image_alt', true);
	    } else {
	      	$alt = $attachment->post_excerpt;
	    }

	    $paddingbtn = ($image[2]/$image[1]) * 100;
    	$output .= '<div class="'.$itemsize.' g_item"><div class="grid_item gallery_item">';
	      	if($link != 'none') { 
	        	$output .='<a href="'.esc_url($attachment_url).'" '.$lightbox_data.' class="gallery-link">';
	      	}
    		$output .= '<div class="kt-intrinsic" style="padding-bottom:'.$paddingbtn.'%;" itemprop="image" itemscope itemtype="http://schema.org/ImageObject">';
    			$output .= '<img src="'.esc_url($image[0]).'" width="'.esc_attr($image[1]).'" height="'.esc_attr($image[2]).'" alt="'.esc_attr($alt).'" '.$img_srcset_output.' itemprop="contentUrl" class="kt-gallery-img"/>';
    			$output .= '<meta itemprop="url" content="'.esc_url($image[0]).'">';
                $output .= '<meta itemprop="width" content="'.esc_attr($image[1]).'">';
                $output .= '<meta itemprop="height" content="'.esc_attr($image[2]).'>">';
    		$output .= '</div>';
      		if (trim($attachment->post_excerpt) && $caption == 'true') {
      			$output .= '<div class="photo-caption-bg"></div>';
        		$output .= '<div class="caption kad_caption">';
        			$output .= '<div class="kad_caption_inner">' . wptexturize($attachment->post_excerpt) . '</div>';
        		$output .= '</div>';
      		}
	      	if($link != 'none') { 
	        	$output .= '</a>';
	      	}
    	$output .= '</div></div>';
  }
  $output .= '</div>';
  
  return $output;
}
add_action('init', 'kt_tool_ascend_gallery_setup_init');
function kt_tool_ascend_gallery_setup_init() {
	$the_theme = wp_get_theme();
	if($the_theme->get( 'Name' ) == 'Ascend' || ($the_theme->get( 'Template') == 'ascend') ) {
		$ascend = get_option( 'ascend' );
	    if(isset($ascend['kadence_gallery']) && $ascend['kadence_gallery'] == '1')  {
		  	remove_shortcode('gallery');
		  	add_shortcode('gallery', 'kt_toolkit_shortcode_gallery');
		} 
	}
}