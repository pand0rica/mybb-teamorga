<form method="post" action="showthread.php?tid={$tid}" style="display:inline; margin-left:3px;">
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="teamorga_action" value="kick_participant" />
	<input type="hidden" name="ttid" value="{$teamorga_ttid}" />
	<input type="hidden" name="kick_uid" value="{$kick_uid}" />
	<button type="submit" class="teamorga-btn-kick" title="Aus Thema entfernen">&#10005;</button>
</form>
