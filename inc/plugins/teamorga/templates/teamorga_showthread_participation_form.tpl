<div class="teamorga-participation-form">
	<div class="teamorga-status-info">Wie möchtest du an diesem Thema teilnehmen?</div>
	<form method="post" action="showthread.php?tid={$tid}" id="teamorga-pform-{$teamorga_ttid}">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
		<input type="hidden" name="teamorga_action" value="update_participation" />
		<input type="hidden" name="ttid" value="{$teamorga_ttid}" />
		<div class="teamorga-form-row">
			<button type="submit" name="teamorga_status" value="approved" class="teamorga-btn teamorga-btn-approve">
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
				Mitmachen
			</button>
			<button type="button" class="teamorga-btn teamorga-btn-withdraw" onclick="document.getElementById('teamorga-time-{$teamorga_ttid}').style.display='block'; this.style.display='none';">
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
				Mehr Zeit
			</button>
			<button type="submit" name="teamorga_status" value="abstain" class="teamorga-btn">
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
				Enthalten
			</button>
		</div>
		<div class="teamorga-time-expand" id="teamorga-time-{$teamorga_ttid}" style="display:none;">
			<div class="teamorga-form-row">
				<label for="teamorga-pd-{$teamorga_ttid}">Ich brauche Zeit bis:</label>
				<input type="date" id="teamorga-pd-{$teamorga_ttid}" name="proposed_deadline" />
				<button type="submit" name="teamorga_status" value="time" class="teamorga-btn teamorga-btn-approve">
					Bestätigen
				</button>
			</div>
		</div>
	</form>
</div>
