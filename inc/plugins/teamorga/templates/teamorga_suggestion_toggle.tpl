<form method="post" action="showthread.php?tid={$post['tid']}" class="teamorga-inline-form">
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="teamorga_action" value="toggle_suggestion_active" />
	<input type="hidden" name="sug_id" value="{$suggestion['id']}" />
	<input type="submit" class="button" title="{$title}" value="{$toggle_btn_content}" />
</form>