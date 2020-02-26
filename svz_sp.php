<?php 
/*
Plugin Name: Static Post Viewer
Description: Generates a simple series of previews for the posts currently published, with a number of costimizable settings for orientation, size, and content of the preview.
Version: 1.0
Author: SysVisionz
Author URI: https://sysvisionz.com
*/

function add_svz_sp_styles() {
	wp_enqueue_style('svz_sp_style', plugins_url() . '/static-post-viewer/svz_sp.css');
}

add_action('wp_enqueue_scripts', 'add_svz_sp_styles');

function create_image_element ($props) {
	extract($props);
	if($thumbnail && !empty($image)){
		$style = $img_max_height || $img_max_width ? 'style="' : '' . !empty($img_max_width) ? "max-width: " . $img_max_width : '' . !empty($img_max_width) && !empty($img_max_height) ? " " : '' . !empty($img_max_height) ? "max-height: " . $img_max_height : '' . $img_max_height || $img_max_width ? '"' : '';
		return "<div class=\"svz-sp-image-container $id\" $style ><a href=\"$permalink\" ><img class=\"svz-sp-image\" src=\"$thumbnail\" /></a></div>";
	}
}

function add_svz_sp_script() {
	?>	
		<script>
			function svzSPAddNeutralListener(element){
				return element.addEventListener 
				? function(type, callback, options) { return element.addEventListener(type, callback, options)}
				: function(type, callback, options) { return element.attachEvent( `on${type}`, callback, options) }
			}

			const svzSPCurrentIndex = {};
			const svzSPCompleted = {};

			function svzSPLoadAdditional(instance, index, dimensions){
				const ajaxValues = {
			        url: '<?php echo admin_url( 'admin-ajax.php' )?>',
			        nonce: '<?php echo wp_create_nonce('svz_sp') ?>',
			    }
				const getURI = config => {
					const {nonce, url} = ajaxValues;
					let retval = url + "?action=svz_sp_get";
					config = {nonce, ...config}
					for (const i in config) {
						retval+= "&" + i + "=" + config[i];
					}
					return retval;
				}
				const xhttp = new XMLHttpRequest();
				xhttp.onreadystatechange = function() {
				    if (this.readyState == 4 && this.status == 200) {
						const {content, complete} = JSON.parse(xhttp.responseText);
						document.getElementById('svz-sp-container-' + index).innerHTML +=  content;
						if (complete){
							const button = document.getElementById('load-button-' + index );
							svzSPCompleted[index] = true;
							button.parentNode.removeChild(button);
						}
				    }
				    resize(index, dimensions);
				};
				svzSPCurrentIndex[index] = svzSPCurrentIndex[index] ? svzSPCurrentIndex[index] + 1 : 1;
				xhttp.open("GET", getURI({type: 'load_more', index: svzSPCurrentIndex[index]}), true);
				xhttp.setRequestHeader('instance',  instance)
				xhttp.send();
			}
			function resize (id, dimensions) {
				const images = Array.from(document.getElementsByClassName(`svz-sp-image-container ${id}`));
				for (const i in images){
				    images[i].style.height = `${images[i].clientWidth * dimensions}px`
				}
			}
			function svzSPInit(data, id, dimensions, loadOnScroll, automatic){
				resize(id, dimensions);
				let resizeWaiting=false;
				svzSPAddNeutralListener(window)('resize', function() {
					if(!resizeWaiting){
						resizeWaiting = setTimeout(function(){resizeWaiting = false}, 20);
						resize(id, dimensions);
					}
				})
				svzSPAddNeutralListener(window)('load', function(){
					resize( id, dimensions );
					if (loadOnScroll && automatic){
						let scrollWaiting = false;
						svzSPAddNeutralListener(window)('scroll', function() {
							if(loadOnScroll && !scrollWaiting){
								scrollWaiting = setTimeout(function(){scrollWaiting = false}, 20);
								const container = document.getElementById(`svz-sp-container-${id}`);
								if (container.getBoundingClientRect().top + container.clientHeight - innerHeight - 100 < 0 && !svzSPCompleted[id]) {
									svzSPLoadAdditional(data, id, dimensions);
								}
							}
						})
					}
					if (document.getElementById(`load-button-${id}` )){
						svzSPAddNeutralListener(document.getElementById(`load-button-${id}` ))('click', function(){
							svzSPLoadAdditional(data, id, dimensions)
						});
					}
				})
			}
			function svzSPAddNeutralListener(element){
				return element.addEventListener 
				? function(type, callback, options) { return element.addEventListener(type, callback, options)}
				: function(type, callback, options) { return element.attachEvent( `on${type}`, callback, options) }
			}
			function conditional (element, test, target){
				const elem = document.getElementById(element);
				return function (callback) { 
					callback(document.getElementById(target), test(elem));
					svzSPAddNeutralListener(elem)('change', function() { callback(document.getElementById(target), test(elem)) } );
				}
			}
		</script>
	<?php
}

