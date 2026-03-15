<?php
/**
 * Plugin Name: Mastodon
 * Plugin URI: https://frank-web.dedyn.io
 * Description: Synchronizes FlatPress entries and comments with Mastodon. <a href="./fp-plugins/mastodon/doc_mastodon.txt" title="Instructions" target="_blank">[Instructions]</a>
 * Version: 1.0.0
 * Author: Fraenkiman
 * Author URI: https://frank-web.dedyn.io
 */

defined('ABSPATH') or define('ABSPATH', dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR);
defined('FP_CONTENT') or exit;

if (!defined('PLUGIN_MASTODON_DIR')) {
	define('PLUGIN_MASTODON_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
}
if (!defined('PLUGIN_MASTODON_STATE_DIR')) {
	define('PLUGIN_MASTODON_STATE_DIR', FP_CONTENT . 'mastodon' . DIRECTORY_SEPARATOR);
}
if (!defined('PLUGIN_MASTODON_STATE_FILE')) {
	define('PLUGIN_MASTODON_STATE_FILE', PLUGIN_MASTODON_STATE_DIR . 'state.json');
}
if (!defined('PLUGIN_MASTODON_LOCK_FILE')) {
	define('PLUGIN_MASTODON_LOCK_FILE', PLUGIN_MASTODON_STATE_DIR . 'sync.lock');
}
if (!defined('PLUGIN_MASTODON_LOG_FILE')) {
	define('PLUGIN_MASTODON_LOG_FILE', PLUGIN_MASTODON_STATE_DIR . 'sync.log');
}
if (!defined('PLUGIN_MASTODON_APP_NAME')) {
	define('PLUGIN_MASTODON_APP_NAME', 'FlatPress Mastodon');
}
if (!defined('PLUGIN_MASTODON_DEFAULT_SYNC_TIME')) {
	define('PLUGIN_MASTODON_DEFAULT_SYNC_TIME', '23:00');
}
if (!defined('PLUGIN_MASTODON_MAX_STATUS_PAGES')) {
	define('PLUGIN_MASTODON_MAX_STATUS_PAGES', 5);
}
if (!defined('PLUGIN_MASTODON_IMPORTED_MEDIA_WIDTH')) {
	define('PLUGIN_MASTODON_IMPORTED_MEDIA_WIDTH', 180);
}

/**
 * @phpstan-type MastodonOptions array{
 *     instance_url:string,
 *     username:string,
 *     password:string,
 *     sync_time:string,
 *     client_id:string,
 *     client_secret:string,
 *     access_token:string,
 *     authorization_code:string,
 *     last_authorize_url:string,
 *     app_scopes?:string,
 *     token_scopes?:string
 * }
 * @phpstan-type MastodonStats array{
 *     imported_entries:int,
 *     updated_entries:int,
 *     exported_entries:int,
 *     updated_remote_entries:int,
 *     imported_comments:int,
 *     exported_comments:int,
 *     updated_remote_comments:int
 * }
 * @phpstan-type MastodonState array{
 *     version:int,
 *     last_run:string,
 *     last_error:string,
 *     last_remote_status_id:string,
 *     entries:array<string, array<string, mixed>>,
 *     entries_remote:array<string, string>,
 *     comments:array<string, array<string, mixed>>,
 *     comments_remote:array<string, string>,
 *     stats:MastodonStats
 * }
 */


/**
 * Return the default plugin option values.
 * @return MastodonOptions
 */
function plugin_mastodon_default_options() {
	return array(
		'instance_url' => '',
		'username' => '',
		'password' => '',
		'sync_time' => PLUGIN_MASTODON_DEFAULT_SYNC_TIME,
		'client_id' => '',
		'client_secret' => '',
		'access_token' => '',
		'authorization_code' => '',
		'last_authorize_url' => ''
	);
}

/**
 * Return the default runtime state structure.
 * @return MastodonState
 */
function plugin_mastodon_default_state() {
	return array(
		'version' => 1,
		'last_run' => '',
		'last_error' => '',
		'last_remote_status_id' => '',
		'entries' => array(),
		'entries_remote' => array(),
		'comments' => array(),
		'comments_remote' => array(),
		'stats' => array(
			'imported_entries' => 0,
			'updated_entries' => 0,
			'exported_entries' => 0,
			'updated_remote_entries' => 0,
			'imported_comments' => 0,
			'exported_comments' => 0,
			'updated_remote_comments' => 0
		)
	);
}

/**
 * Return the OAuth scopes requested by the plugin.
 * @return string
 */
function plugin_mastodon_oauth_scopes() {
	return 'read:accounts read:statuses write:statuses write:media';
}

/**
 * Return a value from the request-local plugin cache.
 * @param string $bucket
 * @param string $key
 * @param bool|null $hit
 * @return mixed
 */
function plugin_mastodon_runtime_cache_get($bucket, $key, &$hit = null) {
	$bucket = (string) $bucket;
	$key = (string) $key;
	if ($hit !== null) {
		$hit = false;
	}
	if (!isset($GLOBALS ['plugin_mastodon_runtime_cache']) || !is_array($GLOBALS ['plugin_mastodon_runtime_cache'])) {
		$GLOBALS ['plugin_mastodon_runtime_cache'] = array();
	}
	if (!isset($GLOBALS ['plugin_mastodon_runtime_cache'] [$bucket]) || !is_array($GLOBALS ['plugin_mastodon_runtime_cache'] [$bucket])) {
		return null;
	}
	if (array_key_exists($key, $GLOBALS ['plugin_mastodon_runtime_cache'] [$bucket])) {
		if ($hit !== null) {
			$hit = true;
		}
		return $GLOBALS ['plugin_mastodon_runtime_cache'] [$bucket] [$key];
	}
	return null;
}

/**
 * Store a value in the request-local plugin cache.
 * @param string $bucket
 * @param string $key
 * @param mixed $value
 * @return mixed
 */
function plugin_mastodon_runtime_cache_set($bucket, $key, $value) {
	$bucket = (string) $bucket;
	$key = (string) $key;
	if (!isset($GLOBALS ['plugin_mastodon_runtime_cache']) || !is_array($GLOBALS ['plugin_mastodon_runtime_cache'])) {
		$GLOBALS ['plugin_mastodon_runtime_cache'] = array();
	}
	if (!isset($GLOBALS ['plugin_mastodon_runtime_cache'] [$bucket]) || !is_array($GLOBALS ['plugin_mastodon_runtime_cache'] [$bucket])) {
		$GLOBALS ['plugin_mastodon_runtime_cache'] [$bucket] = array();
	}
	$GLOBALS ['plugin_mastodon_runtime_cache'] [$bucket] [$key] = $value;
	return $value;
}

/**
 * Clear one request-local plugin cache bucket or the complete cache.
 * @param string $bucket
 * @return void
 */
function plugin_mastodon_runtime_cache_clear($bucket = '') {
	$bucket = (string) $bucket;
	if ($bucket === '') {
		unset($GLOBALS ['plugin_mastodon_runtime_cache']);
		return;
	}
	if (isset($GLOBALS ['plugin_mastodon_runtime_cache'] [$bucket])) {
		unset($GLOBALS ['plugin_mastodon_runtime_cache'] [$bucket]);
	}
}

/**
 * Check whether shared APCu caching is available for the plugin.
 * @return bool
 */
function plugin_mastodon_apcu_enabled() {
	return function_exists('is_apcu_on') && is_apcu_on();
}

/**
 * Build the namespaced APCu key used by this plugin.
 * @param string $suffix
 * @return string
 */
function plugin_mastodon_apcu_cache_key($suffix) {
	$suffix = 'mastodon:' . (string) $suffix;
	if (function_exists('apcu_key')) {
		return apcu_key($suffix);
	}
	return $suffix;
}

/**
 * Fetch a value from APCu through the FlatPress namespace helper.
 * @param string $suffix
 * @param bool|null $hit
 * @return mixed
 */
function plugin_mastodon_apcu_fetch($suffix, &$hit = null) {
	if ($hit !== null) {
		$hit = false;
	}
	if (!plugin_mastodon_apcu_enabled() || !function_exists('apcu_get')) {
		return null;
	}
	return apcu_get('mastodon:' . (string) $suffix, $hit);
}

/**
 * Store a value in APCu through the FlatPress namespace helper.
 * @param string $suffix
 * @param mixed $value
 * @param int $ttl
 * @return bool
 */
function plugin_mastodon_apcu_store($suffix, $value, $ttl) {
	if (!plugin_mastodon_apcu_enabled() || !function_exists('apcu_set')) {
		return false;
	}
	$ttl = max(0, (int) $ttl);
	return (bool) apcu_set('mastodon:' . (string) $suffix, $value, $ttl);
}

/**
 * Delete a value from APCu using the FlatPress namespace key builder.
 * @param string $suffix
 * @return void
 */
function plugin_mastodon_apcu_delete($suffix) {
	if (!plugin_mastodon_apcu_enabled() || !function_exists('apcu_delete')) {
		return;
	}
	$cacheKey = plugin_mastodon_apcu_cache_key($suffix);
	@apcu_delete($cacheKey);
}

/**
 * Read a cheap file metadata snapshot for cache validation.
 * @param string $path
 * @return array{exists:bool,mt:int|null,sz:int|null}
 */
function plugin_mastodon_file_prestat($path) {
	$path = (string) $path;
	clearstatcache(true, $path);
	if (!@file_exists($path)) {
		return array('exists' => false, 'mt' => null, 'sz' => null);
	}
	$mtime = @filemtime($path);
	$size = @filesize($path);
	return array(
		'exists' => true,
		'mt' => ($mtime === false ? null : (int) $mtime),
		'sz' => ($size === false ? null : (int) $size)
	);
}

/**
 * Convert a file metadata snapshot into a stable cache signature.
 * @param array{exists:bool,mt:int|null,sz:int|null} $prestat
 * @return string
 */
function plugin_mastodon_file_prestat_signature($prestat) {
	if (empty($prestat ['exists'])) {
		return 'missing';
	}
	return (isset($prestat ['mt']) && $prestat ['mt'] !== null ? (string) $prestat ['mt'] : 'na') . ':' . (isset($prestat ['sz']) && $prestat ['sz'] !== null ? (string) $prestat ['sz'] : 'na');
}


/**
 * Load the saved plugin options and merge them with defaults.
 * @return MastodonOptions
 */
function plugin_mastodon_get_options() {
	global $fp_config;

	$cached = plugin_mastodon_runtime_cache_get('options', 'normalized', $hit);
	if ($hit && is_array($cached)) {
		return $cached;
	}

	$defaults = plugin_mastodon_default_options();
	$config = isset($fp_config ['plugins'] ['mastodon']) && is_array($fp_config ['plugins'] ['mastodon']) ? $fp_config ['plugins'] ['mastodon'] : array();

	foreach (array('password', 'client_secret', 'access_token', 'authorization_code') as $secretKey) {
		if (isset($config [$secretKey]) && $config [$secretKey] !== '') {
			$config [$secretKey] = plugin_mastodon_secret_decode($config [$secretKey]);
		}
	}

	$options = array_merge($defaults, $config);
	$options ['instance_url'] = plugin_mastodon_normalize_instance_url($options ['instance_url']);
	$options ['sync_time'] = plugin_mastodon_normalize_sync_time($options ['sync_time']);
	return plugin_mastodon_runtime_cache_set('options', 'normalized', $options);
}

/**
 * Persist plugin options.
 * @param MastodonOptions|array<string, mixed> $options
 * @return bool
 */
function plugin_mastodon_save_options($options) {
	$defaults = plugin_mastodon_default_options();
	$merged = array_merge($defaults, is_array($options) ? $options : array());

	foreach (array('instance_url', 'username', 'sync_time', 'last_authorize_url') as $plainKey) {
		plugin_addoption('mastodon', $plainKey, (string) $merged [$plainKey]);
	}

	foreach (array('password', 'client_id', 'client_secret', 'access_token', 'authorization_code') as $secretKey) {
		$value = (string) $merged [$secretKey];
		if ($secretKey === 'client_id') {
			plugin_addoption('mastodon', $secretKey, $value);
		} else {
			plugin_addoption('mastodon', $secretKey, plugin_mastodon_secret_encode($value));
		}
	}

	$result = plugin_saveoptions('mastodon');
	plugin_mastodon_runtime_cache_clear('options');
	if ($result) {
		$merged ['instance_url'] = plugin_mastodon_normalize_instance_url((string) $merged ['instance_url']);
		$merged ['sync_time'] = plugin_mastodon_normalize_sync_time((string) $merged ['sync_time']);
		plugin_mastodon_runtime_cache_set('options', 'normalized', $merged);
	}
	return $result;
}

/**
 * Build the encryption key used for stored secrets.
 * @return string
 */
function plugin_mastodon_secret_key() {
	global $fp_config;
	$key = '';
	if (isset($fp_config ['general'] ['blogid']) && is_string($fp_config ['general'] ['blogid'])) {
		$key = $fp_config ['general'] ['blogid'];
	}
	if ($key === '' && defined('BLOG_BASEURL')) {
		$key = BLOG_BASEURL;
	}
	if ($key === '') {
		$key = 'flatpress-mastodon';
	}
	return hash('sha256', $key, true);
}

/**
 * Encode a secret value before storing it in the configuration.
 * @param string $value
 * @return string
 */
function plugin_mastodon_secret_encode($value) {
	$value = (string) $value;
	if ($value === '') {
		return '';
	}
	if (function_exists('openssl_encrypt') && function_exists('openssl_cipher_iv_length')) {
		$ivLength = openssl_cipher_iv_length('AES-256-CBC');
		if (is_int($ivLength) && $ivLength > 0) {
			$iv = function_exists('random_bytes') ? random_bytes($ivLength) : openssl_random_pseudo_bytes($ivLength);
			$cipher = openssl_encrypt($value, 'AES-256-CBC', plugin_mastodon_secret_key(), OPENSSL_RAW_DATA, $iv);
			if ($cipher !== false) {
				return 'enc:' . base64_encode($iv . $cipher);
			}
		}
	}
	return 'plain:' . base64_encode($value);
}

/**
 * Decode a previously stored secret value.
 * @param string $value
 * @return string
 */
function plugin_mastodon_secret_decode($value) {
	$value = (string) $value;
	if ($value === '') {
		return '';
	}
	if (strpos($value, 'enc:') === 0) {
		$blob = base64_decode(substr($value, 4), true);
		if ($blob === false) {
			return '';
		}
		if (function_exists('openssl_decrypt') && function_exists('openssl_cipher_iv_length')) {
			$ivLength = openssl_cipher_iv_length('AES-256-CBC');
			$iv = substr($blob, 0, $ivLength);
			$cipher = substr($blob, $ivLength);
			$plain = openssl_decrypt($cipher, 'AES-256-CBC', plugin_mastodon_secret_key(), OPENSSL_RAW_DATA, $iv);
			return ($plain === false) ? '' : (string) $plain;
		}
		return '';
	}
	if (strpos($value, 'plain:') === 0) {
		$plain = base64_decode(substr($value, 6), true);
		return ($plain === false) ? '' : (string) $plain;
	}
	return $value;
}

/**
 * Normalize the configured Mastodon instance URL.
 * @param string $url
 * @return string
 */
function plugin_mastodon_normalize_instance_url($url) {
	$url = trim((string) $url);
	if ($url === '') {
		return '';
	}
	if (!preg_match('!^https?://!i', $url)) {
		$url = 'https://' . $url;
	}
	$parts = @parse_url($url);
	if (!is_array($parts) || empty($parts ['host'])) {
		return '';
	}
	$scheme = isset($parts ['scheme']) ? strtolower($parts ['scheme']) : 'https';
	$host = strtolower($parts ['host']);
	$port = isset($parts ['port']) ? ':' . (int) $parts ['port'] : '';
	$path = isset($parts ['path']) ? trim($parts ['path'], '/') : '';
	$normalized = $scheme . '://' . $host . $port;
	if ($path !== '') {
		$normalized .= '/' . $path;
	}
	return rtrim($normalized, '/');
}

/**
 * Normalize the configured daily sync time.
 * @param string $time
 * @return string
 */
function plugin_mastodon_normalize_sync_time($time) {
	$time = trim((string) $time);
	if (!preg_match('/^\d{1,2}:\d{2}$/', $time)) {
		return PLUGIN_MASTODON_DEFAULT_SYNC_TIME;
	}
	list($hour, $minute) = array_map('intval', explode(':', $time, 2));
	if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
		return PLUGIN_MASTODON_DEFAULT_SYNC_TIME;
	}
	return str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
}

/**
 * Ensure that the plugin runtime directory exists.
 * @return bool
 */
