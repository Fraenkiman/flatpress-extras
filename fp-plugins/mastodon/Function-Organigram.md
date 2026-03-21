# Mastodon Plugin – PHP Function Organigram

## Purpose of this document

This document gives developers a structured overview of the Mastodon plugin PHP functions.

The layout is intentionally hierarchical so responsibilities, call paths, and important helper functions can be identified quickly.

## Notes

- The focus is the plugin itself.
- The descriptions are derived from the source code.
- The four admin methods `setup()`, `main()`, and `onsubmit()` are implemented at the end of the file inside the `AdminPanelAction` class and are therefore included here.
- The terms **Entry** and **Comment** follow the wording used by the source code.

## Main organigram / call levels

### 1. Entry points and control flow

- `setup()`
  - `plugin_mastodon_admin_assign()`

- `onsubmit()`
  - normalize and save configuration
  - register the OAuth app
  - exchange the authorization code for an access token
  - trigger a manual synchronization
  - `plugin_mastodon_admin_assign()`

- `plugin_mastodon_maybe_sync()`
  - `plugin_mastodon_run_sync()`
    - `plugin_mastodon_sync_local_to_remote()`
    - `plugin_mastodon_sync_remote_to_local()`

### 2. Local export to Mastodon

- `plugin_mastodon_sync_local_to_remote()`
  - `plugin_mastodon_list_local_entries()`
  - `plugin_mastodon_build_entry_status_text()`
  - `plugin_mastodon_collect_local_entry_media()`
  - `plugin_mastodon_upload_media_items()`
  - `plugin_mastodon_create_status()`
  - `plugin_mastodon_update_status()`
  - `plugin_mastodon_build_comment_status_text()`
  - `plugin_mastodon_resolve_comment_reply_target()`

### 3. Remote import into FlatPress

- `plugin_mastodon_sync_remote_to_local()`
  - `plugin_mastodon_verify_credentials()`
  - `plugin_mastodon_fetch_account_statuses()`
  - `plugin_mastodon_import_remote_entry()`
  - `plugin_mastodon_fetch_status_context()`
  - `plugin_mastodon_import_remote_context_descendants()`
  - `plugin_mastodon_import_remote_comment()`

### 4. Cross-cutting modules

- options, secrets, and feature toggles
- runtime cache, APCu, and persisted state files
- date/time filters for the synchronization window
- text, URL, emoji, and BBCode/HTML conversion
- media handling and file synchronization
- HTTP, OAuth, and Mastodon API access

## A. Entry points and admin integration

- `setup()` — Prepare the admin panel and assign Mastodon plugin data to Smarty.
- `main()` — Return control to the FlatPress admin panel without extra processing.
- `onsubmit()` — Handle admin form submissions, OAuth actions, configuration saves, and manual synchronization.
- `plugin_mastodon_admin_assign()` — Assign plugin data to Smarty for the admin panel.
- `plugin_mastodon_maybe_sync()` — Run the scheduled synchronization when the current request is due.
- `plugin_mastodon_run_sync()` — Run a full synchronization cycle.
- `plugin_mastodon_sync_due()` — Determine whether the scheduled synchronization is currently due.

## B. Defaults, configuration, secrets, and feature toggles

- `plugin_mastodon_default_options()` — Return the default plugin option set.
- `plugin_mastodon_default_state()` — Return the default runtime state structure.
- `plugin_mastodon_oauth_scopes()` — Return the OAuth scopes requested by the plugin.
- `plugin_mastodon_get_options()` — Load the saved plugin options and merge them with defaults.
- `plugin_mastodon_save_options()` — Persist plugin options.
- `plugin_mastodon_secret_key()` — Build the encryption key used for stored secrets.
- `plugin_mastodon_secret_encode()` — Encode a secret value before storing it in the configuration.
- `plugin_mastodon_secret_decode()` — Decode a previously stored secret value.
- `plugin_mastodon_normalize_instance_url()` — Normalize the configured Mastodon instance URL.
- `plugin_mastodon_normalize_sync_time()` — Normalize the configured daily sync time.
- `plugin_mastodon_normalize_sync_start_date()` — Normalize the configured sync start date.
- `plugin_mastodon_normalize_update_local_from_remote()` — Normalize the toggle that controls whether existing local content may be updated from remote Mastodon data.
- `plugin_mastodon_should_update_local_from_remote()` — Check whether remote Mastodon updates may overwrite already existing local FlatPress content.
- `plugin_mastodon_normalize_import_synced_comments_as_entries()` — Normalize the toggle that allows importing already synchronized local comments as entries.
- `plugin_mastodon_should_import_synced_comments_as_entries()` — Check whether a remote Mastodon status that is already mapped to a local FlatPress comment may also be imported as an entry.

