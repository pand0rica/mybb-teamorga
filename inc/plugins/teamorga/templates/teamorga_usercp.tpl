<html>
<head>
<title>{$lang->teamorga_ucp_title} - {$mybb->settings['bbname']}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
{$usercpnav}
<td valign="top">
	<div class="teamorga-box">
		<h3>{$lang->teamorga_ucp_open_tasks}</h3>
		<p class="teamorga-ucp-intro">{$lang->teamorga_ucp_intro}</p>
		<ul class="teamorga-ucp-list">
			{$task_list}
		</ul>
	</div>
	<div class="teamorga-box">
		<h3>{$lang->teamorga_ucp_reminder_add}</h3>
		{$reminder_form}
	</div>
	<div class="teamorga-box">
		<h3>{$lang->teamorga_ucp_reminders_done}</h3>
		<ul class="teamorga-ucp-list">
			{$done_list}
		</ul>
	</div>
</td>
</tr>
</table>
{$footer}
</body>
</html>