function plugin_mastodon_ensure_state_dir() {
	return fs_mkdir(PLUGIN_MASTODON_STATE_DIR);
}

/**
 * Append a line to the plugin sync log.
 * @param string $message
 * @return void
 */
function plugin_mastodon_log($message) {
	plugin_mastodon_ensure_state_dir();
	$line = '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . trim((string) $message) . PHP_EOL;
	@file_put_contents(PLUGIN_MASTODON_LOG_FILE, $line, FILE_APPEND);
}

/**
 * Load the persisted runtime state from disk.
 * @return MastodonState
 */
function plugin_mastodon_state_read() {
	plugin_mastodon_ensure_state_dir();
	$defaults = plugin_mastodon_default_state();
	$prestat = plugin_mastodon_file_prestat(PLUGIN_MASTODON_STATE_FILE);
	if (empty($prestat ['exists'])) {
		plugin_mastodon_runtime_cache_clear('state');
		return $defaults;
	}

	$signature = plugin_mastodon_file_prestat_signature($prestat);
	$cached = plugin_mastodon_runtime_cache_get('state', $signature, $hit);
	if ($hit && is_array($cached)) {
		return $cached;
	}

	$legacySignature = plugin_mastodon_runtime_cache_get('state', '__signature__', $legacyHit);
	if ($legacyHit && $legacySignature !== $signature) {
		plugin_mastodon_runtime_cache_clear('state');
	}

	if (function_exists('io_load_file')) {
		$json = io_load_file(PLUGIN_MASTODON_STATE_FILE, $prestat);
	} else {
		$json = @file_get_contents(PLUGIN_MASTODON_STATE_FILE);
	}
	if (!is_string($json) || trim($json) === '') {
		return $defaults;
	}
	$data = json_decode($json, true);
	if (!is_array($data)) {
		return $defaults;
	}
	$state = plugin_mastodon_state_normalize(array_merge($defaults, $data));
	plugin_mastodon_runtime_cache_set('state', '__signature__', $signature);
	return plugin_mastodon_runtime_cache_set('state', $signature, $state);
}

/**
 * Persist the runtime state to disk.
 * @param MastodonState|array<string, mixed> $state
 * @return bool
 */
function plugin_mastodon_state_write($state) {
	plugin_mastodon_ensure_state_dir();
	$state = plugin_mastodon_state_normalize($state);
	$json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	if (!is_string($json)) {
		return false;
	}
	$payload = $json . PHP_EOL;
	$written = function_exists('io_write_file') ? io_write_file(PLUGIN_MASTODON_STATE_FILE, $payload) : (@file_put_contents(PLUGIN_MASTODON_STATE_FILE, $payload) !== false);
	plugin_mastodon_runtime_cache_clear('state');
	if ($written) {
		$prestat = plugin_mastodon_file_prestat(PLUGIN_MASTODON_STATE_FILE);
		$signature = plugin_mastodon_file_prestat_signature($prestat);
		plugin_mastodon_runtime_cache_set('state', '__signature__', $signature);
		plugin_mastodon_runtime_cache_set('state', $signature, $state);
	}
	return $written;
}

/**
 * Normalize a runtime state array and fill in missing keys.
 * @param MastodonState|array<string, mixed> $state
 * @return MastodonState
 */
function plugin_mastodon_state_normalize($state) {
	$defaults = plugin_mastodon_default_state();
	$state = is_array($state) ? array_merge($defaults, $state) : $defaults;
	foreach (array('entries', 'entries_remote', 'comments', 'comments_remote', 'stats') as $key) {
		if (!isset($state [$key]) || !is_array($state [$key])) {
			$state [$key] = $defaults [$key];
		}
	}
	$state ['stats'] = array_merge($defaults ['stats'], $state ['stats']);
	return $state;
}

/**
 * Build the compound state key used for comment mappings.
 * @param string $entryId
 * @param string $commentId
 * @return string
 */
function plugin_mastodon_state_comment_key($entryId, $commentId) {
	return (string) $entryId . ':' . (string) $commentId;
}

/**
 * Store the mapping between a local entry and a remote status.
 * @param MastodonState|array<string, mixed> $state
 * @param string $localId
 * @param string $remoteId
 * @param string $source
 * @param bool $hash
 * @param string $remoteUrl
 * @param string $remoteUpdatedAt
 * @return void
 */
function plugin_mastodon_state_set_entry_mapping(&$state, $localId, $remoteId, $source, $hash, $remoteUrl, $remoteUpdatedAt) {
	$localId = (string) $localId;
	$remoteId = (string) $remoteId;
	$state ['entries'] [$localId] = array(
		'remote_id' => $remoteId,
		'source' => $source,
		'hash' => $hash,
		'remote_url' => (string) $remoteUrl,
		'remote_updated_at' => (string) $remoteUpdatedAt
	);
	$state ['entries_remote'] [$remoteId] = $localId;
}

/**
 * Store the mapping between a local comment and a remote status.
 * @param MastodonState|array<string, mixed> $state
 * @param string $entryId
 * @param string $commentId
 * @param string $remoteId
 * @param string $source
 * @param bool $hash
 * @param string $remoteUrl
 * @param string $remoteUpdatedAt
 * @param string $parentCommentId
 * @param string $inReplyToRemoteId
 * @return void
 */
function plugin_mastodon_state_set_comment_mapping(&$state, $entryId, $commentId, $remoteId, $source, $hash, $remoteUrl, $remoteUpdatedAt, $parentCommentId = '', $inReplyToRemoteId = '') {
	$key = plugin_mastodon_state_comment_key($entryId, $commentId);
	$state ['comments'] [$key] = array(
		'entry_id' => (string) $entryId,
		'comment_id' => (string) $commentId,
		'remote_id' => (string) $remoteId,
		'source' => (string) $source,
		'hash' => (string) $hash,
		'remote_url' => (string) $remoteUrl,
		'remote_updated_at' => (string) $remoteUpdatedAt,
		'parent_comment_id' => (string) $parentCommentId,
		'in_reply_to_remote_id' => (string) $inReplyToRemoteId
	);
	$state ['comments_remote'] [(string) $remoteId] = array(
		'entry_id' => (string) $entryId,
		'comment_id' => (string) $commentId
	);
}

/**
 * Return mapping metadata for a local entry.
 * @param MastodonState|array<string, mixed> $state
 * @param string $localId
 * @return array<string, mixed>
 */
function plugin_mastodon_state_get_entry_meta($state, $localId) {
	$localId = (string) $localId;
	return isset($state ['entries'] [$localId]) && is_array($state ['entries'] [$localId]) ? $state ['entries'] [$localId] : array();
}

/**
 * Return mapping metadata for a local comment.
 * @param MastodonState|array<string, mixed> $state
 * @param string $entryId
 * @param string $commentId
 * @return array<string, mixed>
 */
function plugin_mastodon_state_get_comment_meta($state, $entryId, $commentId) {
	$key = plugin_mastodon_state_comment_key($entryId, $commentId);
	return isset($state ['comments'] [$key]) && is_array($state ['comments'] [$key]) ? $state ['comments'] [$key] : array();
}

/**
 * Parse an ISO date/time string into FlatPress date format.
 * @param string $value
 * @return string
 */
function plugin_mastodon_parse_iso_datetime($value) {
	$value = trim((string) $value);
	if ($value === '') {
		return '';
	}
	try {
		$date = new DateTime($value);
		return $date->format('Y-m-d H:i:s');
	} catch (Exception $e) {
		return '';
	}
}

/**
 * Parse an ISO date/time value into a Unix timestamp.
 * @param string $value
 * @return int
 */
function plugin_mastodon_parse_iso_timestamp($value) {
	$value = trim((string) $value);
	if ($value === '') {
		return 0;
	}
	if (ctype_digit($value)) {
		return (int) $value;
	}
	try {
		$date = new DateTime($value);
		return (int) $date->format('U');
	} catch (Exception $e) {
		$timestamp = @strtotime($value);
		return $timestamp === false ? 0 : (int) $timestamp;
	}
}

/**
 * Resolve the best timestamp for a remote Mastodon status.
 * @param array<string, mixed> $remoteStatus
 * @return int
 */
function plugin_mastodon_remote_status_timestamp($remoteStatus) {
	$remoteStatus = is_array($remoteStatus) ? $remoteStatus : array();
	foreach (array('created_at', 'published', 'date', 'timestamp', 'edited_at') as $field) {
		if (empty($remoteStatus [$field])) {
			continue;
		}
		$timestamp = plugin_mastodon_parse_iso_timestamp($remoteStatus [$field]);
		if ($timestamp > 0) {
			return $timestamp;
		}
	}
	return time();
}

/**
 * Return the normalized visibility of a remote Mastodon status.
 * @param array<string, mixed> $remoteStatus
 * @return string
 */
function plugin_mastodon_remote_status_visibility($remoteStatus) {
	$remoteStatus = is_array($remoteStatus) ? $remoteStatus : array();
	if (empty($remoteStatus ['visibility'])) {
		return '';
	}
	return strtolower(trim((string) $remoteStatus ['visibility']));
}

/**
 * Determine whether a remote Mastodon status may be imported.
 * @param array<string, mixed> $remoteStatus
 * @return bool
 */
function plugin_mastodon_remote_status_is_importable($remoteStatus) {
	$visibility = plugin_mastodon_remote_status_visibility($remoteStatus);
	if ($visibility === 'direct' || $visibility === 'private') {
		return false;
	}
	return true;
}

/**
 * Return the comment fields that may contain a parent reference.
 * @return array<int, string>
 */
function plugin_mastodon_comment_parent_fields() {
	return array('replyto', 'reply_to', 'parent', 'parent_id', 'in_reply_to', 'in_reply_to_id', 'replytoid', 'reply_to_id', 'inreplyto');
}

/**
 * Normalize a stored local comment parent identifier.
 * @param string $value
 * @return string
 */
function plugin_mastodon_normalize_comment_parent_id($value) {
	$value = trim((string) $value);
	if ($value === '') {
		return '';
	}
	if (preg_match('/^comment\d{6}-\d{6}$/', $value)) {
		return $value;
	}
	return '';
}

/**
 * Detect the local parent comment identifier from comment data.
 * @param string $entryId
 * @param array<string, mixed> $comment
 * @return string
 */
function plugin_mastodon_detect_local_comment_parent_id($entryId, $comment) {
	$comment = is_array($comment) ? $comment : array();
	foreach (plugin_mastodon_comment_parent_fields() as $field) {
		if (empty($comment [$field])) {
			continue;
		}
		$candidate = plugin_mastodon_normalize_comment_parent_id($comment [$field]);
		if ($candidate !== '' && comment_exists($entryId, $candidate)) {
			return $candidate;
		}
	}
	return '';
}

/**
 * Resolve the remote reply target for a local comment export.
 * @param MastodonState|array<string, mixed> $state
 * @param string $entryId
 * @param array<string, mixed> $comment
 * @param string $defaultRemoteId
 * @return array{remote_id:string, parent_comment_id:string}
 */
function plugin_mastodon_resolve_comment_reply_target($state, $entryId, $comment, $defaultRemoteId) {
	$parentCommentId = plugin_mastodon_detect_local_comment_parent_id($entryId, $comment);
	if ($parentCommentId !== '') {
		$parentMeta = plugin_mastodon_state_get_comment_meta($state, $entryId, $parentCommentId);
		if (!empty($parentMeta ['remote_id'])) {
			return array(
				'remote_id' => (string) $parentMeta ['remote_id'],
				'parent_comment_id' => $parentCommentId
			);
		}
	}
	return array(
		'remote_id' => (string) $defaultRemoteId,
		'parent_comment_id' => $parentCommentId
	);
}

/**
 * Guess a subject line from imported plain text.
 * @param string $text
 * @return string
 */
function plugin_mastodon_guess_subject($text) {
	$text = plugin_mastodon_plain_text_from_bbcode($text);
	if ($text === '') {
		return 'Mastodon';
	}

	$lines = preg_split("/\n+/u", $text);
	$candidate = '';
	foreach ($lines as $line) {
		$line = trim((string) $line);
		if ($line === '') {
			continue;
		}
		if ($candidate === '' && plugin_mastodon_subject_line_is_noise($line)) {
			continue;
		}
		$candidate = $line;
		break;
	}

	if ($candidate === '' && !empty($lines)) {
		$candidate = trim((string) reset($lines));
	}
	if ($candidate === '') {
		return 'Mastodon';
	}

	$candidate = preg_replace('/\s+/u', ' ', $candidate);
	if (function_exists('mb_substr')) {
		$candidate = mb_substr($candidate, 0, 72, 'UTF-8');
	} else {
		$candidate = substr($candidate, 0, 72);
	}

	return trim((string) $candidate);
}

/**
 * Decode HTML entities using the plugin defaults.
 * @param string $text
 * @return string
 */
