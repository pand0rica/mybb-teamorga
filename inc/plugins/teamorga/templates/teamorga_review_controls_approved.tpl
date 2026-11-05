<span class="teamorga-review-yes">
	<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
	{$lang->teamorga_vote_you_approved}
</span>
<form method="post" action="showthread.php?tid={$tid}&amp;pid={$post['pid']}#pid{$post['pid']}" class="teamorga-inline-form">
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="teamorga_action" value="withdraw_review" />
	<input type="hidden" name="sug_id" value="{$suggestion['id']}" />
	<input type="submit" class="button" value="{$lang->teamorga_vote_withdraw}" />
</form>