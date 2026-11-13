<form method="post" action="usercp.php?action=teamorga">
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="teamorga_do" value="add_reminder" />
	<div class="teamorga-form-body">
		<div class="teamorga-form-row">
			<label for="teamorga_reminder_title">{$lang->teamorga_ucp_reminder_title}</label>
			<input type="text" id="teamorga_reminder_title" name="reminder_title" maxlength="255" />
		</div>
		<div class="teamorga-form-row">
			<label for="teamorga_reminder_date">{$lang->teamorga_ucp_reminder_date}</label>
			<input type="date" id="teamorga_reminder_date" name="reminder_date" value="{$form_date}" />
		</div>
		<div class="teamorga-form-row">
			<label for="teamorga_reminder_note">{$lang->teamorga_ucp_reminder_note}</label>
			<textarea id="teamorga_reminder_note" name="reminder_note" rows="3"></textarea>
		</div>
		<div class="teamorga-form-actions">
			<input type="submit" class="button" value="{$lang->teamorga_ucp_reminder_submit}" />
		</div>
	</div>
</form>
