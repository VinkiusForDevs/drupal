<?php

namespace Drupal\vinkius_connector;

class Options {
  const KEY = 'vinkius_connector_settings';
  const API_BASE_URL = 'https://api.vinkius.com';
  const FRONTEND_BASE_URL = 'https://cloud.vinkius.com';

  public static function defaults() {
    return [
      'language' => 'site',
      'site_secret' => bin2hex(random_bytes(24)),
      'halt' => FALSE,
      'connected' => FALSE,
      'badge_scan_id' => '',
      'digest_enabled' => FALSE,
      'digest_email' => '',
      'halted_at' => NULL,
    ];
  }

  public static function all() {
    $stored = \Drupal::state()->get(self::KEY, []);
    if (!is_array($stored)) {
      $stored = [];
    }
    $merged = array_merge(self::defaults(), $stored);
    if (empty($merged['site_secret'])) {
      $merged['site_secret'] = bin2hex(random_bytes(24));
      self::save($merged);
    }
    return $merged;
  }

  public static function save($row) {
    \Drupal::state()->set(self::KEY, $row);
  }

  public static function get($key, $default = NULL) {
    $row = self::all();
    return array_key_exists($key, $row) ? $row[$key] : $default;
  }

  public static function set($key, $value) {
    $row = self::all();
    $row[$key] = $value;
    self::save($row);
  }

  public static function is_configured() {
    return TRUE;
  }

  public static function is_halted() {
    return (bool) self::get('halt', FALSE);
  }
}