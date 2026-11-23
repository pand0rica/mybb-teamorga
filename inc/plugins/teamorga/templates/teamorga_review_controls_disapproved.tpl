<span class="teamorga-review-no">
	<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
	{$lang->teamorga_vote_you_disapproved}
</span>

<form method="post" action="showthread.php?tid={$tid}&amp;pid={$post['pid']}#pid{$post['pid']}" class="teamorga-inline-form">
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="teamorga_action" value="withdraw_review" />
	<input type="hidden" name="sug_id" value="{$suggestion['id']}" />
	<input type="submit" class="button" value="{$lang->teamorga_vote_withdraw}" />
</form>