function plugin_mastodon_html_entity_decode($text) {
	$text = (string) $text;
	return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Return the absolute base URL of the current FlatPress installation.
 * @return string
 */
function plugin_mastodon_blog_base_url() {
	global $fp_config;

	$url = '';
	if (isset($fp_config ['general'] ['www']) && is_string($fp_config ['general'] ['www'])) {
		$url = trim($fp_config ['general'] ['www']);
	} elseif (defined('BLOG_BASEURL')) {
		$url = (string) BLOG_BASEURL;
	}

	if ($url === '') {
		$scheme = 'http';
		if (!empty($_SERVER ['HTTPS']) && strtolower((string) $_SERVER ['HTTPS']) !== 'off') {
			$scheme = 'https';
		}
		$host = isset($_SERVER ['HTTP_HOST']) ? trim((string) $_SERVER ['HTTP_HOST']) : 'localhost';
		$script = isset($_SERVER ['SCRIPT_NAME']) ? (string) $_SERVER ['SCRIPT_NAME'] : '/';
		$basePath = rtrim(str_replace('\\', '/', dirname($script)), '/');
		$url = $scheme . '://' . $host . ($basePath !== '' ? $basePath . '/' : '/');
	}

	$parts = @parse_url($url);
	if (!is_array($parts) || empty($parts ['host'])) {
		return '';
	}

	$scheme = isset($parts ['scheme']) ? strtolower((string) $parts ['scheme']) : 'https';
	$host = strtolower((string) $parts ['host']);
	$port = isset($parts ['port']) ? ':' . (int) $parts ['port'] : '';
	$path = isset($parts ['path']) ? trim((string) $parts ['path'], '/') : '';

	return $scheme . '://' . $host . $port . ($path !== '' ? '/' . $path : '') . '/';
}

/**
 * Extract the URL token from a BBCode or attribute fragment.
 * @param string $value
 * @return string
 */
function plugin_mastodon_extract_url_token($value) {
	$value = plugin_mastodon_html_entity_decode(strip_tags((string) $value));
	$value = trim($value);
	if ($value === '') {
		return '';
	}

	$value = trim($value, " \t\n\r\0\x0B\"'");
	$parts = preg_split('/\s+/', $value);
	return isset($parts [0]) ? trim((string) $parts [0]) : '';
}

/**
 * Convert a URL or path into an absolute URL when possible.
 * @param string $url
 * @return string
 */
function plugin_mastodon_absolute_url($url) {
	$url = plugin_mastodon_extract_url_token($url);
	if ($url === '') {
		return '';
	}

	if (preg_match('!^(https?://|mailto:)!i', $url)) {
		return $url;
	}

	$base = plugin_mastodon_blog_base_url();
	if ($base === '') {
		return $url;
	}

	$baseParts = @parse_url($base);
	if (!is_array($baseParts) || empty($baseParts ['host'])) {
		return $url;
	}

	$scheme = isset($baseParts ['scheme']) ? strtolower((string) $baseParts ['scheme']) : 'https';
	$host = strtolower((string) $baseParts ['host']);
	$port = isset($baseParts ['port']) ? ':' . (int) $baseParts ['port'] : '';

	if (strpos($url, '//') === 0) {
		return $scheme . ':' . $url;
	}

	if (strpos($url, '/') === 0) {
		return $scheme . '://' . $host . $port . $url;
	}

	$url = preg_replace('!^\./!u', '', $url);
	return $base . ltrim((string) $url, '/');
}

/**
 * Return a localized plugin string or a provided fallback.
 * @param string $key
 * @param string $default
 * @return string
 */
function plugin_mastodon_lang_string($key, $default) {
	static $strings = null;

	if ($strings === null) {
		$strings = array();
		if (function_exists('lang_load')) {
			$lang = lang_load('plugin:mastodon');
			if (is_array($lang) && isset($lang ['admin'] ['plugin'] ['mastodon']) && is_array($lang ['admin'] ['plugin'] ['mastodon'])) {
				$strings = $lang ['admin'] ['plugin'] ['mastodon'];
			}
		}
	}

	return isset($strings [$key]) && is_string($strings [$key]) && $strings [$key] !== '' ? $strings [$key] : (string) $default;
}

/**
 * Convert an emoticon HTML entity into a Unicode character.
 * @param string $value
 * @return string
 */
function plugin_mastodon_emoticon_entity_to_unicode($value) {
	$value = trim((string) $value);
	if ($value === '') {
		return '';
	}

	return html_entity_decode($value, defined('ENT_HTML5') ? ENT_QUOTES | ENT_HTML5 : ENT_QUOTES, 'UTF-8');
}

/**
 * Return the FlatPress emoticon-to-Unicode lookup map.
 * @return array<string, string>
 */
function plugin_mastodon_emoticon_map() {
	static $map = null;

	if ($map !== null) {
		return $map;
	}

	$map = array(
		':smile:' => '😄',
		':smiley:' => '😃',
		':wink:' => '😉',
		':blush:' => '😊',
		':grin:' => '😁',
		':smirk:' => '😏',
		':heart_eyes:' => '😍',
		':sunglasses:' => '😎',
		':laughing:' => '😆',
		':joy:' => '😂',
		':neutral_face:' => '😐',
		':flushed:' => '😳',
		':hushed:' => '😮',
		':dizzy_face:' => '😵',
		':cry:' => '😢',
		':persevere:' => '😣',
		':worried:' => '😟',
		':angry:' => '😠',
		':mag:' => '🔍',
		':hot_beverage:' => '☕',
		':exclamation:' => '❗',
		':question:' => '❓'
	);

	if (isset($GLOBALS ['plugin_emoticons']) && is_array($GLOBALS ['plugin_emoticons'])) {
		$detected = array();
		foreach ($GLOBALS ['plugin_emoticons'] as $shortcode => $entity) {
			$shortcode = trim((string) $shortcode);
			$unicode = plugin_mastodon_emoticon_entity_to_unicode($entity);
			if ($shortcode !== '' && $unicode !== '') {
				$detected [$shortcode] = $unicode;
			}
		}
		if (!empty($detected)) {
			$map = $detected;
		}
	}

	return $map;
}

/**
 * Replace FlatPress emoticon shortcodes with Unicode glyphs.
 * @param string $text
 * @return string
 */
function plugin_mastodon_replace_emoticon_shortcodes_with_unicode($text) {
	$text = (string) $text;
	$map = plugin_mastodon_emoticon_map();
	return empty($map) ? $text : strtr($text, $map);
}

/**
 * Replace Unicode emoticons with FlatPress shortcodes.
 * @param string $text
 * @return string
 */
function plugin_mastodon_replace_unicode_emoticons_with_shortcodes($text) {
	$text = (string) $text;
	$map = plugin_mastodon_emoticon_map();
	if (empty($map)) {
		return $text;
	}

	$reverse = array();
	foreach ($map as $shortcode => $unicode) {
		if ($unicode !== '') {
			$reverse [$unicode] = $shortcode;
		}
	}

	return empty($reverse) ? $text : strtr($text, $reverse);
}

/**
 * Determine whether a host name resolves to a public endpoint.
 * @param string $host
 * @return bool
 */
function plugin_mastodon_is_public_host($host) {
	$host = strtolower(trim((string) $host, ". \t\n\r\0\x0B"));
	if ($host === '') {
		return false;
	}

	if ($host === 'localhost' || substr($host, -10) === '.localhost' || substr($host, -6) === '.local' || substr($host, -5) === '.test' || substr($host, -8) === '.invalid' || substr($host, -8) === '.example') {
		return false;
	}

	$ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : '';
	if ($ip !== '') {
		return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
	}

	return strpos($host, '.') !== false;
}

/**
 * Return a Mastodon-safe public URL or an empty string.
 * @param string $url
 * @return string
 */
function plugin_mastodon_public_url_for_mastodon($url) {
	$url = plugin_mastodon_absolute_url($url);
	if ($url === '' || preg_match('!^mailto:!i', $url)) {
		return '';
	}

	$parts = @parse_url($url);
	if (!is_array($parts) || empty($parts ['host']) || !plugin_mastodon_is_public_host($parts ['host'])) {
		return '';
	}

	return $url;
}

/**
 * Convert FlatPress BBCode into plain text for Mastodon export.
 * @param string $text
 * @return string
 */
function plugin_mastodon_plain_text_from_bbcode($text) {
	$text = (string) $text;
	if ($text === '') {
		return '';
	}

	$text = preg_replace_callback(
		'!\[url=([^\]]+)\](.*?)\[/url\]!is',
		function ($matches) {
			$label = trim(plugin_mastodon_html_entity_decode(strip_tags((string) $matches [2])));
			if ($label !== '') {
				return $label;
			}
			return plugin_mastodon_absolute_url($matches [1]);
		},
		$text
	);
	$text = preg_replace_callback(
		'!\[url\](.*?)\[/url\]!is',
		function ($matches) {
			return plugin_mastodon_absolute_url($matches [1]);
		},
		$text
	);
	$text = preg_replace('!\[img\](.*?)\[/img\]!is', '', $text);
	$text = preg_replace('!\[\s*img\b[^\]]*\]!is', '', $text);
	$text = preg_replace('!\[\s*gallery\b[^\]]*\]!is', '', $text);
	$text = preg_replace('!\[(\/?)(b|i|u|h1|h2|h3|h4|list|\*|left|right|center|justify|color|size|font|flash|youtube|video|audio|mail|html|raw|more|table|tr|td|th|caption|tbody|thead|tfoot|quote|code)(=[^\]]*)?\]!is', '', $text);
	$text = strip_tags($text);
	$text = plugin_mastodon_html_entity_decode($text);
	$text = str_replace(array("\r\n", "\r"), "\n", $text);
	$text = preg_replace("/[ \t]+\n/u", "\n", $text);
	$text = preg_replace("/\n{3,}/", "\n\n", $text);

	return trim((string) $text);
}

/**
 * Determine whether an extracted line should be ignored as a subject.
 * @param mixed $line
 * @return bool
 */
function plugin_mastodon_subject_line_is_noise($line) {
	$line = trim((string) $line);
	if ($line === '') {
		return true;
	}

	if (preg_match('!^(https?://\S+|www\.\S+)$!iu', $line)) {
		return true;
	}
	if (preg_match('/^(?:@[\pL\pN._-]+(?:@[\pL\pN.-]+)?\s*)+$/u', $line)) {
		return true;
	}
	if (preg_match('/^@[^\s]+(?:\s+[A-Za-z0-9.-]+\.[A-Za-z]{2,})$/u', $line)) {
		return true;
	}
	if (preg_match('/^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/u', $line)) {
		return true;
	}

	return false;
}

/**
 * Determine whether two host names belong to the same domain family.
 * @param mixed $left
 * @param mixed $right
 * @return bool
 */
function plugin_mastodon_domains_match($left, $right) {
	$left = strtolower(trim((string) $left, ". \t\n\r\0\x0B"));
	$right = strtolower(trim((string) $right, ". \t\n\r\0\x0B"));
	if ($left === '' || $right === '') {
		return false;
	}
	return $left === $right || substr($left, -strlen($right) - 1) === '.' . $right || substr($right, -strlen($left) - 1) === '.' . $left;
}

/**
 * Clean imported text before saving it to FlatPress.
 * @param string $text
 * @return string
 */
function plugin_mastodon_cleanup_imported_text($text) {
	$text = str_replace(array("\r\n", "\r"), "\n", (string) $text);
	$text = preg_replace("/\n{3,}/", "\n\n", $text);
	$lines = explode("\n", $text);
	$clean = array();

	foreach ($lines as $line) {
		$line = preg_replace('/[ \t]+/u', ' ', rtrim((string) $line));
		$trimmed = trim((string) $line);

		if ($trimmed !== '' && !empty($clean)) {
			$lastIndex = null;
			for ($i = count($clean) - 1; $i >= 0; $i--) {
				if (trim((string) $clean [$i]) !== '') {
					$lastIndex = $i;
					break;
				}
			}

			if ($lastIndex !== null) {
				$previous = trim((string) $clean [$lastIndex]);

				if (preg_match('!^\[url=([^\]]+)\](@[^\[]+)\[/url\]$!u', $previous, $matches) && preg_match('/^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/u', $trimmed)) {
					$host = @parse_url(plugin_mastodon_absolute_url($matches [1]), PHP_URL_HOST);
					if (is_string($host) && plugin_mastodon_domains_match($host, $trimmed) && strpos($matches [2], '@' . $trimmed) === false) {
						$clean [$lastIndex] = '[url=' . plugin_mastodon_absolute_url($matches [1]) . ']' . $matches [2] . '@' . $trimmed . ' [/url]';
						continue;
					}
				}

				if (preg_match('!^\[url\](https?://[^\[]+)\[/url\]$!u', $previous, $matches) && preg_match('/^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/u', $trimmed)) {
					$host = @parse_url(plugin_mastodon_absolute_url($matches [1]), PHP_URL_HOST);
					if (is_string($host) && plugin_mastodon_domains_match($host, $trimmed)) {
						continue;
					}
				}
			}
		}

		if ($trimmed === '' && (!empty($clean) && end($clean) === '')) {
			continue;
		}

		$clean [] = $trimmed;
	}

	return trim(implode("\n", $clean));
}

/**
 * Convert DOM child nodes into FlatPress BBCode text.
 * @param DOMNode $node
 * @return string
 */
function plugin_mastodon_dom_children_to_flatpress($node) {
	$output = '';
	if (!$node || !$node->hasChildNodes()) {
		return $output;
	}

	foreach ($node->childNodes as $child) {
		$output .= plugin_mastodon_dom_node_to_flatpress($child);
	}

	return $output;
}

/**
 * Convert a single DOM node into FlatPress BBCode text.
 * @param DOMNode $node
 * @return string
 */
function plugin_mastodon_dom_node_to_flatpress($node) {
	if (!$node) {
		return '';
	}

	if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
		return plugin_mastodon_html_entity_decode($node->nodeValue);
	}

	if ($node->nodeType !== XML_ELEMENT_NODE) {
		return '';
	}

	$name = strtolower((string) $node->nodeName);
	if (in_array($name, array('script', 'style', 'template'), true)) {
		return '';
	}

	if ($name === 'br') {
		return "\n";
	}
	if ($name === 'hr') {
		return "\n----\n";
	}
	if ($name === 'img') {
		$alt = trim((string) $node->getAttribute('alt'));
		$class = ' ' . strtolower(trim((string) $node->getAttribute('class'))) . ' ';
		if ($alt !== '' && preg_match('/^:[A-Za-z0-9_+\-]+:$/', $alt)) {
			return $alt;
		}
		if ($alt !== '' && (strpos($class, ' emoji ') !== false || strpos($class, ' emojione ') !== false || strpos($class, ' custom-emoji ') !== false)) {
			return $alt;
		}
		return '';
	}
	if ($name === 'blockquote') {
		$inner = trim(plugin_mastodon_dom_children_to_flatpress($node));
		return $inner === '' ? '' : "\n[quote]\n" . $inner . "\n[/quote]\n";
	}
	if ($name === 'strong' || $name === 'b') {
		return '[b]' . trim(plugin_mastodon_dom_children_to_flatpress($node)) . '[/b]';
	}
	if ($name === 'em' || $name === 'i') {
		return '[i]' . trim(plugin_mastodon_dom_children_to_flatpress($node)) . '[/i]';
	}
	if ($name === 'pre') {
		$inner = plugin_mastodon_html_entity_decode($node->textContent);
		$inner = str_replace(array("\r\n", "\r"), "\n", $inner);
		return "\n[code]\n" . trim($inner) . "\n[/code]\n";
	}
	if ($name === 'code') {
		$parentName = ($node->parentNode && isset($node->parentNode->nodeName)) ? strtolower((string) $node->parentNode->nodeName) : '';
		if ($parentName === 'pre') {
			return plugin_mastodon_html_entity_decode($node->textContent);
		}
		return '[code]' . trim(plugin_mastodon_html_entity_decode($node->textContent)) . '[/code]';
	}
	if ($name === 'ul' || $name === 'ol') {
		$items = trim(plugin_mastodon_dom_children_to_flatpress($node));
		return $items === '' ? '' : "\n[list]\n" . $items . "\n[/list]\n";
	}
	if ($name === 'li') {
		$item = trim(plugin_mastodon_dom_children_to_flatpress($node));
		return $item === '' ? '' : '[*] ' . $item . "\n";
	}
	if ($name === 'a') {
		$href = plugin_mastodon_absolute_url($node->getAttribute('href'));
		$label = plugin_mastodon_plain_text_from_bbcode(plugin_mastodon_dom_children_to_flatpress($node));
		$label = trim(preg_replace('/\s+/u', ' ', $label));

		if ($href === '') {
			return $label;
		}
		if ($label === '') {
			return '[url]' . $href . '[/url]';
		}

		$normalizedLabel = preg_replace('!^https?://!iu', '', rtrim($label, '/'));
		$normalizedHref = preg_replace('!^https?://!iu', '', rtrim($href, '/'));
		if ($normalizedLabel === $normalizedHref) {
			return '[url]' . $href . '[/url]';
		}

		return '[url=' . $href . ']' . $label . '[/url]';
	}
	if (in_array($name, array('p', 'div', 'section', 'article', 'header', 'footer'), true)) {
		$inner = trim(plugin_mastodon_dom_children_to_flatpress($node));
		return $inner === '' ? '' : $inner . "\n\n";
	}
	if (in_array($name, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6'), true)) {
		$inner = trim(plugin_mastodon_dom_children_to_flatpress($node));
		return $inner === '' ? '' : '[b]' . $inner . '[/b]' . "\n\n";
	}

	return plugin_mastodon_dom_children_to_flatpress($node);
}

/**
 * Return the public URL for a FlatPress entry.
 * @param string $entryId
 * @param array<string, mixed> $entry
 * @return string
 */
function plugin_mastodon_public_entry_url($entryId, $entry) {
	$entryId = (string) $entryId;
	if ($entryId === '') {
		return '';
	}

	$entry = is_array($entry) ? $entry : array();
	$currentPost = isset($GLOBALS ['post']) ? $GLOBALS ['post'] : null;
	$hadPost = array_key_exists('post', $GLOBALS);

	$GLOBALS ['post'] = $entry;
	$link = function_exists('get_permalink') ? get_permalink($entryId) : '';
	if ($hadPost) {
		$GLOBALS ['post'] = $currentPost;
	} else {
		unset($GLOBALS ['post']);
	}

	return plugin_mastodon_absolute_url($link);
}

/**
 * Return the public comments URL for a FlatPress entry.
 * @param string $entryId
 * @param array<string, mixed> $entry
 * @return string
 */
function plugin_mastodon_public_comments_url($entryId, $entry) {
	$entryId = (string) $entryId;
	if ($entryId === '') {
		return '';
	}

	$entry = is_array($entry) ? $entry : array();
	$currentPost = isset($GLOBALS ['post']) ? $GLOBALS ['post'] : null;
	$hadPost = array_key_exists('post', $GLOBALS);

	$GLOBALS ['post'] = $entry;
	$link = function_exists('get_comments_link') ? get_comments_link($entryId) : '';
	if ($hadPost) {
		$GLOBALS ['post'] = $currentPost;
	} else {
		unset($GLOBALS ['post']);
	}

	return plugin_mastodon_absolute_url($link);
}

/**
 * Convert Mastodon HTML content into FlatPress BBCode.
 * @param string $html
 * @return string
 */
function plugin_mastodon_mastodon_html_to_flatpress($html) {
	$html = (string) $html;
	if ($html === '') {
		return '';
	}

	$text = '';
	if (class_exists('DOMDocument')) {
		$internalErrors = function_exists('libxml_use_internal_errors') ? libxml_use_internal_errors(true) : false;
		$doc = new DOMDocument('1.0', 'UTF-8');
		$flags = 0;
		if (defined('LIBXML_NONET')) {
			$flags |= LIBXML_NONET;
		}
		$loaded = @$doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>' . $html . '</body></html>', $flags);
		if ($loaded) {
			$body = $doc->getElementsByTagName('body')->item(0);
			if ($body) {
				$text = trim(plugin_mastodon_dom_children_to_flatpress($body));
			}
		}
		if (function_exists('libxml_clear_errors')) {
			libxml_clear_errors();
		}
		if (function_exists('libxml_use_internal_errors')) {
			libxml_use_internal_errors($internalErrors);
		}
	}

	if ($text === '') {
		$replacements = array(
			'!<br\s*/?>!i' => "\n",
			'!</p>\s*<p[^>]*>!i' => "\n\n",
			'!<p[^>]*>!i' => '',
			'!</p>!i' => '',
			'!<blockquote\b[^>]*>!i' => '[quote]',
			'!</blockquote>!i' => '[/quote]',
			'!<(strong|b)\b[^>]*>!i' => '[b]',
			'!</(strong|b)>!i' => '[/b]',
			'!<(em|i)\b[^>]*>!i' => '[i]',
			'!</(em|i)>!i' => '[/i]',
			'!<pre\b[^>]*><code\b[^>]*>!i' => '[code]',
			'!</code></pre>!i' => '[/code]',
			'!<pre\b[^>]*>!i' => '[code]',
			'!</pre>!i' => '[/code]',
			'!<code\b[^>]*>!i' => '[code]',
			'!</code>!i' => '[/code]',
			'!<li\b[^>]*>!i' => "\n[*] ",
			'!</li>!i' => '',
			'!<ul\b[^>]*>!i' => "\n[list]\n",
			'!</ul>!i' => "\n[/list]\n",
			'!<ol\b[^>]*>!i' => "\n[list]\n",
			'!</ol>!i' => "\n[/list]\n"
		);

		foreach ($replacements as $pattern => $replacement) {
			$html = preg_replace($pattern, $replacement, $html);
		}

		$html = preg_replace_callback(
			'!<img\s[^>]*alt=(["\'])(.*?)\1[^>]*>!is',
			function ($matches) {
				$alt = trim(plugin_mastodon_html_entity_decode((string) $matches [2]));
				return preg_match('/^:[A-Za-z0-9_+\-]+:$/', $alt) ? $alt : '';
			},
			$html
		);

		$text = preg_replace_callback(
			'!<a\s[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)</a>!is',
			function ($matches) {
				$href = plugin_mastodon_absolute_url($matches [2]);
				$label = trim(plugin_mastodon_html_entity_decode(strip_tags($matches [3])));
				if ($href === '') {
					return $label;
				}
				if ($label === '') {
					return '[url]' . $href . '[/url]';
				}
				return '[url=' . $href . ']' . $label . '[/url]';
			},
			$html
		);

		$text = strip_tags($text);
		$text = plugin_mastodon_html_entity_decode($text);
	}

	$text = plugin_mastodon_replace_unicode_emoticons_with_shortcodes($text);
	$text = str_replace(array("\r\n", "\r"), "\n", $text);
	$text = preg_replace("/[ \t]+\n/u", "\n", $text);
	$text = preg_replace("/\n{3,}/", "\n\n", $text);
	$text = plugin_mastodon_cleanup_imported_text($text);

	return trim((string) $text);
}

/**
 * Convert FlatPress content into Mastodon-ready plain text.
 * @param string $text
 * @return string
 */
function plugin_mastodon_flatpress_to_mastodon($text) {
	$text = plugin_mastodon_replace_emoticon_shortcodes_with_unicode((string) $text);
	if ($text === '') {
		return '';
	}

	$text = preg_replace_callback(
		'!<a\s[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)</a>!is',
		function ($matches) {
			$url = plugin_mastodon_public_url_for_mastodon($matches [2]);
			$label = trim(plugin_mastodon_html_entity_decode(strip_tags((string) $matches [3])));
			if ($url === '') {
				return $label;
			}
			if ($label === '' || $label === $url) {
				return $url;
			}
			return $label . ' ' . $url;
		},
		$text
	);

	$text = preg_replace_callback(
		'!\[url=([^\]]+)\](.*?)\[/url\]!is',
		function ($matches) {
			$url = plugin_mastodon_public_url_for_mastodon($matches [1]);
			$label = trim(plugin_mastodon_html_entity_decode(strip_tags((string) $matches [2])));
			if ($url === '') {
				return $label;
			}
			if ($label === '' || $label === $url) {
				return $url;
			}
			return $label . ' ' . $url;
		},
		$text
	);

	$text = preg_replace_callback(
		'!\[url\](.*?)\[/url\]!is',
		function ($matches) {
			return plugin_mastodon_public_url_for_mastodon($matches [1]);
		},
		$text
	);

	$text = preg_replace_callback(
		'!\[img\](.*?)\[/img\]!is',
		function ($matches) {
			return plugin_mastodon_public_url_for_mastodon($matches [1]);
		},
		$text
	);

	$text = preg_replace('!\[\s*img\b[^\]]*\]!is', '', $text);
	$text = preg_replace('!\[\s*gallery\b[^\]]*\]!is', '', $text);

	$text = preg_replace_callback(
		'!\[list(?:=[^\]]*)?\](.*?)\[/list\]!is',
		function ($matches) {
			$body = str_replace(array("\r\n", "\r"), "\n", (string) $matches [1]);
			$body = preg_replace('/\[\*\]\s*/iu', "\n• ", $body);
			return "\n" . trim($body) . "\n";
		},
		$text
	);

	$text = preg_replace_callback(
		'!\[quote\](.*?)\[/quote\]!is',
		function ($matches) {
			$body = trim((string) $matches [1]);
			$body = preg_replace('/^\s+/m', '', $body);
			$body = preg_replace('/^/m', '> ', $body);
			return "\n" . $body . "\n";
		},
		$text
	);

	$text = preg_replace_callback(
		'!\[code\](.*?)\[/code\]!is',
		function ($matches) {
			return "\n```\n" . trim((string) $matches [1]) . "\n```\n";
		},
		$text
	);

	$text = preg_replace_callback(
		'!\[(h1|h2|h3|h4)\](.*?)\[/\1\]!is',
		function ($matches) {
			$heading = trim(plugin_mastodon_html_entity_decode(strip_tags((string) $matches [2])));
			return $heading === '' ? '' : "\n" . $heading . "\n";
		},
		$text
	);

	$text = preg_replace('!\[more\]!is', "\n", $text);
	$text = preg_replace('!\[(\/?)(b|i|u|left|right|center|justify|color|size|font|flash|youtube|video|audio|mail|html|raw|table|tr|td|th|caption|tbody|thead|tfoot)(=[^\]]*)?\]!is', '', $text);
	$text = strip_tags($text);
	$text = plugin_mastodon_html_entity_decode($text);
	$text = str_replace(array("\r\n", "\r"), "\n", $text);
	$text = preg_replace("/[ \t]+\n/u", "\n", $text);
	$text = preg_replace("/\n{3,}/", "\n\n", $text);

	return trim((string) $text);
}

/**
 * Limit text to a maximum number of characters.
 * @param string $text
 * @param int $limit
 * @return string
 */
function plugin_mastodon_limit_text($text, $limit) {
	$text = trim((string) $text);
	$limit = (int) $limit;
	if ($limit <= 0) {
		return $text;
	}
	$length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
	if ($length <= $limit) {
		return $text;
	}
	$suffix = '…';
	if (function_exists('mb_substr')) {
		return rtrim(mb_substr($text, 0, $limit - 1, 'UTF-8')) . $suffix;
	}
	return rtrim(substr($text, 0, $limit - 1)) . $suffix;
}

/**
 * Build a change-detection hash for a FlatPress entry.
 * @param array<string, mixed> $entry
 * @return string
 */
function plugin_mastodon_entry_hash($entry) {
	$subject = isset($entry ['subject']) ? (string) $entry ['subject'] : '';
	$content = isset($entry ['content']) ? (string) $entry ['content'] : '';
	$mediaSignature = plugin_mastodon_entry_media_signature($content);
	return sha1($subject . "\n" . $content . "\n" . $mediaSignature);
}

/**
 * Build a change-detection hash for a FlatPress comment.
 * @param array<string, mixed> $comment
 * @return string
 */
function plugin_mastodon_comment_hash($comment) {
	$name = isset($comment ['name']) ? (string) $comment ['name'] : '';
	$content = isset($comment ['content']) ? (string) $comment ['content'] : '';
	$parent = '';
	foreach (array('replyto', 'reply_to', 'parent', 'parent_id', 'in_reply_to', 'in_reply_to_id', 'replytoid', 'reply_to_id', 'inreplyto') as $parentKey) {
		if (!empty($comment [$parentKey])) {
			$parent = trim((string) $comment [$parentKey]);
			break;
		}
	}
	return sha1($name . "\n" . $content . "\n" . $parent);
}

/**
 * Sanitize a string so it can be used as a path component.
 * @param string $value
 * @return string
 */
function plugin_mastodon_safe_path_component($value) {
	$value = strtolower(trim((string) $value));
	if ($value === '') {
		return 'item';
	}
	$value = preg_replace('/[^a-z0-9._-]+/i', '-', $value);
	$value = trim((string) $value, '-.');
	return $value !== '' ? $value : 'item';
}

/**
 * Sanitize a file name for local storage.
 * @param string $filename
 * @return string
 */
function plugin_mastodon_safe_filename($filename) {
	$filename = trim((string) $filename);
	$filename = str_replace(array('\\', '/'), '-', $filename);
	$filename = preg_replace('/[^A-Za-z0-9._-]+/u', '-', $filename);
	$filename = trim((string) $filename, '-.');
	return $filename !== '' ? $filename : 'file';
}

/**
 * Resolve a FlatPress media path to an absolute file path.
 * @param string $relativePath
 * @return string
 */
function plugin_mastodon_media_relative_to_absolute($relativePath) {
	$relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
	if ($relativePath === '') {
		return '';
	}
	return ABS_PATH . FP_CONTENT . $relativePath;
}

/**
 * Ensure that a media directory exists.
 * @param string $path
 * @return bool
 */
function plugin_mastodon_media_prepare_directory($path) {
	$path = rtrim((string) $path, '/\\');
	if ($path === '') {
		return false;
	}
	if (is_dir($path)) {
		return true;
	}
	return @mkdir($path, DIR_PERMISSIONS, true) || is_dir($path);
}

/**
 * Delete a directory tree used for imported media.
 * @param string $path
 * @return void
 */
function plugin_mastodon_media_delete_tree($path) {
	$path = rtrim((string) $path, '/\\');
	if ($path === '' || (!file_exists($path) && !is_link($path))) {
		return;
	}
	if (is_file($path) || is_link($path)) {
		@unlink($path);
		return;
	}
	$items = @scandir($path);
	if (is_array($items)) {
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			plugin_mastodon_media_delete_tree($path . DIRECTORY_SEPARATOR . $item);
		}
	}
	@rmdir($path);
}

