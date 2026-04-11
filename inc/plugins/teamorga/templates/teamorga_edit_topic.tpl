<div class="teamorga-box">
	<h3>Teamorga-Einstellungen bearbeiten</h3>
	<form method="post" action="showthread.php?tid={$teamorga_tid}">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
		<input type="hidden" name="teamorga_action" value="edit_topic_save" />
		<input type="hidden" name="ttid" value="{$teamorga_ttid}" />
		<div class="teamorga-form-body">
			<div class="teamorga-form-row">
				<label for="teamorga_deadline">Deadline</label>
				<input type="date" id="teamorga_deadline" name="teamorga_deadline" value="{$teamorga_deadline_raw}" />
			</div>
			<div class="teamorga-form-row">
				<label for="teamorga_prio">Priorität</label>
				<select id="teamorga_prio" name="teamorga_prio">
					<option value="low" {$prio_selected['low']}>Niedrig</option>
					<option value="normal" {$prio_selected['normal']}>Normal</option>
					<option value="medium" {$prio_selected['medium']}>Mittel</option>
					<option value="high" {$prio_selected['high']}>Hoch</option>
					<option value="inactive" {$prio_selected['inactive']}>Inaktiv</option>
				</select>
			</div>
			<div class="teamorga-form-actions">
				<button type="submit" class="teamorga-btn teamorga-btn-approve">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
					Speichern
				</button>
				<a href="showthread.php?tid={$teamorga_tid}" class="teamorga-btn">Abbrechen</a>
			</div>
		</div>
	</form>
</div>