add_action('wp_head', 'add_svz_sp_script');

function create_details_element($props){
	extract($props);
	$content_elem = "<div class=\"svz-sp-details-container\"> <div class=\"svz-sp-header-container\"><a href=\"$permalink\"><h3> $title </h3></a>";
	if ($props["timestamp"] || $props['author']){
		$content_elem .= "<p>";
		if ($author){
			$content_elem .= "<span>";
			if((!empty($user->first_name) || !empty($user->last_name)) && !$force_display_name){
				$content_elem .= (!empty($user->first_name) ? $user->first_name : '')
					. (!empty($user->first_name) && $user->last_name ? ' ' : '')
					. (!empty($user->last_name) ? $user->last_name : '');
			}
			else{
				$content_elem .= $user->display_name;
			}
			if ($timestamp){
				$content_elem .= '<br>';
			}
			$content_elem .= "</span>";
		}
		if ($timestamp){
			$content_elem .= "<span>" . date('F j, Y', strtotime($post_date)) . "</span>";
		}
		$content_elem .= "</p>";
	}
	$content_elem .= "</div>";
	if($post_excerpt && $excerpt){
		$content_elem .= "<div class=\"svz-sp-excerpt-container\"><p> $post_excerpt </p></div>";
	}
	$content_elem .= "</div>";
	return $content_elem;
}

function create_row ($row_props, $post_props, $image_props, $details_props) {
	extract($row_props);
	$percentage = $post_percent * count($row) . "%";
	$post_props["per_row"] = $per_row;
	$content_elem = '';
	for ($i = 0; $i < count($row); $i++ ){
		$post_props = array_merge($post_props, array( "post" => $row[$i]));
		$details_props = array_merge($details_props, array(
			"user" => get_userdata($row[$i]->post_author),
			"post_date" => $row[$i]->post_date,
			"post_excerpt" => $row[$i]->post_excerpt,
			"permalink" => get_permalink($row[$i]),
			"title" => $row[$i]->post_title
		));
		$image_props = array_merge($image_props, array(
			"thumbnail" => wp_get_attachment_image_src(get_post_thumbnail_id($row[$i]->ID), 'medium')[0],
			"permalink" => $details_props["permalink"]
		));
		$content_elem .= create_post($post_props, $image_props, $details_props);
	}
	return "<div class=\"svz-sp-row-container\"> <div class=\"svz-sp-row\" style=\"width: $percentage\"> $content_elem </div></div>";
}

function create_post ($props, $image_props, $details_props){
	extract($props);
	$details_props['post']= $post;
	$orientation = $orient_left ? " orient-left" : '';
	$image_elem = create_image_element($image_props);
	$details_elem = create_details_element($details_props);
	return "<div class=\"svz-sp-post-container $orientation\"> $image_elem $details_elem </div>";
}