/**
 * Copy a directory tree used for media synchronization.
 * @param string $source
 * @param string $target
 * @return bool
 */
function plugin_mastodon_media_copy_tree($source, $target) {
	if (!is_dir($source)) {
		return false;
	}
	if (!plugin_mastodon_media_prepare_directory($target)) {
		return false;
	}
	$items = @scandir($source);
	if (!is_array($items)) {
		return false;
	}
	foreach ($items as $item) {
		if ($item === '.' || $item === '..') {
			continue;
		}
		$sourcePath = $source . DIRECTORY_SEPARATOR . $item;
		$targetPath = $target . DIRECTORY_SEPARATOR . $item;
		if (is_dir($sourcePath)) {
			if (!plugin_mastodon_media_copy_tree($sourcePath, $targetPath)) {
				return false;
			}
			continue;
		}
		if (!@copy($sourcePath, $targetPath)) {
			$data = @file_get_contents($sourcePath);
			if ($data === false || @file_put_contents($targetPath, $data) === false) {
				return false;
			}
		}
	}
	return true;
}

/**
 * Escape a value for safe BBCode attribute usage.
 * @param string $value
 * @return string
 */
function plugin_mastodon_bbcode_attr_escape($value) {
	$value = str_replace(array("\r", "\n"), ' ', (string) $value);
	$value = trim((string) $value);
	$value = str_replace(array('"', '[', ']'), array('&quot;', '(', ')'), $value);
	return $value;
}

/**
 * Guess the MIME type of a local media file.
 * @param string $path
 * @return string
 */
function plugin_mastodon_media_guess_mime_type($path) {
	$path = (string) $path;
	if ($path === '') {
		return 'application/octet-stream';
	}
	$signature = (string) @filemtime($path) . ':' . (string) @filesize($path);
	$cacheKey = $path . '|' . $signature;
	$cached = plugin_mastodon_runtime_cache_get('mime_type', $cacheKey, $hit);
	if ($hit && is_string($cached) && $cached !== '') {
		return $cached;
	}
	if (function_exists('mime_content_type')) {
		$mime = @mime_content_type($path);
		if (is_string($mime) && $mime !== '') {
			return plugin_mastodon_runtime_cache_set('mime_type', $cacheKey, $mime);
		}
	}
	$extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
	$map = array(
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png' => 'image/png',
		'gif' => 'image/gif',
		'webp' => 'image/webp',
		'bmp' => 'image/bmp',
		'svg' => 'image/svg+xml'
	);
	$mime = isset($map [$extension]) ? $map [$extension] : 'application/octet-stream';
	return plugin_mastodon_runtime_cache_set('mime_type', $cacheKey, $mime);
}

/**
 * Parse key/value attributes from a FlatPress media tag.
 * @param string $text
 * @return array<string, string>
 */
function plugin_mastodon_media_parse_tag_attributes($text) {
	$text = (string) $text;
	$attributes = array();
	if ($text === '') {
		return $attributes;
	}
	if (preg_match_all('/([a-z0-9_-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s\]]+))/iu', $text, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$key = strtolower((string) $match [1]);
			if ($key === '') {
				continue;
			}
			$value = '';
			if (isset($match [3]) && $match [3] !== '') {
				$value = $match [3];
			} elseif (isset($match [4]) && $match [4] !== '') {
				$value = $match [4];
			} elseif (isset($match [5])) {
				$value = $match [5];
			}
			$attributes [$key] = plugin_mastodon_html_entity_decode(trim((string) $value));
		}
	}
	return $attributes;
}

/**
 * Collect local images referenced by an entry or gallery tag.
 * @param array<string, mixed> $entry
 * @return array<int, array<string, mixed>>
 */
function plugin_mastodon_collect_local_entry_media($entry) {
	$content = isset($entry ['content']) ? (string) $entry ['content'] : '';
	$media = array();
	$seen = array();

	if ($content === '') {
		return $media;
	}

	$cacheKey = sha1($content);
	$cached = plugin_mastodon_runtime_cache_get('entry_media', $cacheKey, $hit);
	if ($hit && is_array($cached)) {
		return $cached;
	}

	if (preg_match_all('/\[\s*gallery\b([^\]]*)\]/iu', $content, $galleryMatches, PREG_SET_ORDER)) {
		foreach ($galleryMatches as $match) {
			$attrText = isset($match [1]) ? (string) $match [1] : '';
			if (!preg_match('/=\s*["\']?([^\s\]"\']+)/u', $attrText, $pathMatch)) {
				continue;
			}
			$galleryDir = trim((string) $pathMatch [1]);
			$galleryDir = rtrim(str_replace('\\', '/', $galleryDir), '/');
			if ($galleryDir === '') {
				continue;
			}
			$galleryPath = plugin_mastodon_media_relative_to_absolute($galleryDir);
			if (!is_dir($galleryPath)) {
				continue;
			}
			$captions = function_exists('gallery_read_captions') ? gallery_read_captions($galleryDir) : array();
			$imageFiles = function_exists('gallery_read_images') ? gallery_read_images($galleryDir) : array();
			if (empty($imageFiles)) {
				$items = @scandir($galleryPath);
				$imageFiles = array();
				if (is_array($items)) {
					foreach ($items as $item) {
						if ($item === '.' || $item === '..' || $item === '.captions.conf' || $item === 'captions.conf' || $item === 'texte.conf') {
							continue;
						}
						if (is_file($galleryPath . DIRECTORY_SEPARATOR . $item)) {
							$imageFiles [] = $item;
						}
					}
				}
				sort($imageFiles);
			}
			foreach ($imageFiles as $imageFile) {
				$relativePath = $galleryDir . '/' . $imageFile;
				$absolutePath = plugin_mastodon_media_relative_to_absolute($relativePath);
				$key = strtolower($relativePath);
				if (!is_file($absolutePath) || isset($seen [$key])) {
					continue;
				}
				$media [] = array(
					'relative_path' => $relativePath,
					'absolute_path' => $absolutePath,
					'description' => isset($captions [$imageFile]) ? trim((string) $captions [$imageFile]) : ''
				);
				$seen [$key] = true;
			}
		}
	}

	if (preg_match_all('/\[\s*img\b([^\]]*)\]/iu', $content, $imgMatches, PREG_SET_ORDER)) {
		foreach ($imgMatches as $match) {
			$attrText = isset($match [1]) ? (string) $match [1] : '';
			if (!preg_match('/=\s*["\']?([^\s\]"\']+)/u', $attrText, $pathMatch)) {
				continue;
			}
			$relativePath = trim((string) $pathMatch [1]);
			if ($relativePath === '' || preg_match('!^https?://!i', $relativePath)) {
				continue;
			}
			$attributes = plugin_mastodon_media_parse_tag_attributes($attrText);
			$description = '';
			if (!empty($attributes ['title'])) {
				$description = $attributes ['title'];
			} elseif (!empty($attributes ['alt'])) {
				$description = $attributes ['alt'];
			}
			$absolutePath = plugin_mastodon_media_relative_to_absolute($relativePath);
			$key = strtolower($relativePath);
			if (!is_file($absolutePath) || isset($seen [$key])) {
				continue;
			}
			$media [] = array(
				'relative_path' => $relativePath,
				'absolute_path' => $absolutePath,
				'description' => $description
			);
			$seen [$key] = true;
		}
	}

	if (preg_match_all('/\[img\](.*?)\[\/img\]/isu', $content, $inlineMatches, PREG_SET_ORDER)) {
		foreach ($inlineMatches as $match) {
			$relativePath = trim((string) $match [1]);
			if ($relativePath === '' || preg_match('!^https?://!i', $relativePath)) {
				continue;
			}
			$absolutePath = plugin_mastodon_media_relative_to_absolute($relativePath);
			$key = strtolower($relativePath);
			if (!is_file($absolutePath) || isset($seen [$key])) {
				continue;
			}
			$media [] = array(
				'relative_path' => $relativePath,
				'absolute_path' => $absolutePath,
				'description' => ''
			);
			$seen [$key] = true;
		}
	}

	return plugin_mastodon_runtime_cache_set('entry_media', $cacheKey, $media);
}

