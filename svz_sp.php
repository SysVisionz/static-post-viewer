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

function add_svz_sp_js(){
	wp_enqueue_script('svz_sp_script', plugins_url() . '/static-post-viewer/svz_sp.js');
}

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

	private function create_image_element ($props) {
		$img_max_height = !empty($props["img_max_height"]) ? $props['img_max_height'] : '';
		$img_max_width = !empty($props['img_max_width']) ? $props['img_max_width'] : '';
		$image = $props['image'];
		$thumbnail = $props["thumbnail"];
		if($thumbnail && $image){
			?>
			<div class="svz-sp-image-container" <?php echo $img_max_height || $img_max_width ? 'style="' : '' . !empty($img_max_width) ? "max-width: " . $img_max_width : '' . !empty($img_max_width) && !empty($img_max_height) ? " " : '' . !empty($img_max_height) ? "max-height: " . $img_max_height : '' . $img_max_height || $img_max_width ? '"' : '' ?>
			>
				<img class="svz-sp-image <?php echo $props["id"] ?>" src="<?php echo $thumbnail ?>" />
			</div>
			<?php
		}
	}

	private function create_details_element($props){
		$excerpt = $props["excerpt"]; 
		$author = $props["author"];
		$timestamp = $props["timestamp"]; 
		$force_display_name =$props["force_display_name"];
		$user = $props["user"];
		$title = $props["post"]->post_title;
		$post = $props["post"];
		?>
		<div class="svz-sp-details-container">
			<div class="svz-sp-header-container">
				<a href="<?php echo get_permalink($post) ?>">
					<h3>
						<?php echo $title ?>
					</h3>
				</a>
				<?php if ($props["timestamp"] || $props['author']){
					?><p>
						<?php if ($timestamp){?>
							<span><?php echo $timestamp ? $post->post_date . ($author ? ', ' : '') : ''?></span>
						<?php } 
						if ($author){?>
							<span><?php if((!empty($user->first_name) || !empty($user->last_name)) && !$force_display_name){
									echo (!empty($user->first_name) ? $user->first_name : '')
									. (!empty($user->first_name) && $user->last_name ? ' ' : '')
									. (!empty($user->last_name) ? $user->last_name : '');
								}
								else{
									echo $user->display_name;
								}
								?>
							</span>
							<?php
						}?>
					</p>
					<?php
				}?>
			</div>
			<?php if($post->post_excerpt && $excerpt){
				?>
				<div class="svz-sp-excerpt-container">
					<p>
						<?php $post->post_excerpt ?>
					</p>
				</div>
				<?php
			}?>
		</div>
	<?php
	}

	private function create_row ($row_props, $post_props, $image_props, $details_props) {
		extract($row_props);
		$post_props["per_row"] = $per_row;
		?>
		<div class="svz-sp-row-container">
			<div class="svz-sp-row" style="width: <?php echo  $post_percent * count($row) ?>%";>
				<?php
				for ($i = 0; $i < count($row); $i++ ){
					$post_props["post"] = $row[$i];
					$details_props["user"] = get_userdata($row[$i]->post_author);
					$image_props["thumbnail"] = wp_get_attachment_image_src(get_post_thumbnail_id($row[$i]->ID), 'medium')[0];
					$this->create_post($post_props, $image_props, $details_props);
				}?>
			</div>
		</div>
		<?php
	}

	private function create_post ($props, $image_props, $details_props){
		$per_row = $props["per_row"];
		$details_props['post']= $props["post"];
		?>
			<div class="svz-sp-post-container <?php echo $props["orient_left"] ? " orient-left" : '' ?>">
				<?php 
					$this->create_image_element($image_props);
					$this->create_details_element($details_props);
				?>
			</div>
		<?php 
	}

	public function widget( $args, $instance ) {
		$id = rand(1000, 9999);
		?>
		<script>
			function addNeutralListener(element){
				return element.addEventListener 
				? function(type, callback, options) { return element.addEventListener(type, callback, options)}
				: function(type, callback, options) { return element.attachEvent( `on${type}`, callback, options) }
			}

			function initResize(){
				const resize = () => {
					const images = Array.from(document.getElementsByClassName('svz-sp-image <?php echo $id ?>'));
					for (const i in images){
					    images[i].parentNode.style.height = `${<?php echo $instance["16x10"] ? ".625 * " : ""?>images[i].parentNode.clientWidth}px`
					}
				}
				resize();
				let waiting=false;
				addNeutralListener(window)('resize', () => {
					if(!waiting){
						waiting = setTimeout(() => waiting = false, 20);
						resize();
					}
				})
			}
		</script>
		<?php
		$image_props = array(
			"image_max_height" => $instance["img_max_height"], 
			"img_max_width" => $instance["img_max_width"], 
			"image" => $instance["image"],
			"id" => $id
		);
		$details_props = array(
			"excerpt" => $instance["excerpt"], 
			"author" => $instance["author"], 
			"timestamp" => $instance["timestamp"], 
			"force_display_name" => $instance["force_display_name"],
		);
		$per_row = !empty($instance["per_row"]) && $instance["per_row"] ? $instance["per_row"] : 3;
		$row_props = array(
			"post_percent" => 100/$per_row, 
			"per_row" => $per_row
		);
		$post_props = array(
			"orient_left" => ($per_row == 1 && !$instance["orient_switch"]) || ($per_row != 1 && $instance["orient_switch"]), 
			"rows_number" => !empty($instance["rows_number"]) && $instance["rows_number"] ? $instance["rows_number"] : 1000
		);
		$posts = get_posts(array("numberposts" => $post_props["rows_number"] * $per_row));
		$rows_number = !empty($instance["rows_number"]) && $instance["rows_number"] != '' ? $instance["rows_number"] : count($posts) / $per_row;
		?>
			<div class="svz-sp-container" onload="initResize">
				<?php
				for ($row_i = 0; $row_i < $rows_number; $row_i++){
					$row_props["row"] = array_slice($posts, $row_i * $row_props['per_row'], $per_row);
					$this->create_row($row_props, $post_props, $image_props, $details_props);
				} ?>
			</div>
		<?php
	}
 
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'excerpt' => true, 'image' => true, 'timestamp' => true, "orient_switch" => false ) );
		?>
		<script>
			function addNeutralListener(element){
				return element.addEventListener 
				? function(type, callback, options) { return element.addEventListener(type, callback, options)}
				: function(type, callback, options) {
					return element.attachEvent( `on${type}`, callback, options);
				}
			}
			function conditional (element, test, target){
				const elem = document.getElementById(element);
				return function (callback) { 
					callback(document.getElementById(target), test(elem));
					addNeutralListener(elem)('change', function() { callback(document.getElementById(target), test(elem)) } );
				}
			}
			const pairs = [["<?php echo $this->get_field_name('image') ?>", "image-container-details"], ["<?php echo $this->get_field_name('author') ?>", 'force-author-displayname']];
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
					<span>16x10 Image Size: </span>
					<input type="checkbox" name="<?php echo $this->get_field_name('16x10') ?>" <?php echo esc_attr($instance['16x10']) ? "checked" : "" ?> />
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
		$old_instance['16x10'] = ( !empty( $new_instance['16x10'] ) ) ? true: false;
		$old_instance['force_display_name'] = ( !empty( $new_instance['force_display_name'] ) ) ? true: false;
		return $old_instance;
	}
 
}
$simple_posts = new Simple_Posts();

?>