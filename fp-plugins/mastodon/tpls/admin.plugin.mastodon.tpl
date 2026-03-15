<h2>{$plang.head|escape}</h2>

{include file="shared:errorlist.tpl"}

<p>{$plang.intro|escape}</p>

{html_form class="option-set"}
	<h3>{$plang.config_head|escape}</h3>
	<dl class="option-list">
		<dt><label for="instance_url">{$plang.instance_url|escape}</label></dt>
		<dd><input type="url" class="regular-text" id="instance_url" name="instance_url" value="{$mastodon_cfg.instance_url|escape}"></dd>

		<dt><label for="sync_time">{$plang.sync_time|escape}</label></dt>
		<dd><input type="time" id="sync_time" name="sync_time" value="{$mastodon_cfg.sync_time|escape}"></dd>
	</dl>

	<div class="buttonbar">
		<input type="submit" name="mastodon_save" value="{$plang.save|escape}">
	</div>

	<h3>{$plang.oauth_head|escape}</h3>
	<p>{$plang.oauth_desc|escape}</p>
	<div class="buttonbar">
		<input type="submit" name="mastodon_register_app" value="{$plang.register_app|escape}">
	</div>

	<dl class="option-list">
		<dt>{$plang.authorize_url|escape}</dt>
		<dd>
			{if $mastodon_authorize_url}
				<input type="url" readonly="readonly" class="regular-text" value="{$mastodon_authorize_url|escape}">
			{else}
				<em>-</em>
			{/if}
		</dd>

		<dt><label for="authorization_code">{$plang.authorization_code|escape}</label></dt>
		<dd><input type="text" class="regular-text" id="authorization_code" name="authorization_code" value=""></dd>
	</dl>

	<div class="buttonbar">
		<input type="submit" name="mastodon_exchange_code" value="{$plang.exchange_code|escape}">
		<input type="submit" name="mastodon_clear_token" value="{$plang.clear_token|escape}">
	</div>

	<h3>{$plang.run_now_head|escape}</h3>
	<div class="buttonbar">
		<input type="submit" name="mastodon_run_now" value="{$plang.run_now|escape}">
	</div>
{/html_form}

<h3>{$plang.status_head|escape}</h3>
<table>
	<tbody>
		<tr>
			<th>{$plang.temp_dir|escape}</th>
			<td><code>{$mastodon_temp_dir|escape}</code></td>
		</tr>
		<tr>
			<th>{$plang.token_state|escape}</th>
			<td>{if $mastodon_cfg.access_token}{$plang.token_available|escape}{else}{$plang.token_missing|escape}{/if}</td>
		</tr>
		<tr>
			<th>{$plang.last_run|escape}</th>
			<td>{if $mastodon_state.last_run}{$mastodon_state.last_run|escape}{else}-{/if}</td>
		</tr>
		<tr>
			<th>{$plang.last_error|escape}</th>
			<td>{if $mastodon_state.last_error}{$mastodon_state.last_error|escape}{else}-{/if}</td>
		</tr>
	</tbody>
</table>

<h3>{$plang.stats_head|escape}</h3>
<table>
	<tbody>
		<tr><th>{$plang.stats_imported_entries|escape}</th><td>{$mastodon_state.stats.imported_entries|default:0}</td></tr>
		<tr><th>{$plang.stats_updated_entries|escape}</th><td>{$mastodon_state.stats.updated_entries|default:0}</td></tr>
		<tr><th>{$plang.stats_exported_entries|escape}</th><td>{$mastodon_state.stats.exported_entries|default:0}</td></tr>
		<tr><th>{$plang.stats_updated_remote_entries|escape}</th><td>{$mastodon_state.stats.updated_remote_entries|default:0}</td></tr>
		<tr><th>{$plang.stats_imported_comments|escape}</th><td>{$mastodon_state.stats.imported_comments|default:0}</td></tr>
		<tr><th>{$plang.stats_exported_comments|escape}</th><td>{$mastodon_state.stats.exported_comments|default:0}</td></tr>
		<tr><th>{$plang.stats_updated_remote_comments|escape}</th><td>{$mastodon_state.stats.updated_remote_comments|default:0}</td></tr>
	</tbody>
</table>
