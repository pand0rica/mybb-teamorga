<div class="h4w-post-item"><strong><label for="teamorga_deadline">{$lang->teamorga_deadline}</label></strong></div>
<div class="h4w-post-item"><input type="date" class="textbox" id="teamorga_deadline" name="teamorga_deadline" value="{$default_deadline}" /></div>
<div class="h4w-post-item"><strong><label for="teamorga_prio">{$lang->teamorga_priority}</label></strong></div>
<div class="h4w-post-item">
	<select id="teamorga_prio" name="teamorga_prio">
		<option value="low">{$lang->teamorga_prio_low}</option>
		<option value="normal" selected="selected">{$lang->teamorga_prio_normal}</option>
		<option value="medium">{$lang->teamorga_prio_medium}</option>
		<option value="high">{$lang->teamorga_prio_high}</option>
		<option value="inactive">{$lang->teamorga_prio_inactive}</option>
	</select>
</div>
