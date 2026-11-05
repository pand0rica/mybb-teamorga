<div class="teamorga-participation-form">
	<div class="teamorga-status-info">{$lang->teamorga_participation_question}</div>
	<form method="post" action="showthread.php?tid={$tid}" id="teamorga-pform-{$teamorga_ttid}">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
		<input type="hidden" name="teamorga_action" value="update_participation" />
		<input type="hidden" name="ttid" value="{$teamorga_ttid}" />
		<div class="teamorga-form-row">
			<input type="submit" class="button" name="teamorga_status_approved" value="{$lang->teamorga_status_approved}" />
			<button type="button" class="button" onclick="document.getElementById('teamorga-time-{$teamorga_ttid}').style.display='block'; this.style.display='none';">{$lang->teamorga_status_time}</button>
			<input type="submit" class="button" name="teamorga_status_abstain" value="{$lang->teamorga_status_abstain}" />
		</div>
		<div class="teamorga-time-expand" id="teamorga-time-{$teamorga_ttid}" style="display:none;">
			<div class="teamorga-form-row">
				<label for="teamorga-pd-{$teamorga_ttid}">{$lang->teamorga_need_time_until}</label>
				<input type="date" id="teamorga-pd-{$teamorga_ttid}" name="proposed_deadline" />
				<input type="submit" class="button" name="teamorga_status_time" value="{$lang->teamorga_confirm}" />
			</div>
		</div>
	</form>
</div>
