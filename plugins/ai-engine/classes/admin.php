<?php
class Meow_MWAI_Admin extends MeowCommon_Admin {

	public $core;
	public $generator_content;
	public $generator_images;
	public $playground;

	public function __construct( $core ) {
		$this->core = $core;
		parent::__construct( MWAI_PREFIX, MWAI_ENTRY, MWAI_DOMAIN, class_exists( 'MeowPro_MWAI_Core' ) );
		if ( is_admin() ) {
			$this->generator_content = $this->core->get_option( 'module_generator_content' );
			$this->generator_images = $this->core->get_option( 'module_generator_images' );

			$this->playground = $this->core->get_option( 'module_playground' );
			if ( $this->core->can_access_settings() ) {
				add_action( 'admin_menu', array( $this, 'app_menu' ) );
			}

			if ( $this->core->can_access_features() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
				add_action( 'admin_menu', array( $this, 'admin_menu' ) );
				add_filter( 'post_row_actions', [ $this, 'post_row_actions' ], 10, 2 );
				add_action( 'admin_footer', [ $this, 'admin_footer' ] );
			}
		}
	}

	function admin_menu() {

		// Generate New (under Posts)
		if ( $this->generator_content) {
			add_submenu_page( 'edit.php', 'Generate New', 'Generate New', 'manage_options', 'mwai_content_generator', 
				array( $this, 'ai_content_generator' ), 2 );
		}

		// In Tools
		if ( $this->playground ) {
			add_management_page( 'AI Playground', __( 'AI Playground', 'ai-engine' ), 'manage_options', 
				'mwai_dashboard', array( $this, 'ai_playground' ) );
		}
		if ( $this->generator_content ) {
			add_management_page( 'Content Generator', 'AI Content Generator', 'manage_options', 'mwai_content_generator', 
				array( $this, 'ai_content_generator' ) );
		}
		if ( $this->generator_images ) {
			add_management_page( 'Image Generator', 'AI Image Generator', 'manage_options', 'mwai_image_generator', 
				array( $this, 'ai_image_generator' ) );
		}

		// In the Admin Bar:
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 100 );
	}

	function admin_bar_menu( $wp_admin_bar ) {
		$url = MWAI_URL . "/images/wand.png";
		$image_html = "<img style='height: 22px; margin-bottom: -5px; margin-right: 10px;' src='${url}' alt='UI Engine' />";
		$args = null;
		
		if ( $this->playground ) {
			$args = array(
				'id' => 'mwai-playground',
				'title' => $image_html . __( 'AI Playground', 'ai-engine' ),
				'href' => admin_url( 'tools.php?page=mwai_dashboard' ),
				'meta' => array( 'class' => 'mwai-playground' ),
			);
		}
		else if ( $this->generator_content ) {
			$args = array(
				'id' => 'mwai-content-generator',
				'title' => $image_html . __( 'AI Content Generator', 'ai-engine' ),
				'href' => admin_url( 'tools.php?page=mwai_content_generator' ),
				'meta' => array( 'class' => 'mwai-content-generator' ),
			);
		}
		else if ( $this->generator_images ) {
			$args = array(
				'id' => 'mwai-image-generator',
				'title' => $image_html . __( 'AI Image Generator', 'ai-engine' ),
				'href' => admin_url( 'tools.php?page=mwai_image_generator' ),
				'meta' => array( 'class' => 'mwai-image-generator' ),
			);
		}

		if ( $args ) {
			$wp_admin_bar->add_node( $args );
		}
	}

	public function ai_playground() {
		echo '<div id="mwai-playground"></div>';
	}

	public function ai_content_generator() {
		echo '<div id="mwai-content-generator"></div>';
	}

	public function ai_image_generator() {
		echo '<div id="mwai-image-generator"></div>';
	}

	function post_row_actions( $actions, $post ) {
		//if ( $post->post_type === 'post' ) {
			$actions['ai_titles'] = '<a class="mwai-link-title" href="#" data-id="' .
				$post->ID . '" data-title="' . $post->post_title . '">
				<span class="dashicons dashicons-update"></span> Generate Titles</a>';
		//}
		return $actions;
	}

	function admin_footer() {
		echo '<div id="mwai-admin-postsList"></div>';
	}

	function admin_enqueue_scripts() {

		// Load the scripts
		$physical_file = MWAI_PATH . '/app/index.js';
		$cache_buster = file_exists( $physical_file ) ? filemtime( $physical_file ) : MWAI_VERSION;

		wp_register_script( 'mwai_meow_plugin-vendor', MWAI_URL . 'app/vendor.js',
			['wp-element', 'wp-i18n'], $cache_buster
		);
		wp_register_script( 'mwai_meow_plugin', MWAI_URL . 'app/index.js',
			['mwai_meow_plugin-vendor', 'wp-blocks', 'wp-components', 'wp-data', 'wp-edit-post',
				'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins'], $cache_buster
		);
		register_block_type( 'ai-engine/input-field', array( 'editor_script' => 'mwai_meow_plugin' ));
		wp_set_script_translations( 'mwai_meow_plugin', 'ai-engine' );
		wp_enqueue_script('mwai_meow_plugin' );

		// Localize and options
		wp_localize_script( 'mwai_meow_plugin', 'mwai_meow_plugin', [
			'api_url' => rest_url( 'ai-engine/v1' ),
			'rest_url' => rest_url(),
			'plugin_url' => MWAI_URL,
			'prefix' => MWAI_PREFIX,
			'domain' => MWAI_DOMAIN,
			'is_pro' => class_exists( 'MeowPro_MWAI_Core' ),
			'is_registered' => !!$this->is_registered(),
			'rest_nonce' => wp_create_nonce( 'wp_rest' ),
			'session' => $this->core->get_session_id(),
			'options' => $this->core->get_all_options(),
			'pricing' => MWAI_OPENAI_PRICING,
		] );
	}

	function is_registered() {
		return apply_filters( MWAI_PREFIX . '_meowapps_is_registered', false, MWAI_PREFIX );
	}

	function app_menu() {
		add_submenu_page( 'meowapps-main-menu', 'AI Engine', 'AI Engine', 'manage_options',
			'mwai_settings', array( $this, 'admin_settings' ) );
	}

	function admin_settings() {
		echo '<div id="mwai-admin-settings"></div>';
	}
}

?>