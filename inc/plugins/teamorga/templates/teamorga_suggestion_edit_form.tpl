<div class="teamorga-suggestion-editform">
	<form method="post" action="showthread.php?tid={$post['tid']}&amp;pid={$post['pid']}#pid{$post['pid']}">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
		<input type="hidden" name="teamorga_action" value="save_suggestion" />
		<input type="hidden" name="sug_id" value="{$suggestion['id']}" />
		<textarea name="suggestion" rows="4">{$suggestion['suggestion']}</textarea>
		<div class="teamorga-form-actions">
			<button type="submit" class="teamorga-btn teamorga-btn-approve">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
				Speichern
			</button>
			<a href="showthread.php?tid={$post['tid']}#pid{$post['pid']}" class="teamorga-btn">Abbrechen</a>
		</div>
	</form>
</div>