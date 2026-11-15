<div class="teamorga-suggestion-box">
	<div class="teamorga-suggestion-head">
		<span class="teamorga-suggestion-author">{$lang->teamorga_suggestion_submit_title}</span>
	</div>
	<div class="teamorga-suggestion-editform">
		<p class="teamorga-suggestion-submit-hint">{$lang->teamorga_suggestion_submit_hint}</p>
		<form method="post" action="showthread.php?tid={$post['tid']}&amp;pid={$post['pid']}#pid{$post['pid']}">
			<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
			<input type="hidden" name="teamorga_action" value="add_suggestion_save" />
			<textarea name="new_suggestion" rows="4" placeholder="{$lang->teamorga_suggestion_placeholder}">{$teamorga_suggestion_value}</textarea>
			<div class="teamorga-form-actions">
				<input type="submit" class="button" value="{$lang->teamorga_save}" />
				<input type="submit" class="button" form="teamorga_cancel_add_{$post['pid']}" value="{$lang->teamorga_cancel}" />
			</div>
		</form>
		<form method="get" action="showthread.php#pid{$post['pid']}" id="teamorga_cancel_add_{$post['pid']}"><input type="hidden" name="tid" value="{$post['tid']}" />{$teamorga_cancel_page_field}</form>
	</div>
</div>
