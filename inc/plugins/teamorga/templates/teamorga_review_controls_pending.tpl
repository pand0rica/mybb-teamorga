<form method="post" action="showthread.php?tid={$tid}&amp;pid={$post['pid']}#pid{$post['pid']}" style="display: inline;">
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="teamorga_action" value="review_suggestion" />
	<input type="hidden" name="sug_id" value="{$suggestion['id']}" />
	<input type="hidden" name="review_decision" value="approved" />
	<button type="submit" class="teamorga-btn teamorga-btn-approve">
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
		Dafür
	</button>
</form>
<form method="post" action="showthread.php?tid={$tid}&amp;pid={$post['pid']}#pid{$post['pid']}" style="display: inline; margin-left: 6px;">
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="teamorga_action" value="review_suggestion" />
	<input type="hidden" name="sug_id" value="{$suggestion['id']}" />
	<input type="hidden" name="review_decision" value="disapproved" />
	<button type="submit" class="teamorga-btn teamorga-btn-change">
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
		Änderung erwünscht
	</button>
</form>