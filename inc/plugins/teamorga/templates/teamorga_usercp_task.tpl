<div class="teamorga-ucp-item {$task_prio_class}">
	<div class="teamorga-ucp-head">
		<a href="{$task_href}" class="teamorga-ucp-title">{$task_subject}</a>
		{$task_badge}
	</div>
	<div class="teamorga-ucp-meta">
		<span>{$lang->teamorga_deadline}: {$task_deadline}</span>
		<span>{$task_prio_label}</span>
		<span>{$lang->teamorga_ucp_owner}: {$task_owner}</span>
		<span class="teamorga-ucp-open">{$task_topic_open}</span>
	</div>
	<div class="teamorga-ucp-suggestions">
		{$task_suggestions}
	</div>
</div>
