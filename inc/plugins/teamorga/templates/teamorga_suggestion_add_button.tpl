<div class="teamorga-suggestion-add">
	<form method="get" action="showthread.php#pid{$post['pid']}" class="teamorga-inline-form">
		<input type="hidden" name="tid" value="{$post['tid']}" />
		<input type="hidden" name="pid" value="{$post['pid']}" />
		<input type="hidden" name="teamorga_action" value="add_suggestion" />
		{$teamorga_add_page_field}
		<input type="submit" class="button" value="{$lang->teamorga_suggestion_add}" />
	</form>
</div>
