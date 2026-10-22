<?php

namespace Drupal\vinkius_connector\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AjaxController extends ControllerBase {
  public function handle(Request $request) {
    $route = trim($request->query->get('route', ''), '/');
    $public = ['agent-manifest','consent','consent/revoke'];
    if (!in_array($route, $public, TRUE)) {
      if (!$this->currentUser()->hasPermission('administer vinkius connector')) {
        return new JsonResponse(['error' => 'forbidden'], 403);
      }
    }

    $body = [];
    $content = $request->getContent();
    if ($content) {
      $decoded = json_decode($content, TRUE);
      if (is_array($decoded)) {
        $body = $decoded;
      }
    }
    $params = array_merge($request->query->all(), $request->request->all(), $body);

    $map = [
      'agent-manifest' => 'agent_manifest',
      'consent' => 'consent_create',
      'consent/revoke' => 'consent_revoke',
      'panel/status' => 'panel_status',
      'panel/section' => 'panel_section',
      'panel/capabilities' => 'panel_capabilities',
      'panel/incidents' => 'panel_incidents',
      'panel/briefing' => 'panel_briefing',
      'panel/evidence-packs' => 'panel_evidence_packs',
      'panel/agents-export' => 'panel_agents_export',
      'panel/tokens' => 'panel_tokens',
      'panel/halt' => 'panel_halt',
      'panel/resume' => 'panel_resume',
      'panel/circuit-reset' => 'panel_circuit_reset',
      'panel/regen-secret' => 'panel_regen_secret',
      'panel/connector' => 'panel_connector',
      'panel/evidence-pack/generate' => 'panel_evidence_pack_generate',
      'panel/evidence-pack' => 'panel_evidence_pack',
      'panel/audit' => 'panel_audit',
      'panel/settings' => 'panel_settings',
      'auth/device/init' => 'device_init',
      'auth/device/poll' => 'device_poll',
      'auth/logout' => 'logout',
    ];

    if (!isset($map[$route])) {
      return new JsonResponse(['error' => 'not_found', 'route' => $route], 404);
    }

    $rest = '\Drupal\vinkius_connector\Rest\Rest';
    $method = $map[$route];
    // Rest class doesn't exist yet; we'll create a minimal Rest class.
    if (!class_exists($rest)) {
      return new JsonResponse(['error' => 'rest_not_implemented'], 500);
    }
    $req = new \Drupal\vinkius_connector\WP_REST_Request();
    // populate request params
    foreach ($params as $k => $v) {
      $req->set($k, $v);
    }
    try {
      $result = $rest::$method($req);
      if ($result instanceof \Drupal\vinkius_connector\WP_REST_Response) {
        return new JsonResponse($result->data, $result->status);
      }
      return new JsonResponse($result);
    }
    catch (\Throwable $e) {
      return new JsonResponse(['error' => $e->getMessage()], 500);
    }
  }
}