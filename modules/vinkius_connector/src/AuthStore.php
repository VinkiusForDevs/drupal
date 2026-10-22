<?php

namespace Drupal\vinkius_connector;

class AuthStore {
  const KEY = 'vinkius_connector_auth';

  public static function store(array $payload) {
    \Drupal::state()->set(self::KEY, [
      'access_token' => isset($payload['access_token']) ? (string) $payload['access_token'] : '',
      'refresh_token' => isset($payload['refresh_token']) ? (string) $payload['refresh_token'] : '',
      'expires_at' => time() + (isset($payload['expires_in']) ? (int) $payload['expires_in'] : 3600),
    ]);
    Options::set('connected', TRUE);
  }

  public static function get() {
    $row = \Drupal::state()->get(self::KEY, []);
    return is_array($row) ? $row : [];
  }

  public static function access_token() {
    $row = self::get();
    if (empty($row['access_token'])) {
      return Result::not_connected();
    }
    if ((int) ($row['expires_at'] ?? 0) - time() < 30) {
      $refresh = self::refresh();
      if (!$refresh->is_ok()) {
        return $refresh;
      }
      $row = self::get();
      if (empty($row['access_token'])) {
        return Result::not_connected();
      }
    }
    return Result::ok(['token' => $row['access_token']]);
  }

  public static function refresh() {
    $row = self::get();
    if (empty($row['refresh_token'])) {
      return Result::not_connected();
    }
    $client = new HttpClient();
    $result = $client->request('POST', '/auth/refresh', ['refresh_token' => $row['refresh_token']]);
    if (!$result->is_ok()) {
      if (in_array($result->code(), ['vc_http_401', 'vc_http_400'], TRUE)) {
        self::clear();
      }
      return $result;
    }
    $data = $result->data();
    if (!empty($data['access_token'])) {
      self::store($data);
    }
    return Result::ok(['token' => $data['access_token'] ?? '']);
  }

  public static function clear() {
    \Drupal::state()->delete(self::KEY);
    Options::set('connected', FALSE);
  }

  public static function status() {
    $row = self::get();
    $state = 'not_connected';
    if (!empty($row['access_token'])) {
      $state = ((int) ($row['expires_at'] ?? 0) > time()) ? 'connected' : 'expired';
    }
    return [
      'state' => $state,
      'has_refresh' => !empty($row['refresh_token']),
      'expires_at' => (int) ($row['expires_at'] ?? 0),
    ];
  }
}