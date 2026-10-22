<?php

namespace Drupal\vinkius_connector;

class WP_REST_Request implements \ArrayAccess {
  private $params = [];
  public function set($key, $value) { $this->params[$key] = $value; }
  public function get_param($key) {
    return $this->params[$key] ?? NULL;
  }
  public function offsetExists($offset): bool { return isset($this->params[$offset]); }
  public function offsetGet($offset): mixed { return $this->params[$offset] ?? NULL; }
  public function offsetSet($offset, $value): void { $this->params[$offset] = $value; }
  public function offsetUnset($offset): void { unset($this->params[$offset]); }
}

class WP_REST_Response {
  public $data;
  public $status = 200;
  public function __construct($data = NULL, $status = 200) {
    $this->data = $data;
    $this->status = $status;
  }
}