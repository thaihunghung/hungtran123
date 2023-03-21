<?php

class Meow_MWAI_Modules_Assistants {
  private $core = null;

  public function __construct() {
    global $mwai_core;
    $this->core = $mwai_core;

    // Add Metadata Metabox to Product Post Type Edit Page
    add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
  }

  function add_meta_boxes() {
    if ( get_post_type() !== 'product' ) {
      return;
    }
    add_meta_box( 'meow-mwai-metadata',
      __( 'AI Engine', 'meow-mwai' ),
      array( $this, 'render_metadata_metabox' ),
      'product', 'side', 'high'
    );
  }

  function render_metadata_metabox( $post ) {
    echo '<div id="mwai-admin-wcAssistant"></div>';
  }
}