## C. Caching, files, logging, and runtime state

- `plugin_mastodon_runtime_cache_get()` — Return a value from the request-local plugin cache.
- `plugin_mastodon_runtime_cache_set()` — Store a value in the request-local plugin cache.
- `plugin_mastodon_runtime_cache_clear()` — Clear one request-local plugin cache bucket or the complete cache.
- `plugin_mastodon_apcu_enabled()` — Check whether shared APCu caching is available for the plugin.
- `plugin_mastodon_apcu_cache_key()` — Build the namespaced APCu key used by this plugin.
- `plugin_mastodon_apcu_fetch()` — Fetch a value from APCu through the FlatPress namespace helper.
- `plugin_mastodon_apcu_store()` — Store a value in APCu through the FlatPress namespace helper.
- `plugin_mastodon_apcu_delete()` — Delete a value from APCu using the FlatPress namespace key builder.
- `plugin_mastodon_file_prestat()` — Read a cheap file metadata snapshot for cache validation.
- `plugin_mastodon_file_prestat_signature()` — Convert a file metadata snapshot into a stable cache signature.
- `plugin_mastodon_ensure_state_dir()` — Ensure that the plugin runtime directory exists.
- `plugin_mastodon_log()` — Append a line to the plugin sync log.
- `plugin_mastodon_state_read()` — Load the persisted runtime state from disk.
- `plugin_mastodon_state_write()` — Persist the runtime state to disk.
- `plugin_mastodon_state_normalize()` — Normalize a runtime state array and fill in missing keys.
- `plugin_mastodon_state_comment_key()` — Build the compound state key used for comment mappings.
- `plugin_mastodon_state_set_entry_mapping()` — Store the mapping between a local entry and a remote status.
- `plugin_mastodon_state_set_comment_mapping()` — Store the mapping between a local comment and a remote status.
- `plugin_mastodon_state_get_entry_meta()` — Return mapping metadata for a local entry.
- `plugin_mastodon_state_get_comment_meta()` — Return mapping metadata for a local comment.

## D. Date, time, and synchronization window helpers

- `plugin_mastodon_timestamp_date_key()` — Convert a Unix timestamp into a stable UTC date key.
- `plugin_mastodon_local_item_date_key()` — Determine the date key of a local FlatPress entry or comment.
- `plugin_mastodon_remote_status_date_key()` — Determine the date key of a remote Mastodon status.
- `plugin_mastodon_date_matches_sync_start()` — Determine whether a content date passes the configured sync start date.
- `plugin_mastodon_local_item_matches_sync_start()` — Determine whether a local FlatPress item should be synchronized.
- `plugin_mastodon_remote_status_matches_sync_start()` — Determine whether a remote Mastodon status should be synchronized.
- `plugin_mastodon_parse_iso_datetime()` — Parse an ISO date/time string into FlatPress date format.
- `plugin_mastodon_parse_iso_timestamp()` — Parse an ISO date/time value into a Unix timestamp.
- `plugin_mastodon_remote_status_timestamp()` — Resolve the best timestamp for a remote Mastodon status.

## E. Comment threading and remote status eligibility

