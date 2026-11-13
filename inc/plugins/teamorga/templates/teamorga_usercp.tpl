<html>
<head>
<title>{$lang->teamorga_ucp_title} - {$mybb->settings['bbname']}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="sidemenu-wrapper">

	<!-- Menü + Handle (eine Einheit) -->
	<div class="sidemenu">
		<div class="sidemenu-inner">
			{$usercpnav}
		</div>

		<div class="sidemenu-handle">
			<span class="sidemenu-arrow">›</span>
		</div>
	</div>

	<!-- OVERLAY -->
	<div class="sidemenu-overlay"></div>

	<!-- Content -->
	<div class="sidemenu-content">
		<div class="teamorga-box">
			<h3>{$lang->teamorga_ucp_open_tasks}</h3>
			<p class="teamorga-ucp-intro">{$lang->teamorga_ucp_intro}</p>
			<div class="teamorga-ucp-list">
				{$task_list}
			</div>
		</div>
		<div class="teamorga-box">
			<h3>{$lang->teamorga_ucp_reminder_add}</h3>
			{$reminder_form}
		</div>
		<div class="teamorga-box">
			<h3>{$lang->teamorga_ucp_reminders_done}</h3>
			<div class="teamorga-ucp-list">
				{$done_list}
			</div>
		</div>
	</div>
</div>
{$footer}
</body>
</html>