/**
 * Build a signature for media references contained in entry content.
 * @param mixed $content
 * @return string
 */
function plugin_mastodon_entry_media_signature($content) {
	$content = (string) $content;
	if ($content === '') {
		return '';
	}
	$signatureParts = array();
	$media = plugin_mastodon_collect_local_entry_media(array('content' => $content));
	foreach ($media as $item) {
		if (empty($item ['absolute_path']) || !is_file($item ['absolute_path'])) {
			continue;
		}
		$signatureParts [] = implode('|', array(
			isset($item ['relative_path']) ? (string) $item ['relative_path'] : '',
			isset($item ['description']) ? (string) $item ['description'] : '',
			(string) @filesize($item ['absolute_path']),
			(string) @filemtime($item ['absolute_path'])
		));
	}
	return sha1(implode("\n", $signatureParts));
}

/**
 * Extract image attachments from a remote Mastodon status.
 * @param array<string, mixed> $remoteStatus
 * @return array<int, array<string, mixed>>
 */
function plugin_mastodon_remote_status_image_attachments($remoteStatus) {
	$attachments = array();
	if (empty($remoteStatus ['media_attachments']) || !is_array($remoteStatus ['media_attachments'])) {
		return $attachments;
	}
	foreach ($remoteStatus ['media_attachments'] as $attachment) {
		if (!is_array($attachment)) {
			continue;
		}
		$type = !empty($attachment ['type']) ? strtolower((string) $attachment ['type']) : '';
		if ($type !== 'image') {
			continue;
		}
		$attachments [] = $attachment;
	}
	return $attachments;
}

/**
 * Resolve the best downloadable source URL for a remote attachment.
 * @param array<string, mixed> $attachment
 * @return string
 */
function plugin_mastodon_remote_media_source_url($attachment) {
	foreach (array('url', 'remote_url', 'preview_url', 'text_url') as $field) {
		if (!empty($attachment [$field]) && is_string($attachment [$field])) {
			return trim((string) $attachment [$field]);
		}
	}
	return '';
}

/**
 * Resolve the best description for a remote attachment.
 * @param array<string, mixed> $attachment
 * @return string
 */
function plugin_mastodon_remote_media_description($attachment) {
	foreach (array('description', 'name') as $field) {
		if (!empty($attachment [$field]) && is_string($attachment [$field])) {
			return trim((string) $attachment [$field]);
		}
	}
	return '';
}

/**
 * Download a remote media asset.
 * @param string $url
 * @param array<int|string, string> $headers
 * @return array<string, mixed>
 */
function plugin_mastodon_media_download($url, $headers) {
	return plugin_mastodon_http_request('GET', $url, is_array($headers) ? $headers : array(), '', '');
}

/**
 * Build FlatPress BBCode for imported remote media attachments.
 *
 * Note: Imported media is stored inside the plugin runtime tree before BBCode is generated.
 * @param MastodonOptions|array<string, mixed> $options
 * @param array<string, mixed> $remoteStatus
 * @return string
 */
function plugin_mastodon_build_imported_media_bbcode(&$options, $remoteStatus) {
	$attachments = plugin_mastodon_remote_status_image_attachments($remoteStatus);
	if (empty($attachments)) {
		return '';
	}
	$remoteId = !empty($remoteStatus ['id']) ? plugin_mastodon_safe_path_component($remoteStatus ['id']) : 'status';
	$finalDir = ABS_PATH . IMAGES_DIR . 'mastodon' . DIRECTORY_SEPARATOR . 'status-' . $remoteId;
	$tempDir = ABS_PATH . PLUGIN_MASTODON_STATE_DIR . 'tmp' . DIRECTORY_SEPARATOR . 'status-' . $remoteId;
	plugin_mastodon_media_delete_tree($tempDir);
	if (!plugin_mastodon_media_prepare_directory($tempDir)) {
		plugin_mastodon_log('Unable to prepare temporary media directory for remote status ' . $remoteId);
		return '';
	}

	$captions = array();
	$savedFiles = array();
	$index = 0;
	$downloadHeaders = array();
	if (!empty($options ['access_token'])) {
		$downloadHeaders [] = 'Authorization: Bearer ' . $options ['access_token'];
	}
	foreach ($attachments as $attachment) {
		$sourceUrl = plugin_mastodon_remote_media_source_url($attachment);
		if ($sourceUrl === '') {
			continue;
		}
		$download = plugin_mastodon_media_download($sourceUrl, $downloadHeaders);
		if (!$download ['ok'] || $download ['body'] === '') {
			plugin_mastodon_log('Failed to download remote media for status ' . $remoteId . ' from ' . $sourceUrl . ': ' . plugin_mastodon_response_error_message($download));
			continue;
		}
		$index++;
		$basename = plugin_mastodon_safe_filename((string) basename(parse_url($sourceUrl, PHP_URL_PATH)));
		if ($basename === '' || strpos($basename, '.') === false) {
			$extension = '';
			if (!empty($download ['headers'] ['content-type']) && preg_match('!image/([a-z0-9.+-]+)!i', (string) $download ['headers'] ['content-type'], $mimeMatch)) {
				$extension = '.' . strtolower((string) $mimeMatch [1]);
			}
			if ($extension === '.jpeg') {
				$extension = '.jpg';
			}
			$basename = sprintf('%02d', $index) . $extension;
		}
		$basename = sprintf('%02d-', $index) . ltrim($basename, '-');
		$filePath = $tempDir . DIRECTORY_SEPARATOR . $basename;
		if (@file_put_contents($filePath, $download ['body']) === false) {
			plugin_mastodon_log('Failed to store imported remote media file ' . $filePath);
			continue;
		}
		$savedFiles [] = $basename;
		$description = plugin_mastodon_remote_media_description($attachment);
		if ($description !== '') {
			$captions [$basename] = $description;
		}
	}

	if (empty($savedFiles)) {
		plugin_mastodon_media_delete_tree($tempDir);
		return '';
	}

	plugin_mastodon_media_delete_tree($finalDir);
	if (!plugin_mastodon_media_copy_tree($tempDir, $finalDir)) {
		plugin_mastodon_media_delete_tree($tempDir);
		plugin_mastodon_log('Failed to copy imported media into FlatPress images directory for status ' . $remoteId);
		return '';
	}
	plugin_mastodon_media_delete_tree($tempDir);

	if (count($savedFiles) > 1 && !empty($captions)) {
		$captionLines = array();
		foreach ($captions as $fileName => $caption) {
			$captionLines [] = $fileName . ' = ' . str_replace(array("\r", "\n"), ' ', $caption);
		}
		@file_put_contents($finalDir . DIRECTORY_SEPARATOR . '.captions.conf', implode(PHP_EOL, $captionLines) . PHP_EOL);
	}

	$galleryRelative = 'images/mastodon/status-' . $remoteId;
	if (count($savedFiles) > 1) {
		return "\n\n[gallery=" . $galleryRelative . ' width=' . PLUGIN_MASTODON_IMPORTED_MEDIA_WIDTH . "]";
	}

	$singleRelative = $galleryRelative . '/' . $savedFiles [0];
	$singleDescription = isset($captions [$savedFiles [0]]) ? trim((string) $captions [$savedFiles [0]]) : '';
	$tag = "\n\n[img=" . $singleRelative . ' width=' . PLUGIN_MASTODON_IMPORTED_MEDIA_WIDTH;
	if ($singleDescription !== '') {
		$tag .= ' title="' . plugin_mastodon_bbcode_attr_escape($singleDescription) . '"';
	}
	$tag .= ']';
	return $tag;
}

/**
 * Load and cache the Mastodon instance configuration document.
 * @param MastodonOptions|array<string, mixed> $options
 * @return array<string, mixed>
 */
function plugin_mastodon_instance_configuration($options) {
	static $cache = array();
	$base = plugin_mastodon_normalize_instance_url(isset($options ['instance_url']) ? $options ['instance_url'] : '');
	if ($base === '') {
		return array();
	}
	if (isset($cache [$base])) {
		return $cache [$base];
	}

	$apcuKey = 'instance_configuration:' . sha1($base);
	$apcuValue = plugin_mastodon_apcu_fetch($apcuKey, $apcuHit);
	if ($apcuHit && is_array($apcuValue)) {
		$cache [$base] = $apcuValue;
		return $cache [$base];
	}

	$response = plugin_mastodon_mastodon_json($options, 'GET', '/api/v2/instance', array(), false);
	$cache [$base] = ($response ['ok'] && !empty($response ['json'] ['configuration']) && is_array($response ['json'] ['configuration'])) ? $response ['json'] ['configuration'] : array();
	if ($cache [$base] !== array()) {
		plugin_mastodon_apcu_store($apcuKey, $cache [$base], 900);
	}
	return $cache [$base];
}

/**
 * Return the media attachment limit of the configured instance.
 * @param MastodonOptions|array<string, mixed> $options
 * @return int
 */
function plugin_mastodon_instance_media_limit($options) {
	$configuration = plugin_mastodon_instance_configuration($options);
	if (!empty($configuration ['statuses'] ['max_media_attachments'])) {
		return max(1, (int) $configuration ['statuses'] ['max_media_attachments']);
	}
	return 4;
}

/**
 * Return the media description length limit of the configured instance.
 * @param MastodonOptions|array<string, mixed> $options
 * @return int
 */
function plugin_mastodon_instance_media_description_limit($options) {
	$configuration = plugin_mastodon_instance_configuration($options);
	if (!empty($configuration ['media_attachments'] ['description_limit'])) {
		return max(0, (int) $configuration ['media_attachments'] ['description_limit']);
	}
	return 1500;
}

/**
 * Perform a multipart HTTP request.
 * @param string $method
 * @param string $url
 * @param array<int|string, string> $headers
 * @param array<string, mixed> $fields
 * @return array<string, mixed>
 */
function plugin_mastodon_http_request_multipart($method, $url, $headers, $fields) {
	$method = strtoupper((string) $method);
	$url = (string) $url;
	$headers = is_array($headers) ? $headers : array();
	$fields = is_array($fields) ? $fields : array();

	if (isset($GLOBALS ['plugin_mastodon_test_http_requests']) && is_array($GLOBALS ['plugin_mastodon_test_http_requests'])) {
		$GLOBALS ['plugin_mastodon_test_http_requests'] [] = array(
			'method' => $method,
			'url' => $url,
			'headers' => $headers,
			'body' => '',
			'content_type' => 'multipart/form-data',
			'multipart' => $fields
		);
	}

	$testKey = $method . ' ' . $url;
	if (isset($GLOBALS ['plugin_mastodon_test_http_responses'] [$testKey])) {
		$mock = $GLOBALS ['plugin_mastodon_test_http_responses'] [$testKey];
		if (is_array($mock) && isset($mock [0]) && is_array($mock [0])) {
			$next = array_shift($mock);
			$GLOBALS ['plugin_mastodon_test_http_responses'] [$testKey] = $mock;
			$mock = $next;
		}
		return array(
			'ok' => !empty($mock ['ok']),
			'code' => isset($mock ['code']) ? (int) $mock ['code'] : 200,
			'headers' => isset($mock ['headers']) && is_array($mock ['headers']) ? $mock ['headers'] : array(),
			'body' => isset($mock ['body']) ? (string) $mock ['body'] : '',
			'error' => isset($mock ['error']) ? (string) $mock ['error'] : ''
		);
	}

	if (function_exists('curl_init')) {
		$payload = array();
		foreach ($fields as $name => $value) {
			if (is_array($value) && !empty($value ['__file_path'])) {
				if (function_exists('curl_file_create')) {
					$payload [$name] = curl_file_create($value ['__file_path'], isset($value ['__mime_type']) ? $value ['__mime_type'] : plugin_mastodon_media_guess_mime_type($value ['__file_path']), isset($value ['__file_name']) ? $value ['__file_name'] : basename($value ['__file_path']));
				} else {
					$payload [$name] = '@' . $value ['__file_path'];
				}
			} else {
				$payload [$name] = is_scalar($value) ? (string) $value : '';
			}
		}
		$responseHeaders = array();
		$ch = curl_init($url);
		$options = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT => 90,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_HEADERFUNCTION => function ($curl, $headerLine) use (&$responseHeaders) {
				$len = strlen($headerLine);
				$headerLine = trim($headerLine);
				if ($headerLine !== '' && strpos($headerLine, ':') !== false) {
					list($name, $value) = explode(':', $headerLine, 2);
					$responseHeaders [strtolower(trim($name))] = trim($value);
				}
				return $len;
			},
			CURLOPT_USERAGENT => 'FlatPress-Mastodon/0.1'
		);
		curl_setopt_array($ch, $options);
		$responseBody = curl_exec($ch);
		$errorNo = curl_errno($ch);
		$error = curl_error($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if (!is_php85_plus()) {
			curl_close($ch);
		}
		return array(
			'ok' => ($errorNo === 0 && $code >= 200 && $code < 300),
			'code' => $code,
			'headers' => $responseHeaders,
			'body' => is_string($responseBody) ? $responseBody : '',
			'error' => (string) $error
		);
	}

	$boundary = '----FlatPressMastodon' . md5(uniqid('', true));
	$body = '';
	foreach ($fields as $name => $value) {
		$body .= '--' . $boundary . "\r\n";
		if (is_array($value) && !empty($value ['__file_path'])) {
			$filePath = $value ['__file_path'];
			$fileName = isset($value ['__file_name']) ? $value ['__file_name'] : basename($filePath);
			$mimeType = isset($value ['__mime_type']) ? $value ['__mime_type'] : plugin_mastodon_media_guess_mime_type($filePath);
			$fileData = @file_get_contents($filePath);
			if ($fileData === false) {
				return array('ok' => false, 'code' => 0, 'headers' => array(), 'body' => '', 'error' => 'Unable to read upload file');
			}
			$body .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . addcslashes($fileName, "\"\\") . "\r\n";
			$body .= 'Content-Type: ' . $mimeType . "\r\n\r\n";
			$body .= $fileData . "\r\n";
		} else {
			$body .= 'Content-Disposition: form-data; name="' . $name . "\r\n\r\n" . (is_scalar($value) ? (string) $value : '') . "\r\n";
		}
	}
	$body .= '--' . $boundary . "--\r\n";

	$headers [] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
	$headers [] = 'Content-Length: ' . strlen($body);
	$headerString = implode("\r\n", $headers);
	$context = stream_context_create(array(
		'http' => array(
			'method' => $method,
			'timeout' => 90,
			'ignore_errors' => true,
			'header' => $headerString,
			'content' => $body
		)
	));
	return plugin_mastodon_stream_context_request($url, $context);
}

/**
 * Upload local media items to Mastodon and collect the created media IDs.
 *
 * Note: Media uploads may intentionally fall back to text-only export when the current token lacks write:media.
 * @param MastodonOptions|array<string, mixed> $options
 * @param array<int, array<string, mixed>> $mediaItems
 * @param int $limit
 * @return array<string, mixed>
 */
