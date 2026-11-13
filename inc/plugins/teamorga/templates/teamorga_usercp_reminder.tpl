<li class="teamorga-ucp-item teamorga-ucp-reminder {$rem_class}">
	<div class="teamorga-ucp-head">
		<span class="teamorga-ucp-badge teamorga-ucp-reminder-badge">{$lang->teamorga_ucp_badge_reminder}</span>
		<strong class="teamorga-ucp-title">{$rem_title}</strong>
		{$rem_badge}
	</div>
	<div class="teamorga-ucp-meta">{$rem_date}</div>
	<div class="teamorga-ucp-note">{$rem_note}</div>
	<div class="teamorga-ucp-actions">
		<form method="post" action="usercp.php?action=teamorga" style="display:inline;">
			<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
			<input type="hidden" name="teamorga_do" value="toggle_reminder" />
			<input type="hidden" name="reminder_id" value="{$rem_id}" />
			<input type="submit" class="button" value="{$rem_toggle_lbl}" />
		</form>
		<form method="post" action="usercp.php?action=teamorga" style="display:inline;" onsubmit="return confirm('{$rem_delete_confirm}');">
			<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
			<input type="hidden" name="teamorga_do" value="delete_reminder" />
			<input type="hidden" name="reminder_id" value="{$rem_id}" />
			<input type="submit" class="button" value="{$lang->teamorga_ucp_reminder_delete}" />
		</form>
	</div>
</li>
