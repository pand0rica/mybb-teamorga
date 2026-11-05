<span class="teamorga-time-proposal-controls">
	<form method="post" action="showthread.php?tid={$tid}" class="teamorga-inline-form">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
		<input type="hidden" name="teamorga_action" value="decide_time_proposal" />
		<input type="hidden" name="ttid" value="{$teamorga_ttid}" />
		<input type="hidden" name="proposal_uid" value="{$proposal_uid}" />
		<input type="submit" class="button" name="proposal_accept" value="{$lang->teamorga_accept}" />
		<input type="submit" class="button" name="proposal_decline" value="{$lang->teamorga_decline}" />
	</form>
</span>
