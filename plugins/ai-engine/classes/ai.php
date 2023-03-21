<?php

class Meow_MWAI_AI {
  private $core = null;
  private $localApiKey = null;

  public function __construct( $core ) {
    $this->core = $core;
    $this->localApiKey = $this->core->get_option( 'openai_apikey' );
  }

  // Record usage of the API on a monthly basis
  public function record_usage( $model, $prompt_tokens, $completion_tokens = 0 ) {
    if ( !is_numeric( $prompt_tokens ) ) {
      throw new Exception( 'Record usage: prompt_tokens is not a number.' );
    }
    if ( !is_numeric( $completion_tokens ) ) {
      $completion_tokens = 0;
    }
    if ( !$model ) {
      throw new Exception( 'Record usage: model is missing.' );
    }
    $usage = $this->core->get_option( 'openai_usage' );
    $month = date( 'Y-m' );
    if ( !isset( $usage[$month] ) ) {
      $usage[$month] = array();
    }
    if ( !isset( $usage[$month][$model] ) ) {
      $usage[$month][$model] = array(
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0
      );
    }
    $usage[$month][$model]['prompt_tokens'] += $prompt_tokens;
    $usage[$month][$model]['completion_tokens'] += $completion_tokens;
    $usage[$month][$model]['total_tokens'] += $prompt_tokens + $completion_tokens;
    $this->core->update_option( 'openai_usage', $usage );
    return [
      'prompt_tokens' => $prompt_tokens,
      'completion_tokens' => $completion_tokens,
      'total_tokens' => $prompt_tokens + $completion_tokens
    ];
  }

  public function record_image_usage( $model, $resolution, $images ) {
    if ( !$model || !$resolution || !$images ) {
      throw new Exception( 'Missing parameters for record_dalle_usage.' );
    }
    $usage = $this->core->get_option( 'openai_usage' );
    $month = date( 'Y-m' );
    if ( !isset( $usage[$month] ) ) {
      $usage[$month] = array();
    }
    if ( !isset( $usage[$month][$model] ) ) {
      $usage[$month][$model] = array(
        'resolution' => array(),
        'images' => 0
      );
    }
    if ( !isset( $usage[$month][$model]['resolution'][$resolution] ) ) {
      $usage[$month][$model]['resolution'][$resolution] = 0;
    }
    $usage[$month][$model]['resolution'][$resolution] += $images;
    $usage[$month][$model]['images'] += $images;
    $this->core->update_option( 'openai_usage', $usage );
    return [
      'resolution' => $resolution,
      'images' => $images
    ];
  }

  public function runTextQuery( $query ) {
    if ( empty( $query->apiKey ) ) {
      $query->apiKey = $this->localApiKey;
    }
    $url = 'https://api.openai.com/v1/completions';
    $options = array(
      "headers" => "Content-Type: application/json\r\n" . "Authorization: Bearer " . $query->apiKey . "\r\n",
      "method" => "POST",
      "timeout" => 120,
      "body" => json_encode( array(
        "model" => $query->model,
        "prompt" => $query->prompt,
        "stop" => $query->stop,
        "n" => $query->maxResults,
        "max_tokens" => $query->maxTokens,
        "temperature" => $query->temperature,
      ) ),
      "sslverify" => false
    );

    try {
      $response = wp_remote_get( $url, $options );
      if ( is_wp_error( $response ) ) {
        throw new Exception( $response->get_error_message() );
      }
      $response = wp_remote_retrieve_body( $response );
      $data = json_decode( $response, true );
      
      // Error handling
      if ( isset( $data['error'] ) ) {
        $message = $data['error']['message'];
        // If the message contains "Incorrect API key provided: THE_KEY.", replace the key by "----".
        if ( preg_match( '/API key provided(: .*)\./', $message, $matches ) ) {
          $message = str_replace( $matches[1], '', $message );
        }
        throw new Exception( $message );
      }
      if ( !$data['model'] ) {
        error_log( print_r( $data, 1 ) );
        throw new Exception( "Got an unexpected response from OpenAI. Check your PHP Error Logs." );
      }
      $answer = new Meow_MWAI_Answer( $query );
      $usage = $this->record_usage( $data['model'], $data['usage']['prompt_tokens'],
        $data['usage']['completion_tokens'] );
      $answer->setUsage( $usage );
      $answer->setChoices( $data['choices'] );
      return $answer;
    }
    catch ( Exception $e ) {
      error_log( $e->getMessage() );
      throw new Exception( 'Error while calling OpenAI: ' . $e->getMessage() );
    }
  }

  // Request to DALL-E API
  public function runImageQuery( $query ) {
    if ( empty( $query->apiKey ) ) {
      $query->apiKey = $this->localApiKey;
    }
    $url = 'https://api.openai.com/v1/images/generations';
    $options = array(
      "headers" => "Content-Type: application/json\r\n" . "Authorization: Bearer " . $query->apiKey . "\r\n",
      "method" => "POST",
      "timeout" => 120,
      "body" => json_encode( array(
        "prompt" => $query->prompt,
        "n" => $query->maxResults,
        "size" => '1024x1024',
      ) ),
      "sslverify" => false
    );

    try {
      $response = wp_remote_get( $url, $options );
      if ( is_wp_error( $response ) ) {
        throw new Exception( $response->get_error_message() );
      }
      $response = wp_remote_retrieve_body( $response );
      $data = json_decode( $response, true );
      
      // Error handling
      if ( isset( $data['error'] ) ) {
        $message = $data['error']['message'];
        // If the message contains "Incorrect API key provided: THE_KEY.", replace the key by "----".
        if ( preg_match( '/API key provided(: .*)\./', $message, $matches ) ) {
          $message = str_replace( $matches[1], '', $message );
        }
        throw new Exception( $message );
      }

      $answer = new Meow_MWAI_Answer( $query );
      $usage = $this->record_image_usage( "dall-e", "1024x1024", $query->maxResults );
      $answer->setUsage( $usage );
      $answer->setChoices( $data['data'] );
      return $answer;
    }
    catch ( Exception $e ) {
      error_log( $e->getMessage() );
      throw new Exception( 'Error while calling OpenAI: ' . $e->getMessage() );
    }
  }

  public function throwException( $message ) {
    $message = apply_filters( 'mwai_ai_exception', $message );
    throw new Exception( $message );
  }

  public function run( $query ) {

    // Check if the query is allowed
    $limits = $this->core->get_option( 'limits' );
    $ok = apply_filters( 'mwai_ai_allowed', true, $query, $limits );
    if ( $ok !== true ) {
      $message = is_string( $ok ) ? $ok : 'Unauthorized query.';
      $this->throwException( $message );
    }

    // Allow to modify the query
    $query = apply_filters( 'mwai_ai_query', $query );

    // Run the query
    $answer = null;
    try {
      if ( $query instanceof Meow_MWAI_QueryText ) {
        $answer = $this->runTextQuery( $query );
      }
      else if ( $query instanceof Meow_MWAI_QueryImage ) {
        $answer = $this->runImageQuery( $query );
      }
      else {
        $this->throwException( 'Invalid query.' );
      }
    }
    catch ( Exception $e ) {
      $this->throwException( $e->getMessage() );
    }

    // Let's allow some modififications of the answer
    $answer = apply_filters( 'mwai_ai_reply', $answer, $query );
    return $answer;
  }
}
