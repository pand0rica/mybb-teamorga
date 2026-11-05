<form method="post" action="showthread.php?tid={$tid}&amp;pid={$post['pid']}#pid{$post['pid']}" class="teamorga-inline-form">
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="teamorga_action" value="review_suggestion" />
	<input type="hidden" name="sug_id" value="{$suggestion['id']}" />
	<input type="hidden" name="review_decision" value="approved" />
	<input type="submit" class="button" value="{$lang->teamorga_vote_approve}" />
</form>
<form method="post" action="showthread.php?tid={$tid}&amp;pid={$post['pid']}#pid{$post['pid']}" class="teamorga-inline-form">
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="teamorga_action" value="review_suggestion" />
	<input type="hidden" name="sug_id" value="{$suggestion['id']}" />
	<input type="hidden" name="review_decision" value="disapproved" />
	<input type="submit" class="button" value="{$lang->teamorga_status_disapproved}" />
</form>