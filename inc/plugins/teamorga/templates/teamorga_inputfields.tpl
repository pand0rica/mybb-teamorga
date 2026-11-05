<tr>
	<td class="thead" colspan="2">{$lang->teamorga_settings_title}</td>
</tr>
<tr>
	<td class="trow2" style="width:30%"><label for="teamorga_deadline">{$lang->teamorga_deadline}</label></td>
	<td class="trow2"><input type="date" id="teamorga_deadline" name="teamorga_deadline" value="{$default_deadline}" /></td>
</tr>
<tr>
	<td class="trow1"><label for="teamorga_prio">{$lang->teamorga_priority}</label></td>
	<td class="trow1">
		<select id="teamorga_prio" name="teamorga_prio">
					<option value="low">{$lang->teamorga_prio_low}</option>
					<option value="normal" selected="selected">{$lang->teamorga_prio_normal}</option>
					<option value="medium">{$lang->teamorga_prio_medium}</option>
					<option value="high">{$lang->teamorga_prio_high}</option>
					<option value="inactive">{$lang->teamorga_prio_inactive}</option>
		</select>
	</td>
</tr>