function plugin_mastodon_upload_media_items($options, $mediaItems, $limit) {
	$mediaIds = array();
	$mediaItems = is_array($mediaItems) ? $mediaItems : array();
	$limit = max(0, (int) $limit);
	if ($limit < 1 || empty($mediaItems)) {
		return array('ok' => true, 'media_ids' => array(), 'uploaded' => 0, 'skipped' => 0, 'error' => '');
	}

	$descriptionLimit = plugin_mastodon_instance_media_description_limit($options);
	$skipped = 0;
	foreach ($mediaItems as $index => $item) {
		if (count($mediaIds) >= $limit) {
			$skipped++;
			continue;
		}
		if (empty($item ['absolute_path']) || !is_file($item ['absolute_path'])) {
			continue;
		}
		$filePath = (string) $item ['absolute_path'];
		$description = isset($item ['description']) ? trim((string) $item ['description']) : '';
		if ($descriptionLimit > 0 && $description !== '') {
			$description = plugin_mastodon_limit_text($description, $descriptionLimit, '');
		}
		$fields = array(
			'file' => array(
				'__file_path' => $filePath,
				'__file_name' => basename($filePath),
				'__mime_type' => plugin_mastodon_media_guess_mime_type($filePath)
			)
		);
		if ($description !== '') {
			$fields ['description'] = $description;
		}
		$headers = array('Accept: application/json');
		if (!empty($options ['access_token'])) {
			$headers [] = 'Authorization: Bearer ' . $options ['access_token'];
		}
		$response = plugin_mastodon_http_request_multipart('POST', plugin_mastodon_normalize_instance_url(isset($options ['instance_url']) ? $options ['instance_url'] : '') . '/api/v2/media', $headers, $fields);
		$data = json_decode(isset($response ['body']) ? $response ['body'] : '', true);
		if (!is_array($data)) {
			$data = array();
		}
		if (!$response ['ok'] || empty($data ['id'])) {
			return array(
				'ok' => false,
				'media_ids' => $mediaIds,
				'uploaded' => count($mediaIds),
				'skipped' => $skipped,
				'error' => plugin_mastodon_response_error_message(array_merge($response, array('json' => $data)))
			);
		}
		$mediaIds [] = (string) $data ['id'];
	}
	if ($skipped > 0) {
		plugin_mastodon_log('Skipped ' . $skipped . ' local media attachment(s) because the Mastodon instance allows only ' . $limit . ' attachment(s) per status.');
	}
	return array('ok' => true, 'media_ids' => $mediaIds, 'uploaded' => count($mediaIds), 'skipped' => $skipped, 'error' => '');
}

/**
 * Collect entry files recursively from the FlatPress content tree.
 * @param string $dir
 * @param array<int, string> $files
 * @return void
 */
function plugin_mastodon_collect_entry_files($dir, &$files) {
	$items = @scandir($dir);
	if (!is_array($items)) {
		return;
	}
	foreach ($items as $item) {
		if ($item === '.' || $item === '..') {
			continue;
		}
		$path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $item;
		if (is_dir($path)) {
			if (basename($path) === 'drafts' || basename($path) === 'static' || basename($path) === 'seometa') {
				continue;
			}
			plugin_mastodon_collect_entry_files($path, $files);
		} elseif (preg_match('/^entry\d{6}-\d{6}\.txt$/', $item)) {
			$files [] = $path;
		}
	}
}

/**
 * List local FlatPress entry identifiers.
 * @return array<int, string>
 */
function plugin_mastodon_list_local_entries() {
	$files = array();
	plugin_mastodon_collect_entry_files(CONTENT_DIR, $files);
	rsort($files);
	$entries = array();
	foreach ($files as $file) {
		$id = basename($file, EXT);
		$entry = entry_parse($id);
		if (!$entry || !is_array($entry)) {
			continue;
		}
		if (isset($entry ['categories']) && is_array($entry ['categories']) && in_array('draft', $entry ['categories'], true)) {
			continue;
		}
		$entries [$id] = $entry;
	}
	return $entries;
}

/**
 * Parse raw HTTP response headers.
 * @param string $rawHeaders
 * @return array{code:int, headers:array<int, string>}
 */
function plugin_mastodon_parse_http_response_headers($rawHeaders) {
	$responseHeaders = array();
	$code = 0;
	if (!is_array($rawHeaders)) {
		$rawHeaders = array();
	}
	foreach ($rawHeaders as $line) {
		if (!is_string($line)) {
			continue;
		}
		if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches)) {
			$code = (int) $matches [1];
			continue;
		}
		if (strpos($line, ':') !== false) {
			list($name, $value) = explode(':', $line, 2);
			$responseHeaders [strtolower(trim($name))] = trim($value);
		}
	}
	return array(
		'code' => $code,
		'headers' => $responseHeaders
	);
}

/**
 * Perform an HTTP request through a stream context fallback.
 *
 * Note: This stream-based fallback avoids PHP 8.4 deprecations around locally scoped response headers.
 * @param string $url
 * @param mixed $context
 * @return array<string, mixed>
 */
function plugin_mastodon_stream_context_request($url, $context) {
	$rawHeaders = array();
	$responseBody = '';
	$error = '';

	$stream = @fopen($url, 'rb', false, $context);
	if (is_resource($stream)) {
		$meta = stream_get_meta_data($stream);
		if (isset($meta ['wrapper_data']) && is_array($meta ['wrapper_data'])) {
			$rawHeaders = $meta ['wrapper_data'];
		}
		$body = stream_get_contents($stream);
		if (is_string($body)) {
			$responseBody = $body;
		}
		fclose($stream);
	} else {
		$error = 'Unable to open HTTP stream';
		if (function_exists('http_get_last_response_headers')) {
			$headersFromRuntime = http_get_last_response_headers();
			$rawHeaders = is_array($headersFromRuntime) ? $headersFromRuntime : array();
		}
	}

	$parsedHeaders = plugin_mastodon_parse_http_response_headers($rawHeaders);
	return array(
		'ok' => ($parsedHeaders ['code'] >= 200 && $parsedHeaders ['code'] < 300),
		'code' => $parsedHeaders ['code'],
		'headers' => $parsedHeaders ['headers'],
		'body' => $responseBody,
		'error' => $error
	);
}

/**
 * Build an application/x-www-form-urlencoded query string.
 * @param array<string, mixed> $params
 * @return string
 */
function plugin_mastodon_http_build_query($params) {
	if (!is_array($params) || empty($params)) {
		return '';
	}

	$parts = array();
	$append = function ($key, $value) use (&$parts, &$append) {
		if (is_array($value)) {
			foreach ($value as $item) {
				$append($key . '[]', $item);
			}
			return;
		}
		if (is_bool($value)) {
			$value = $value ? '1' : '0';
		} elseif ($value === null) {
			$value = '';
		}
		$parts [] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
	};

	foreach ($params as $key => $value) {
		$append((string) $key, $value);
	}

	return implode('&', $parts);
}

/**
 * Perform an HTTP request using cURL or the stream fallback.
 * @param string $method
 * @param string $url
 * @param array<int|string, string> $headers
 * @param string $body
 * @param string $contentType
 * @return array<string, mixed>
 */
function plugin_mastodon_http_request($method, $url, $headers, $body, $contentType) {
	$method = strtoupper((string) $method);
	$url = (string) $url;
	$headers = is_array($headers) ? $headers : array();
	$contentType = (string) $contentType;
	$body = ($body === null) ? '' : (string) $body;

	if (isset($GLOBALS ['plugin_mastodon_test_http_requests']) && is_array($GLOBALS ['plugin_mastodon_test_http_requests'])) {
		$GLOBALS ['plugin_mastodon_test_http_requests'] [] = array(
			'method' => $method,
			'url' => $url,
			'headers' => $headers,
			'body' => $body,
			'content_type' => $contentType
		);
	}

	$testKey = $method . ' ' . $url;
	if (isset($GLOBALS ['plugin_mastodon_test_http_responses'] [$testKey])) {
		$mock = $GLOBALS ['plugin_mastodon_test_http_responses'] [$testKey];
		if (is_array($mock) && isset($mock [0]) && is_array($mock [0])) {
			$next = array_shift($mock);
			$GLOBALS ['plugin_mastodon_test_http_responses'] [$testKey] = $mock;
			$mock = $next;
		}
		return array(
			'ok' => !empty($mock ['ok']),
			'code' => isset($mock ['code']) ? (int) $mock ['code'] : 200,
			'headers' => isset($mock ['headers']) && is_array($mock ['headers']) ? $mock ['headers'] : array(),
			'body' => isset($mock ['body']) ? (string) $mock ['body'] : '',
			'error' => isset($mock ['error']) ? (string) $mock ['error'] : ''
		);
	}

	if ($contentType !== '') {
		$headers [] = 'Content-Type: ' . $contentType;
	}

	if (function_exists('curl_init')) {
		$responseHeaders = array();
		$ch = curl_init($url);
		$options = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT => 45,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_HEADERFUNCTION => function ($curl, $headerLine) use (&$responseHeaders) {
				$len = strlen($headerLine);
				$headerLine = trim($headerLine);
				if ($headerLine !== '' && strpos($headerLine, ':') !== false) {
					list($name, $value) = explode(':', $headerLine, 2);
					$responseHeaders [strtolower(trim($name))] = trim($value);
				}
				return $len;
			},
			CURLOPT_USERAGENT => 'FlatPress-Mastodon/0.1'
		);

		if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH' || $method === 'DELETE') {
			$options [CURLOPT_POSTFIELDS] = $body;
		}
		curl_setopt_array($ch, $options);
		$responseBody = curl_exec($ch);
		$errorNo = curl_errno($ch);
		$error = curl_error($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if (!is_php85_plus()) {
			curl_close($ch);
		}
		return array(
			'ok' => ($errorNo === 0 && $code >= 200 && $code < 300),
			'code' => $code,
			'headers' => $responseHeaders,
			'body' => is_string($responseBody) ? $responseBody : '',
			'error' => (string) $error
		);
	}

	if (ini_get('allow_url_fopen')) {
		$headerString = implode("\r\n", $headers);
		$context = stream_context_create(array(
			'http' => array(
				'method' => $method,
				'timeout' => 45,
				'ignore_errors' => true,
				'header' => $headerString,
				'content' => $body
			)
		));
		return plugin_mastodon_stream_context_request($url, $context);
	}

	return array(
		'ok' => false,
		'code' => 0,
		'headers' => array(),
		'body' => '',
		'error' => 'No HTTP transport available'
	);
}

/**
 * Call the Mastodon API and return the raw HTTP response.
 * @param MastodonOptions|array<string, mixed> $options
 * @param string $method
 * @param string $path
 * @param array<string, mixed> $params
 * @param bool $auth
 * @return array<string, mixed>
 */
function plugin_mastodon_mastodon_api($options, $method, $path, $params, $auth) {
	$base = plugin_mastodon_normalize_instance_url(isset($options ['instance_url']) ? $options ['instance_url'] : '');
	if ($base === '') {
		return array('ok' => false, 'code' => 0, 'body' => '', 'headers' => array(), 'error' => 'Missing instance URL');
	}

	$url = $base . $path;
	$headers = array('Accept: application/json');
	$body = '';
	$contentType = 'application/x-www-form-urlencoded; charset=UTF-8';

	if ($auth && !empty($options ['access_token'])) {
		$headers [] = 'Authorization: Bearer ' . $options ['access_token'];
	}

	if (is_array($params) && !empty($params)) {
		if (strtoupper($method) === 'GET') {
			$url .= '?' . plugin_mastodon_http_build_query($params);
			$contentType = '';
		} else {
			$body = plugin_mastodon_http_build_query($params);
		}
	} else {
		if (strtoupper($method) === 'GET') {
			$contentType = '';
		}
	}

	$response = plugin_mastodon_http_request($method, $url, $headers, $body, $contentType);
	if (!$response ['ok']) {
		plugin_mastodon_log('HTTP ' . $method . ' ' . $url . ' failed: ' . $response ['code'] . ' ' . $response ['error'] . ' ' . plugin_mastodon_limit_text($response ['body'], 400));
	}
	return $response;
}

/**
 * Call the Mastodon API and decode a JSON response.
 * @param MastodonOptions|array<string, mixed> $options
 * @param string $method
 * @param string $path
 * @param array<string, mixed> $params
 * @param bool $auth
 * @return array<string, mixed>
 */
function plugin_mastodon_mastodon_json($options, $method, $path, $params, $auth) {
	$response = plugin_mastodon_mastodon_api($options, $method, $path, $params, $auth);
	$data = json_decode($response ['body'], true);
	if (!is_array($data)) {
		$data = array();
	}
	$response ['json'] = $data;
	return $response;
}

/**
 * Extract the most useful error message from an API response.
 * @param array<string, mixed> $response
 * @return string
 */
function plugin_mastodon_response_error_message($response) {
	$response = is_array($response) ? $response : array();
	if (!empty($response ['json'] ['error'])) {
		return trim((string) $response ['json'] ['error']);
	}
	if (!empty($response ['error'])) {
		return trim((string) $response ['error']);
	}
	if (!empty($response ['body']) && is_string($response ['body'])) {
		$decoded = json_decode($response ['body'], true);
		if (is_array($decoded) && !empty($decoded ['error'])) {
			return trim((string) $decoded ['error']);
		}
		$body = trim(strip_tags($response ['body']));
		if ($body !== '') {
			return $body;
		}
	}
	if (!empty($response ['code'])) {
		return 'HTTP ' . (int) $response ['code'];
	}
	return 'request_failed';
}

/**
 * Register the FlatPress application on the configured Mastodon instance.
 * @param MastodonOptions|array<string, mixed> $options
 * @return array<string, mixed>
 */
function plugin_mastodon_register_app(&$options) {
	$params = array(
		'client_name' => PLUGIN_MASTODON_APP_NAME,
		'redirect_uris' => 'urn:ietf:wg:oauth:2.0:oob',
		'scopes' => plugin_mastodon_oauth_scopes(),
		'website' => defined('BLOG_BASEURL') ? BLOG_BASEURL : ''
	);
	$response = plugin_mastodon_mastodon_json($options, 'POST', '/api/v1/apps', $params, false);
	if (!empty($response ['json'] ['client_id']) && !empty($response ['json'] ['client_secret'])) {
		$options ['client_id'] = (string) $response ['json'] ['client_id'];
		$options ['client_secret'] = (string) $response ['json'] ['client_secret'];
		$options ['last_authorize_url'] = plugin_mastodon_build_authorize_url($options);
		plugin_mastodon_save_options($options);
	}
	return $response;
}

/**
 * Build the OAuth authorization URL.
 * @param MastodonOptions|array<string, mixed> $options
 * @return string
 */
function plugin_mastodon_build_authorize_url($options) {
	$base = plugin_mastodon_normalize_instance_url(isset($options ['instance_url']) ? $options ['instance_url'] : '');
	if ($base === '' || empty($options ['client_id'])) {
		return '';
	}
	$query = array(
		'client_id' => $options ['client_id'],
		'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob',
		'response_type' => 'code',
		'scope' => plugin_mastodon_oauth_scopes()
	);
	return $base . '/oauth/authorize?' . http_build_query($query, '', '&');
}

/**
 * Exchange an OAuth authorization code for an access token.
 * @param MastodonOptions|array<string, mixed> $options
 * @param int $code
 * @return array<string, mixed>
 */
function plugin_mastodon_exchange_code_for_token(&$options, $code) {
	$code = trim((string) $code);
	if ($code === '') {
		return array('ok' => false, 'code' => 0, 'json' => array(), 'body' => '', 'headers' => array(), 'error' => 'Missing authorization code');
	}
	$params = array(
		'grant_type' => 'authorization_code',
		'client_id' => isset($options ['client_id']) ? $options ['client_id'] : '',
		'client_secret' => isset($options ['client_secret']) ? $options ['client_secret'] : '',
		'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob',
		'code' => $code,
		'scope' => plugin_mastodon_oauth_scopes()
	);
	$response = plugin_mastodon_mastodon_json($options, 'POST', '/oauth/token', $params, false);
	if (!empty($response ['json'] ['access_token'])) {
		$options ['access_token'] = (string) $response ['json'] ['access_token'];
		$options ['authorization_code'] = '';
		plugin_mastodon_save_options($options);
	}
	return $response;
}

/**
 * Verify the currently configured access token.
 * @param MastodonOptions|array<string, mixed> $options
 * @return array<string, mixed>
 */
