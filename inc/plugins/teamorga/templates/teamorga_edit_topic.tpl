<div class="teamorga-box">
	<h3>{$lang->teamorga_edit_title}</h3>
	<form method="post" action="showthread.php?tid={$teamorga_tid}">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
		<input type="hidden" name="teamorga_action" value="edit_topic_save" />
		<input type="hidden" name="ttid" value="{$teamorga_ttid}" />
		<div class="teamorga-form-body">
			<div class="teamorga-form-row">
				<label for="teamorga_deadline">{$lang->teamorga_deadline}</label>
				<input type="date" id="teamorga_deadline" name="teamorga_deadline" value="{$teamorga_deadline_raw}" />
			</div>
			<div class="teamorga-form-row">
				<label for="teamorga_prio">{$lang->teamorga_priority}</label>
				<select id="teamorga_prio" name="teamorga_prio">
					<option value="low" {$prio_selected['low']}>{$lang->teamorga_prio_low}</option>
					<option value="normal" {$prio_selected['normal']}>{$lang->teamorga_prio_normal}</option>
					<option value="medium" {$prio_selected['medium']}>{$lang->teamorga_prio_medium}</option>
					<option value="high" {$prio_selected['high']}>{$lang->teamorga_prio_high}</option>
					<option value="inactive" {$prio_selected['inactive']}>{$lang->teamorga_prio_inactive}</option>
				</select>
			</div>
			<div class="teamorga-form-actions">
				<input type="submit" class="button" value="{$lang->teamorga_save}" />
				<input type="submit" class="button" form="teamorga_cancel_form" value="{$lang->teamorga_cancel}" />
			</div>
		</div>
	</form>
	<form method="get" action="showthread.php" id="teamorga_cancel_form"><input type="hidden" name="tid" value="{$teamorga_tid}" /></form>
</div>