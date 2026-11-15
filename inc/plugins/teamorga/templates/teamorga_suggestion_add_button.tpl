<div class="teamorga-suggestion-add">
	<details class="teamorga-suggestion-add-toggle"{$teamorga_add_open}>
		<summary class="button">{$lang->teamorga_suggestion_add}</summary>
		<div class="teamorga-suggestion-editform">
			<p class="teamorga-suggestion-submit-hint">{$lang->teamorga_suggestion_submit_hint}</p>
			<form method="post" action="showthread.php?tid={$post['tid']}&amp;pid={$post['pid']}">
				<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
				<input type="hidden" name="teamorga_action" value="add_suggestion_save" />
				<textarea name="new_suggestion" rows="4" placeholder="{$lang->teamorga_suggestion_placeholder}"></textarea>
				<div class="teamorga-form-actions">
					<input type="submit" class="button" value="{$lang->teamorga_save}" />
				</div>
			</form>
		</div>
	</details>
</div>
