<?php

namespace Drupal\vinkius_connector;

class ConsentRepo {
  const SCOPES = [
    'content.read',
    'content.write',
    'users.subscribe',
    'media.upload',
  ];

  public static function table() {
    return 'vinkius_connector_consents';
  }

  public static function record($external_id, $uid, array $scopes, $purpose = 'agent_action', $ip = '') {
    $db = \Drupal::database();
    $external_id = trim(strip_tags((string) $external_id));
    if ($external_id === '') {
      return 0;
    }
    $valid = array_values(array_intersect($scopes, self::SCOPES));
    $valid = empty($valid) ? ['content.read'] : $valid;
    $ip_hash = self::hash_ip($ip);
    $existing = $db->select(self::table(), 'c')
      ->fields('c', ['id'])
      ->condition('external_id', $external_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    $now = gmdate('Y-m-d H:i:s');
    if ($existing) {
      $db->update(self::table())
        ->fields([
          'uid' => (int) $uid,
          'scopes' => json_encode($valid),
          'purpose' => trim(strip_tags((string) $purpose)),
          'ip_hash' => $ip_hash,
          'revoked_at' => NULL,
          'created_at' => $now,
        ])
        ->condition('id', (int) $existing)
        ->execute();
      return (int) $existing;
    }
    return (int) $db->insert(self::table())
      ->fields([
        'external_id' => $external_id,
        'uid' => (int) $uid,
        'scopes' => json_encode($valid),
        'purpose' => trim(strip_tags((string) $purpose)),
        'ip_hash' => $ip_hash,
        'created_at' => $now,
        'revoked_at' => NULL,
      ])
      ->execute();
  }

  public static function revoke($row_id) {
    $db = \Drupal::database();
    $db->update(self::table())
      ->fields(['revoked_at' => gmdate('Y-m-d H:i:s')])
      ->condition('id', (int) $row_id)
      ->execute();
    return TRUE;
  }

  public static function find($external_id) {
    $db = \Drupal::database();
    $row = $db->select(self::table(), 'c')
      ->fields('c')
      ->condition('external_id', trim(strip_tags((string) $external_id)))
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public static function list_all($limit = 100) {
    $db = \Drupal::database();
    $limit = max(1, min((int) $limit, 500));
    $rows = $db->select(self::table(), 'c')
      ->fields('c')
      ->orderBy('created_at', 'DESC')
      ->range(0, $limit)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    return $rows ?: [];
  }

  public static function is_active($external_id, $scope) {
    $row = self::find($external_id);
    if (!$row || !empty($row['revoked_at'])) {
      return FALSE;
    }
    $scopes = json_decode((string) $row['scopes'], TRUE);
    return is_array($scopes) && in_array($scope, $scopes, TRUE);
  }

  public static function revoke_token($row_id) {
    $secret = (string) Options::get('site_secret');
    return substr(hash_hmac('sha256', 'revoke:' . (int) $row_id, $secret), 0, 32);
  }

  public static function resolve_revoke_token($token) {
    $db = \Drupal::database();
    $token = substr((string) $token, 0, 32);
    if ($token === '') {
      return 0;
    }
    $secret = (string) Options::get('site_secret');
    $rows = $db->select(self::table(), 'c')
      ->fields('c', ['id'])
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $row_token = substr(hash_hmac('sha256', 'revoke:' . (int) $r['id'], $secret), 0, 32);
      if (hash_equals($row_token, $token)) {
        return (int) $r['id'];
      }
    }
    return 0;
  }

  private static function hash_ip($ip) {
    $ip = trim((string) $ip);
    if ($ip === '') {
      return '';
    }
    return substr(hash_hmac('sha256', $ip, 'drupal_salt' . (\Drupal::request()->getSchemeAndHttpHost())), 0, 64);
  }
}