function plugin_mastodon_verify_credentials($options) {
	$instanceUrl = plugin_mastodon_normalize_instance_url(isset($options ['instance_url']) ? $options ['instance_url'] : '');
	$accessToken = isset($options ['access_token']) ? (string) $options ['access_token'] : '';
	$cacheKey = sha1($instanceUrl . '|' . $accessToken);
	$cached = plugin_mastodon_runtime_cache_get('verify_credentials', $cacheKey, $hit);
	if ($hit && is_array($cached)) {
		return $cached;
	}
	$response = plugin_mastodon_mastodon_json($options, 'GET', '/api/v1/accounts/verify_credentials', array(), true);
	return plugin_mastodon_runtime_cache_set('verify_credentials', $cacheKey, $response);
}

/**
 * Return the status character limit of the configured instance.
 * @param MastodonOptions|array<string, mixed> $options
 * @return int
 */
function plugin_mastodon_instance_character_limit($options) {
	$configuration = plugin_mastodon_instance_configuration($options);
	if (!empty($configuration ['statuses'] ['max_characters'])) {
		return (int) $configuration ['statuses'] ['max_characters'];
	}
	return 500;
}

/**
 * Fetch statuses for the authenticated Mastodon account.
 * @param MastodonOptions|array<string, mixed> $options
 * @param string $accountId
 * @param string $sinceId
 * @return array<int, array<string, mixed>>
 */
function plugin_mastodon_fetch_account_statuses($options, $accountId, $sinceId) {
	$statuses = array();
	$params = array(
		'limit' => 40,
		'exclude_reblogs' => 'true',
		'exclude_replies' => 'true'
	);
	if ($sinceId !== '') {
		$params ['since_id'] = $sinceId;
	}

	$page = 0;
	$maxId = '';
	do {
		$page++;
		if ($maxId !== '') {
			$params ['max_id'] = $maxId;
		}
		$response = plugin_mastodon_mastodon_json($options, 'GET', '/api/v1/accounts/' . rawurlencode($accountId) . '/statuses', $params, true);
		if (!$response ['ok']) {
			break;
		}
		$pageItems = isset($response ['json']) && is_array($response ['json']) ? $response ['json'] : array();
		if (empty($pageItems)) {
			break;
		}
		foreach ($pageItems as $item) {
			if (is_array($item) && !empty($item ['id'])) {
				$statuses [] = $item;
			}
		}
		$lastItem = end($pageItems);
		if (!is_array($lastItem) || empty($lastItem ['id']) || $sinceId !== '') {
			break;
		}
		$maxId = (string) $lastItem ['id'];
	} while ($page < PLUGIN_MASTODON_MAX_STATUS_PAGES);

	return $statuses;
}

/**
 * Fetch the conversation context for a Mastodon status.
 * @param MastodonOptions|array<string, mixed> $options
 * @param string $statusId
 * @return array<string, mixed>
 */
function plugin_mastodon_fetch_status_context($options, $statusId) {
	$response = plugin_mastodon_mastodon_json($options, 'GET', '/api/v1/statuses/' . rawurlencode($statusId) . '/context', array(), true);
	return ($response ['ok'] && isset($response ['json'])) ? $response ['json'] : array();
}

/**
 * Create a Mastodon status.
 * @param MastodonOptions|array<string, mixed> $options
 * @param string $text
 * @param string $inReplyToId
 * @param array<int, string> $mediaIds
 * @return array<string, mixed>
 */
function plugin_mastodon_create_status($options, $text, $inReplyToId, $mediaIds) {
	$params = array('status' => $text, 'visibility' => 'public');
	$mediaIds = is_array($mediaIds) ? array_values(array_filter($mediaIds, 'strlen')) : array();
	if ($inReplyToId !== '') {
		$params ['in_reply_to_id'] = $inReplyToId;
	}
	if (!empty($mediaIds)) {
		$params ['media_ids'] = $mediaIds;
	}
	return plugin_mastodon_mastodon_json($options, 'POST', '/api/v1/statuses', $params, true);
}

/**
 * Update an existing Mastodon status.
 * @param MastodonOptions|array<string, mixed> $options
 * @param string $remoteId
 * @param string $text
 * @param array<int, string> $mediaIds
 * @return array<string, mixed>
 */
function plugin_mastodon_update_status($options, $remoteId, $text, $mediaIds) {
	$params = array('status' => $text);
	$mediaIds = is_array($mediaIds) ? array_values(array_filter($mediaIds, 'strlen')) : array();
	if (!empty($mediaIds)) {
		$params ['media_ids'] = $mediaIds;
	}
	return plugin_mastodon_mastodon_json($options, 'PUT', '/api/v1/statuses/' . rawurlencode($remoteId), $params, true);
}

/**
 * Build the status body used when exporting a FlatPress entry.
 * @param string $entryId
 * @param array<string, mixed> $entry
 * @param int $charLimit
 * @return string
 */
function plugin_mastodon_build_entry_status_text($entryId, $entry, $charLimit) {
	$subject = isset($entry ['subject']) ? trim((string) $entry ['subject']) : '';
	$content = isset($entry ['content']) ? trim((string) $entry ['content']) : '';
	$parts = array();

	if ($subject !== '') {
		$parts [] = $subject;
	}
	if ($content !== '') {
		$body = plugin_mastodon_flatpress_to_mastodon($content);
		if ($body !== '') {
			$parts [] = $body;
		}
	}

	$text = trim(implode("\n\n", $parts));
	$link = plugin_mastodon_public_url_for_mastodon(plugin_mastodon_public_entry_url($entryId, $entry));
	if ($link !== '') {
		$available = $charLimit - 1 - (function_exists('mb_strlen') ? mb_strlen($link, 'UTF-8') : strlen($link));
		if ($available < 32) {
			return trim((string) plugin_mastodon_limit_text($text, max(0, $charLimit)));
		}
		$text = plugin_mastodon_limit_text($text, $available) . "\n" . $link;
		return trim((string) $text);
	}

	return trim((string) plugin_mastodon_limit_text($text, $charLimit));
}

/**
 * Build the status body used when exporting a FlatPress comment.
 * @param string $entryId
 * @param array<string, mixed> $entry
 * @param array<string, mixed> $comment
 * @param int $charLimit
 * @return string
 */
function plugin_mastodon_build_comment_status_text($entryId, $entry, $comment, $charLimit) {
	$name = isset($comment ['name']) ? trim((string) $comment ['name']) : '';
	$content = isset($comment ['content']) ? trim((string) $comment ['content']) : '';
	$body = plugin_mastodon_flatpress_to_mastodon($content);
	$text = $body;

	if ($name !== '' && $name !== 'Anonymous' && $name !== 'Mastodon') {
		$format = plugin_mastodon_lang_string('comment_by_format', 'Comment by %s:');
		if (strpos($format, '%s') !== false) {
			$text = sprintf($format, $name) . "\n\n" . $body;
		} else {
			$text = rtrim($format) . ' ' . $name . "\n\n" . $body;
		}
	}

	return trim((string) plugin_mastodon_limit_text($text, $charLimit));
}

/**
 * Import a remote Mastodon status into FlatPress as an entry.
 * @param MastodonOptions|array<string, mixed> $options
 * @param MastodonState|array<string, mixed> $state
 * @param array<string, mixed> $remoteStatus
 * @return bool|string|array<string, mixed>
 */
function plugin_mastodon_import_remote_entry(&$options, &$state, $remoteStatus) {
	$remoteId = isset($remoteStatus ['id']) ? (string) $remoteStatus ['id'] : '';
	if ($remoteId === '' || !plugin_mastodon_remote_status_is_importable($remoteStatus)) {
		return false;
	}

	$content = plugin_mastodon_mastodon_html_to_flatpress(isset($remoteStatus ['content']) ? $remoteStatus ['content'] : '');
	$mediaBbcode = plugin_mastodon_build_imported_media_bbcode($options, $remoteStatus);
	if ($mediaBbcode !== '') {
		$content = trim($content . $mediaBbcode);
	}
	$subject = plugin_mastodon_guess_subject($content);
	$author = '';
	if (!empty($remoteStatus ['account'] ['display_name'])) {
		$author = plugin_mastodon_html_entity_decode(strip_tags($remoteStatus ['account'] ['display_name']));
	}
	if ($author === '' && !empty($remoteStatus ['account'] ['acct'])) {
		$author = '@' . $remoteStatus ['account'] ['acct'];
	}
	if ($author === '') {
		$author = 'Mastodon';
	}

	$url = isset($remoteStatus ['url']) ? (string) $remoteStatus ['url'] : '';
	$footer = '';
	if ($url !== '') {
		$footer = "[url=" . $url . ']Mastodon[/url]';
	}
	$entry = array(
		'version' => system_ver(),
		'subject' => $subject,
		'content' => trim($content . $footer),
		'author' => $author,
		'date' => plugin_mastodon_remote_status_timestamp($remoteStatus)
	);
	$hash = plugin_mastodon_entry_hash($entry);
	$remoteUpdatedAt = isset($remoteStatus ['edited_at']) && $remoteStatus ['edited_at'] ? plugin_mastodon_parse_iso_datetime($remoteStatus ['edited_at']) : plugin_mastodon_parse_iso_datetime(isset($remoteStatus ['created_at']) ? $remoteStatus ['created_at'] : '');

	if (isset($state ['entries_remote'] [$remoteId])) {
		$localId = $state ['entries_remote'] [$remoteId];
		$currentMeta = plugin_mastodon_state_get_entry_meta($state, $localId);
		if (!empty($currentMeta ['hash']) && $currentMeta ['hash'] === $hash) {
			return $localId;
		}
		$existing = entry_parse($localId);
		if (is_array($existing) && !empty($existing ['date'])) {
			$entry ['date'] = $existing ['date'];
		}
		$result = entry_save($entry, $localId);
		if (is_string($result) && $result !== '') {
			plugin_mastodon_state_set_entry_mapping($state, $result, $remoteId, 'remote', $hash, $url, $remoteUpdatedAt);
			$state ['stats'] ['updated_entries']++;
			return $result;
		}
		return false;
	}

	$result = entry_save($entry, null);
	if (is_string($result) && $result !== '') {
		plugin_mastodon_state_set_entry_mapping($state, $result, $remoteId, 'remote', $hash, $url, $remoteUpdatedAt);
		$state ['stats'] ['imported_entries']++;
		return $result;
	}
	return false;
}

/**
 * Import a remote Mastodon reply into FlatPress as a comment.
 * @param MastodonState|array<string, mixed> $state
 * @param string $entryId
 * @param array<string, mixed> $remoteComment
 * @param string $parentCommentId
 * @param string $inReplyToRemoteId
 * @return bool|string|array<string, mixed>
 */
function plugin_mastodon_import_remote_comment(&$state, $entryId, $remoteComment, $parentCommentId = '', $inReplyToRemoteId = '') {
	$remoteId = isset($remoteComment ['id']) ? (string) $remoteComment ['id'] : '';
	if ($remoteId === '' || $entryId === '' || !plugin_mastodon_remote_status_is_importable($remoteComment)) {
		return false;
	}
	$content = plugin_mastodon_mastodon_html_to_flatpress(isset($remoteComment ['content']) ? $remoteComment ['content'] : '');
	$name = '';
	if (!empty($remoteComment ['account'] ['display_name'])) {
		$name = plugin_mastodon_html_entity_decode(strip_tags($remoteComment ['account'] ['display_name']));
	}
	if ($name === '' && !empty($remoteComment ['account'] ['acct'])) {
		$name = '@' . $remoteComment ['account'] ['acct'];
	}
	if ($name === '') {
		$name = 'Mastodon';
	}
	$comment = array(
		'version' => system_ver(),
		'loggedin' => '0',
		'name' => $name,
		'url' => !empty($remoteComment ['account'] ['url']) ? (string) $remoteComment ['account'] ['url'] : '',
		'content' => $content,
		'date' => plugin_mastodon_remote_status_timestamp($remoteComment)
	);
	if ($parentCommentId !== '') {
		$comment ['replyto'] = $parentCommentId;
	}
	$hash = plugin_mastodon_comment_hash($comment);
	$remoteUpdatedAt = isset($remoteComment ['edited_at']) && $remoteComment ['edited_at'] ? plugin_mastodon_parse_iso_datetime($remoteComment ['edited_at']) : plugin_mastodon_parse_iso_datetime(isset($remoteComment ['created_at']) ? $remoteComment ['created_at'] : '');

	if (isset($state ['comments_remote'] [$remoteId]) && is_array($state ['comments_remote'] [$remoteId])) {
		$ref = $state ['comments_remote'] [$remoteId];
		$currentMeta = plugin_mastodon_state_get_comment_meta($state, $ref ['entry_id'], $ref ['comment_id']);
		if (!empty($currentMeta ['hash']) && $currentMeta ['hash'] === $hash && (empty($currentMeta ['parent_comment_id']) || $currentMeta ['parent_comment_id'] === (string) $parentCommentId) && (empty($currentMeta ['in_reply_to_remote_id']) || $currentMeta ['in_reply_to_remote_id'] === (string) $inReplyToRemoteId)) {
			return $ref ['comment_id'];
		}
		$file = comment_exists($ref ['entry_id'], $ref ['comment_id']);
		if ($file) {
			$existing = comment_parse($ref ['entry_id'], $ref ['comment_id']);
			if (is_array($existing) && !empty($existing ['date'])) {
				$comment ['date'] = $existing ['date'];
			}
			$stored = array_change_key_case($comment, CASE_UPPER);
			@io_write_file($file, utils_kimplode($stored));
			plugin_mastodon_state_set_comment_mapping($state, $ref ['entry_id'], $ref ['comment_id'], $remoteId, 'remote', $hash, isset($remoteComment ['url']) ? (string) $remoteComment ['url'] : '', $remoteUpdatedAt, $parentCommentId, $inReplyToRemoteId);
			$state ['stats'] ['updated_entries']++;
			return $ref ['comment_id'];
		}
	}

	$result = comment_save($entryId, $comment);
	if (is_string($result) && $result !== '') {
		plugin_mastodon_state_set_comment_mapping($state, $entryId, $result, $remoteId, 'remote', $hash, isset($remoteComment ['url']) ? (string) $remoteComment ['url'] : '', $remoteUpdatedAt, $parentCommentId, $inReplyToRemoteId);
		$state ['stats'] ['imported_comments']++;
		return $result;
	}
	return false;
}

/**
 * Synchronize remote Mastodon content into FlatPress.
 * @param MastodonOptions|array<string, mixed> $options
 * @param MastodonState|array<string, mixed> $state
 * @return bool
 */
function plugin_mastodon_sync_remote_to_local(&$options, &$state) {
	$verify = plugin_mastodon_verify_credentials($options);
	if (!$verify ['ok'] || empty($verify ['json'] ['id'])) {
		$state ['last_error'] = 'verify_credentials_failed';
		return false;
	}

	$accountId = (string) $verify ['json'] ['id'];
	$sinceId = isset($state ['last_remote_status_id']) ? (string) $state ['last_remote_status_id'] : '';
	$statuses = plugin_mastodon_fetch_account_statuses($options, $accountId, $sinceId);

	$maxRemoteId = $sinceId;
	foreach ($statuses as $status) {
		if (!is_array($status) || empty($status ['id'])) {
			continue;
		}
		$statusId = (string) $status ['id'];
		if ($maxRemoteId === '' || strcmp($statusId, $maxRemoteId) > 0) {
			$maxRemoteId = $statusId;
		}
		if (!plugin_mastodon_remote_status_is_importable($status)) {
			plugin_mastodon_log('Skipping non-public remote status ' . $statusId . ' with visibility ' . plugin_mastodon_remote_status_visibility($status));
			continue;
		}
		$entryId = plugin_mastodon_import_remote_entry($options, $state, $status);
		if (!$entryId) {
			continue;
		}
		$context = plugin_mastodon_fetch_status_context($options, $statusId);
		if (empty($context ['descendants']) || !is_array($context ['descendants'])) {
			continue;
		}

		$pending = array_values($context ['descendants']);
		$importedRemoteIds = array();
		$blockedRemoteIds = array();
		$guard = 0;
		while (!empty($pending) && $guard < 50) {
			$guard++;
			$remaining = array();
			$progress = false;
			foreach ($pending as $descendant) {
				if (!is_array($descendant) || empty($descendant ['id'])) {
					continue;
				}
				$descendantId = (string) $descendant ['id'];
				if (!plugin_mastodon_remote_status_is_importable($descendant)) {
					$blockedRemoteIds [$descendantId] = true;
					$progress = true;
					plugin_mastodon_log('Skipping non-public remote reply ' . $descendantId . ' with visibility ' . plugin_mastodon_remote_status_visibility($descendant));
					continue;
				}
				$parentRemoteId = isset($descendant ['in_reply_to_id']) ? (string) $descendant ['in_reply_to_id'] : '';
				if ($parentRemoteId !== '' && isset($blockedRemoteIds [$parentRemoteId])) {
					$blockedRemoteIds [$descendantId] = true;
					$progress = true;
					plugin_mastodon_log('Skipping remote reply ' . $descendantId . ' because parent reply ' . $parentRemoteId . ' is not importable');
					continue;
				}
				$parentCommentId = '';
				$canImportNow = ($parentRemoteId === '' || $parentRemoteId === $statusId);
				if (!$canImportNow && isset($state ['comments_remote'] [$parentRemoteId]) && is_array($state ['comments_remote'] [$parentRemoteId])) {
					$parentRef = $state ['comments_remote'] [$parentRemoteId];
					if (!empty($parentRef ['entry_id']) && (string) $parentRef ['entry_id'] === $entryId && !empty($parentRef ['comment_id'])) {
						$parentCommentId = (string) $parentRef ['comment_id'];
						$canImportNow = true;
					}
				}
				if (!$canImportNow && isset($importedRemoteIds [$parentRemoteId])) {
					$parentCommentId = (string) $importedRemoteIds [$parentRemoteId];
					$canImportNow = true;
				}
				if (!$canImportNow) {
					$remaining [] = $descendant;
					continue;
				}
				$commentId = plugin_mastodon_import_remote_comment($state, $entryId, $descendant, $parentCommentId, $parentRemoteId);
				if ($commentId) {
					$importedRemoteIds [$descendantId] = $commentId;
					$progress = true;
				} else {
					$remaining [] = $descendant;
				}
			}
			if (!$progress) {
				foreach ($remaining as $descendant) {
					if (!is_array($descendant) || empty($descendant ['id'])) {
						continue;
					}
					$parentRemoteId = isset($descendant ['in_reply_to_id']) ? (string) $descendant ['in_reply_to_id'] : '';
					plugin_mastodon_import_remote_comment($state, $entryId, $descendant, '', $parentRemoteId);
				}
				$remaining = array();
			}
			$pending = $remaining;
		}
	}

	if ($maxRemoteId !== '') {
		$state ['last_remote_status_id'] = $maxRemoteId;
	}
	return true;
}

