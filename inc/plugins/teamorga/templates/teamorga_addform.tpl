<div class="teamorga-box">
	<h3>Thema zu Teamorga hinzufügen</h3>
	<form method="post" action="showthread.php?tid={$teamorga_addform_tid}">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
		<input type="hidden" name="teamorga_action" value="save_add" />
		<div class="teamorga-form-body">
			<div class="teamorga-form-row">
				<label for="teamorga_deadline">Deadline</label>
				<input type="date" id="teamorga_deadline" name="teamorga_deadline" value="{$teamorga_addform_deadline}" />
			</div>
			<div class="teamorga-form-row">
				<label for="teamorga_prio">Priorität</label>
				<select id="teamorga_prio" name="teamorga_prio">
					<option value="low">Niedrig</option>
					<option value="normal" selected="selected">Normal</option>
					<option value="medium">Mittel</option>
					<option value="high">Hoch</option>
				</select>
			</div>
			<div class="teamorga-form-actions">
				<button type="submit" class="teamorga-btn teamorga-btn-add">Hinzufügen</button>
			</div>
		</div>
	</form>
</div>