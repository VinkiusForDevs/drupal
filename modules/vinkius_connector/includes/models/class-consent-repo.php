<?php
/**
 * Visitor-consent repository (Drupal port).
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Stores per-visitor agent consents using Drupal's database layer.
 */
final class ConsentRepo {

	const SCOPES = array(
		'content.read',
		'content.write',
		'users.subscribe',
		'media.upload',
	);

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		return 'vinkius_connector_consents';
	}

	/**
	 * Record or update a consent.
	 *
	 * @param string   $external_id Visitor id.
	 * @param int      $wp_user_id  Drupal uid, 0 for anonymous.
	 * @param string[] $scopes      Scopes.
	 * @param string   $purpose     Purpose key.
	 * @param string   $ip          Raw IP (hashed).
	 * @return int
	 */
	public static function record( $external_id, $wp_user_id, array $scopes, $purpose = 'agent_action', $ip = '' ) {
		$db = \Drupal::database();

		$external_id = trim( strip_tags( (string) $external_id ) );

		if ( '' === $external_id ) {
			return 0;
		}

		$valid = array_values( array_intersect( $scopes, self::SCOPES ) );
		$valid = empty( $valid ) ? array( 'content.read' ) : $valid;

		$existing = $db->select( self::table(), 'c' )
			->fields( 'c', array( 'id' ) )
			->condition( 'external_id', $external_id )
			->range( 0, 1 )
			->execute()
			->fetchField();

		$now = gmdate( 'Y-m-d H:i:s' );
		$row = array(
			'uid'        => (int) $wp_user_id,
			'scopes'     => wp_json_encode( $valid ),
			'purpose'    => trim( strip_tags( (string) $purpose ) ),
			'ip_hash'    => self::hash_ip( $ip ),
			'revoked_at' => null,
			'created_at' => $now,
		);

		if ( $existing ) {
			$db->update( self::table() )->fields( $row )->condition( 'id', (int) $existing )->execute();
			return (int) $existing;
		}

		$row['external_id'] = $external_id;

		return (int) $db->insert( self::table() )->fields( $row )->execute();
	}

	/**
	 * Revoke by row id.
	 *
	 * @param int $row_id Row id.
	 * @return bool
	 */
	public static function revoke( $row_id ) {
		$db = \Drupal::database();
		$db->update( self::table() )
			->fields( array( 'revoked_at' => gmdate( 'Y-m-d H:i:s' ) ) )
			->condition( 'id', (int) $row_id )
			->execute();

		return true;
	}

	/**
	 * Find by external id.
	 *
	 * @param string $external_id Visitor id.
	 * @return array|null
	 */
	public static function find( $external_id ) {
		$db = \Drupal::database();
		$row = $db->select( self::table(), 'c' )
			->fields( 'c' )
			->condition( 'external_id', trim( strip_tags( (string) $external_id ) ) )
			->range( 0, 1 )
			->execute()
			->fetchAssoc();

		return $row ?: null;
	}

	/**
	 * List all rows.
	 *
	 * @param int $limit Row cap.
	 * @return array
	 */
	public static function list_all( $limit = 100 ) {
		$db = \Drupal::database();
		$limit = max( 1, min( (int) $limit, 500 ) );

		$rows = $db->select( self::table(), 'c' )
			->fields( 'c' )
			->orderBy( 'created_at', 'DESC' )
			->range( 0, $limit )
			->execute()
			->fetchAll( \PDO::FETCH_ASSOC );

		return $rows ?: array();
	}

	/**
	 * Whether an active consent covers a scope.
	 *
	 * @param string $external_id Visitor id.
	 * @param string $scope       Scope key.
	 * @return bool
	 */
	public static function is_active( $external_id, $scope ) {
		$row = self::find( $external_id );

		if ( ! $row || ! empty( $row['revoked_at'] ) ) {
			return false;
		}

		$scopes = json_decode( (string) $row['scopes'], true );

		return is_array( $scopes ) && in_array( $scope, $scopes, true );
	}

	/**
	 * Revocation token.
	 *
	 * @param int $row_id Row id.
	 * @return string
	 */
	public static function revoke_token( $row_id ) {
		$secret = (string) \VinkiusConnector\Options::get( 'site_secret' );

		return substr( hash_hmac( 'sha256', 'revoke:' . (int) $row_id, $secret ), 0, 32 );
	}

	/**
	 * Resolve a revocation token.
	 *
	 * @param string $token Token.
	 * @return int
	 */
	public static function resolve_revoke_token( $token ) {
		$token = substr( (string) $token, 0, 32 );

		if ( '' === $token ) {
			return 0;
		}

		$secret = (string) \VinkiusConnector\Options::get( 'site_secret' );
		$db = \Drupal::database();
		$rows = $db->select( self::table(), 'c' )
			->fields( 'c', array( 'id' ) )
			->orderBy( 'id', 'ASC' )
			->execute()
			->fetchAll( \PDO::FETCH_ASSOC );

		foreach ( $rows as $r ) {
			$row_token = substr( hash_hmac( 'sha256', 'revoke:' . (int) $r['id'], $secret ), 0, 32 );

			if ( hash_equals( $row_token, $token ) ) {
				return (int) $r['id'];
			}
		}

		return 0;
	}

	/**
	 * Keyed IP hash.
	 *
	 * @param string $ip Raw IP.
	 * @return string
	 */
	private static function hash_ip( $ip ) {
		$ip = trim( (string) $ip );

		if ( '' === $ip ) {
			return '';
		}

		$salt = \Drupal::service( 'private_key' )->get( 'vinkius_connector' );

		return substr( hash_hmac( 'sha256', $ip, $salt ), 0, 64 );
	}
}