/**
 * Synchronize local FlatPress content to Mastodon.
 * @param MastodonOptions|array<string, mixed> $options
 * @param MastodonState|array<string, mixed> $state
 * @return bool
 */
function plugin_mastodon_sync_local_to_remote(&$options, &$state) {
	$charLimit = plugin_mastodon_instance_character_limit($options);
	$mediaLimit = plugin_mastodon_instance_media_limit($options);
	$entries = plugin_mastodon_list_local_entries();
	$hadFailure = false;
	foreach ($entries as $entryId => $entry) {
		$meta = plugin_mastodon_state_get_entry_meta($state, $entryId);
		if (!empty($meta ['source']) && $meta ['source'] === 'remote') {
			continue;
		}
		$hash = plugin_mastodon_entry_hash($entry);
		$text = plugin_mastodon_build_entry_status_text($entryId, $entry, $charLimit);
		if ($text === '') {
			continue;
		}

		$mediaItems = plugin_mastodon_collect_local_entry_media($entry);
		$upload = plugin_mastodon_upload_media_items($options, $mediaItems, $mediaLimit);
		if (!$upload ['ok']) {
			$hadFailure = true;
			$state ['last_error'] = 'local_entry_media_upload_failed: ' . $entryId . ' (' . $upload ['error'] . ')';
			plugin_mastodon_log('Local entry media upload failed for ' . $entryId . ': ' . $upload ['error']);
			continue;
		}
		$mediaIds = isset($upload ['media_ids']) && is_array($upload ['media_ids']) ? $upload ['media_ids'] : array();

		if (!empty($meta ['remote_id'])) {
			if (!empty($meta ['hash']) && $meta ['hash'] === $hash) {
				// no change
			} else {
				$updated = plugin_mastodon_update_status($options, $meta ['remote_id'], $text, $mediaIds);
				if ($updated ['ok'] && !empty($updated ['json'] ['id'])) {
					plugin_mastodon_state_set_entry_mapping($state, $entryId, $meta ['remote_id'], 'local', $hash, isset($updated ['json'] ['url']) ? $updated ['json'] ['url'] : '', plugin_mastodon_parse_iso_datetime(isset($updated ['json'] ['edited_at']) ? $updated ['json'] ['edited_at'] : ''));
					$state ['stats'] ['updated_remote_entries']++;
				} else {
					$hadFailure = true;
					$state ['last_error'] = 'local_entry_update_failed: ' . $entryId . ' (' . plugin_mastodon_response_error_message($updated) . ')';
					plugin_mastodon_log('Local entry update failed for ' . $entryId . ': ' . plugin_mastodon_response_error_message($updated));
					continue;
				}
			}
		} else {
			$created = plugin_mastodon_create_status($options, $text, '', $mediaIds);
			if ($created ['ok'] && !empty($created ['json'] ['id'])) {
				plugin_mastodon_state_set_entry_mapping($state, $entryId, $created ['json'] ['id'], 'local', $hash, isset($created ['json'] ['url']) ? $created ['json'] ['url'] : '', plugin_mastodon_parse_iso_datetime(isset($created ['json'] ['created_at']) ? $created ['json'] ['created_at'] : ''));
				$state ['stats'] ['exported_entries']++;
				$meta = plugin_mastodon_state_get_entry_meta($state, $entryId);
			} else {
				$hadFailure = true;
				$state ['last_error'] = 'local_entry_export_failed: ' . $entryId . ' (' . plugin_mastodon_response_error_message($created) . ')';
				plugin_mastodon_log('Local entry export failed for ' . $entryId . ': ' . plugin_mastodon_response_error_message($created));
				continue;
			}
		}

		$entryMeta = plugin_mastodon_state_get_entry_meta($state, $entryId);
		$entryRemoteId = !empty($entryMeta ['remote_id']) ? $entryMeta ['remote_id'] : '';
		if ($entryRemoteId === '') {
			continue;
		}

		$commentIds = comment_getlist($entryId);
		foreach ($commentIds as $commentId) {
			$comment = comment_parse($entryId, $commentId);
			if (!$comment || !is_array($comment)) {
				continue;
			}
			$commentMeta = plugin_mastodon_state_get_comment_meta($state, $entryId, $commentId);
			if (!empty($commentMeta ['source']) && $commentMeta ['source'] === 'remote') {
				continue;
			}
			$commentHash = plugin_mastodon_comment_hash($comment);
			$text = plugin_mastodon_build_comment_status_text($entryId, $entry, $comment, $charLimit);
			if ($text === '') {
				continue;
			}
			$replyTarget = plugin_mastodon_resolve_comment_reply_target($state, $entryId, $comment, $entryRemoteId);
			$replyToRemoteId = !empty($replyTarget ['remote_id']) ? (string) $replyTarget ['remote_id'] : $entryRemoteId;
			$parentCommentId = !empty($replyTarget ['parent_comment_id']) ? (string) $replyTarget ['parent_comment_id'] : '';
			if (!empty($commentMeta ['remote_id'])) {
				if (!empty($commentMeta ['hash']) && $commentMeta ['hash'] === $commentHash && ((isset($commentMeta ['parent_comment_id']) ? (string) $commentMeta ['parent_comment_id'] : '') === $parentCommentId) && ((isset($commentMeta ['in_reply_to_remote_id']) ? (string) $commentMeta ['in_reply_to_remote_id'] : '') === $replyToRemoteId)) {
					continue;
				}
				$updated = plugin_mastodon_update_status($options, $commentMeta ['remote_id'], $text, array());
				if ($updated ['ok'] && !empty($updated ['json'] ['id'])) {
					plugin_mastodon_state_set_comment_mapping($state, $entryId, $commentId, $commentMeta ['remote_id'], 'local', $commentHash, isset($updated ['json'] ['url']) ? $updated ['json'] ['url'] : '', plugin_mastodon_parse_iso_datetime(isset($updated ['json'] ['edited_at']) ? $updated ['json'] ['edited_at'] : ''), $parentCommentId, $replyToRemoteId);
					$state ['stats'] ['updated_remote_comments']++;
				} else {
					$hadFailure = true;
					$state ['last_error'] = 'local_comment_update_failed: ' . $entryId . '/' . $commentId . ' (' . plugin_mastodon_response_error_message($updated) . ')';
					plugin_mastodon_log('Local comment update failed for ' . $entryId . '/' . $commentId . ': ' . plugin_mastodon_response_error_message($updated));
				}
			} else {
				$created = plugin_mastodon_create_status($options, $text, $replyToRemoteId, array());
				if ($created ['ok'] && !empty($created ['json'] ['id'])) {
					plugin_mastodon_state_set_comment_mapping($state, $entryId, $commentId, $created ['json'] ['id'], 'local', $commentHash, isset($created ['json'] ['url']) ? $created ['json'] ['url'] : '', plugin_mastodon_parse_iso_datetime(isset($created ['json'] ['created_at']) ? $created ['json'] ['created_at'] : ''), $parentCommentId, $replyToRemoteId);
					$state ['stats'] ['exported_comments']++;
				} else {
					$hadFailure = true;
					$state ['last_error'] = 'local_comment_export_failed: ' . $entryId . '/' . $commentId . ' (' . plugin_mastodon_response_error_message($created) . ')';
					plugin_mastodon_log('Local comment export failed for ' . $entryId . '/' . $commentId . ': ' . plugin_mastodon_response_error_message($created));
				}
			}
		}
	}

	return !$hadFailure;
}

/**
 * Determine whether the scheduled synchronization is currently due.
 * @param MastodonOptions|array<string, mixed> $options
 * @param MastodonState|array<string, mixed> $state
 * @param int $timestamp
 * @return bool
 */
function plugin_mastodon_sync_due($options, $state, $timestamp) {
	$timestamp = (int) $timestamp;
	if (empty($state ['last_run'])) {
		return true;
	}
	$syncTime = plugin_mastodon_normalize_sync_time(isset($options ['sync_time']) ? $options ['sync_time'] : '');
	$target = strtotime(date('Y-m-d', $timestamp) . ' ' . $syncTime . ':00');
	if ($target === false || $timestamp < $target) {
		return false;
	}
	$lastRun = strtotime((string) $state ['last_run']);
	if ($lastRun === false) {
		return true;
	}
	return date('Y-m-d', $lastRun) !== date('Y-m-d', $timestamp);
}

/**
 * Run a full synchronization cycle.
 * @param bool $force
 * @return array<string, mixed>
 */
function plugin_mastodon_run_sync($force) {
	$options = plugin_mastodon_get_options();
	$state = plugin_mastodon_state_read();

	if ($options ['instance_url'] === '') {
		$state ['last_error'] = 'missing_instance_url';
		plugin_mastodon_state_write($state);
		return array('ok' => false, 'state' => $state, 'message' => 'missing_instance_url');
	}
	if ($options ['access_token'] === '') {
		$state ['last_error'] = 'missing_access_token';
		plugin_mastodon_state_write($state);
		return array('ok' => false, 'state' => $state, 'message' => 'missing_access_token');
	}
	if (!$force && !plugin_mastodon_sync_due($options, $state, time())) {
		return array('ok' => true, 'state' => $state, 'message' => 'not_due');
	}

	plugin_mastodon_ensure_state_dir();
	$lockHandle = @fopen(PLUGIN_MASTODON_LOCK_FILE, 'c+');
	if (!$lockHandle) {
		$state ['last_error'] = 'lock_open_failed';
		plugin_mastodon_state_write($state);
		return array('ok' => false, 'state' => $state, 'message' => 'lock_open_failed');
	}
	if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
		return array('ok' => true, 'state' => $state, 'message' => 'sync_locked');
	}

	$state ['last_error'] = '';
	$state ['stats'] = plugin_mastodon_default_state() ['stats'];

	$okRemote = plugin_mastodon_sync_remote_to_local($options, $state);
	$okLocal = false;
	if ($okRemote) {
		$okLocal = plugin_mastodon_sync_local_to_remote($options, $state);
	}

	if ($okRemote && $okLocal) {
		$state ['last_run'] = date('Y-m-d H:i:s');
		$state ['last_error'] = '';
		plugin_mastodon_log('Synchronization completed successfully');
	} elseif ($state ['last_error'] === '') {
		$state ['last_error'] = 'sync_failed';
		plugin_mastodon_log('Synchronization failed');
	}

	plugin_mastodon_state_write($state);
	@flock($lockHandle, LOCK_UN);
	@fclose($lockHandle);

	return array('ok' => ($okRemote && $okLocal), 'state' => $state, 'message' => $state ['last_error'] === '' ? 'ok' : $state ['last_error']);
}

/**
 * Run the scheduled synchronization when the current request is due.
 * @return void
 */
function plugin_mastodon_maybe_sync() {
	if (PHP_SAPI === 'cli') {
		return;
	}
	if (isset($_SERVER ['REQUEST_METHOD']) && strtoupper($_SERVER ['REQUEST_METHOD']) === 'POST') {
		return;
	}
	plugin_mastodon_run_sync(false);
}
add_action('init', 'plugin_mastodon_maybe_sync', 20);

/**
 * Assign plugin data to Smarty for the admin panel.
 * @param Smarty $smarty
 * @return void
 */
function plugin_mastodon_admin_assign(&$smarty) {
	$options = plugin_mastodon_get_options();
	$state = plugin_mastodon_state_read();
	$authorizeUrl = plugin_mastodon_build_authorize_url($options);
	if ($authorizeUrl === '' && !empty($options ['last_authorize_url'])) {
		$authorizeUrl = $options ['last_authorize_url'];
	}

	$smarty->assign('mastodon_cfg', array(
		'instance_url' => $options ['instance_url'],
		'username' => $options ['username'],
		'password' => $options ['password'],
		'sync_time' => $options ['sync_time'],
		'client_id' => $options ['client_id'],
		'client_secret' => $options ['client_secret'] !== '' ? '••••••••' : '',
		'access_token' => $options ['access_token'] !== '' ? '••••••••' : '',
		'authorization_code' => $options ['authorization_code']
	));
	$smarty->assign('mastodon_state', $state);
	$smarty->assign('mastodon_authorize_url', $authorizeUrl);
	$smarty->assign('mastodon_temp_dir', PLUGIN_MASTODON_STATE_DIR);
}

if (class_exists('AdminPanelAction')) {

	class admin_plugin_mastodon extends AdminPanelAction {

		var $langres = 'plugin:mastodon';

		function setup() {
			$this->smarty->assign('admin_resource', 'plugin:mastodon/admin.plugin.mastodon');
			plugin_mastodon_admin_assign($this->smarty);
		}

		function main() {
			return 0;
		}

		function onsubmit($data = null) {
			$options = plugin_mastodon_get_options();

			if (isset($_POST ['mastodon_save'])) {
				$options ['instance_url'] = plugin_mastodon_normalize_instance_url(isset($_POST ['instance_url']) ? $_POST ['instance_url'] : '');
				$options ['username'] = trim(isset($_POST ['username']) ? (string) $_POST ['username'] : '');
				$options ['password'] = trim(isset($_POST ['password']) ? (string) $_POST ['password'] : '');
				$options ['sync_time'] = plugin_mastodon_normalize_sync_time(isset($_POST ['sync_time']) ? $_POST ['sync_time'] : '');
				$options ['authorization_code'] = trim(isset($_POST ['authorization_code']) ? (string) $_POST ['authorization_code'] : '');
				plugin_mastodon_save_options($options);
				$this->smarty->assign('success', 1);
			} elseif (isset($_POST ['mastodon_register_app'])) {
				$options ['instance_url'] = plugin_mastodon_normalize_instance_url(isset($_POST ['instance_url']) ? $_POST ['instance_url'] : $options ['instance_url']);
				plugin_mastodon_save_options($options);
				$response = plugin_mastodon_register_app($options);
				$this->smarty->assign('success', $response ['ok'] ? 2 : -2);
			} elseif (isset($_POST ['mastodon_exchange_code'])) {
				$code = trim(isset($_POST ['authorization_code']) ? (string) $_POST ['authorization_code'] : '');
				$response = plugin_mastodon_exchange_code_for_token($options, $code);
				$this->smarty->assign('success', $response ['ok'] ? 3 : -3);
			} elseif (isset($_POST ['mastodon_run_now'])) {
				$result = plugin_mastodon_run_sync(true);
				$this->smarty->assign('success', $result ['ok'] ? 4 : -4);
			} elseif (isset($_POST ['mastodon_clear_token'])) {
				$options ['access_token'] = '';
				$options ['authorization_code'] = '';
				plugin_mastodon_save_options($options);
				$this->smarty->assign('success', 5);
			}

			plugin_mastodon_admin_assign($this->smarty);
			return 0;
		}
	}

	admin_addpanelaction('plugin', 'mastodon', true);
}
?>