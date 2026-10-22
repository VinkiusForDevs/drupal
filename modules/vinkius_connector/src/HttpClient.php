<?php

namespace Drupal\vinkius_connector;

use GuzzleHttp\Exception\RequestException;

class HttpClient {
  const TIMEOUT = 30;

  public function request($method, $path, $body = NULL, $bearer = NULL) {
    $base = rtrim(Options::API_BASE_URL, '/');
    $url = $base . (strpos($path, '/') === 0 ? $path : '/' . $path);
    $options = [
      'timeout' => self::TIMEOUT,
      'headers' => [
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
      ],
    ];
    if ($bearer) {
      $options['headers']['Authorization'] = 'Bearer ' . $bearer;
    }
    if ($body !== NULL) {
      $options['json'] = $body;
    }
    try {
      $response = \Drupal::httpClient()->request(strtoupper($method), $url, $options);
      $status = $response->getStatusCode();
      $raw = (string) $response->getBody();
      $decoded = json_decode($raw, TRUE);
      if ($status >= 200 && $status < 300) {
        return Result::ok(is_array($decoded) ? $decoded : ['raw' => $decoded]);
      }
      return Result::error('vc_http_' . $status, is_array($decoded) ? $decoded : ['message' => $raw]);
    }
    catch (RequestException $e) {
      $response = $e->getResponse();
      if ($response) {
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $decoded = json_decode($raw, TRUE);
        return Result::error('vc_http_' . $status, is_array($decoded) ? $decoded : ['message' => $raw]);
      }
      return Result::error('vc_http_curl', ['message' => $e->getMessage()]);
    }
    catch (\Exception $e) {
      return Result::error('vc_http_error', ['message' => $e->getMessage()]);
    }
  }
}