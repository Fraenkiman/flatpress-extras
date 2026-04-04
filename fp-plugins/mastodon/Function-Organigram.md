# Mastodon Plugin – PHP Function Organigram

## Purpose of this document

This document gives developers a structured overview of the Mastodon plugin PHP functions.

The layout is intentionally hierarchical so responsibilities, call paths, and important helper functions can be identified quickly.

## Notes

- The focus is the plugin itself.
- The descriptions are derived from the source code.
- The four admin methods `setup()`, `main()`, and `onsubmit()` are implemented at the end of the file inside the `AdminPanelAction` class and are therefore included here.
- The terms **Entry** and **Comment** follow the wording used by the source code.

## Function count

The plugin file currently contains **145** callable functions/methods documented in this organigram:
- **142** top-level plugin functions
- **3** admin panel class methods (`setup()`, `main()`, `onsubmit()`)

## High-level call flow

### Frontend and scheduler entry points

- `plugin_mastodon_head()`  
  Reads the saved Mastodon configuration, normalizes the instance URL and username, and emits:
  - `<link rel="me" ...>`
  - `<meta name="fediverse:creator" ...>`

- `plugin_mastodon_maybe_sync()`  
  Calls `plugin_mastodon_sync_due()` and, if due, runs `plugin_mastodon_run_sync()`.

- `plugin_mastodon_run_sync()`  
  Executes the two main directions in order:
  1. `plugin_mastodon_sync_local_to_remote()`
  2. `plugin_mastodon_sync_remote_to_local()`

### Local → remote export path

- `plugin_mastodon_sync_local_to_remote()`
  - loads options and persisted state
  - retrieves export candidates with `plugin_mastodon_list_local_entries()`
  - orders entries with `plugin_mastodon_compare_local_entries_for_export()`
  - builds entry text with `plugin_mastodon_build_entry_status_text()`
  - collects local images and galleries with `plugin_mastodon_collect_local_entry_media()`
  - uploads media through `plugin_mastodon_upload_media_items()`
  - creates or updates Mastodon statuses with `plugin_mastodon_create_status()` / `plugin_mastodon_update_status()`
  - builds comment replies with `plugin_mastodon_build_comment_status_text()`
  - resolves reply targets with `plugin_mastodon_resolve_comment_reply_target()`
  - persists mappings with `plugin_mastodon_state_set_entry_mapping()` and `plugin_mastodon_state_set_comment_mapping()`

### Remote → local import path

- `plugin_mastodon_sync_remote_to_local()`
  - validates credentials with `plugin_mastodon_verify_credentials()`
  - fetches account statuses with `plugin_mastodon_fetch_account_statuses()`
  - imports top-level statuses with `plugin_mastodon_import_remote_entry()`
  - fetches thread context with `plugin_mastodon_fetch_status_context()`
  - imports replies with `plugin_mastodon_import_remote_context_descendants()`
  - imports individual replies through `plugin_mastodon_import_remote_comment()`
  - refreshes known older threads with `plugin_mastodon_collect_known_entry_context_targets()`

### Admin panel flow

- `setup()` assigns the plugin data for the Smarty view.
- `main()` keeps the FlatPress admin lifecycle stable.
- `onsubmit()` handles:
  - configuration normalization and storage
  - OAuth app registration
  - authorization code exchange
  - manual synchronization


## A. Entry points and admin integration

- `plugin_mastodon_head()` — Print Mastodon profile metadata into the HTML head.
- `plugin_mastodon_maybe_sync()` — Run the scheduled synchronization when the current request is due.
- `plugin_mastodon_run_sync()` — Run a full synchronization cycle.
- `plugin_mastodon_sync_due()` — Determine whether the scheduled synchronization is currently due.
- `plugin_mastodon_admin_assign()` — Assign plugin data to Smarty for the admin panel.
- `setup()` — Register the Mastodon admin panel template and assign plugin data to Smarty.
- `main()` — Keep the admin panel lifecycle compatible with FlatPress without extra processing.
- `onsubmit()` — Process configuration saves, OAuth actions, and the manual synchronization trigger.

## B. Defaults, configuration, secrets, and feature toggles

