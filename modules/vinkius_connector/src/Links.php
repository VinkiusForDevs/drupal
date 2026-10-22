<?php

namespace Drupal\vinkius_connector;

class Links {
  const SCREENS = [
    'servers' => '/servers',
    'governance' => '/governance',
    'studio' => '/mcp-studio/published',
    'marketplace' => '/marketplace',
    'seller' => '/settings/earnings',
    'settings' => '/settings',
    'upgrade' => '/upgrade',
    'device' => '/device',
  ];

  public static function cloud($screen) {
    $base = rtrim(Options::FRONTEND_BASE_URL, '/');
    $path = self::SCREENS[$screen] ?? '';
    return $base . $path;
  }

  public static function all() {
    $urls = [];
    foreach (array_keys(self::SCREENS) as $screen) {
      $urls[$screen] = self::cloud($screen);
    }
    return $urls;
  }

  public static function capability($slug) {
    return rtrim(Options::FRONTEND_BASE_URL, '/') . '/ai-agent-connect/' . rawurlencode((string) $slug);
  }
}