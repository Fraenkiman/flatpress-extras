<?php
$lang ['admin'] ['plugin'] ['submenu'] ['mastodon'] = 'Mastodon';

$lang ['admin'] ['plugin'] ['mastodon'] = array(
	'head' => 'Mastodon synchronization',
	'intro' => 'This plugin synchronizes FlatPress entries and comments with Mastodon.',
	'config_head' => 'Connection and schedule',
	'instance_url' => 'Mastodon URL',
	'username' => 'Mastodon user',
	'password' => 'Mastodon password',
	'sync_time' => 'Daily sync time',
	'save' => 'Save settings',
	'oauth_head' => 'OAuth helper',
	'oauth_desc' => 'Current Mastodon versions use OAuth access tokens for API access. Register an app, open the authorization URL, paste the shown code and exchange it for a token.',
	'register_app' => 'Register Mastodon app',
	'authorize_url' => 'Authorization URL',
	'authorization_code' => 'Authorization code',
	'exchange_code' => 'Exchange code for token',
	'clear_token' => 'Remove saved token',
	'run_now_head' => 'Manual synchronization',
	'run_now' => 'Run synchronization now',
	'status_head' => 'Status',
	'temp_dir' => 'Runtime directory',
	'last_run' => 'Last sync',
	'last_error' => 'Last error',
	'stats_head' => 'Last sync counters',
	'stats_imported_entries' => 'Imported entries',
	'stats_updated_entries' => 'Updated local entries',
	'stats_exported_entries' => 'Exported entries',
	'stats_updated_remote_entries' => 'Updated Mastodon entries',
	'stats_imported_comments' => 'Imported comments',
	'stats_exported_comments' => 'Exported comments',
	'stats_updated_remote_comments' => 'Updated Mastodon comments',
	'token_state' => 'Token state',
	'token_available' => 'Access token available',
	'token_missing' => 'No access token saved',
	'comment_by_format' => 'Comment by %s:',
	'msgs' => array(
		1 => 'The plugin settings were saved.',
		2 => 'The Mastodon app was registered. You can now authorize the app.',
		3 => 'The authorization code was exchanged for an access token.',
		4 => 'The synchronization finished successfully.',
		5 => 'The stored access token was removed.',
		-2 => 'The Mastodon app could not be registered.',
		-3 => 'The authorization code could not be exchanged for a token.',
		-4 => 'The synchronization failed. Please check the status below and fp-content/mastodon/sync.log.'
	)
);
?>