- `plugin_mastodon_remote_status_visibility()` — Return the normalized visibility of a remote Mastodon status.
- `plugin_mastodon_remote_status_is_importable()` — Determine whether a remote Mastodon status may be imported.
- `plugin_mastodon_comment_parent_fields()` — Return the comment fields that may contain a parent reference.
- `plugin_mastodon_normalize_comment_parent_id()` — Normalize a stored local comment parent identifier.
- `plugin_mastodon_detect_local_comment_parent_id()` — Detect the local parent comment identifier from comment data.
- `plugin_mastodon_resolve_comment_reply_target()` — Resolve the remote reply target for a local comment export.

## F. Text, URL, language, and format conversion

- `plugin_mastodon_guess_subject()` — Guess a subject line from imported plain text.
- `plugin_mastodon_html_entity_decode()` — Decode HTML entities using the plugin defaults.
- `plugin_mastodon_blog_base_url()` — Return the absolute base URL of the current FlatPress installation.
- `plugin_mastodon_extract_url_token()` — Extract the URL token from a BBCode or attribute fragment.
- `plugin_mastodon_absolute_url()` — Convert a URL or path into an absolute URL when possible.
- `plugin_mastodon_lang_string()` — Return a localized plugin string or a provided fallback.
- `plugin_mastodon_emoticon_entity_to_unicode()` — Convert an emoticon HTML entity into a Unicode character.
- `plugin_mastodon_emoticon_map()` — Return the FlatPress emoticon-to-Unicode lookup map.
- `plugin_mastodon_replace_emoticon_shortcodes_with_unicode()` — Replace FlatPress emoticon shortcodes with Unicode glyphs.
- `plugin_mastodon_replace_unicode_emoticons_with_shortcodes()` — Replace Unicode emoticons with FlatPress shortcodes.
- `plugin_mastodon_is_public_host()` — Determine whether a host name resolves to a public endpoint.
- `plugin_mastodon_public_url_for_mastodon()` — Return a Mastodon-safe public URL or an empty string.
- `plugin_mastodon_plain_text_from_bbcode()` — Convert FlatPress BBCode into plain text for Mastodon export.
- `plugin_mastodon_subject_line_is_noise()` — Determine whether an extracted line should be ignored as a subject.
- `plugin_mastodon_domains_match()` — Determine whether two host names belong to the same domain family.
- `plugin_mastodon_clean_remote_html()` — Clean imported Mastodon HTML before conversion.
- `plugin_mastodon_remote_html_to_flatpress()` — Convert Mastodon HTML into FlatPress-oriented text/BBCode.
- `plugin_mastodon_flatpress_to_mastodon()` — Convert FlatPress content into Mastodon-oriented plain text.
- `plugin_mastodon_guess_entry_subject()` — Determine the best title for an imported entry.

## G. Local content access, media discovery, and hashing

- `plugin_mastodon_list_local_entries()` — Return the list of local FlatPress entries that are relevant for export.
- `plugin_mastodon_entry_file()` — Resolve the path of a local FlatPress entry file.
- `plugin_mastodon_load_local_entry()` — Load and parse a local FlatPress entry file.
- `plugin_mastodon_find_comment_file()` — Resolve the path of a local FlatPress comment file.
- `plugin_mastodon_load_local_comment()` — Load and parse a local FlatPress comment file.
- `plugin_mastodon_comment_public_url()` — Build the public URL of a local FlatPress comment.
- `plugin_mastodon_entry_permalink()` — Build the public permalink of a local FlatPress entry.
- `plugin_mastodon_media_guess_mime_type()` — Determine the MIME type for a local file.
- `plugin_mastodon_collect_local_entry_media()` — Collect local images and gallery files from an entry body.
- `plugin_mastodon_build_media_signature()` — Build a stable change signature for the media attached to an entry.
- `plugin_mastodon_entry_hash()` — Build the local export hash of an entry.
- `plugin_mastodon_comment_hash()` — Build the local export hash of a comment.

## H. HTTP, OAuth, Mastodon API, and media transfer

