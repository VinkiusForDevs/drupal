<?php

namespace Drupal\vinkius_connector;

class Result {
  private $state;
  private $data;
  private $code;

  private function __construct($state, $data = NULL, $code = '') {
    $this->state = $state;
    $this->data = $data;
    $this->code = $code;
  }

  public static function ok($data = []) {
    return new self('ok', is_array($data) ? $data : ['value' => $data]);
  }
  public static function not_configured() {
    return new self('not_configured', NULL, 'vc_not_configured');
  }
  public static function not_connected() {
    return new self('not_connected', NULL, 'vc_not_connected');
  }
  public static function error($code, $data = NULL) {
    return new self('error', is_array($data) ? $data : NULL, $code);
  }

  public function is_ok() { return $this->state === 'ok'; }
  public function state() { return $this->state; }
  public function code() { return $this->code; }
  public function data() { return (array) $this->data; }
  public function to_view() {
    return [
      'state' => $this->state,
      'code' => $this->code,
      'data' => (array) $this->data,
    ];
  }
}