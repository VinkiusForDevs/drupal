<?php

namespace Drupal\vinkius_connector\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

class VinkiusController extends ControllerBase {
  public function admin() {
    $section = \Drupal::request()->query->get('section', 'overview');
    $links = \Drupal\vinkius_connector\Links::all();
    $config = [
      'restUrl' => Url::fromRoute('vinkius_connector.ajax')->toString() . '?',
      'restNonce' => \Drupal::csrfToken()->get(),
      'links' => $links,
      'gov' => [
        'capabilityBase' => \Drupal\vinkius_connector\Links::capability(''),
        'upgrade' => \Drupal\vinkius_connector\Links::cloud('upgrade'),
      ],
      'labels' => [],
    ];
    $build = [];
    $build['vinkius'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['vc-wrap']],
      '#attached' => [
        'library' => ['vinkius_connector/vinkius'],
        'drupalSettings' => ['VCConfig' => $config],
      ],
      '#markup' => $this->adminMarkup($section),
    ];
    return $build;
  }

  protected function adminMarkup($section = 'overview') {
    $status = \Drupal\vinkius_connector\AuthStore::status()['state'];
    $halt = \Drupal\vinkius_connector\Options::is_halted() ? '1' : '0';
    $out = '<div class="wrap vc-wrap" data-status="' . htmlspecialchars($status) . '">';
    $out .= '<h1 class="vc-title">Vinkius AI Governance</h1>';
    $out .= '<div class="vc-nav" style="margin:10px 0;">';
    $pages = ['connect','mission-control','agent-activity','connector-traffic','access-tokens','agent-passport','ai-spend','capability-reliability','security-posture','request-failures','compliance','incidents','consent','settings'];
    foreach ($pages as $p) {
      $out .= '<a style="margin-right:8px;" href="?section=' . urlencode($p) . '">' . htmlspecialchars($p) . '</a>';
    }
    $out .= '</div>';
    $out .= '<div id="vc-gov" class="vc-gov" data-section="' . htmlspecialchars($section) . '" data-status="' . htmlspecialchars($status) . '"></div>';
    $out .= '</div>';
    return $out;
  }
}