- `plugin_mastodon_default_options()` — Return the default plugin option values.
- `plugin_mastodon_default_state()` — Return the default runtime state structure.
- `plugin_mastodon_oauth_scopes()` — Return the OAuth scopes requested by the plugin.
- `plugin_mastodon_get_options()` — Load the saved plugin options and merge them with defaults.
- `plugin_mastodon_save_options()` — Persist plugin options.
- `plugin_mastodon_secret_key()` — Build the encryption key used for stored secrets.
- `plugin_mastodon_secret_encode()` — Encode a secret value before storing it in the configuration.
- `plugin_mastodon_secret_decode()` — Decode a previously stored secret value.
- `plugin_mastodon_normalize_instance_url()` — Normalize the configured Mastodon instance URL.
- `plugin_mastodon_normalize_head_username()` — Normalize the configured Mastodon username for HTML head metadata.
- `plugin_mastodon_instance_authority()` — Return the Mastodon instance authority used in fediverse creator metadata.
- `plugin_mastodon_profile_url()` — Build the public Mastodon profile URL used for the rel-me link.
- `plugin_mastodon_fediverse_creator_value()` — Build the fediverse creator meta value.
- `plugin_mastodon_normalize_sync_time()` — Normalize the configured daily sync time.
- `plugin_mastodon_normalize_sync_start_date()` — Normalize the configured sync start date.
- `plugin_mastodon_normalize_update_local_from_remote()` — Normalize the toggle that controls whether existing local content may be updated from remote Mastodon data.
- `plugin_mastodon_should_update_local_from_remote()` — Check whether remote Mastodon updates may overwrite already existing local FlatPress content.
- `plugin_mastodon_normalize_import_synced_comments_as_entries()` — Normalize the toggle that allows importing already synchronized local comments as entries.
- `plugin_mastodon_should_import_synced_comments_as_entries()` — Check whether a remote Mastodon status that is already mapped to a local FlatPress comment may also be imported as an entry.

## C. Caching, filesystem helpers, logging, and persisted state

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
- `plugin_mastodon_state_normalize()` —— Normalize a runtime state array and fill in missing keys.
- `plugin_mastodon_state_comment_key()` — Build the compound state key used for comment mappings.
- `plugin_mastodon_state_set_entry_mapping()` — Store the mapping between a local entry and a remote status.
- `plugin_mastodon_state_set_comment_mapping()` — Store the mapping between a local comment and a remote status.
- `plugin_mastodon_state_get_entry_meta()` — Return mapping metadata for a local entry.
- `plugin_mastodon_state_get_comment_meta()` — Return mapping metadata for a local comment.

## D. Date, timestamp, visibility, and threading helpers

- `plugin_mastodon_timestamp_date_key()` — Convert a Unix timestamp into a stable UTC date key.
- `plugin_mastodon_local_item_date_key()` — Determine the date key of a local FlatPress entry or comment.
- `plugin_mastodon_remote_status_date_key()` — Determine the date key of a remote Mastodon status.
- `plugin_mastodon_date_matches_sync_start()` — Determine whether a content date passes the configured sync start date.
- `plugin_mastodon_local_item_matches_sync_start()` — Determine whether a local FlatPress item should be synchronized.
- `plugin_mastodon_remote_status_matches_sync_start()` — Determine whether a remote Mastodon status should be synchronized.
- `plugin_mastodon_parse_iso_datetime()` — Parse an ISO date/time string into FlatPress date format.
- `plugin_mastodon_parse_iso_timestamp()` — Parse an ISO date/time value into a Unix timestamp.
- `plugin_mastodon_remote_status_timestamp()` — Resolve the best timestamp for a remote Mastodon status.
- `plugin_mastodon_remote_status_visibility()` — Return the normalized visibility of a remote Mastodon status.
- `plugin_mastodon_remote_status_is_importable()` — Determine whether a remote Mastodon status may be imported.
- `plugin_mastodon_comment_parent_fields()` — Return the comment fields that may contain a parent reference.
- `plugin_mastodon_normalize_comment_parent_id()` — Normalize a stored local comment parent identifier.
- `plugin_mastodon_detect_local_comment_parent_id()` — Detect the local parent comment identifier from comment data.
- `plugin_mastodon_resolve_comment_reply_target()` — Resolve the remote reply target for a local comment export.

