<form method="post" action="showthread.php?tid={$tid}" class="teamorga-inline-form">
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="teamorga_action" value="update_ownership" />
	<input type="hidden" name="ttid" value="{$teamorga_ttid}" />
	<input type="hidden" name="teamorga_ownership" value="ownership_add" />
	<input type="submit" class="button" value="{$lang->teamorga_take_over}" />
</form>