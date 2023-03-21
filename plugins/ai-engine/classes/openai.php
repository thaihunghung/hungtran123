<?php

class Meow_MWAI_OpenAI
{
  private $core = null;
  private $apiKey = null;

  public function __construct($core)
  {
    $this->core = $core;
    $this->apiKey = $this->core->get_option('openai_apikey');
  }

  public function listFiles()
  {
    return $this->run('GET', '/files');
  }

  function getSuffixForModel($model)
  {
    preg_match("/:([a-zA-Z\-]{1,40})-([0-9]{4})-([0-9]{2})-([0-9]{2})/", $model, $matches);
    if (count($matches) > 0) {
      return $matches[1];
    }
    return 'N/A';
  }

  function getBaseModel($model)
  {
    preg_match("/:([a-zA-Z\-]{1,40})-([0-9]{4})-([0-9]{2})-([0-9]{2})/", $model, $matches);
    if (count($matches) > 0) {
      return $matches[1];
    }
    return 'N/A';
  }

  public function listFineTunes()
  {
    $finetunes = $this->run('GET', '/fine-tunes');
    $finetunes['data'] = array_map(function ($finetune) {
      $finetune['suffix'] = $this->getSuffixForModel($finetune['fine_tuned_model']);
      return $finetune;
    }, $finetunes['data']);

    // Get option openai_finetunes_deleted
    $deleted_finetunes = $this->core->get_option('openai_finetunes_deleted');
    // Remove all deleted finetunes from the list, make a new array, without using array_filter

    $finetunes['data'] = array_values(array_filter($finetunes['data'], function ($finetune) use ($deleted_finetunes) {
      return !in_array($finetune['fine_tuned_model'], $deleted_finetunes);
    }));

    $finetunes_option = $this->core->get_option('openai_finetunes');
    $fresh_finetunes_options = array_map(function ($finetune) use ($finetunes_option) {
      $entry = [];
      $model = $finetune['fine_tuned_model'];
      $entry['suffix'] = $finetune['suffix'];
      $entry['model'] = $model;
      $entry['enabled'] = true;
      for ($i = 0; $i < count($finetunes_option); $i++) {
        if ($finetunes_option[$i]['model'] === $model) {
          $entry['enabled'] = $finetunes_option[$i]['enabled'];
          break;
        }
      }
      return $entry;
    }, $finetunes['data']);
    $this->core->update_option('openai_finetunes', $fresh_finetunes_options);
    return $finetunes;
  }

  public function uploadFile($filename, $data)
  {
    $result = $this->run('POST', '/files', null, ['data' => $data, 'filename' => $filename]);
    return $result;
  }

  public function deleteFile($fileId)
  {
    return $this->run('DELETE', '/files/' . $fileId);
  }

  public function deleteFineTune($modelId)
  {
    return $this->run('DELETE', '/models/' . $modelId);
  }

  public function downloadFile($fileId)
  {
    return $this->run('GET', '/files/' . $fileId . '/content', null, null, false);
  }

  public function fineTuneFile($fileId, $model, $suffix)
  {
    $result = $this->run('POST', '/fine-tunes', [
      'training_file' => $fileId,
      'model' => $model,
      'suffix' => $suffix
    ]);
    return $result;
  }

  public function create_body_for_file($file, $boundary)
  {
    $fields = array(
      'purpose' => 'fine-tune',
      'file' => $file['filename']
    );

    $body = '';
    foreach ($fields as $name => $value) {
      $body .= "--$boundary\r\n";
      $body .= "Content-Disposition: form-data; name=\"$name\"";
      if ($name == 'file') {
        $body .= "; filename=\"{$value}\"\r\n";
        $body .= "Content-Type: application/json\r\n\r\n";
        $body .= $file['data'] . "\r\n";
      } else {
        $body .= "\r\n\r\n$value\r\n";
      }
    }
    $body .= "--$boundary--\r\n";
    return $body;
  }

