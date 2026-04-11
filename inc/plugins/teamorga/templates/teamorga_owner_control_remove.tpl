<form method="post" action="showthread.php?tid={$tid}" style="display:inline; margin-left:4px;">
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="teamorga_action" value="update_ownership" />
	<input type="hidden" name="ttid" value="{$teamorga_ttid}" />
	<input type="hidden" name="teamorga_ownership" value="ownership_remove" />
	<button type="submit" class="teamorga-btn-ownership teamorga-btn-ownership--remove">abgeben</button>
</form>