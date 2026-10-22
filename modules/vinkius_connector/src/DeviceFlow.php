<?php

namespace Drupal\vinkius_connector;

class DeviceFlow {
  const BUNDLE_KEY = 'vinkius_connector_device_bundle';

  public function init() {
    $client = new HttpClient();
    $result = $client->request('POST', '/auth/device', ['client_name' => 'Drupal']);
    if (!$result->is_ok()) {
      return $result;
    }
    $data = $result->data();
    $bundle = [
      'device_code' => isset($data['device_code']) ? (string) $data['device_code'] : '',
      'user_code' => isset($data['user_code']) ? (string) $data['user_code'] : '',
      'expires_in' => isset($data['expires_in']) ? (int) $data['expires_in'] : 900,
      'interval' => isset($data['interval']) ? (int) $data['interval'] : 5,
      'created_at' => time(),
    ];
    $verification_url = $data['verification_uri_complete'] ?? $data['verification_url'] ?? NULL;
    if (empty($verification_url)) {
      $verification_url = rtrim(Options::FRONTEND_BASE_URL, '/') . '/device';
    }
    $bundle['verification_url'] = $verification_url;
    \Drupal::state()->set(self::BUNDLE_KEY, $bundle);
    unset($bundle['device_code'], $bundle['created_at']);
    return Result::ok($bundle);
  }

  public function poll() {
    $bundle = \Drupal::state()->get(self::BUNDLE_KEY, []);
    if (!is_array($bundle) || empty($bundle['device_code'])) {
      return Result::error('vc_device_no_session');
    }
    if (time() - (int) $bundle['created_at'] > $bundle['expires_in']) {
      \Drupal::state()->delete(self::BUNDLE_KEY);
      return Result::error('vc_device_expired');
    }
    $client = new HttpClient();
    $result = $client->request('POST', '/auth/device/poll', ['device_code' => $bundle['device_code']]);
    if (!$result->is_ok()) {
      $payload = $result->data();
      $upstream = isset($payload['error']) ? (string) $payload['error'] : '';
      if (in_array($upstream, ['authorization_pending', 'slow_down'], TRUE)) {
        return Result::ok(['status' => 'pending']);
      }
      if (in_array($upstream, ['expired_token', 'invalid_grant'], TRUE)) {
        \Drupal::state()->delete(self::BUNDLE_KEY);
        return Result::error('vc_device_expired');
      }
      return Result::ok(['status' => 'pending']);
    }
    $data = $result->data();
    if (!empty($data['access_token'])) {
      AuthStore::store($data);
      \Drupal::state()->delete(self::BUNDLE_KEY);
      return Result::ok(['status' => 'success']);
    }
    return Result::ok(['status' => 'pending']);
  }

  public function abort() {
    \Drupal::state()->delete(self::BUNDLE_KEY);
  }

  public function is_pending() {
    $bundle = \Drupal::state()->get(self::BUNDLE_KEY, []);
    return is_array($bundle) && !empty($bundle['device_code']);
  }

  public static function bundle() {
    $bundle = \Drupal::state()->get(self::BUNDLE_KEY, []);
    return is_array($bundle) ? $bundle : [];
  }
}