## E. Text, URLs, language strings, tags, emojis, and BBCode/HTML conversion

- `plugin_mastodon_guess_subject()` — Guess a subject line from imported plain text.
- `plugin_mastodon_html_entity_decode()` — Decode HTML entities using the plugin defaults.
- `plugin_mastodon_blog_base_url()` — Return the absolute base URL of the current FlatPress installation.
- `plugin_mastodon_extract_url_token()` — Extract the URL token from a BBCode or attribute fragment.
- `plugin_mastodon_absolute_url()` — Convert a URL or path into an absolute URL when possible.
- `plugin_mastodon_lang_string()` — Return a localized plugin string or a provided fallback.
- `plugin_mastodon_tag_plugin_active()` — Determine whether the Tag plugin is active for the current FlatPress request.
- `plugin_mastodon_normalize_tag_list()` — Normalize a list of tag labels.
- `plugin_mastodon_extract_flatpress_tags()` — Extract FlatPress Tag plugin labels from an entry body.
- `plugin_mastodon_strip_flatpress_tag_bbcode()` — Remove Tag plugin BBCode blocks from entry content.
- `plugin_mastodon_mastodon_hashtag_footer()` — Convert FlatPress tag labels into a Mastodon hashtag footer line.
- `plugin_mastodon_remote_status_tags()` — Collect remote Mastodon tags from a status entity.
- `plugin_mastodon_strip_trailing_mastodon_hashtag_footer()` — Remove a trailing Mastodon hashtag footer from imported plain text.
- `plugin_mastodon_build_flatpress_tag_bbcode()` — Build Tag plugin BBCode from a list of remote Mastodon tags.
- `plugin_mastodon_emoticon_entity_to_unicode()` — Convert an emoticon HTML entity into a Unicode character.
- `plugin_mastodon_emoticon_map()` — Return the FlatPress emoticon-to-Unicode lookup map.
- `plugin_mastodon_replace_emoticon_shortcodes_with_unicode()` — Replace FlatPress emoticon shortcodes with Unicode glyphs.
- `plugin_mastodon_replace_unicode_emoticons_with_shortcodes()` — Replace Unicode emoticons with FlatPress shortcodes.
- `plugin_mastodon_is_public_host()` — Determine whether a host name resolves to a public endpoint.
- `plugin_mastodon_public_url_for_mastodon()` — Return a Mastodon-safe public URL or an empty string.
- `plugin_mastodon_plain_text_from_bbcode()` — Convert FlatPress BBCode into plain text for Mastodon export.
- `plugin_mastodon_subject_line_is_noise()` — Determine whether an extracted line should be ignored as a subject.
- `plugin_mastodon_domains_match()` — Determine whether two host names belong to the same domain family.
- `plugin_mastodon_cleanup_imported_text()` — Clean imported text before saving it to FlatPress.
- `plugin_mastodon_dom_children_to_flatpress()` — Convert DOM child nodes into FlatPress BBCode text.
- `plugin_mastodon_dom_node_to_flatpress()` — Convert a single DOM node into FlatPress BBCode text.
- `plugin_mastodon_public_entry_url()` — Return the public URL for a FlatPress entry.
- `plugin_mastodon_public_comments_url()` — Return the public comments URL for a FlatPress entry.
- `plugin_mastodon_public_comment_url()` — Return the public URL for a specific FlatPress comment.
- `plugin_mastodon_mastodon_html_to_flatpress()` — Convert Mastodon HTML content into FlatPress BBCode.
- `plugin_mastodon_flatpress_to_mastodon()` — Convert FlatPress content into Mastodon-ready plain text.
- `plugin_mastodon_limit_text()` — Limit text to a maximum number of characters.

## F. Local content access, media processing, hashing, and export ordering

