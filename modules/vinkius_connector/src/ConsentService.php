<?php

namespace Drupal\vinkius_connector;

class ConsentService {
  public static function create($request, $ip) {
    if (Options::is_halted()) {
      return ['error' => 'site_halted', 'message' => 'Agent actions are halted on this site right now.'];
    }
    $ip = (string) $ip;
    if ($ip !== '') {
      $rl_key = 'vc_consent_rl_' . substr(sha1($ip), 0, 16);
      $rl_hits = (int) \Drupal::state()->get($rl_key, 0);
      if ($rl_hits >= 25) {
        return ['error' => 'rate_limited', 'message' => 'Too many requests. Please try again later.'];
      }
      \Drupal::state()->set($rl_key, $rl_hits + 1);
    }
    $hp = (string) ($request['hp'] ?? '');
    if ($hp !== '') {
      return ['ok' => FALSE, 'row_id' => 0];
    }
    $uid = (int) \Drupal::currentUser()->id();
    $external_id = '';
    if ($uid > 0) {
      $external_id = 'drupal_user_' . $uid;
    }
    else {
      $external_id = (string) ($request['external_id'] ?? '');
      if (strpos($external_id, 'drupal_user_') === 0) {
        $external_id = '';
      }
    }
    if (strlen($external_id) > 255) {
      $external_id = substr($external_id, 0, 255);
    }
    if ($external_id === '') {
      $external_id = 'v_' . substr(sha1($ip . '|' . bin2hex(random_bytes(4))), 0, 24);
    }
    $scopes = $request['scopes'] ?? NULL;
    if (!is_array($scopes)) {
      $scopes = ['content.read'];
    }
    $purpose = (string) ($request['purpose'] ?? '');
    $purpose = $purpose !== '' ? $purpose : 'agent_action';
    $row_id = ConsentRepo::record($external_id, $uid, $scopes, $purpose, $ip);
    if (!$row_id) {
      return ['ok' => FALSE, 'error' => 'record_failed'];
    }
    $recorded = ConsentRepo::find($external_id);
    return [
      'ok' => TRUE,
      'row_id' => $row_id,
      'external_id' => $external_id,
      'scopes' => json_decode((string) ($recorded['scopes'] ?? '[]'), TRUE),
      'revoke_url' => '/vinkius/ajax?route=consent/revoke&token=' . ConsentRepo::revoke_token($row_id),
    ];
  }

  public static function revoke_throttle_ok($ip) {
    $ip = (string) $ip;
    if ($ip === '') {
      return TRUE;
    }
    $rl_key = 'vc_revoke_rl_' . substr(sha1($ip), 0, 16);
    $rl_hits = (int) \Drupal::state()->get($rl_key, 0);
    if ($rl_hits >= 10) {
      return FALSE;
    }
    \Drupal::state()->set($rl_key, $rl_hits + 1);
    return TRUE;
  }
}