function create_section ($per_row, $rows_number, $posts, $row_props, $post_props, $image_props, $details_props, $complete){
	$retval = array("content" => '', "complete" => $complete);
	for ($row_i = 0; $row_i < $rows_number; $row_i++){
		$row_props["row"] = array_slice($posts, $row_i * $row_props['per_row'], $per_row);
		if (count($row_props["row"]) > 0){
			$retval['content'] .= create_row( $row_props, $post_props, $image_props, $details_props);
		}
	}
	return $retval;
}

function svz_sp_get(){
    check_ajax_referer( 'svz_sp', 'nonce' );
	switch ($_GET['type']) {
		case 'load_more':
			extract(json_decode(str_replace("\\\"", "\"" , $_SERVER['HTTP_INSTANCE']), true));
			$index = $_GET['index'] * $rows_number * $per_row;
			$posts = get_posts(array("numberposts" => 1000, "post_status" => 'publish'));
			echo json_encode(create_section($per_row, $rows_number, array_slice($posts, $index, $rows_number * $per_row), $row_props, $post_props, $image_props, $details_props, count($posts) <= $index + $per_row * $rows_number));
			break;
		default: 
			echo 'invalid get command: ' . json_encode($_GET);
			break;
	}
	wp_die();
}

add_action('wp_ajax_nopriv_svz_sp_get', 'svz_sp_get');
add_action('wp_ajax_svz_sp_get', 'svz_sp_get');

add_action('wp_enqueue_scripts', 'add_svz_sp_js');

class Simple_Posts extends WP_Widget {
 
	function __construct() {
 
		parent::__construct(
			'simple-posts',  // Base ID
			'Simple Blog List'   // Name
		);
 
		add_action( 'widgets_init', function() {
			register_widget( 'simple_posts' );
		});
 
	}