- `plugin_mastodon_entry_hash()` — Build a change-detection hash for a FlatPress entry.
- `plugin_mastodon_comment_hash()` — Build a change-detection hash for a FlatPress comment.
- `plugin_mastodon_safe_path_component()` — Sanitize a string so it can be used as a path component.
- `plugin_mastodon_safe_filename()` — Sanitize a file name for local storage.
- `plugin_mastodon_media_relative_to_absolute()` — Resolve a FlatPress media path to an absolute file path.
- `plugin_mastodon_media_prepare_directory()` — Ensure that a media directory exists.
- `plugin_mastodon_media_delete_tree()` — Delete a directory tree used for imported media.
- `plugin_mastodon_media_copy_tree()` — Copy a directory tree used for media synchronization.
- `plugin_mastodon_bbcode_attr_escape()` — Escape a value for safe BBCode attribute usage.
- `plugin_mastodon_media_guess_mime_type()` — Guess the MIME type of a local media file.
- `plugin_mastodon_media_parse_tag_attributes()` — Parse key/value attributes from a FlatPress media tag.
- `plugin_mastodon_collect_local_entry_media()` — Collect local images referenced by an entry or gallery tag.
- `plugin_mastodon_entry_media_signature()` — Build a signature for media references contained in entry content.
- `plugin_mastodon_remote_status_image_attachments()` — Extract image attachments from a remote Mastodon status.
- `plugin_mastodon_remote_media_source_url()` — Resolve the best downloadable source URL for a remote attachment.
- `plugin_mastodon_remote_media_description()` — Resolve the best description for a remote attachment.
- `plugin_mastodon_media_download()` — Download a remote media asset.
- `plugin_mastodon_build_imported_media_bbcode()` — Build FlatPress BBCode for imported remote media attachments.
- `plugin_mastodon_collect_entry_files()` — Collect entry files recursively from the FlatPress content tree.
- `plugin_mastodon_local_item_timestamp()` — Resolve the best timestamp for a local FlatPress item.
- `plugin_mastodon_compare_local_entries_for_export()` — Compare local FlatPress entries for Mastodon export order.
- `plugin_mastodon_list_local_entries()` — List local FlatPress entry identifiers.

## G. HTTP transport, OAuth, Mastodon API calls, and media upload

- `plugin_mastodon_parse_http_response_headers()` — Parse raw HTTP response headers.
- `plugin_mastodon_stream_context_request()` — Perform an HTTP request through a stream context fallback.
- `plugin_mastodon_http_build_query()` — Build an application/x-www-form-urlencoded query string.
- `plugin_mastodon_http_request()` — Perform an HTTP request using cURL or the stream fallback.
- `plugin_mastodon_mastodon_api()` — Call the Mastodon API and return the raw HTTP response.
- `plugin_mastodon_mastodon_json()` — Call the Mastodon API and decode a JSON response.
- `plugin_mastodon_response_error_message()` — Extract the most useful error message from an API response.
- `plugin_mastodon_register_app()` — Register the FlatPress application on the configured Mastodon instance.
- `plugin_mastodon_build_authorize_url()` — Build the OAuth authorization URL.
- `plugin_mastodon_exchange_code_for_token()` — Exchange an OAuth authorization code for an access token.
- `plugin_mastodon_verify_credentials()` — Verify the currently configured access token.
- `plugin_mastodon_instance_configuration()` — Load and cache the Mastodon instance configuration document.
- `plugin_mastodon_instance_media_limit()` — Return the media attachment limit of the configured instance.
- `plugin_mastodon_instance_media_description_limit()` — Return the media description length limit of the configured instance.
- `plugin_mastodon_http_request_multipart()` — Perform a multipart HTTP request.
- `plugin_mastodon_upload_media_items()` — Upload local media items to Mastodon and collect the created media IDs.
- `plugin_mastodon_instance_character_limit()` — Return the status character limit of the configured instance.
- `plugin_mastodon_fetch_account_statuses()` — Fetch statuses for the authenticated Mastodon account.
- `plugin_mastodon_fetch_status_context()` — Fetch the conversation context for a Mastodon status.
- `plugin_mastodon_create_status()` — Create a Mastodon status.
- `plugin_mastodon_update_status()` — Update an existing Mastodon status.