  public function run($method, $url, $query = null, $file = null, $json = true)
  {
    $apiKey = $this->apiKey;
    $headers = "Content-Type: application/json\r\n" . "Authorization: Bearer " . $apiKey . "\r\n";
    $body = $query ? json_encode($query) : null;
    if (!empty($file)) {
      $boundary = wp_generate_password(24, false);
      $headers  = [
        'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        'Authorization' => 'Bearer ' . $this->apiKey,
      ];
      $body = $this->create_body_for_file($file, $boundary);
    }

    $url = 'https://api.openai.com/v1' . $url;
    $options = [
      "headers" => $headers,
      "method" => $method,
      "timeout" => 120,
      "body" => $body,
      "sslverify" => false
    ];

    try {
      $response = wp_remote_request($url, $options);
      if (is_wp_error($response)) {
        throw new Exception($response->get_error_message());
      }
      $response = wp_remote_retrieve_body($response);
      $data = $json ? json_decode($response, true) : $response;

      // Error handling
      if (isset($data['error'])) {
        $message = $data['error']['message'];
        // If the message contains "Incorrect API key provided: THE_KEY.", replace the key by "----".
        if (preg_match('/API key provided(: .*)\./', $message, $matches)) {
          $message = str_replace($matches[1], '', $message);
        }
        throw new Exception($message);
      }

      return $data;
    }
    catch (Exception $e) {
      error_log($e->getMessage());
      throw new Exception('Error while calling OpenAI: ' . $e->getMessage());
    }
  }

  private function calculatePrice( $model, $units, $option = null )
  {
    foreach ( MWAI_OPENAI_PRICING as $price ) {
      if ( $price['model'] == $model ) {
        if ( $price['type'] == 'image' ) {
          if ( !$option ) {
            error_log( "AI Engine: Image models require an option." );
            return null;
          }
          else {
            foreach ( $price['options'] as $imageType ) {
              if ( $imageType['option'] == $option ) {
                return $imageType['price'] * $units;
              }
            }
          }
        }
        else {
          return $price['price'] * $price['unit'] * $units;
        }
      }
    }
    error_log( "AI Engine: Invalid model ($model)." );
    return null;
  }

  public function getPrice( Meow_MWAI_Query $query, Meow_MWAI_Answer $answer )
  {
    $model = $query->model;
    $modelBase = null;
    $units = 0;
    $option = null;
    if ( is_a( $query, 'Meow_MWAI_QueryText' ) ) {
      // Finetuned models
      if ( preg_match('/^([a-zA-Z]{0,32}):/', $model, $matches ) ) {
        $modelBase = "fn-" . $matches[1];
      }
      // Standard models
      else if ( preg_match('/^text-(\w+)-\d+/', $model, $matches ) ) {
        $modelBase = $matches[1];
      }
      if ( empty( $modelBase ) ) {
        error_log("AI Engine: Cannot find the base model for $model.");
        return null;
      }
      $units = $answer->getTotalTokens();
    }
    else if ( is_a( $query, 'Meow_MWAI_QueryImage' ) ) {
      $modelBase = 'dall-e';
      $units = $query->maxResults;
      $option = "1024x1024";
    }
    return $this->calculatePrice( $modelBase, $units, $option );
  }

  public function getIncidents() {
    $url = 'https://status.openai.com/history.rss';
    $response = wp_remote_get( $url );
    if ( is_wp_error( $response ) ) {
      throw new Exception( $response->get_error_message() );
    }
    $response = wp_remote_retrieve_body( $response );
    $xml = simplexml_load_string( $response );
    $incidents = array();
    $oneWeekAgo = time() - 7 * 24 * 60 * 60;
    foreach ( $xml->channel->item as $item ) {
      $date = strtotime( $item->pubDate );
      if ( $date > $oneWeekAgo ) {
        $incidents[] = array(
          'title' => (string) $item->title,
          'description' => (string) $item->description,
          'date' => $date
        );
      }
    }
    return $incidents;
  }
}
