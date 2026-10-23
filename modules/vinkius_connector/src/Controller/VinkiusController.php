<?php

namespace Drupal\vinkius_connector\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Renders the Vinkius governance panel.
 */
class VinkiusController extends ControllerBase {

  /**
   * Screen map: slug => ported screen class.
   */
  protected function screens() {
    return [
      'connect' => 'VinkiusConnector\\Admin\\ScreenConnect',
      'mission-control' => 'VinkiusConnector\\Admin\\ScreenMissionControl',
      'agent-activity' => 'VinkiusConnector\\Admin\\ScreenAgentActivity',
      'connector-traffic' => 'VinkiusConnector\\Admin\\ScreenConnectorTraffic',
      'access-tokens' => 'VinkiusConnector\\Admin\\ScreenAccessTokens',
      'agent-passport' => 'VinkiusConnector\\Admin\\ScreenAgentPassport',
      'ai-spend' => 'VinkiusConnector\\Admin\\ScreenAiSpend',
      'capability-reliability' => 'VinkiusConnector\\Admin\\ScreenCapabilityReliability',
      'security-posture' => 'VinkiusConnector\\Admin\\ScreenSecurityPosture',
      'request-failures' => 'VinkiusConnector\\Admin\\ScreenRequestFailures',
      'compliance' => 'VinkiusConnector\\Admin\\ScreenCompliance',
      'incidents' => 'VinkiusConnector\\Admin\\ScreenIncidents',
      'consent' => 'VinkiusConnector\\Admin\\ScreenConsent',
      'settings' => 'VinkiusConnector\\Admin\\ScreenSettings',
    ];
  }

  /**
   * Admin panel page.
   */
  public function admin() {
    $request = \Drupal::request();

    // Settings form POST.
    if ($request->isMethod('POST') && $request->request->get('action') === 'vc_save_settings') {
      \VinkiusConnector\Admin\ScreenSettings::save_settings();
      return $this->redirect('vinkius_connector.admin');
    }

    $screens = $this->screens();
    $section = (string) $request->query->get('section', 'connect');
    $section = preg_replace('/[^a-z0-9\-]/', '', $section);

    if (!isset($screens[$section])) {
      $section = 'connect';
    }

    $restUrl = Url::fromRoute('vinkius_connector.ajax')->toString() . '?route=';

    $config = [
      'restUrl' => $restUrl,
      'restNonce' => \Drupal::csrfToken()->get(),
      'links' => \VinkiusConnector\Links::all(),
      'gov' => [
        'capabilityBase' => \VinkiusConnector\Links::capability(''),
        'upgrade' => \VinkiusConnector\Links::cloud('upgrade'),
      ],
      'labels' => [],
    ];

    $markup = $this->nav($section) . $this->renderScreen($screens[$section]);

    $build = [];
    $build['vinkius'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['vc-wrap']],
      '#attached' => [
        'library' => ['vinkius_connector/vinkius'],
        'drupalSettings' => ['VCConfig' => $config],
      ],
      '#markup' => $markup,
    ];

    return $build;
  }

  /**
   * Public visitor-consent form.
   */
  public function consent() {
    $endpoint = Url::fromRoute('vinkius_connector.ajax')->toString() . '?route=consent';

    $html = '<div class="vc-consent" style="max-width:560px;margin:40px auto;">'
      . '<h1>AI agent consent</h1>'
      . '<p>Choose which actions AI agents may perform on this site. You can revoke consent at any time.</p>'
      . '<form id="vc-consent-form">'
      . '<fieldset><legend>Scopes</legend>'
      . '<label><input type="checkbox" name="scopes" value="content.read" checked> Read content</label>'
      . '<label><input type="checkbox" name="scopes" value="content.write"> Write content</label>'
      . '<label><input type="checkbox" name="scopes" value="users.subscribe"> Subscribe users</label>'
      . '<label><input type="checkbox" name="scopes" value="media.upload"> Upload media</label>'
      . '</fieldset>'
      . '<button type="submit">Give consent</button>'
      . '<p id="vc-consent-status" role="status"></p>'
      . '</form></div>'
      . '<script>'
      . 'document.getElementById("vc-consent-form").addEventListener("submit",function(e){'
      . 'e.preventDefault();'
      . 'var scopes=Array.prototype.slice.call(document.querySelectorAll("input[name=scopes]:checked")).map(function(n){return n.value;});'
      . 'var id="drupal-"+Math.random().toString(36).slice(2);'
      . 'fetch(' . json_encode($endpoint . '&external_id=') . '+encodeURIComponent(id),{'
      . 'method:"POST",headers:{"Content-Type":"application/json"},'
      . 'body:JSON.stringify({scopes:scopes,external_id:id,purpose:"agent_action"})'
      . '}).then(function(r){return r.json();}).then(function(res){'
      . 'document.getElementById("vc-consent-status").textContent=(res&&res.state==="ok")?"Consent recorded.":"Could not record consent.";'
      . '}).catch(function(){document.getElementById("vc-consent-status").textContent="Could not record consent.";});'
      . '});'
      . '</script>';

    return [
      '#markup' => $html,
      '#attached' => [
        'library' => ['vinkius_connector/vinkius'],
      ],
    ];
  }

  /**
   * Render one ported screen.
   */
  protected function renderScreen($class) {
    ob_start();
    $class::render_page();
    return (string) ob_get_clean();
  }

  /**
   * Panel navigation.
   */
  protected function nav($active) {
    $labels = [
      'connect' => 'AI Connections',
      'mission-control' => 'Mission Control',
      'agent-activity' => 'Agent Activity',
      'connector-traffic' => 'Connector Traffic',
      'access-tokens' => 'Access Tokens',
      'agent-passport' => 'Agent Passport',
      'ai-spend' => 'AI Spend',
      'capability-reliability' => 'Capability Reliability',
      'security-posture' => 'Security Posture',
      'request-failures' => 'Request Failures',
      'compliance' => 'Compliance & Evidence',
      'incidents' => 'Incidents',
      'consent' => 'Consent',
      'settings' => 'Settings',
    ];

    $out = '<nav class="vc-nav" style="margin:10px 0;display:flex;gap:8px;flex-wrap:wrap;">';
    foreach ($labels as $slug => $label) {
      $style = $slug === $active ? 'font-weight:700;' : '';
      $url = Url::fromRoute('vinkius_connector.admin', [], ['query' => ['section' => $slug]])->toString();
      $out .= '<a style="' . $style . '" href="' . $url . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }
    $out .= '</nav>';

    return $out;
  }

}
