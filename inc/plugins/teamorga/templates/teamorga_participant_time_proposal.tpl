<span class="teamorga-time-proposal-controls">
	<form method="post" action="showthread.php?tid={$tid}" style="display:inline;">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
		<input type="hidden" name="teamorga_action" value="decide_time_proposal" />
		<input type="hidden" name="ttid" value="{$teamorga_ttid}" />
		<input type="hidden" name="proposal_uid" value="{$proposal_uid}" />
		<button type="submit" name="proposal_decision" value="accept" class="teamorga-btn teamorga-btn-approve teamorga-btn-proposal">
			<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
			Annehmen
		</button>
		<button type="submit" name="proposal_decision" value="decline" class="teamorga-btn teamorga-btn-proposal">
			<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
			Ablehnen
		</button>
	</form>
</span>
