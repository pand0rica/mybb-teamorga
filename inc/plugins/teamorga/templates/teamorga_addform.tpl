<div class="teamorga-box">
	<h3>{$lang->teamorga_add_title}</h3>
	<form method="post" action="showthread.php?tid={$teamorga_addform_tid}">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
		<input type="hidden" name="teamorga_action" value="save_add" />
		<div class="teamorga-form-body">
			<div class="teamorga-form-row">
				<label for="teamorga_deadline">{$lang->teamorga_deadline}</label>
				<input type="date" id="teamorga_deadline" name="teamorga_deadline" value="{$teamorga_addform_deadline}" />
			</div>
			<div class="teamorga-form-row">
				<label for="teamorga_prio">{$lang->teamorga_priority}</label>
				<select id="teamorga_prio" name="teamorga_prio">
					<option value="low">{$lang->teamorga_prio_low}</option>
					<option value="normal" selected="selected">{$lang->teamorga_prio_normal}</option>
					<option value="medium">{$lang->teamorga_prio_medium}</option>
					<option value="high">{$lang->teamorga_prio_high}</option>
				</select>
			</div>
			<div class="teamorga-form-actions">
				<input type="submit" class="button" value="{$lang->teamorga_add_submit}" />
			</div>
		</div>
	</form>
</div>