- `plugin_mastodon_http_request()` — Perform an HTTP request against Mastodon or a related file endpoint.
- `plugin_mastodon_api_url()` — Build an absolute Mastodon API URL from the configured instance URL.
- `plugin_mastodon_verify_credentials()` — Validate the current access token against Mastodon.
- `plugin_mastodon_register_app()` — Register the OAuth application on the Mastodon instance.
- `plugin_mastodon_authorize_url()` — Build the interactive Mastodon authorization URL.
- `plugin_mastodon_exchange_code()` — Exchange the returned authorization code for an access token.
- `plugin_mastodon_fetch_account_statuses()` — Fetch account statuses for remote import.
- `plugin_mastodon_fetch_status_context()` — Fetch the thread context of a Mastodon status.
- `plugin_mastodon_instance_configuration()` — Fetch and cache Mastodon instance limits and capabilities.
- `plugin_mastodon_create_status()` — Create a new Mastodon status.
- `plugin_mastodon_update_status()` — Update an existing Mastodon status.
- `plugin_mastodon_upload_media_file()` — Upload one local media file to Mastodon.
- `plugin_mastodon_upload_media_items()` — Upload multiple local media files and return their media IDs.
- `plugin_mastodon_download_remote_media()` — Download remote Mastodon media into FlatPress storage.

## I. Import and export builders

- `plugin_mastodon_build_entry_status_text()` — Build the exported Mastodon text for a FlatPress entry.
- `plugin_mastodon_build_comment_status_text()` — Build the exported Mastodon text for a FlatPress comment.
- `plugin_mastodon_import_remote_entry()` — Import one Mastodon status as a FlatPress entry.
- `plugin_mastodon_import_remote_comment()` — Import one Mastodon reply as a FlatPress comment.
- `plugin_mastodon_import_remote_context_descendants()` — Import reply descendants from a Mastodon thread context.
- `plugin_mastodon_import_known_entry_contexts()` — Refresh already known remote entry contexts to detect newly added replies on older threads.

## J. Synchronization orchestration

- `plugin_mastodon_sync_local_to_remote()` — Export local FlatPress entries and comments to Mastodon.
- `plugin_mastodon_sync_remote_to_local()` — Import Mastodon statuses and replies into FlatPress.

## K. Admin panel class methods

- `setup()` — Register the plugin panel, load data, and prepare the UI state.
- `main()` — Keep the admin panel lifecycle compatible with FlatPress.
- `onsubmit()` — Process all form actions, configuration updates, OAuth actions, and manual sync triggers.

## Developer reading order

For new developers, this reading order is usually the quickest way to understand the plugin:

1. `plugin_mastodon_run_sync()`
2. `plugin_mastodon_sync_local_to_remote()`
3. `plugin_mastodon_sync_remote_to_local()`
4. `plugin_mastodon_import_remote_entry()`
5. `plugin_mastodon_import_remote_comment()`
6. `plugin_mastodon_build_entry_status_text()`
7. `plugin_mastodon_build_comment_status_text()`
8. `plugin_mastodon_state_read()` and `plugin_mastodon_state_write()`
9. `plugin_mastodon_get_options()`
10. `plugin_mastodon_http_request()`

## Practical architecture summary

The plugin consists of five major layers:

1. **Admin and scheduling layer**  
   Configuration, OAuth actions, manual sync, scheduled sync.

2. **Synchronization orchestration layer**  
   Decides when and how local and remote content is processed.

3. **Transformation layer**  
   Converts FlatPress text, BBCode, emojis, URLs, and media references into Mastodon format and back.

4. **Persistence and mapping layer**  
   Stores configuration, secrets, runtime state, mapping metadata, and sync history.

5. **Transport layer**  
   Handles HTTP, OAuth, Mastodon API endpoints, media uploads, and media downloads.

## Maintenance notes

- Changes to entry or comment mapping usually affect both sync directions.
- Changes to text conversion should be tested for entries, comments, emojis, links, and media placeholders.
- Changes to date logic should be tested with the sync start date and with already synchronized older threads.
- Changes to media handling should be tested with single images, galleries, missing files, and limited Mastodon scopes.
- Changes to runtime state should always be checked against `state.json`, log output, and repeated sync runs.
