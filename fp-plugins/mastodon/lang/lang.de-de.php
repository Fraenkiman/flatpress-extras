<?php
$lang ['admin'] ['plugin'] ['submenu'] ['mastodon'] = 'Mastodon';

$lang ['admin'] ['plugin'] ['mastodon'] = array(
	'head' => 'Mastodon-Synchronisierung',
	'intro' => 'Dieses Plugin synchronisiert FlatPress-Einträge und -Kommentare mit Mastodon.',
	'config_head' => 'Verbindung und Zeitplan',
	'instance_url' => 'Mastodon-URL',
	'username' => 'Mastodon-Benutzer',
	'password' => 'Mastodon-Passwort',
	'sync_time' => 'Tägliche Synchronisationszeit',
	'save' => 'Einstellungen speichern',
	'oauth_head' => 'OAuth-Helfer',
	'oauth_desc' => 'Aktuelle Mastodon-Versionen verwenden für den API-Zugriff OAuth-Access-Tokens. Registriere eine App, öffne die Autorisierungs-URL, füge den angezeigten Code ein und tausche ihn gegen ein Token aus.',
	'register_app' => 'Mastodon-App registrieren',
	'authorize_url' => 'Autorisierungs-URL',
	'authorization_code' => 'Autorisierungscode',
	'exchange_code' => 'Code gegen Token tauschen',
	'clear_token' => 'Gespeichertes Token entfernen',
	'run_now_head' => 'Manuelle Synchronisierung',
	'run_now' => 'Synchronisierung jetzt starten',
	'status_head' => 'Status',
	'temp_dir' => 'Laufzeitverzeichnis',
	'last_run' => 'Letzte Synchronisierung',
	'last_error' => 'Letzter Fehler',
	'stats_head' => 'Zähler der letzten Synchronisierung',
	'stats_imported_entries' => 'Importierte Einträge',
	'stats_updated_entries' => 'Aktualisierte lokale Einträge',
	'stats_exported_entries' => 'Exportierte Einträge',
	'stats_updated_remote_entries' => 'Aktualisierte Mastodon-Einträge',
	'stats_imported_comments' => 'Importierte Kommentare',
	'stats_exported_comments' => 'Exportierte Kommentare',
	'stats_updated_remote_comments' => 'Aktualisierte Mastodon-Kommentare',
	'token_state' => 'Token-Status',
	'token_available' => 'Access-Token vorhanden',
	'token_missing' => 'Kein Access-Token gespeichert',
	'comment_by_format' => 'Kommentar von %s:',
	'msgs' => array(
		1 => 'Die Plugin-Einstellungen wurden gespeichert.',
		2 => 'Die Mastodon-App wurde registriert. Du kannst die App jetzt autorisieren.',
		3 => 'Der Autorisierungscode wurde gegen ein Access-Token getauscht.',
		4 => 'Die Synchronisierung wurde erfolgreich abgeschlossen.',
		5 => 'Das gespeicherte Access-Token wurde entfernt.',
		-2 => 'Die Mastodon-App konnte nicht registriert werden.',
		-3 => 'Der Autorisierungscode konnte nicht gegen ein Token getauscht werden.',
		-4 => 'Die Synchronisierung ist fehlgeschlagen. Prüfe den Status unten und fp-content/mastodon/sync.log.'
	)
);
?>