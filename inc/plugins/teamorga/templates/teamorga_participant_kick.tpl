<form method="post" action="showthread.php?tid={$tid}" class="teamorga-inline-form">
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="teamorga_action" value="kick_participant" />
	<input type="hidden" name="ttid" value="{$teamorga_ttid}" />
	<input type="hidden" name="kick_uid" value="{$kick_uid}" />
	<input type="submit" class="button" title="{$lang->teamorga_kick_title}" value="{$lang->teamorga_kick}" />
</form>