## H. Import/export builders and synchronization orchestration

- `plugin_mastodon_build_entry_status_text()` — Build the status body used when exporting a FlatPress entry.
- `plugin_mastodon_build_comment_status_text()` — Build the status body used when exporting a FlatPress comment.
- `plugin_mastodon_import_remote_entry()` — Import a remote Mastodon status into FlatPress as an entry.
- `plugin_mastodon_import_remote_comment()` — Import a remote Mastodon reply into FlatPress as a comment.
- `plugin_mastodon_import_remote_context_descendants()` — Import remote Mastodon replies from a fetched thread context.
- `plugin_mastodon_collect_known_entry_context_targets()` — Collect known synchronized entry threads that should have their Mastodon reply context refreshed.
- `plugin_mastodon_sync_remote_to_local()` — Synchronize remote Mastodon content into FlatPress.
- `plugin_mastodon_sync_local_to_remote()` — Synchronize local FlatPress content to Mastodon.

## Recommended reading order for new developers

A practical way to understand the plugin is:

1. `plugin_mastodon_run_sync()`
2. `plugin_mastodon_sync_local_to_remote()`
3. `plugin_mastodon_sync_remote_to_local()`
4. `plugin_mastodon_build_entry_status_text()`
5. `plugin_mastodon_build_comment_status_text()`
6. `plugin_mastodon_import_remote_entry()`
7. `plugin_mastodon_import_remote_comment()`
8. `plugin_mastodon_state_read()` / `plugin_mastodon_state_write()`
9. `plugin_mastodon_get_options()`
10. `plugin_mastodon_http_request()`
11. `plugin_mastodon_collect_local_entry_media()`
12. `plugin_mastodon_instance_configuration()`

## Current feature areas reflected in the function set

The plugin includes dedicated function groups for:

- Mastodon identity metadata in the HTML head
- runtime cache and optional APCu-backed shared cache
- encrypted storage of sensitive configuration values
- sync start date filtering
- optional remote overwrite of existing local content
- optional import of already synchronized comments as entries
- tag synchronization through the FlatPress Tag plugin BBCode
- emoji conversion between FlatPress-style shortcodes and Mastodon-style Unicode
- bidirectional media synchronization for entry images and galleries
- old-thread reply refresh during remote import
- explicit export ordering so older local entries are posted before newer ones

## Maintenance notes

When changing the plugin, these clusters usually need to stay in sync:

- **configuration + admin UI + normalization helpers**
- **state mappings + import/export code**
- **text conversion + media conversion + hashtag handling**
- **date filters + scheduling + known-thread refresh**
- **HTTP transport + OAuth + instance capability caching**

A change in one of these areas often requires corresponding updates in the simulation script.

## Alphabetical appendix

The following appendix lists every function once more in alphabetical order for quick lookup.

