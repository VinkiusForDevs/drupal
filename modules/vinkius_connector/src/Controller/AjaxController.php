<?php

namespace Drupal\vinkius_connector\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * AJAX bridge between the ported panel and the Vinkius API.
 */
class AjaxController extends ControllerBase {

  /**
   * Public (agent-facing) routes.
   */
  protected function publicRoutes() {
    return ['agent-manifest', 'consent', 'consent/revoke'];
  }

  /**
   * Route => static method on the ported REST class.
   */
  protected function routeMap() {
    return [
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
  }

  /**
   * Handle an AJAX request.
   */
  public function handle(Request $request) {
    $raw = (string) $request->query->get('route', '');
    $extra = [];

    if (strpos($raw, '?') !== false) {
      [$raw, $queryString] = explode('?', $raw, 2);
      parse_str($queryString, $extra);
    }

    $route = trim($raw, '/');
    $isPublic = in_array($route, $this->publicRoutes(), true);

    if (!$isPublic && !$this->currentUser()->hasPermission('administer vinkius connector')) {
      return new JsonResponse(['error' => 'forbidden'], 403);
    }

    $body = [];
    $content = $request->getContent();
    if ($content) {
      $decoded = json_decode($content, true);
      if (is_array($decoded)) {
        $body = $decoded;
      }
    }

    $map = $this->routeMap();
    if (!isset($map[$route])) {
      return new JsonResponse(['error' => 'not_found', 'route' => $route], 404);
    }

    $params = array_merge($request->query->all(), $request->request->all(), $extra, $body);
    unset($params['route']);

    $req = new \WP_REST_Request();
    foreach ($params as $key => $value) {
      $req->set($key, $value);
    }

    $rest = 'VinkiusConnector\\Rest\\Rest';
    $method = $map[$route];

    try {
      $result = $rest::$method($req);
    }
    catch (\Throwable $e) {
      return new JsonResponse(['error' => $e->getMessage()], 500);
    }

    if ($result instanceof \WP_REST_Response) {
      return new JsonResponse($result->data, $result->status ?: 200);
    }

    return new JsonResponse($result);
  }

}