	public function widget( $args, $instance ) {
		$id = rand(1000, 9999);
		$image_props = array(
			"img_max_height" 	=> $instance["img_max_height"], 
			"img_max_width" 	=> $instance["img_max_width"], 
			"image" 			=> $instance["image"],
			"id"				=> $id
		);
		$details_props = array(
			"excerpt" 				=> $instance["excerpt"], 
			"author" 				=> $instance["author"], 
			"timestamp" 			=> $instance["timestamp"], 
			"force_display_name" 	=> $instance["force_display_name"],
		);
		$per_row = !empty($instance["per_row"]) && $instance["per_row"] ? $instance["per_row"] : 3;
		$row_props = array(
			"post_percent" 	=> round(100/$per_row, 2), 
			"per_row" 		=> $per_row
		);
		$post_props = array(
			"orient_left" 	=> ($per_row == 1 && !$instance["orient_switch"]) || ($per_row != 1 && $instance["orient_switch"]), 
			"rows_number" 	=> !empty($instance["rows_number"]) && $instance["rows_number"] ? $instance["rows_number"] : 1000
		);
		$posts = get_posts(array("numberposts" => 1000, "post_status" => "publish"));
		$rows_number = $post_props["rows_number"] < count($posts) / $per_row ? $post_props["rows_number"] : count($posts) / $per_row;
		?>
			<script>
				svzSPCurrentIndex['<?php echo $id ?>'] = 0;
				svzSPCompleted['<?php echo $id?>'] = false;
				svzSPInit(
					<?php echo '`' . json_encode(array("per_row" => $per_row, "rows_number"=> $rows_number, "row_props"=> $row_props, "post_props"=> $post_props, "image_props" => $image_props, "details_props" => $details_props)) . '`' ?>,
					<?php echo $id ?>,
					<?php echo (!empty($instance["dimensions"]) ? $instance["dimensions"] : 1)?>, 
					<?php echo (!empty($instance["load_more"]) && $instance["load_more"] && !empty($instance['load_on_scroll']) && $instance['load_on_scroll'] ? "true" : "false")?>, 
					<?php echo(!empty($instance['load_on_scroll']) && $instance['load_on_scroll'] ? 'true' : 'false') ?>)
			</script>
			<div>
				<div class="svz-sp-container" id="<?php echo 'svz-sp-container-' . $id ?>">
					<?php echo create_section ($per_row, $rows_number, array_slice($posts, 0, $rows_number * $per_row), $row_props, $post_props, $image_props, $details_props, 	$post_props["rows_number"] <= count($posts) / $per_row)['content']?>
				</div>
					<?php if (!empty($instance['load_more']) && $instance['load_more'] && (empty($instance['load_on_scroll']) || !$instance['load_on_scroll']) && $post_props["rows_number"] <= count($posts) / $per_row) {?>
						<div id="load-button-<?php echo $id ?>" class="button" >Load More</button>
			</div>
			<?php
		}
	}
 
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'excerpt' => true, 'image' => true, 'timestamp' => true, "orient_switch" => false, "load_more" => false, "load_on_scroll" => true) );
		?>
		<script>
			const pairs = [
				["<?php echo $this->get_field_name('load_more') ?>", "load-more-details-container"], 
				["<?php echo $this->get_field_name('image') ?>", "image-container-details"], 
				["<?php echo $this->get_field_name('author') ?>", 'force-author-displayname']
			];
			for (const i in pairs){
				conditional(pairs[i][0], function(targ) { return targ.checked }, pairs[i][1])(function(elem, checked){elem.style.display = checked ? '' : 'none'});
			}
		</script>
		<div>
			<div>
				<span>Number of Posts per Row: </span>
				<input placeholder="3" type="text" name="<?php echo $this->get_field_name('per_row') ?>" value="<?php echo esc_attr($instance['per_row']) ?>" />
			</div>
			<div>
				<span>Max Rows: </span>
				<input placeholder="1000" type="text" name="<?php echo $this->get_field_name('rows_number') ?>" value="<?php echo esc_attr($instance['rows_number']) ?>" />
			</div>
			<div>
				<span>Load More: </span>
				<input type="checkbox" id="<?php echo $this->get_field_name('load_more') ?>" name="<?php echo $this->get_field_name('load_more') ?>" <?php echo esc_attr($instance['load_more']) ? "checked" : '' ?> />
			</div>
			<div id="load-more-details-container">
				<div>
					<span>Automatically Load on Scroll: </span>
					<input type="checkbox" name="<?php echo $this->get_field_name('load_on_scroll') ?>" <?php echo esc_attr($instance['load_on_scroll']) ? "checked" : "" ?> />
				</div>
			</div>				
			<div>
				<span>Include Excerpt: </span>
				<input type="checkbox" name="<?php echo $this->get_field_name('excerpt') ?>" <?php echo esc_attr($instance['excerpt']) ? "checked" : ''?> />
			</div>
			<div>
				<span>Include Timestamp: </span>
				<input type="checkbox" name="<?php echo $this->get_field_name('timestamp') ?>" <?php echo esc_attr($instance['timestamp']) ? "checked": '' ?> />
			</div>
			<div>
				<span>Include Author: </span>
				<input type="checkbox" id="<?php echo $this->get_field_name('author') ?>" name="<?php echo $this->get_field_name('author') ?>" <?php echo esc_attr($instance['author']) ? "checked": '' ?> />
			</div>
			<div id="force-author-displayname">
				<span>Use Display Name Only: </span>
				<input type="checkbox" name="<?php echo $this->get_field_name('force_display_name') ?>" <?php echo esc_attr($instance['force_display_name']) ? "checked": '' ?> />
			</div>
			<div>
				<span>Include Image:</span>
				<input type="checkbox" id="<?php echo $this->get_field_name('image') ?>" name="<?php echo $this->get_field_name('image') ?>" <?php echo esc_attr($instance['image']) ? "checked" : '' ?> />
			</div>
			<div id="image-container-details">
				<div>
					<span>Max Height for Image Container: </span>
					<input type="text" name="<?php echo $this->get_field_name('img_max_height') ?>" value="<?php echo esc_attr($instance['img_max_height']) ?>" />
				</div>
				<div>
					<span>Max Width for Image Container: </span>
					<input type="text" name="<?php echo $this->get_field_name('img_max_width') ?>" value="<?php echo esc_attr($instance['img_max_width']) ?>" />
				</div>
				<div>
					<span>Image Dimensions: </span>
					<div>
						<input type="radio" name="<?php echo $this->get_field_name('dimensions') ?>" <?php echo esc_attr($instance['dimensions']) == 1 || empty($instance['dimensions']) ? "checked" : "" ?> value=1 />
						<label for="<?php echo $this->get_field_name('dimensions') ?>1">1:1</label>
					</div>
					<div>
						<input type="radio" name="<?php echo $this->get_field_name('dimensions') ?>" <?php echo esc_attr($instance['dimensions']) == .5625 ? "checked" : "" ?> value=.5625 />
						<label for="<?php echo $this->get_field_name('dimensions') ?>1">16:9</label>
					</div>
					<div>
						<input type="radio" name="<?php echo $this->get_field_name('dimensions') ?>" <?php echo esc_attr($instance['dimensions']) == .625 ? "checked" : "" ?> value=.625 />
						<label for="<?php echo $this->get_field_name('dimensions') ?>1">16:10</label>
					</div>
					<div>
						<input type="radio" name="<?php echo $this->get_field_name('dimensions') ?>" <?php echo esc_attr($instance['dimensions']) == .66 ? "checked" : "" ?> value=.66 />
						<label for="<?php echo $this->get_field_name('dimensions') ?>1">3:2</label>
					</div>
					<div>
						<input type="radio" name="<?php echo $this->get_field_name('dimensions') ?>" <?php echo esc_attr($instance['dimensions']) == .75 ? "checked" : "" ?> value=.75 />
						<label for="<?php echo $this->get_field_name('dimensions') ?>1">4:3</label>
					</div>
					<div>
						<input type="radio" name="<?php echo $this->get_field_name('dimensions') ?>" <?php echo esc_attr($instance['dimensions']) == 1.25 ? "checked" : "" ?> value=1.25 />
						<label for="<?php echo $this->get_field_name('dimensions') ?>1">16:20</label>
					</div>
				</div>
				<div>
					<span>Switch Image Position: </span>
					<input type="checkbox" name="<?php echo $this->get_field_name('orient_switch') ?>" <?php echo esc_attr($instance['orient_switch']) ? "checked" : "" ?> />
				</div>
			 </div>
		</div>
		<?php
	}
 
	public function update( $new_instance, $old_instance ) {
		$old_instance['rows_number'] = ( !empty( $new_instance['rows_number'] ) ) ? strip_tags( $new_instance['rows_number'] ) : '';
		$old_instance['per_row'] = ( !empty( $new_instance['per_row'] ) ) ? strip_tags( $new_instance['per_row'] ) : 3;
		$old_instance['orient_switch'] = ( !empty( $new_instance['orient_switch'] ) ) ? true : false;
		$old_instance['img_max_height'] = ( !empty( $new_instance['img_max_height'] ) ) ? strip_tags( $new_instance['img_max_height'] ) : '';
		$old_instance['img_max_width'] = ( !empty( $new_instance['img_max_width'] ) ) ? strip_tags( $new_instance['img_max_width'] ) : '';
		$old_instance['image'] = ( !empty( $new_instance['image'] ) ) ? true : false;
		$old_instance['excerpt'] =  ( !empty( $new_instance['excerpt'] ) ) ? true: false;
		$old_instance['author'] = ( !empty( $new_instance['author'] ) ) ? true: false;
		$old_instance['timestamp'] = ( !empty( $new_instance['timestamp'] ) ) ? true: false;
		$old_instance['dimensions'] = ( !empty( $new_instance['dimensions'] ) ) ? $new_instance['dimensions'] : '1';
		$old_instance['force_display_name'] = ( !empty( $new_instance['force_display_name'] ) ) ? true: false;
		$old_instance['load_more'] = ( !empty( $new_instance['load_more'] ) ) ? true: false;
		$old_instance['load_on_scroll'] = ( !empty( $new_instance['load_on_scroll'] ) ) ? true: false;
		return $old_instance;
	}
 
}
$simple_posts = new Simple_Posts();

?>