- `main()` — line 4372
- `onsubmit()` — line 4376
- `plugin_mastodon_absolute_url()` — line 1259
- `plugin_mastodon_admin_assign()` — line 4335
- `plugin_mastodon_apcu_cache_key()` — line 214
- `plugin_mastodon_apcu_delete()` — line 258
- `plugin_mastodon_apcu_enabled()` — line 205
- `plugin_mastodon_apcu_fetch()` — line 228
- `plugin_mastodon_apcu_store()` — line 245
- `plugin_mastodon_bbcode_attr_escape()` — line 2399
- `plugin_mastodon_blog_base_url()` — line 1203
- `plugin_mastodon_build_authorize_url()` — line 3487
- `plugin_mastodon_build_comment_status_text()` — line 3729
- `plugin_mastodon_build_entry_status_text()` — line 3662
- `plugin_mastodon_build_flatpress_tag_bbcode()` — line 1523
- `plugin_mastodon_build_imported_media_bbcode()` — line 2691
- `plugin_mastodon_cleanup_imported_text()` — line 1763
- `plugin_mastodon_collect_entry_files()` — line 3043
- `plugin_mastodon_collect_known_entry_context_targets()` — line 4022
- `plugin_mastodon_collect_local_entry_media()` — line 2478
- `plugin_mastodon_comment_hash()` — line 2259
- `plugin_mastodon_comment_parent_fields()` — line 1082
- `plugin_mastodon_compare_local_entries_for_export()` — line 3100
- `plugin_mastodon_create_status()` — line 3626
- `plugin_mastodon_date_matches_sync_start()` — line 768
- `plugin_mastodon_default_options()` — line 86
- `plugin_mastodon_default_state()` — line 107
- `plugin_mastodon_detect_local_comment_parent_id()` — line 1108
- `plugin_mastodon_dom_children_to_flatpress()` — line 1817
- `plugin_mastodon_dom_node_to_flatpress()` — line 1835
- `plugin_mastodon_domains_match()` — line 1749
- `plugin_mastodon_emoticon_entity_to_unicode()` — line 1537
- `plugin_mastodon_emoticon_map()` — line 1550
- `plugin_mastodon_ensure_state_dir()` — line 805
- `plugin_mastodon_entry_hash()` — line 2247
- `plugin_mastodon_entry_media_signature()` — line 2601
- `plugin_mastodon_exchange_code_for_token()` — line 3507
- `plugin_mastodon_extract_flatpress_tags()` — line 1366
- `plugin_mastodon_extract_url_token()` — line 1242
- `plugin_mastodon_fediverse_creator_value()` — line 559
- `plugin_mastodon_fetch_account_statuses()` — line 3566
- `plugin_mastodon_fetch_status_context()` — line 3613
- `plugin_mastodon_file_prestat()` — line 271
- `plugin_mastodon_file_prestat_signature()` — line 291
- `plugin_mastodon_flatpress_to_mastodon()` — line 2110
- `plugin_mastodon_get_options()` — line 302
- `plugin_mastodon_guess_subject()` — line 1152
- `plugin_mastodon_head()` — line 577
- `plugin_mastodon_html_entity_decode()` — line 1194
- `plugin_mastodon_http_build_query()` — line 3227
- `plugin_mastodon_http_request()` — line 3264
- `plugin_mastodon_http_request_multipart()` — line 2847
- `plugin_mastodon_import_remote_comment()` — line 3866
- `plugin_mastodon_import_remote_context_descendants()` — line 3938
- `plugin_mastodon_import_remote_entry()` — line 3766
- `plugin_mastodon_instance_authority()` — line 513
- `plugin_mastodon_instance_character_limit()` — line 3551
- `plugin_mastodon_instance_configuration()` — line 2788
- `plugin_mastodon_instance_media_description_limit()` — line 2831
- `plugin_mastodon_instance_media_limit()` — line 2818
- `plugin_mastodon_is_public_host()` — line 1637
- `plugin_mastodon_lang_string()` — line 1301
- `plugin_mastodon_limit_text()` — line 2225
- `plugin_mastodon_list_local_entries()` — line 3118
- `plugin_mastodon_local_item_date_key()` — line 721
- `plugin_mastodon_local_item_matches_sync_start()` — line 787
- `plugin_mastodon_local_item_timestamp()` — line 3074
- `plugin_mastodon_log()` — line 814
- `plugin_mastodon_mastodon_api()` — line 3377
- `plugin_mastodon_mastodon_hashtag_footer()` — line 1408
- `plugin_mastodon_mastodon_html_to_flatpress()` — line 2008
- `plugin_mastodon_mastodon_json()` — line 3421
- `plugin_mastodon_maybe_sync()` — line 4319
- `plugin_mastodon_media_copy_tree()` — line 2361
- `plugin_mastodon_media_delete_tree()` — line 2334
- `plugin_mastodon_media_download()` — line 2679
- `plugin_mastodon_media_guess_mime_type()` — line 2411
- `plugin_mastodon_media_parse_tag_attributes()` — line 2447
- `plugin_mastodon_media_prepare_directory()` — line 2318
- `plugin_mastodon_media_relative_to_absolute()` — line 2305
- `plugin_mastodon_normalize_comment_parent_id()` — line 1091
- `plugin_mastodon_normalize_head_username()` — line 474
- `plugin_mastodon_normalize_import_synced_comments_as_entries()` — line 682
- `plugin_mastodon_normalize_instance_url()` — line 441
- `plugin_mastodon_normalize_sync_start_date()` — line 631
- `plugin_mastodon_normalize_sync_time()` — line 613
- `plugin_mastodon_normalize_tag_list()` — line 1335
- `plugin_mastodon_normalize_update_local_from_remote()` — line 657
- `plugin_mastodon_oauth_scopes()` — line 133
- `plugin_mastodon_parse_http_response_headers()` — line 3155
- `plugin_mastodon_parse_iso_datetime()` — line 998
- `plugin_mastodon_parse_iso_timestamp()` — line 1016
- `plugin_mastodon_plain_text_from_bbcode()` — line 1679
- `plugin_mastodon_profile_url()` — line 543
- `plugin_mastodon_public_comment_url()` — line 1993
- `plugin_mastodon_public_comments_url()` — line 1965
- `plugin_mastodon_public_entry_url()` — line 1938
- `plugin_mastodon_public_url_for_mastodon()` — line 1660
- `plugin_mastodon_register_app()` — line 3465
- `plugin_mastodon_remote_media_description()` — line 2664
- `plugin_mastodon_remote_media_source_url()` — line 2650
- `plugin_mastodon_remote_status_date_key()` — line 741
- `plugin_mastodon_remote_status_image_attachments()` — line 2627
- `plugin_mastodon_remote_status_is_importable()` — line 1070
- `plugin_mastodon_remote_status_matches_sync_start()` — line 797
- `plugin_mastodon_remote_status_tags()` — line 1429
- `plugin_mastodon_remote_status_timestamp()` — line 1038
- `plugin_mastodon_remote_status_visibility()` — line 1057
- `plugin_mastodon_replace_emoticon_shortcodes_with_unicode()` — line 1604
- `plugin_mastodon_replace_unicode_emoticons_with_shortcodes()` — line 1615
- `plugin_mastodon_resolve_comment_reply_target()` — line 1130
- `plugin_mastodon_response_error_message()` — line 3436
- `plugin_mastodon_run_sync()` — line 4261
- `plugin_mastodon_runtime_cache_clear()` — line 190
- `plugin_mastodon_runtime_cache_get()` — line 144
- `plugin_mastodon_runtime_cache_set()` — line 172
- `plugin_mastodon_safe_filename()` — line 2292
- `plugin_mastodon_safe_path_component()` — line 2277
- `plugin_mastodon_save_options()` — line 333
- `plugin_mastodon_secret_decode()` — line 410
- `plugin_mastodon_secret_encode()` — line 387
- `plugin_mastodon_secret_key()` — line 367
- `plugin_mastodon_should_import_synced_comments_as_entries()` — line 698
- `plugin_mastodon_should_update_local_from_remote()` — line 673
- `plugin_mastodon_state_comment_key()` — line 908
- `plugin_mastodon_state_get_comment_meta()` — line 988
- `plugin_mastodon_state_get_entry_meta()` — line 976
- `plugin_mastodon_state_normalize()` — line 890
- `plugin_mastodon_state_read()` — line 824
- `plugin_mastodon_state_set_comment_mapping()` — line 951
- `plugin_mastodon_state_set_entry_mapping()` — line 923
- `plugin_mastodon_state_write()` — line 866
- `plugin_mastodon_stream_context_request()` — line 3188
- `plugin_mastodon_strip_flatpress_tag_bbcode()` — line 1391
- `plugin_mastodon_strip_trailing_mastodon_hashtag_footer()` — line 1456
- `plugin_mastodon_subject_line_is_noise()` — line 1721
- `plugin_mastodon_sync_due()` — line 4239
- `plugin_mastodon_sync_local_to_remote()` — line 4112
- `plugin_mastodon_sync_remote_to_local()` — line 4056
- `plugin_mastodon_tag_plugin_active()` — line 1325
- `plugin_mastodon_timestamp_date_key()` — line 707
- `plugin_mastodon_update_status()` — line 3646
- `plugin_mastodon_upload_media_items()` — line 2978
- `plugin_mastodon_verify_credentials()` — line 3534
- `setup()` — line 4367
