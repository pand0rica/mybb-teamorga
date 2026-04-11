<form method="post" action="showthread.php?tid={$post['tid']}" style="display: inline;">
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="teamorga_action" value="toggle_suggestion_active" />
	<input type="hidden" name="sug_id" value="{$suggestion['id']}" />
	<button type="submit" class="teamorga-btn teamorga-btn-toggle" title="{$title}">{$toggle_btn_content}</button>
</form>