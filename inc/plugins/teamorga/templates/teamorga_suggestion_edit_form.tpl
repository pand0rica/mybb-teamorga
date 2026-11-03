<div class="teamorga-suggestion-editform">
	<form method="post" action="showthread.php?tid={$post['tid']}&amp;pid={$post['pid']}#pid{$post['pid']}">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
		<input type="hidden" name="teamorga_action" value="save_suggestion" />
		<input type="hidden" name="sug_id" value="{$suggestion['id']}" />
		<textarea name="suggestion" rows="4">{$suggestion['suggestion']}</textarea>
		<div class="teamorga-form-actions">
			<input type="submit" class="button" value="{$lang->teamorga_save}" />
			<input type="submit" class="button" form="teamorga_cancel_sug_{$suggestion['id']}" value="{$lang->teamorga_cancel}" />
		</div>
	</form>
	<form method="get" action="showthread.php#pid{$post['pid']}" id="teamorga_cancel_sug_{$suggestion['id']}"><input type="hidden" name="tid" value="{$post['tid']}" />{$teamorga_cancel_page_field}</form>
</div>