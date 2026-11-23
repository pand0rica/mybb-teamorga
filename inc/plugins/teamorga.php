<?php
/**
 * Teamorga – Team organisation plugin for MyBB 1.8
 *
 * @author   pand0rica
 * @version  1.3.0
 */

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// ACP: settings titles/descriptions from the admin language file (setting_teamorga_*)
if (defined("IN_ADMINCP")) {
    $plugins->add_hook("admin_config_settings_begin", "teamorga_admin_settings_lang");
}

// Only register hooks when the plugin is enabled (setting may not exist before install)
if (!isset($mybb->settings["teamorga_enabled"]) || $mybb->settings["teamorga_enabled"]) {
    $plugins->add_hook("index_start", "teamorga_index");
    $plugins->add_hook("datahandler_post_insert_post_end", "teamorga_on_post_insert");
    $plugins->add_hook("postbit", "teamorga_postbit");
    $plugins->add_hook("global_start", "teamorga_myalerts_register");
    $plugins->add_hook("usercp_menu", "teamorga_usercp_menu", 24);
    $plugins->add_hook("usercp_menu_rpg", "teamorga_usercp_menu_rpg");
    $plugins->add_hook("usercp_start", "teamorga_usercp");

    // Register forum-specific hooks only for enabled forums
    if (teamorga_is_forum_enabled()) {
        $plugins->add_hook("newthread_start", "teamorga_newthread");
        $plugins->add_hook("newthread_do_newthread_end", "teamorga_do_newthread");
        $plugins->add_hook("forumdisplay_thread", "teamorga_forumdisplay");
        $plugins->add_hook("showthread_start", "teamorga_showthread");
        $plugins->add_hook("newreply_start", "teamorga_newreply");
    }
}

// The core templates carry Teamorga's variables in every forum (and while the plugin is switched
// off), its handlers only run in enabled forums: empty defaults first, the handlers overwrite them.
foreach (["index_start", "newthread_start", "newreply_start", "showthread_start", "forumdisplay_start"] as $teamorga_hook) {
    $plugins->add_hook($teamorga_hook, "teamorga_template_defaults", 5);
}

// {$post['teamorga']} in the postbit templates: empty for PMs, previews, announcements and
// while the plugin is switched off; teamorga_postbit() fills it for normal posts
foreach (["postbit", "postbit_prev", "postbit_pm", "postbit_announcement"] as $teamorga_hook) {
    $plugins->add_hook($teamorga_hook, "teamorga_postbit_default", 5);
}

// The alert types stay in the UCP alert settings even while the plugin is switched off
$plugins->add_hook("myalerts_load_lang", "teamorga_myalerts_load_lang");

/**
 * Empty defaults for the template variables Teamorga puts into core templates.
 */
function teamorga_template_defaults(): void
{
    $names = ["teamorga", "teamorga_newreply_suggestion", "teamorga_showthread", "teamorga_addbutton",
        "teamorga_index_alerts", "teamorga_forumdisplay"];
    foreach ($names as $name) {
        if (!isset($GLOBALS[$name])) {
            $GLOBALS[$name] = "";
        }
    }
}

// MyAlerts formatter classes – only defined when MyAlerts is loaded
if (class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter')) {
    class TeamorgaTimeProposalFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
    {
        public function init()
        {
            global $lang;
            if (!isset($lang->teamorga_alert_time_proposal)) {
                $lang->load('teamorga');
            }
        }

        public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
        {
            global $lang;
            $details  = $alert->getExtraDetails();
            $fromUser = $alert->getFromUser();
            $username = htmlspecialchars_uni($fromUser['username'] ?? '');
            $date     = htmlspecialchars_uni($details['proposed_deadline'] ?? '');
            return $lang->sprintf($lang->teamorga_alert_time_proposal, $username, $date);
        }

        public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
        {
            $details = $alert->getExtraDetails();
            $tid     = (int) ($details['tid'] ?? 0);
            return $this->mybb->settings['bburl'] . "/showthread.php?tid={$tid}";
        }
    }

    class TeamorgaSuggestionVoteFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
    {
        public function init()
        {
            global $lang;
            if (!isset($lang->teamorga_alert_vote)) {
                $lang->load('teamorga');
            }
        }

        public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
        {
            global $lang;
            $details  = $alert->getExtraDetails();
            $fromUser = $alert->getFromUser();
            $username = htmlspecialchars_uni($fromUser['username'] ?? '');
            $decision = $details['decision'] ?? '';

            if ($decision === 'approved') {
                return $lang->sprintf($lang->teamorga_alert_vote_approved, $username);
            }
            if ($decision === 'disapproved') {
                return $lang->sprintf($lang->teamorga_alert_vote_disapproved, $username);
            }
            return $lang->sprintf($lang->teamorga_alert_vote, $username);
        }

        public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
        {
            $details = $alert->getExtraDetails();
            $tid     = (int) ($details['tid'] ?? 0);
            $pid     = (int) ($details['pid'] ?? 0);
            return $this->mybb->settings['bburl']
                . "/showthread.php?tid={$tid}&pid={$pid}#pid{$pid}";
        }
    }
}

// ============================================================================
// PLUGIN INFO / INSTALL / UNINSTALL
// ============================================================================

/**
 * Lädt die Sprachdatei einmal pro Request und gibt das $lang-Objekt zurück.
 */
function teamorga_load_lang()
{
    global $lang;
    static $loaded = false;

    if (!$loaded) {
        $lang->load('teamorga');
        $loaded = true;
    }

    return $lang;
}

/**
 * Loads the admin language file on the settings page so MyBB shows the
 * setting_group_teamorga / setting_teamorga_* strings in the forum language.
 */
function teamorga_admin_settings_lang(): void
{
    global $lang;
    $lang->load("teamorga");
}

function teamorga_info()
{
    return [
        "name"        => "Teamorga",
        "description" => "Vereinfacht Absprache und Organisation im Team",
        "website"     => "",
        "author"      => "pand0rica",
        "authorsite"  => "",
        "version"     => "1.3.0",
        "guid"        => "",
        "codename"    => "teamorga",
        "compatibility" => "18*"
    ];
}

function teamorga_install()
{
    global $db;

    $setting_gid = $db->insert_query("settinggroups", [
        "name"        => "teamorga",
        "title"       => "Teamorga",
        "description" => "Settings for Teamorga (team coordination with deadlines, participation and voting).",
        "disporder"   => 50,
        "isdefault"   => 0
    ]);

    $settings = [
        [
            "name"        => "teamorga_enabled",
            "title"       => "Enabled?",
            "description" => "Turn the plugin on or off.",
            "optionscode" => "onoff",
            "value"       => "1"
        ],
        [
            "name"        => "teamorga_forums",
            "title"       => "Forums",
            "description" => "Forums in which Teamorga is available. Select “All forums” for every forum; no selection = none.",
            "optionscode" => "forumselect",
            "value"       => ""
        ],
        [
            "name"        => "teamorga_groups",
            "title"       => "Groups",
            "description" => "User groups that take part in Teamorga in the selected forums. Select “All groups” for everyone; no selection = none.",
            "optionscode" => "groupselect",
            "value"       => "3,4,6"
        ],
        [
            "name"        => "teamorga_edit_groups",
            "title"       => "Editor groups",
            "description" => "Groups that may always edit topics and suggestions, even if they did not create them.",
            "optionscode" => "groupselect",
            "value"       => "3,4,6"
        ],
        [
            "name"        => "teamorga_deadline_default",
            "title"       => "Default deadline (days)",
            "description" => "How many days from today the deadline is preset when a Teamorga topic is created.",
            "optionscode" => "numeric",
            "value"       => "14"
        ],
        [
            "name"        => "teamorga_memo_default",
            "title"       => "Reminder lead time (days)",
            "description" => "How many days before the deadline a reminder appears on the forum index.",
            "optionscode" => "numeric",
            "value"       => "3"
        ],
        [
            "name"        => "teamorga_id",
            "title"       => "Team account ID",
            "description" => "UID of a shared team account. It can create topics but does not count as a voter.",
            "optionscode" => "numeric",
            "value"       => "0"
        ]
    ];

    $disporder = 1;
    foreach ($settings as &$row) {
        $row["gid"]       = (int) $setting_gid;
        $row["disporder"] = $disporder++;
        $db->insert_query("settings", $row);
    }
    unset($row);

    rebuild_settings();

    // Create tables
    $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "teamorga_topic` (
        `id`            int          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `tid`           int          NOT NULL,
        `organizer_uid` int          NOT NULL DEFAULT 0,
        `deadline`      varchar(10)  NOT NULL DEFAULT '',
        `prio`          varchar(50)  NOT NULL DEFAULT 'normal',
        `status`        varchar(50)  NOT NULL DEFAULT 'active',
        `owner`         varchar(100) DEFAULT NULL
    ) ENGINE=MyISAM;");

    $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "teamorga_suggestions` (
        `id`        int          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `tt_id`     int          NOT NULL,
        `suggestion` text        NOT NULL,
        `owner_uid` int          NOT NULL,
        `dateline`  int(10)      NOT NULL,
        `pid`       int(10)      DEFAULT NULL,
        `active`    tinyint(1)   NOT NULL DEFAULT 1
    ) ENGINE=MyISAM;");

    $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "teamorga_suggestion_participants` (
        `id`     int         NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `uid`    int         NOT NULL,
        `tt_id`  int         NOT NULL,
        `sug_id` int         NOT NULL,
        `status` varchar(50) NOT NULL DEFAULT 'unanswered'
    ) ENGINE=MyISAM;");

    $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "teamorga_topic_participants` (
        `id`                int         NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `uid`               int         NOT NULL,
        `tt_id`             int         NOT NULL,
        `status`            varchar(50) NOT NULL DEFAULT 'unanswered',
        `proposed_deadline` varchar(10) NOT NULL DEFAULT ''
    ) ENGINE=MyISAM;");

    teamorga_create_reminders_table();
}

/**
 * Private per-user reminders shown on the UserCP page (idempotent).
 */
function teamorga_create_reminders_table(): void
{
    global $db;

    $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "teamorga_reminders` (
        `id`          int          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `uid`         int          NOT NULL,
        `title`       varchar(255) NOT NULL DEFAULT '',
        `note`        text         NULL,
        `remind_date` varchar(10)  NOT NULL DEFAULT '',
        `done`        tinyint(1)   NOT NULL DEFAULT 0,
        `dateline`    int(10)      NOT NULL DEFAULT 0,
        KEY `uid` (`uid`)
    ) ENGINE=MyISAM;");
}

function teamorga_is_installed()
{
    global $db;
    $query = $db->simple_select("settinggroups", "gid", "name='teamorga'");
    return (bool) $db->num_rows($query);
}

function teamorga_uninstall()
{
    global $db;

    $gid = (int) $db->fetch_field(
        $db->simple_select("settinggroups", "gid", "name='teamorga'"),
        "gid"
    );

    if ($gid) {
        $db->delete_query("settings", "gid={$gid}");
        $db->delete_query("settinggroups", "gid={$gid}");
        rebuild_settings();
    }

    $db->drop_table("teamorga_topic");
    $db->drop_table("teamorga_suggestions");
    $db->drop_table("teamorga_suggestion_participants");
    $db->drop_table("teamorga_topic_participants");
    $db->drop_table("teamorga_reminders");
}

function teamorga_activate()
{
    global $db;

    // Register stylesheet for every theme
    require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";
    $css_path = MYBB_ROOT . "inc/plugins/teamorga/teamorga.css";
    if (file_exists($css_path)) {
        $css_content = $db->escape_string(file_get_contents($css_path));
        $themes = $db->simple_select("themes", "tid");
        while ($theme = $db->fetch_array($themes)) {
            $tid = (int) $theme['tid'];
            $existing = $db->fetch_array(
                $db->simple_select("themestylesheets", "sid", "name='teamorga.css' AND tid={$tid}")
            );
            if (!$existing) {
                $sid = $db->insert_query("themestylesheets", [
                    "name"         => "teamorga.css",
                    "tid"          => $tid,
                    "attachedto"   => "",
                    "stylesheet"   => $css_content,
                    "cachefile"    => "teamorga.css",
                    "lastmodified" => TIME_NOW,
                ]);
                $db->update_query("themestylesheets", ["cachefile" => "css.php?stylesheet={$sid}"], "sid=" . (int) $sid, 1);
            } else {
                $db->update_query("themestylesheets", [
                    "stylesheet"   => $css_content,
                    "lastmodified" => TIME_NOW,
                ], "sid='" . (int) $existing['sid'] . "'", 1);
            }
            update_theme_stylesheet_list($tid);
        }
    }

    // Inject into core templates
    require_once MYBB_ROOT . "inc/adminfunctions_templates.php";
    // Clean up any previous injection locations (idempotent activate)
    find_replace_templatesets("newthread", "#" . preg_quote('{$teamorga}') . "#i", '', 0);
    find_replace_templatesets("newthread", "#" . preg_quote('{$teamorga_newreply_suggestion}') . "#i", '', 0);
    // Inject deadline/prio fields + suggestion textarea as <tr> rows before {$postoptions}
    // {$postoptions} is always inside the main <table class="tborder"> on any MyBB forum
    find_replace_templatesets("newthread",
        "#" . preg_quote('{$postoptions}') . "#i",
        '{$teamorga}{$teamorga_newreply_suggestion}{$postoptions}'
    );
    find_replace_templatesets("newreply",  "#" . preg_quote('{$teamorga_newreply_suggestion}') . "#i", '', 0);
    find_replace_templatesets("newreply",
        "#" . preg_quote('{$postoptions}') . "#i",
        '{$teamorga_newreply_suggestion}{$postoptions}'
    );
    find_replace_templatesets(
        "postbit",
        "#" . preg_quote('{$post[\'message\']}') . "#i",
        '{$post[\'message\']}{$post[\'teamorga\']}'
    );
    find_replace_templatesets(
        "forumdisplay_thread",
        "#" . preg_quote('{$thread[\'profilelink\']}') . "#i",
        '{$thread[\'profilelink\']}<span class="teamorga-fd-wrap">{$teamorga_forumdisplay}</span>'
    );
    // Clean up any previous injection formats before re-injecting (idempotent activate)
    find_replace_templatesets("showthread", '#<tr><td[^>]*>\{\$teamorga_showthread\}</td></tr>#si', '', 0);
    find_replace_templatesets("showthread", "#" . preg_quote('{$teamorga_showthread}') . "#i", '', 0);
    find_replace_templatesets("showthread", "#" . preg_quote('{$teamorga_addbutton}') . "#i", '', 0);
    find_replace_templatesets(
        "showthread",
        "#" . preg_quote('{$newreply}') . "#i",
        '{$teamorga_addbutton} {$newreply}'
    );
    // Main Teamorga box inside posts_container
    find_replace_templatesets(
        "showthread",
        "#" . preg_quote('<td id="posts_container">') . "#i",
        '<td id="posts_container">{$teamorga_showthread}'
    );
    // Remove earlier injections first: every reactivation used to add one more copy
    find_replace_templatesets("index", "#" . preg_quote('{$teamorga_index_alerts}') . "#i", '', 0);
    find_replace_templatesets(
        "index",
        "#" . preg_quote('{$header}') . "#i",
        '{$header}{$teamorga_index_alerts}'
    );

    // Add proposed_deadline column if missing (upgrade path for existing installs)
    $cols = $db->show_fields_from("teamorga_topic_participants");
    $has_col = false;
    foreach ($cols as $col) {
        if ($col["Field"] === "proposed_deadline") { $has_col = true; break; }
    }
    if (!$has_col) {
        $db->query("ALTER TABLE `" . TABLE_PREFIX . "teamorga_topic_participants`
            ADD COLUMN `proposed_deadline` varchar(10) NOT NULL DEFAULT ''");
    }

    // Reminders table (upgrade path for installs older than 1.3.0)
    teamorga_create_reminders_table();

    // Register MyAlerts alert type
    if (function_exists('myalerts_is_activated') && myalerts_is_activated()
        && class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
        if ($alertTypeManager !== null) {
            if ($alertTypeManager->getByCode('teamorga_suggestion_vote') === null) {
                $t = new MybbStuff_MyAlerts_Entity_AlertType();
                $t->setCode('teamorga_suggestion_vote');
                $t->setEnabled(true);
                $t->setCanBeUserDisabled(true);
                $alertTypeManager->add($t);
            }
            if ($alertTypeManager->getByCode('teamorga_time_proposal') === null) {
                $t = new MybbStuff_MyAlerts_Entity_AlertType();
                $t->setCode('teamorga_time_proposal');
                $t->setEnabled(true);
                $t->setCanBeUserDisabled(true);
                $alertTypeManager->add($t);
            }
        }
    }

    // Load plugin templates into DB
    $loader = new TeamorgaTemplateLoader();
    $loader->autoLoad($db);
}

function teamorga_deactivate()
{
    global $db;

    // Remove MyAlerts alert type
    if (function_exists('myalerts_is_activated') && myalerts_is_activated()) {
        $db->delete_query("alert_types", "code IN ('teamorga_suggestion_vote','teamorga_time_proposal')");

        // Refresh the MyAlerts alert-type cache so the deleted types don't linger
        // in it – otherwise a later reactivation reads the stale cache and skips
        // re-adding them, and alerts silently stop (see teamorga_get_alert_type()).
        if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $atm = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
            if ($atm) {
                $atm->getAlertTypes(true);
            }
        }
    }

    // Remove stylesheet from all themes
    require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";
    $db->delete_query("themestylesheets", "name='teamorga.css'");
    $themes = $db->simple_select("themes", "tid");
    while ($theme = $db->fetch_array($themes)) {
        update_theme_stylesheet_list((int) $theme["tid"]);
    }

    // Remove template injections
    require_once MYBB_ROOT . "inc/adminfunctions_templates.php";
    find_replace_templatesets("newthread",         "#" . preg_quote('{$teamorga}')                   . "#i", '', 0);
    find_replace_templatesets("newreply",          "#" . preg_quote('{$teamorga_newreply_suggestion}') . "#i", '', 0);
    find_replace_templatesets("newthread",         "#" . preg_quote('{$teamorga_newreply_suggestion}') . "#i", '', 0);
    find_replace_templatesets("postbit",           "#" . preg_quote('{$post[\'teamorga\']}')          . "#i", '', 0);
    find_replace_templatesets("forumdisplay_thread","#<span class=\"teamorga-fd-wrap\">\{\$teamorga_forumdisplay\}</span>#i", '', 0);
    find_replace_templatesets("forumdisplay_thread","#" . preg_quote('{$teamorga_forumdisplay}')      . "#i", '', 0);
    find_replace_templatesets("showthread",        '#<tr><td[^>]*>\{\$teamorga_showthread\}</td></tr>#si', '', 0);
    find_replace_templatesets("showthread",        "#" . preg_quote('{$teamorga_showthread}') . "#i",         '', 0);
    find_replace_templatesets("showthread",        "#" . preg_quote('{$teamorga_addbutton} {$newreply}') . "#i", '{$newreply}', 0);
    find_replace_templatesets("showthread",        "#" . preg_quote('{$teamorga_addbutton}')  . "#i",         '', 0);
    find_replace_templatesets("index",             "#" . preg_quote('{$teamorga_index_alerts}')       . "#i", '', 0);

    // Remove plugin templates from DB
    $loader = new TeamorgaTemplateLoader();
    $loader->autoUnload($db);
}

// ============================================================================
// HOOK HANDLERS
// ============================================================================

/**
 * newthread_start – Inject deadline/prio fields into the new-thread form.
 */
function teamorga_newthread()
{
    global $mybb, $templates, $teamorga, $teamorga_newreply_suggestion, $lang;
    teamorga_load_lang();


    $teamorga_newreply_suggestion = "";

    if (!teamorga_is_group_allowed()) {
        return;
    }

    $days_offset      = (int) $mybb->settings["teamorga_deadline_default"];
    $default_deadline = (new DateTime())->modify("+{$days_offset} days")->format("Y-m-d");

    // Preserve a typed suggestion across preview / validation reloads.
    $teamorga_suggestion_value = htmlspecialchars_uni($mybb->get_input("new_suggestion"));

    eval("\$teamorga = \"" . $templates->get("teamorga_inputfields") . "\";");
    eval("\$teamorga_newreply_suggestion = \"" . $templates->get("teamorga_newreply_suggestion") . "\";");
}

/**
 * newthread_do_newthread_end – Save teamorga data after a new thread is posted.
 */
function teamorga_do_newthread()
{
    global $db, $mybb, $tid, $pid;

    if (!teamorga_is_group_allowed()) {
        return;
    }

    $deadline = $db->escape_string($mybb->get_input("teamorga_deadline"));
    $prio     = teamorga_sanitize_prio($mybb->get_input("teamorga_prio"));

    $ttid = $db->insert_query("teamorga_topic", [
        "tid"           => (int) $tid,
        "organizer_uid" => (int) $mybb->user["uid"],
        "owner"         => $db->escape_string($mybb->user["username"]),
        "deadline"      => $deadline,
        "prio"          => $prio,
        "status"        => "active"
    ]);

    teamorga_add_participants($ttid, (int) $mybb->user["uid"]);

    $new_suggestion = trim($mybb->get_input("new_suggestion"));
    if ($new_suggestion !== "") {
        // $pid from the global scope may not be reliable at this hook point;
        // fetch the first post id directly from the threads table instead.
        $thread_row = $db->fetch_array($db->simple_select("threads", "firstpost", "tid='" . (int) $tid . "'", ["limit" => 1]));
        $first_pid  = $thread_row ? (int) $thread_row["firstpost"] : 0;

        $organizer_uid = (int) $mybb->user["uid"];
        $suggestion_id = $db->insert_query("teamorga_suggestions", [
            "tt_id"      => (int) $ttid,
            "suggestion" => $db->escape_string($new_suggestion),
            "owner_uid"  => $organizer_uid,
            "pid"        => $first_pid,
            "dateline"   => TIME_NOW,
            "active"     => 1
        ]);

        // Add suggestion participants from topic participants (poster = approved)
        $participants_q = $db->query("SELECT uid FROM " . TABLE_PREFIX . "teamorga_topic_participants WHERE tt_id='" . (int) $ttid . "' AND status!='abstain'");
        while ($prow = $db->fetch_array($participants_q)) {
            $participant_uid = (int) $prow["uid"];
            $db->insert_query("teamorga_suggestion_participants", [
                "uid"    => $participant_uid,
                "tt_id"  => (int) $ttid,
                "sug_id" => (int) $suggestion_id,
                "status" => ($participant_uid === $organizer_uid) ? "approved" : "pending"
            ]);
        }
    }
}

/**
 * forumdisplay_thread – Show deadline and status hints in the thread listing.
 */
function teamorga_forumdisplay()
{
    global $db, $mybb, $thread, $templates, $teamorga_forumdisplay, $lang;
    teamorga_load_lang();


    $teamorga_forumdisplay = "";

    if (!teamorga_is_group_allowed()) {
        return;
    }

    $topic = teamorga_get_topic($thread["tid"]);
    if (!$topic || empty($topic["deadline"]) || empty($topic["prio"])) {
        return;
    }

    $date     = DateTime::createFromFormat("Y-m-d", $topic["deadline"]);
    $deadline = $date ? $date->format("d.m.Y") : "-";

    // Compute days remaining for visual urgency cues
    $now            = new DateTime();
    $days_left      = $date ? (int) $now->diff($date)->days : null;
    $is_past        = $date && $date < $now;
    if ($is_past && $days_left !== null) {
        $days_left = -$days_left;
    }

    $is_inactive = ($topic["prio"] === "inactive");

    if ($days_left === null || $is_inactive) {
        $deadline_label = $deadline;
        $deadline_class = "";
    } elseif ($days_left < 0) {
        $deadline_label = $lang->teamorga_fd_overdue;
        $deadline_class = "teamorga-fd-deadline--overdue";
    } elseif ($days_left === 0) {
        $deadline_label = $lang->teamorga_fd_today;
        $deadline_class = "teamorga-fd-deadline--today";
    } elseif ($days_left <= 3) {
        $deadline_label = $days_left === 1
            ? $lang->teamorga_fd_in_one_day
            : $lang->sprintf($lang->teamorga_fd_in_days, $days_left);
        $deadline_class = "teamorga-fd-deadline--soon";
    } else {
        $deadline_label = $deadline;
        $deadline_class = "";
    }

    // Priority label from language file
    $prio_key       = "teamorga_prio_" . $topic["prio"];
    $teamorga_prio  = isset($lang->$prio_key) ? $lang->$prio_key : ucfirst((string) $topic["prio"]);

    $user_status_class  = "";
    $answer             = "";
    $teamorga_fd_action = "";

    if ($topic["prio"] !== "inactive") {
        $uid    = (int) $mybb->user["uid"];
        $tt_id  = (int) $topic["id"];

        $status_row        = teamorga_get_user_topic_status($uid, $tt_id);
        $raw_status        = $status_row ? $status_row["status"] : "unanswered";
        $user_status_class = "teamorga-fd-status--" . htmlspecialchars_uni($raw_status);

        $memo_days  = (int) $mybb->settings["teamorga_memo_default"];
        $limit_date = (new DateTime())->add(new DateInterval("P{$memo_days}D"));
        $is_urgent  = $date && $date <= $limit_date;

        // CTA: participation not confirmed yet
        if ($raw_status === "unanswered") {
            $answer      = "teamorga-fd--urgent";
            $urgency_mod = $is_urgent ? " teamorga-fd-action--urgent" : "";
            $teamorga_fd_action = "<span class=\"teamorga-fd-action{$urgency_mod}\">"
                . ($is_urgent
                    ? $lang->teamorga_fd_deadline_soon
                    : $lang->teamorga_fd_confirm_participation)
                . "</span>";
        }

        // CTA: pending votes on active suggestions
        if ($uid > 0) {
            $pending_q = $db->query("
                SELECT COUNT(*) AS cnt
                FROM " . TABLE_PREFIX . "teamorga_suggestion_participants sp
                INNER JOIN " . TABLE_PREFIX . "teamorga_suggestions s ON s.id = sp.sug_id
                INNER JOIN " . TABLE_PREFIX . "posts po ON po.pid = s.pid
                WHERE sp.uid = {$uid}
                AND sp.tt_id = {$tt_id}
                AND sp.status = 'pending'
                AND s.active = 1
                AND s.pid IS NOT NULL
            ");
            $pending_count = (int) $db->fetch_field($pending_q, "cnt");

            if ($pending_count > 0) {
                $label = $pending_count === 1
                    ? $lang->teamorga_fd_vote_pending_one
                    : $lang->sprintf($lang->teamorga_fd_vote_pending_many, $pending_count);
                $urgency_mod = $is_urgent ? " teamorga-fd-action--urgent" : "";
                $teamorga_fd_action .= "<span class=\"teamorga-fd-action{$urgency_mod}\">{$label}</span>";
            }
        }
    }

    eval("\$teamorga_forumdisplay = \"" . $templates->get("teamorga_forumdisplay") . "\";");
}

/**
 * showthread_start – Render the team organisation panel above the posts.
 * Also handles all POST actions for topic-level participation.
 */
function teamorga_showthread()
{
    global $db, $mybb, $templates, $lang;
    global $teamorga_showthread;
    global $teamorga_addbutton;
    global $teamorga_ttid, $teamorga_deadline, $teamorga_prio, $teamorga_prio_label;
    global $teamorga_list_mitmachen, $teamorga_list_enthalten, $teamorga_list_mehrzeit, $teamorga_list_unanswered;
    teamorga_load_lang();


    $teamorga_showthread = "";
    $teamorga_addbutton  = "";

    if (!teamorga_is_group_allowed()) {
        return;
    }

    $tid    = $mybb->get_input("tid", MyBB::INPUT_INT);
    $uid    = (int) $mybb->user["uid"];
    $action = $mybb->get_input("teamorga_action");

    // --- Action: add existing thread to teamorga (form display) ---
    if ($action === "prepare_add") {
        if (!teamorga_can_always_edit()) {
            error_no_permission();
        }

        $exists = $db->num_rows($db->simple_select("teamorga_topic", "id", "tid={$tid}"));
        if ($exists) {
            redirect("showthread.php?tid={$tid}", $lang->teamorga_msg_already_added);
        }

        $days_offset                = (int) $mybb->settings["teamorga_deadline_default"];
        $teamorga_addform_deadline  = (new DateTime())->modify("+{$days_offset} days")->format("Y-m-d");
        $teamorga_addform_tid       = $tid;

        eval("\$teamorga_showthread = \"" . $templates->get("teamorga_addform") . "\";");
        return;
    }

    // --- Action: save retroactive addition ---
    if ($mybb->request_method === "post" && $action === "save_add") {
        verify_post_check($mybb->get_input("my_post_key"));

        if (!teamorga_can_always_edit()) {
            error_no_permission();
        }

        $exists = $db->num_rows($db->simple_select("teamorga_topic", "id", "tid={$tid}"));
        if ($exists) {
            redirect("showthread.php?tid={$tid}", $lang->teamorga_msg_already_added);
        }

        $deadline = $db->escape_string($mybb->get_input("teamorga_deadline"));
        $prio     = teamorga_sanitize_prio($mybb->get_input("teamorga_prio"));

        $ttid = $db->insert_query("teamorga_topic", [
            "tid"           => $tid,
            "organizer_uid" => (int) $mybb->user["uid"],
            "deadline"      => $deadline,
            "prio"          => $prio,
            "status"        => "active"
        ]);

        teamorga_add_participants((int) $ttid, (int) $mybb->user["uid"]);
        redirect("showthread.php?tid={$tid}", $lang->teamorga_msg_added);
    }

    // --- Action: show edit form for topic meta ---
    if ($action === "edit_topic") {
        $topic = teamorga_get_topic($tid);
        if (!$topic) {
            error($lang->teamorga_err_not_found);
        }

        if (!teamorga_can_always_edit() && (int) $topic["organizer_uid"] !== $uid) {
            error_no_permission();
        }

        $teamorga_tid    = $tid;
        $teamorga_ttid   = (int) $topic["id"];
        $teamorga_deadline_raw = $topic["deadline"];

        $prio_options = ["low", "normal", "medium", "high", "inactive"];
        $prio_selected = [];
        foreach ($prio_options as $opt) {
            $prio_selected[$opt] = ($topic["prio"] === $opt) ? "selected=\"selected\"" : "";
        }

        eval("\$teamorga_showthread = \"" . $templates->get("teamorga_edit_topic") . "\";");
        return;
    }

    // --- Action: save topic meta edit ---
    if ($mybb->request_method === "post" && $action === "edit_topic_save") {
        verify_post_check($mybb->get_input("my_post_key"));

        $ttid     = $mybb->get_input("ttid", MyBB::INPUT_INT);
        $deadline = $db->escape_string($mybb->get_input("teamorga_deadline"));
        $prio     = teamorga_sanitize_prio($mybb->get_input("teamorga_prio"));

        if ($ttid <= 0) {
            error($lang->teamorga_err_invalid_entry);
        }

        // Verify ttid belongs to current thread and check permissions
        $ttid_topic = $db->fetch_array($db->simple_select("teamorga_topic", "id,organizer_uid", "id={$ttid} AND tid={$tid}"));
        if (!$ttid_topic) {
            error($lang->teamorga_err_invalid_entry);
        }
        if (!teamorga_can_always_edit() && (int) $ttid_topic["organizer_uid"] !== $uid) {
            error_no_permission();
        }

        $db->update_query("teamorga_topic", [
            "deadline" => $deadline,
            "prio"     => $prio
        ], "id={$ttid}");

        redirect("showthread.php?tid={$tid}", $lang->teamorga_msg_settings_updated);
    }

    // --- Render the teamorga panel ---
    $topic = teamorga_get_topic($tid);

    // Show add button if this thread has no teamorga entry yet
    if (!$topic) {
        if (teamorga_can_always_edit()) {
            $add_url = "showthread.php?tid={$tid}&teamorga_action=prepare_add";
            eval("\$teamorga_addbutton = \"" . $templates->get("teamorga_showthread_addbutton") . "\";");
        }
        // $teamorga_showthread stays "" – button is injected separately above the thread
        return;
    }

    $teamorga_ttid = (int) $topic["id"];

    // Auto-deactivate suggestions whose post no longer exists
    $orphan_q = $db->query("
        SELECT s.id FROM " . TABLE_PREFIX . "teamorga_suggestions s
        LEFT JOIN " . TABLE_PREFIX . "posts po ON po.pid = s.pid
        WHERE s.tt_id = {$teamorga_ttid}
          AND s.active = 1
          AND (s.pid IS NULL OR po.pid IS NULL)
    ");
    while ($orphan = $db->fetch_array($orphan_q)) {
        $orphan_id = (int) $orphan["id"];
        $db->update_query("teamorga_suggestions", ["active" => 0], "id={$orphan_id}");
        $db->delete_query("teamorga_suggestion_participants", "sug_id={$orphan_id} AND status='pending'");
    }

    // --- POST: update participation status ---
    if ($mybb->request_method === "post" && $action === "update_participation") {
        verify_post_check($mybb->get_input("my_post_key"));

        $ttid_post = $mybb->get_input("ttid", MyBB::INPUT_INT);

        // Verify ttid belongs to current thread to prevent cross-thread manipulation
        if (!$db->fetch_field($db->simple_select("teamorga_topic", "id", "id={$ttid_post} AND tid={$tid}"), "id")) {
            error($lang->teamorga_err_invalid_entry);
        }

        $allowed      = ["approved", "abstain", "time", "pending", "unanswered", "disapproved"];
        $new_status   = $mybb->get_input("teamorga_status");
        // input.button liefert sein Label als Wert – daher eigener Name je Status.
        foreach (["approved", "abstain", "time"] as $status_key) {
            if ($mybb->get_input("teamorga_status_" . $status_key) !== "") {
                $new_status = $status_key;
            }
        }

        if (!in_array($new_status, $allowed, true)) {
            error($lang->teamorga_err_invalid_status);
        }
        $new_status_escaped = $db->escape_string($new_status);

        $old_row    = $db->fetch_array($db->simple_select("teamorga_topic_participants", "status,id", "uid={$uid} AND tt_id={$ttid_post}"));
        $old_status = $old_row ? $old_row["status"] : null;
        $part_id    = $old_row ? (int) $old_row["id"] : 0;

        // Validate and sanitise proposed deadline when requesting more time
        $proposed_deadline = "";
        if ($new_status === "time") {
            $raw_date = $mybb->get_input("proposed_deadline");
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date)) {
                $proposed_deadline = $raw_date;
            }
        }

        if ($part_id) {
            $db->update_query("teamorga_topic_participants", [
                "status"            => $new_status_escaped,
                "proposed_deadline" => $db->escape_string($proposed_deadline),
            ], "id={$part_id}");
        } elseif (in_array($new_status, ["approved", "abstain", "time", "pending"], true)) {
            $db->insert_query("teamorga_topic_participants", [
                "uid"               => $uid,
                "tt_id"             => $ttid_post,
                "status"            => $new_status_escaped,
                "proposed_deadline" => $db->escape_string($proposed_deadline),
            ]);
        }

        // Fire MyAlerts notification to topic owner when user requests more time
        if ($new_status === "time" && $proposed_deadline !== ""
            && function_exists('myalerts_is_activated') && myalerts_is_activated()
            && class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $topic_row = $db->fetch_array(
                $db->simple_select("teamorga_topic", "tid,owner", "id={$ttid_post}")
            );
            // Resolve owner username → uid
            $owner_uid = 0;
            if ($topic_row && !empty($topic_row["owner"])) {
                $owner_row = $db->fetch_array(
                    $db->simple_select("users", "uid", "username='" . $db->escape_string($topic_row["owner"]) . "'")
                );
                $owner_uid = $owner_row ? (int) $owner_row["uid"] : 0;
            }
            if ($owner_uid > 0 && $owner_uid !== $uid) {
                $atype = teamorga_get_alert_type('teamorga_time_proposal');
                if ($atype) {
                    $date_fmt = DateTime::createFromFormat("Y-m-d", $proposed_deadline);
                    $date_de  = $date_fmt ? $date_fmt->format("d.m.Y") : $proposed_deadline;
                    $alert    = new MybbStuff_MyAlerts_Entity_Alert($owner_uid, $atype, $ttid_post);
                    $alert->setExtraDetails([
                        "tid"               => (int) ($topic_row["tid"] ?? 0),
                        "proposed_deadline" => $date_de,
                    ]);
                    MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
                }
            }
        }

        // If user abstains: remove them from all open (active) suggestions entirely
        if ($new_status === "abstain" && $ttid_post > 0) {
            $db->query("
                DELETE sp FROM " . TABLE_PREFIX . "teamorga_suggestion_participants sp
                INNER JOIN " . TABLE_PREFIX . "teamorga_suggestions s ON s.id = sp.sug_id
                WHERE sp.uid = {$uid}
                AND sp.tt_id = {$ttid_post}
                AND s.active = 1
            ");
        }

        // Ensure participant is enrolled in all active suggestions when joining
        if (in_array($new_status, ["approved", "pending", "time"], true) && $ttid_post > 0) {
            $sugs = $db->query("SELECT id FROM " . TABLE_PREFIX . "teamorga_suggestions WHERE tt_id={$ttid_post} AND active=1");
            while ($srow = $db->fetch_array($sugs)) {
                $sug_id   = (int) $srow["id"];
                $exists_q = $db->simple_select("teamorga_suggestion_participants", "id,status", "uid={$uid} AND sug_id={$sug_id}");
                $exists_r = $db->fetch_array($exists_q);
                if (!$exists_r) {
                    $db->insert_query("teamorga_suggestion_participants", [
                        "uid"    => $uid,
                        "tt_id"  => $ttid_post,
                        "sug_id" => $sug_id,
                        "status" => "pending"
                    ]);
                } elseif ($exists_r["status"] === "unanswered") {
                    $db->update_query("teamorga_suggestion_participants", ["status" => "pending"], "id=" . (int) $exists_r["id"]);
                }
            }
        }

        redirect("showthread.php?tid={$tid}", $lang->teamorga_msg_status_updated);
    }

    // --- POST: update ownership ---
    if ($mybb->request_method === "post" && $action === "update_ownership") {
        verify_post_check($mybb->get_input("my_post_key"));

        $ttid_post    = $mybb->get_input("ttid", MyBB::INPUT_INT);
        $action_type  = $mybb->get_input("teamorga_ownership");

        // Verify ttid belongs to current thread
        if (!$db->fetch_field($db->simple_select("teamorga_topic", "id", "id={$ttid_post} AND tid={$tid}"), "id")) {
            error($lang->teamorga_err_invalid_entry);
        }

        if ($action_type === "ownership_add") {
            $db->update_query("teamorga_topic", ["owner" => $db->escape_string($mybb->user["username"])], "id={$ttid_post}");
        } elseif ($action_type === "ownership_remove") {
            $db->update_query("teamorga_topic", ["owner" => null], "id={$ttid_post}", 1, true);
        } else {
            error($lang->teamorga_err_invalid_ownership);
        }

        redirect("showthread.php?tid={$tid}", $lang->teamorga_msg_ownership_updated);
    }

    // --- POST: kick participant ---
    if ($mybb->request_method === "post" && $action === "kick_participant") {
        verify_post_check($mybb->get_input("my_post_key"));

        if (!teamorga_is_team_account()) {
            error_no_permission();
        }

        $ttid_post = $mybb->get_input("ttid", MyBB::INPUT_INT);
        $kick_uid  = $mybb->get_input("kick_uid", MyBB::INPUT_INT);

        if ($ttid_post <= 0 || $kick_uid <= 0) {
            error($lang->teamorga_err_invalid_params);
        }

        // Verify ttid belongs to current thread to prevent cross-thread manipulation
        if (!$db->fetch_field($db->simple_select("teamorga_topic", "id", "id={$ttid_post} AND tid={$tid}"), "id")) {
            error($lang->teamorga_err_invalid_entry);
        }

        // Remove from topic participants
        $db->delete_query("teamorga_topic_participants", "tt_id={$ttid_post} AND uid={$kick_uid}");

        // Remove from all active suggestion participants in this topic
        $db->query("
            DELETE sp FROM " . TABLE_PREFIX . "teamorga_suggestion_participants sp
            INNER JOIN " . TABLE_PREFIX . "teamorga_suggestions s ON s.id = sp.sug_id
            WHERE sp.tt_id = {$ttid_post}
            AND sp.uid = {$kick_uid}
            AND s.active = 1
        ");

        redirect("showthread.php?tid={$tid}", $lang->teamorga_msg_participant_removed);
    }

    // --- POST: owner decides on a time proposal ---
    if ($mybb->request_method === "post" && $action === "decide_time_proposal") {
        verify_post_check($mybb->get_input("my_post_key"));

        $ttid_post   = $mybb->get_input("ttid", MyBB::INPUT_INT);
        $proposal_uid = $mybb->get_input("proposal_uid", MyBB::INPUT_INT);
        $decision    = $mybb->get_input("proposal_decision");
        if ($mybb->get_input("proposal_accept") !== "") {
            $decision = "accept";
        } elseif ($mybb->get_input("proposal_decline") !== "") {
            $decision = "decline";
        }

        if ($ttid_post <= 0 || $proposal_uid <= 0 || !in_array($decision, ["accept", "decline"], true)) {
            error($lang->teamorga_err_invalid_params);
        }

        // Only topic owner may decide
        $topic_check = teamorga_get_topic($tid);
        if (!$topic_check || strcasecmp($topic_check["owner"] ?? "", $mybb->user["username"]) !== 0) {
            error_no_permission();
        }

        if ($decision === "accept") {
            // Fetch proposed deadline from participant row
            $p_row = $db->fetch_array(
                $db->simple_select("teamorga_topic_participants", "proposed_deadline", "uid={$proposal_uid} AND tt_id={$ttid_post}")
            );
            if ($p_row && !empty($p_row["proposed_deadline"])) {
                $db->update_query("teamorga_topic", [
                    "deadline" => $db->escape_string($p_row["proposed_deadline"]),
                ], "id={$ttid_post}");
            }
            $db->update_query("teamorga_topic_participants", [
                "status"            => "approved",
                "proposed_deadline" => "",
            ], "uid={$proposal_uid} AND tt_id={$ttid_post}");
            redirect("showthread.php?tid={$tid}", $lang->teamorga_msg_proposal_accepted);
        } else {
            $db->update_query("teamorga_topic_participants", [
                "status"            => "abstain",
                "proposed_deadline" => "",
            ], "uid={$proposal_uid} AND tt_id={$ttid_post}");
            // Remove from all active suggestions (same logic as self-abstain)
            $db->query("
                DELETE sp FROM " . TABLE_PREFIX . "teamorga_suggestion_participants sp
                INNER JOIN " . TABLE_PREFIX . "teamorga_suggestions s ON s.id = sp.sug_id
                WHERE sp.uid = {$proposal_uid}
                AND sp.tt_id = {$ttid_post}
                AND s.active = 1
            ");
            redirect("showthread.php?tid={$tid}", $lang->teamorga_msg_proposal_declined);
        }
    }

    // --- Build display data ---
    $date              = DateTime::createFromFormat("Y-m-d", $topic["deadline"]);
    $teamorga_deadline = $date ? $date->format("d.m.Y") : "-";
    $teamorga_prio     = (string) $topic["prio"];
    $prio_key          = "teamorga_prio_" . $teamorga_prio;
    $teamorga_prio_label = isset($lang->$prio_key) ? $lang->$prio_key : ucfirst($teamorga_prio);


    // Build participant lists grouped by status
    $participants_q = $db->query("
        SELECT p.uid, p.status, p.proposed_deadline, u.username
        FROM " . TABLE_PREFIX . "teamorga_topic_participants p
        LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid = p.uid
        WHERE p.tt_id = {$teamorga_ttid}
    ");

    $list_mitmachen = $list_enthalten = $list_mehrzeit = $list_unanswered = [];
    $can_kick      = teamorga_is_team_account();
    $is_owner      = !empty($topic["owner"])
        && strcasecmp($topic["owner"], $mybb->user["username"]) === 0;

    while ($row = $db->fetch_array($participants_q)) {
        $safe_tag  = preg_replace("/[^a-zA-Z0-9_-]/", "", $row["username"]);
        $user_link = "<{$safe_tag}>" . build_profile_link($row["username"]) . "</{$safe_tag}>";

        if ($can_kick) {
            $kick_uid = (int) $row["uid"];
            $kick_btn = "";
            eval("\$kick_btn = \"" . $templates->get("teamorga_participant_kick") . "\";");
            $user_link .= $kick_btn;
        }

        // For "time" participants: show proposed deadline and owner controls
        if ($row["status"] === "time" && !empty($row["proposed_deadline"])) {
            $pd_date = DateTime::createFromFormat("Y-m-d", $row["proposed_deadline"]);
            $pd_fmt  = $pd_date ? $pd_date->format("d.m.Y") : $row["proposed_deadline"];
            $user_link .= " <span class=\"teamorga-time-proposal-date\">(bis " . htmlspecialchars_uni($pd_fmt) . ")</span>";
            if ($is_owner) {
                $proposal_uid = (int) $row["uid"];
                $proposal_date = htmlspecialchars_uni($pd_fmt);
                $time_controls = "";
                eval("\$time_controls = \"" . $templates->get("teamorga_participant_time_proposal") . "\";");
                $user_link .= $time_controls;
            }
        }

        switch ($row["status"]) {
            case "pending":
            case "approved":
                $list_mitmachen[] = $user_link;
                break;
            case "abstain":
            case "disapproved":
                $list_enthalten[] = $user_link;
                break;
            case "time":
                $list_mehrzeit[] = $user_link;
                break;
            default:
                $list_unanswered[] = $user_link;
                break;
        }
    }

    $teamorga_list_mitmachen  = $list_mitmachen  ? implode(", ", $list_mitmachen)  : "<em>" . $lang->teamorga_none . "</em>";
    $teamorga_list_enthalten  = $list_enthalten  ? implode(", ", $list_enthalten)  : "<em>" . $lang->teamorga_none . "</em>";
    $teamorga_list_mehrzeit   = $list_mehrzeit   ? implode(", ", $list_mehrzeit)   : "<em>" . $lang->teamorga_none . "</em>";
    $teamorga_list_unanswered = $list_unanswered ? implode(", ", $list_unanswered) : "<em>" . $lang->teamorga_none . "</em>";

    // Owner display and controls
    if (!empty($topic["owner"])) {
        $safe_tag       = preg_replace("/[^a-zA-Z0-9_-]/", "", $topic["owner"]);
        $teamorga_owner = "<{$safe_tag}>" . build_profile_link($topic["owner"]) . "</{$safe_tag}>";
        if (strcasecmp($topic["owner"], $mybb->user["username"]) === 0) {
            eval("\$teamorga_owner_control = \"" . $templates->get("teamorga_owner_control_remove") . "\";");
        } else {
            $teamorga_owner_control = "";
        }
    } else {
        $teamorga_owner = "<em>" . $lang->teamorga_none . "</em>";
        eval("\$teamorga_owner_control = \"" . $templates->get("teamorga_owner_control_add") . "\";");
    }

    // Build links to active suggestions
    $teamorga_header_suggestions = "";
    $sug_links = teamorga_build_suggestion_links((int) $teamorga_ttid, $tid);
    if (!empty($sug_links)) {
        $teamorga_header_suggestions_links = implode(" &middot; ", $sug_links);
        eval("\$teamorga_header_suggestions = \"" . $templates->get("teamorga_header_suggestions") . "\";");
    }

    // Edit link for organizer / privileged users
    $teamorga_can_edit_topic = "";
    if (teamorga_can_always_edit() || (int) $topic["organizer_uid"] === $uid) {
        eval("\$teamorga_can_edit_topic = \"" . $templates->get("teamorga_showthread_header_edit_link") . "\";");
    }

    // Render sub-templates
    eval("\$teamorga_ownership_form      = \"" . $templates->get("teamorga_showthread_ownership_form") . "\";");
    eval("\$teamorga_header              = \"" . $templates->get("teamorga_showthread_header") . "\";");
    eval("\$teamorga_participation_form  = \"" . $templates->get("teamorga_showthread_participation_form") . "\";");
    eval("\$teamorga_participant_list    = \"" . $templates->get("teamorga_showthread_participant_list") . "\";");
    eval("\$teamorga_suggestion_new      = \"" . $templates->get("teamorga_showthread_suggestion_new") . "\";");

    eval("\$teamorga_showthread = \"" . $templates->get("teamorga_showthread") . "\";");
}

/**
 * newreply_start – Inject suggestion field into reply form for teamorga threads.
 */
function teamorga_newreply()
{
    global $db, $mybb, $templates, $teamorga_newreply_suggestion, $lang;
    teamorga_load_lang();


    $teamorga_newreply_suggestion = "";

    $tid   = $mybb->get_input("tid", MyBB::INPUT_INT);
    $topic = teamorga_get_topic($tid);

    if (!$topic) {
        return;
    }

    // Preserve a typed suggestion across preview / validation reloads.
    $teamorga_suggestion_value = htmlspecialchars_uni($mybb->get_input("new_suggestion"));

    $teamorga_ttid = (int) $topic["id"];
    eval("\$teamorga_newreply_suggestion = \"" . $templates->get("teamorga_newreply_suggestion") . "\";");
}

/**
 * datahandler_post_insert_post_end – Create a suggestion entry when a reply with
 * suggestion text is posted to a teamorga thread.
 */
function teamorga_on_post_insert(&$post)
{
    global $db, $mybb;

    if (!teamorga_is_group_allowed()) {
        return;
    }

    $pid            = (int) $post->pid;
    $tid            = (int) $post->data["tid"];
    $poster_uid     = (int) $post->data["uid"];
    $new_suggestion = trim($mybb->get_input("new_suggestion"));

    if ($new_suggestion === "") {
        return;
    }

    // Only process if this thread is a teamorga thread
    $topic = $db->fetch_array($db->simple_select("teamorga_topic", "*", "tid={$tid}"));
    if (!$topic) {
        return;
    }

    // Avoid duplicate entries for the same post
    $exists_id = (int) $db->fetch_field(
        $db->simple_select("teamorga_suggestions", "id", "pid={$pid}"),
        "id"
    );
    if ($exists_id) {
        return;
    }

    teamorga_create_suggestion((int) $topic["id"], $pid, $poster_uid, $new_suggestion);
}

/**
 * Create a suggestion for a post and enroll all non-abstaining topic participants.
 * The author automatically votes "approved". Returns the new suggestion ID.
 */
function teamorga_create_suggestion(int $tt_id, int $pid, int $poster_uid, string $text): int
{
    global $db;

    $suggestion_id = (int) $db->insert_query("teamorga_suggestions", [
        "tt_id"      => $tt_id,
        "suggestion" => $db->escape_string($text),
        "owner_uid"  => $poster_uid,
        "pid"        => $pid,
        "dateline"   => TIME_NOW,
        "active"     => 1
    ]);

    $participants_q = $db->query("SELECT uid FROM " . TABLE_PREFIX . "teamorga_topic_participants WHERE tt_id={$tt_id} AND status!='abstain'");
    while ($prow = $db->fetch_array($participants_q)) {
        $participant_uid = (int) $prow["uid"];
        $db->insert_query("teamorga_suggestion_participants", [
            "uid"    => $participant_uid,
            "tt_id"  => $tt_id,
            "sug_id" => $suggestion_id,
            "status" => ($participant_uid === $poster_uid) ? "approved" : "pending"
        ]);
    }

    return $suggestion_id;
}

/**
 * postbit helper – for a post WITHOUT a suggestion: let its author add one
 * afterwards. Handles the POST save and renders the button / form.
 */
function teamorga_postbit_add_suggestion(array &$post): void
{
    global $mybb, $db, $templates, $lang;

    $uid = (int) $mybb->user["uid"];
    if ($uid <= 0 || (int) $post["uid"] !== $uid || !teamorga_is_group_allowed()) {
        return;
    }

    static $topic_cache = [];
    $post_tid = (int) $post["tid"];
    if (!array_key_exists($post_tid, $topic_cache)) {
        $topic_cache[$post_tid] = teamorga_get_topic($post_tid);
    }
    $topic = $topic_cache[$post_tid];
    if (!$topic) {
        return;
    }

    $post_pid = (int) $post["pid"];
    $action   = $mybb->get_input("teamorga_action");
    $target   = $mybb->get_input("pid", MyBB::INPUT_INT);

    // --- POST: save a new suggestion for this post ---
    if ($mybb->request_method === "post" && $action === "add_suggestion_save" && $target === $post_pid) {
        verify_post_check($mybb->get_input("my_post_key"));

        $text = trim($mybb->get_input("new_suggestion"));
        if ($text === "") {
            error($lang->teamorga_err_suggestion_empty);
        }

        // Only one suggestion per post (also guards against double submits)
        $exists = (int) $db->fetch_field(
            $db->simple_select("teamorga_suggestions", "id", "pid={$post_pid}"),
            "id"
        );
        if (!$exists) {
            teamorga_create_suggestion((int) $topic["id"], $post_pid, $uid, $text);
        }

        // Land on the new suggestion block (not the post top) so the scroll position stays put
        redirect("showthread.php?tid={$post_tid}&pid={$post_pid}#teamorga_sug_{$post_pid}", $lang->teamorga_msg_suggestion_added);
    }

    // Collapsible form (no page reload, so the scroll position never changes)
    $teamorga_add_open = "";
    eval("\$post[\"teamorga\"] = \"" . $templates->get("teamorga_suggestion_add_button") . "\";");
}

/**
 * Empty {$post['teamorga']} for every postbit flavour (see the hooks at the top of the file).
 */
function teamorga_postbit_default(&$post)
{
    if (!isset($post["teamorga"])) {
        $post["teamorga"] = "";
    }
}

/**
 * Labels for the alert types in the UCP alert settings (alerts.php?action=settings).
 */
function teamorga_myalerts_load_lang()
{
    global $lang;

    if (!isset($lang->myalerts_setting_teamorga_suggestion_vote)) {
        $lang->load('teamorga');
    }
}

/**
 * postbit – Render voting controls on posts that have an active suggestion.
 */
function teamorga_postbit(&$post)
{
    global $mybb, $db, $templates, $lang;
    teamorga_load_lang();


    $post["teamorga"] = "";

    $suggestion = $db->fetch_array(
        $db->simple_select("teamorga_suggestions", "*", "pid=" . (int) $post["pid"])
    );
    if (!$suggestion) {
        teamorga_postbit_add_suggestion($post);
        return;
    }

    $uid    = (int) $mybb->user["uid"];
    $tid    = $mybb->get_input("tid", MyBB::INPUT_INT);
    $action = $mybb->get_input("teamorga_action");

    // --- POST: save suggestion text edit ---
    if ($mybb->request_method === "post" && $action === "save_suggestion") {
        verify_post_check($mybb->get_input("my_post_key"));

        $sug_id = $mybb->get_input("sug_id", MyBB::INPUT_INT);
        if ($sug_id <= 0) {
            error($lang->teamorga_err_invalid_suggestion);
        }

        $sug_row = $db->fetch_array($db->simple_select("teamorga_suggestions", "id,owner_uid", "id={$sug_id}"));
        if (!$sug_row || ((int) $sug_row["owner_uid"] !== $uid && !teamorga_can_always_edit())) {
            error($lang->teamorga_err_no_edit_permission);
        }

        $db->update_query("teamorga_suggestions", [
            "suggestion" => $db->escape_string($mybb->get_input("suggestion"))
        ], "id={$sug_id}");

        redirect("showthread.php?tid={$post['tid']}&pid={$post['pid']}#pid{$post['pid']}", $lang->teamorga_msg_suggestion_updated);
    }

    // --- POST: toggle suggestion active/inactive ---
    if ($mybb->request_method === "post" && $action === "toggle_suggestion_active") {
        verify_post_check($mybb->get_input("my_post_key"));

        $sug_id  = $mybb->get_input("sug_id", MyBB::INPUT_INT);
        if ($sug_id <= 0) {
            error($lang->teamorga_err_invalid_suggestion);
        }
        $srow = $db->fetch_array($db->simple_select("teamorga_suggestions", "id,owner_uid,active,tt_id,pid", "id={$sug_id}"));
        if (!$srow) {
            error($lang->teamorga_err_suggestion_not_found);
        }
        if (!teamorga_can_toggle_suggestion($srow, $uid)) {
            error($lang->teamorga_err_no_permission);
        }

        $new_active = $srow["active"] ? 0 : 1;
        $db->update_query("teamorga_suggestions", ["active" => $new_active], "id={$sug_id}");

        if ($new_active === 0) {
            // Deactivated: remove all still-pending votes — inactive = erledigt
            $db->delete_query("teamorga_suggestion_participants", "sug_id={$sug_id} AND status='pending'");
        } else {
            // Reactivated: re-enroll all current non-abstaining topic participants without an existing row
            $tt_id_sug = (int) $srow["tt_id"];
            $tp_q = $db->query("SELECT uid FROM " . TABLE_PREFIX . "teamorga_topic_participants WHERE tt_id={$tt_id_sug} AND status!='abstain'");
            while ($trow = $db->fetch_array($tp_q)) {
                $tp_uid = (int) $trow["uid"];
                $exists = $db->num_rows($db->simple_select("teamorga_suggestion_participants", "id", "uid={$tp_uid} AND sug_id={$sug_id}"));
                if (!$exists) {
                    $db->insert_query("teamorga_suggestion_participants", [
                        "uid"    => $tp_uid,
                        "tt_id"  => $tt_id_sug,
                        "sug_id" => $sug_id,
                        "status" => "pending",
                    ]);
                }
            }
        }

        $sug_pid = (int) $srow["pid"];
        redirect("showthread.php?tid={$post['tid']}&pid={$sug_pid}#pid{$sug_pid}", $lang->teamorga_msg_suggestion_updated);
    }

    // --- POST: review or withdraw review of suggestion ---
    $new_status = null;
    if ($mybb->request_method === "post" && $action === "review_suggestion") {
        verify_post_check($mybb->get_input("my_post_key"));

        $allowed_reviews = ["approved", "disapproved"];
        $decision        = $mybb->get_input("review_decision");
        if (!in_array($decision, $allowed_reviews, true)) {
            error($lang->teamorga_err_invalid_vote);
        }
        $new_status = $decision;
    } elseif ($mybb->request_method === "post" && $action === "withdraw_review") {
        verify_post_check($mybb->get_input("my_post_key"));
        $new_status = "pending";
    }

    if ($new_status !== null) {
        $sug_id      = $mybb->get_input("sug_id", MyBB::INPUT_INT);
        $participant = $db->fetch_array(
            $db->simple_select("teamorga_suggestion_participants", "*", "uid={$uid} AND sug_id={$sug_id}")
        );
        if (!$participant) {
            error($lang->teamorga_err_not_participant);
        }

        $sug_row = $db->fetch_array(
            $db->simple_select("teamorga_suggestions", "owner_uid,pid,tt_id,active", "id={$sug_id}")
        );
        if (!$sug_row) {
            error($lang->teamorga_err_suggestion_not_found);
        }
        // Inactive suggestions are closed: no voting, no withdrawing
        if (empty($sug_row["active"])) {
            error($lang->teamorga_err_no_permission);
        }

        $db->update_query(
            "teamorga_suggestion_participants",
            ["status" => $db->escape_string($new_status)],
            "uid={$uid} AND sug_id={$sug_id}"
        );

        // Fire MyAlerts notification to suggestion author (only for actual votes, not withdrawals)
        if ($new_status !== "pending"
            && function_exists('myalerts_is_activated') && myalerts_is_activated()
            && class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $owner_uid = (int) ($sug_row["owner_uid"] ?? 0);

            // Don't alert yourself
            if ($owner_uid > 0 && $owner_uid !== $uid) {
                $alertType = teamorga_get_alert_type('teamorga_suggestion_vote');

                if ($alertType) {
                    $alert_pid = (int) ($sug_row["pid"] ?? 0);
                    $alert_tid = (int) $post["tid"];
                    $alert     = new MybbStuff_MyAlerts_Entity_Alert($owner_uid, $alertType, $sug_id);
                    $alert->setExtraDetails([
                        "tid"      => $alert_tid,
                        "pid"      => $alert_pid,
                        "decision" => $new_status,
                    ]);
                    MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
                }
            }
        }

        // Back to the suggestion that was voted on (the handler runs in the first suggestion postbit of the page)
        $sug_pid = (int) $sug_row["pid"];
        $msg     = $new_status === "pending" ? $lang->teamorga_msg_vote_withdrawn : $lang->teamorga_msg_vote_saved;
        redirect("showthread.php?tid={$post['tid']}&pid={$sug_pid}#pid{$sug_pid}", $msg);
    }

    // --- Render suggestion block ---
    if (empty($suggestion["suggestion"])) {
        return;
    }

    require_once MYBB_ROOT . "inc/class_parser.php";
    $parser         = new postParser();
    $parser_options = [
        "allow_html"     => 1,
        "allow_mycode"   => 1,
        "allow_smilies"  => 1,
        "allow_imgcode"  => 1,
        "allow_videocode"=> 1,
        "filter_badwords"=> 1
    ];
    $parsed_text = $parser->parse_message($suggestion["suggestion"], $parser_options);

    $author_user              = get_user($suggestion["owner_uid"]);
    $safe_tag                 = preg_replace("/[^a-zA-Z0-9_-]/", "", $author_user["username"]);
    $teamorga_suggestion_author = "<{$safe_tag}>" . build_profile_link($author_user["username"]) . "</{$safe_tag}>";

    // Status of current user for this suggestion
    $status_row = $db->fetch_array($db->simple_select(
        "teamorga_suggestion_participants",
        "*",
        "uid={$uid} AND tt_id=" . (int) $suggestion["tt_id"] . " AND sug_id=" . (int) $suggestion["id"]
    ));
    $raw_status = $status_row ? $status_row["status"] : "";

    // Edit button (owner or privileged)
    $teamorga_edit_button = "";
    if ((int) $suggestion["owner_uid"] === $uid || teamorga_can_always_edit()) {
        // Preserve the current thread page so the edit form stays on the
        // page where the suggestion actually is (default would be page 1).
        $current_page = $mybb->get_input("page", MyBB::INPUT_INT);
        $page_param   = $current_page > 1 ? "&amp;page=" . $current_page : "";
        $teamorga_edit_button = '<form method="get" action="showthread.php#pid' . (int) $post["pid"] . '" class="teamorga-inline-form">'
            . '<input type="hidden" name="tid" value="' . (int) $post["tid"] . '" />'
            . '<input type="hidden" name="sug_id" value="' . (int) $suggestion["id"] . '" />'
            . '<input type="hidden" name="teamorga_action" value="edit_suggestion" />'
            . ($current_page > 1 ? '<input type="hidden" name="page" value="' . $current_page . '" />' : '')
            . '<input type="submit" class="button" value="' . htmlspecialchars_uni($lang->teamorga_edit) . '" /></form>';
    }

    // Toggle active/inactive button
    $teamorga_toggle_suggestion_button = "";
    if (teamorga_can_toggle_suggestion($suggestion, $uid)) {
        $is_active = !empty($suggestion["active"]);
        // The two fragments end up inside a value="" attribute: no template HTML comments.
        if ($is_active) {
            $title = htmlspecialchars_uni($lang->teamorga_suggestion_deactivate_title);
            eval("\$toggle_btn_content = \"" . $templates->get("teamorga_suggestion_toggle_inactive", 1, 0) . "\";");
        } else {
            $title = htmlspecialchars_uni($lang->teamorga_suggestion_activate_title);
            eval("\$toggle_btn_content = \"" . $templates->get("teamorga_suggestion_toggle_active", 1, 0) . "\";");
        }
        eval("\$teamorga_toggle_suggestion_button = \"" . $templates->get("teamorga_suggestion_toggle") . "\";");
    }

    // Review controls based on user's current vote
    $teamorga_review_controls = "";
    if ($action === "edit_suggestion" && ((int) $suggestion["owner_uid"] === $uid || teamorga_can_always_edit())) {
        // Preserve the current thread page for the cancel link.
        $edit_page      = $mybb->get_input("page", MyBB::INPUT_INT);
        $teamorga_cancel_page_param = $edit_page > 1 ? "&amp;page=" . $edit_page : "";
        $teamorga_cancel_page_field = $edit_page > 1 ? '<input type="hidden" name="page" value="' . $edit_page . '" />' : "";
        $teamorga_suggestion_text = "";
        eval("\$teamorga_suggestion_text = \"" . $templates->get("teamorga_suggestion_edit_form") . "\";");
        $teamorga_suggestion_author = "";
    } else {
        $teamorga_suggestion_text = $parsed_text;
        if ($raw_status === "approved") {
            eval("\$teamorga_review_controls = \"" . $templates->get("teamorga_review_controls_approved") . "\";");
        } elseif ($raw_status === "disapproved") {
            eval("\$teamorga_review_controls = \"" . $templates->get("teamorga_review_controls_disapproved") . "\";");
        } elseif (in_array($raw_status, ["pending", "time"], true)) {
            eval("\$teamorga_review_controls = \"" . $templates->get("teamorga_review_controls_pending") . "\";");
        }
    }

    // Suggestion participant list
    $list_approved = $list_disapproved = $list_pending = [];
    $sp_query = $db->query("
        SELECT p.status, u.username
        FROM " . TABLE_PREFIX . "teamorga_suggestion_participants p
        LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid = p.uid
        WHERE p.sug_id = " . (int) $suggestion["id"] . "
    ");
    while ($row = $db->fetch_array($sp_query)) {
        $safe_tag  = preg_replace("/[^a-zA-Z0-9_-]/", "", $row["username"]);
        $user_link = "<{$safe_tag}>" . build_profile_link($row["username"]) . "</{$safe_tag}>";
        switch ($row["status"]) {
            case "approved":    $list_approved[]    = $user_link; break;
            case "disapproved": $list_disapproved[] = $user_link; break;
            case "pending":     $list_pending[]     = $user_link; break;
        }
    }

    $teamorga_list_approved    = $list_approved    ? implode(", ", $list_approved)    : "<em>" . $lang->teamorga_none . "</em>";
    $teamorga_list_disapproved = $list_disapproved ? implode(", ", $list_disapproved) : "<em>" . $lang->teamorga_none . "</em>";
    $teamorga_list_pending     = $list_pending     ? implode(", ", $list_pending)     : "<em>" . $lang->teamorga_none . "</em>";

    eval("\$teamorga_suggestion_participant_list = \"" . $templates->get("teamorga_suggestion_participant_list") . "\";");

    if (!empty($suggestion["active"])) {
        $teamorga_suggestion_by = $lang->sprintf($lang->teamorga_suggestion_by, $teamorga_suggestion_author);
        eval("\$post[\"teamorga\"] = \"" . $templates->get("teamorga_suggestion_box") . "\";");
    } else {
        $teamorga_suggestion_by = $lang->sprintf($lang->teamorga_suggestion_inactive_by, $teamorga_suggestion_author);
        eval("\$post[\"teamorga\"] = \"" . $templates->get("teamorga_suggestion_box_inactive") . "\";");
    }
}

/**
 * index_start – Show alerts on the forum index for pending teamorga items.
 */
function teamorga_index()
{
    global $db, $mybb, $templates, $teamorga_index_alerts, $lang;
    teamorga_load_lang();


    $teamorga_index_alerts = "";

    if (!teamorga_is_group_allowed() || !$mybb->user["uid"]) {
        return;
    }

    $uid    = (int) $mybb->user["uid"];
    $alerts = "";

    // Alert: topic is still unanswered by current user
    $q = $db->query("
        SELECT t.subject, t.tid
        FROM " . TABLE_PREFIX . "teamorga_topic tt
        JOIN " . TABLE_PREFIX . "teamorga_topic_participants p ON p.tt_id = tt.id
        JOIN " . TABLE_PREFIX . "threads t ON t.tid = tt.tid
        WHERE p.uid = {$uid}
          AND p.status = 'unanswered'
          AND tt.prio != 'inactive'
          AND t.visible = 1
    ");
    while ($row = $db->fetch_array($q)) {
        $threadlink = get_thread_link((int) $row["tid"]);
        $subject    = htmlspecialchars_uni($row["subject"]);
        $teamorga_alert_text = $lang->sprintf($lang->teamorga_index_unanswered, '<a href="' . $threadlink . '">' . $subject . '</a>');
        eval("\$alerts .= \"" . $templates->get("teamorga_index_alert") . "\";");
    }

    // Alert: deadline approaching and user still has pending actions
    $memo_days  = (int) $mybb->settings["teamorga_memo_default"];
    $memo_cutoff = date("Y-m-d", strtotime("+{$memo_days} days"));
    $team_uid   = (int) $mybb->settings["teamorga_id"];

    $q = $db->query("
        SELECT DISTINCT t.subject, t.tid, tt.deadline
        FROM " . TABLE_PREFIX . "threads t
        JOIN " . TABLE_PREFIX . "teamorga_topic tt ON tt.tid = t.tid
        JOIN " . TABLE_PREFIX . "teamorga_topic_participants tp ON tp.tt_id = tt.id AND tp.uid = {$uid} AND tp.status != 'abstain'
        WHERE tt.prio != 'inactive'
          AND tt.deadline != ''
          AND STR_TO_DATE(tt.deadline, '%Y-%m-%d') IS NOT NULL
          AND STR_TO_DATE(tt.deadline, '%Y-%m-%d') <= STR_TO_DATE('" . $db->escape_string($memo_cutoff) . "', '%Y-%m-%d')
          AND t.visible = 1
          AND EXISTS (
              SELECT 1
              FROM " . TABLE_PREFIX . "teamorga_suggestions s
              JOIN " . TABLE_PREFIX . "posts po ON po.pid = s.pid
              JOIN " . TABLE_PREFIX . "teamorga_suggestion_participants sp ON sp.sug_id = s.id AND sp.uid = {$uid}
              WHERE s.tt_id = tt.id
                AND s.active = 1
                AND s.pid IS NOT NULL
                AND sp.status IN ('pending','unanswered','time')
                AND sp.uid != {$team_uid}
          )
    ");
    while ($row = $db->fetch_array($q)) {
        $threadlink = get_thread_link((int) $row["tid"]);
        $subject    = htmlspecialchars_uni($row["subject"]);
        $deadline   = date("d.m.Y", strtotime($row["deadline"]));
        $teamorga_alert_text = $lang->sprintf($lang->teamorga_index_memo, '<a href="' . $threadlink . '">' . $subject . '</a>', $deadline);
        eval("\$alerts .= \"" . $templates->get("teamorga_index_alert_memo") . "\";");
    }

    // Alert: all suggestions approved (for topic owner)
    $q = $db->simple_select(
        "teamorga_topic",
        "*",
        "owner='" . $db->escape_string($mybb->user["username"]) . "' AND prio!='inactive'"
    );
    while ($row = $db->fetch_array($q)) {
        if (teamorga_is_approved((int) $row["tid"])) {
            $topic_thread = $db->fetch_array($db->simple_select("threads", "subject", "tid=" . (int) $row["tid"] . " AND visible = 1"));
            if (!$topic_thread) {
                continue;
            }
            $subject    = htmlspecialchars_uni($topic_thread["subject"]);
            $threadlink = get_thread_link((int) $row["tid"]);
            $teamorga_alert_text = $lang->sprintf($lang->teamorga_index_approved, '<a href="' . $threadlink . '">' . $subject . '</a>');
        eval("\$alerts .= \"" . $templates->get("teamorga_index_alert_approved") . "\";");
        }
    }

    // Alert: active topics without an owner assigned (for everyone with edit rights)
    if (teamorga_can_always_edit()) {
        $q = $db->query("
            SELECT tt.*, t.subject
            FROM " . TABLE_PREFIX . "teamorga_topic tt
            LEFT JOIN " . TABLE_PREFIX . "threads t ON t.tid = tt.tid
            WHERE (tt.owner IS NULL OR tt.owner = '')
              AND tt.prio != 'inactive'
              AND t.visible = 1
        ");
        while ($row = $db->fetch_array($q)) {
            $subject    = htmlspecialchars_uni($row["subject"]);
            $threadlink = get_thread_link((int) $row["tid"]);
            $teamorga_alert_text = $lang->sprintf($lang->teamorga_index_owner_missing, '<a href="' . $threadlink . '">' . $subject . '</a>');
        eval("\$alerts .= \"" . $templates->get("teamorga_index_alert_owner_missing") . "\";");
        }
    }

    $teamorga_index_alerts = $alerts;
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Check whether the teamorga plugin is enabled for the current forum.
 */
function teamorga_is_forum_enabled($fid = null)
{
    global $mybb, $db;

    // MyBB forumselect: "-1" = all forums, "" = none (Teamorga stays off until forums are chosen).
    $forums = trim((string)($mybb->settings["teamorga_forums"] ?? ""));
    if ($forums === "-1") {
        return true;
    }
    if ($forums === "") {
        return false;
    }

    if ($fid === null) {
        if (in_array(THIS_SCRIPT, ["forumdisplay.php", "newthread.php"], true)) {
            $fid = $mybb->get_input("fid", MyBB::INPUT_INT);
        } elseif (in_array(THIS_SCRIPT, ["showthread.php", "newreply.php"], true)) {
            $tid = $mybb->get_input("tid", MyBB::INPUT_INT);
            $fid = (int) $db->fetch_field($db->simple_select("threads", "fid", "tid={$tid}"), "fid");
        }
    }

    if (empty($fid)) {
        return false;
    }

    $allowed = array_map("intval", explode(",", $mybb->settings["teamorga_forums"]));
    return in_array((int) $fid, $allowed, true);
}

/**
 * Check whether the given user (default: current user) belongs to an allowed group.
 */
function teamorga_is_group_allowed($uid = null)
{
    global $mybb;

    // MyBB groupselect: "-1" = all groups, "" = no group (only the team account below).
    $groups = trim((string)($mybb->settings["teamorga_groups"] ?? ""));
    if ($groups === "-1") {
        return true;
    }

    // Team account always has access
    if ($uid === null && teamorga_is_team_account()) {
        return true;
    }

    if ($uid === null) {
        $user = $mybb->user;
    } else {
        $user = get_user((int) $uid);
    }

    $allowed       = array_map("intval", explode(",", $mybb->settings["teamorga_groups"]));
    $user_main     = (int) $user["usergroup"];
    $user_extra    = array_filter(array_map("intval", explode(",", $user["additionalgroups"] ?? "")));

    foreach ($allowed as $gid) {
        if ($gid === $user_main || in_array($gid, $user_extra, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Check whether the current user may always edit suggestions (regardless of ownership).
 */
function teamorga_can_always_edit()
{
    global $mybb;

    // MyBB groupselect: "-1" = all groups, "" = no group (everyone edits only their own).
    $edit_groups = trim((string)($mybb->settings["teamorga_edit_groups"] ?? ""));
    if ($edit_groups === "-1") {
        return true;
    }
    if ($edit_groups === "") {
        return false;
    }

    $allowed    = array_map("intval", explode(",", $mybb->settings["teamorga_edit_groups"]));
    $user_main  = (int) $mybb->user["usergroup"];
    $user_extra = array_filter(array_map("intval", explode(",", $mybb->user["additionalgroups"] ?? "")));

    foreach ($allowed as $gid) {
        if ($gid === $user_main || in_array($gid, $user_extra, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Check whether the current user is the configured team account.
 */
function teamorga_is_team_account(): bool
{
    global $mybb;
    $team_uid = (int) $mybb->settings["teamorga_id"];
    return $team_uid > 0 && (int) $mybb->user["uid"] === $team_uid;
}

/**
 * Register the MyAlerts formatter for suggestion votes.
 */
function teamorga_myalerts_register()
{
    global $mybb, $lang;

    if (!function_exists('myalerts_is_activated') || !myalerts_is_activated()) {
        return;
    }
    if (!class_exists('MybbStuff_MyAlerts_AlertFormatterManager')
        || !class_exists('TeamorgaSuggestionVoteFormatter')) {
        return;
    }

    $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
    if ($formatterManager) {
        $formatterManager->registerFormatter(
            new TeamorgaSuggestionVoteFormatter($mybb, $lang, 'teamorga_suggestion_vote')
        );
        if (class_exists('TeamorgaTimeProposalFormatter')) {
            $formatterManager->registerFormatter(
                new TeamorgaTimeProposalFormatter($mybb, $lang, 'teamorga_time_proposal')
            );
        }
    }
}

/**
 * Return an enabled teamorga MyAlerts alert type, recreating the type row on the
 * fly if it is missing from the alert_types table. Returns the type or null.
 *
 * Root cause this guards against: teamorga_deactivate() deletes its alert_type
 * rows with a raw delete_query(). If the MyAlerts type datacache is not refreshed
 * afterwards, the table loses the rows while the cache still lists them; a later
 * reactivation then reads the stale cache via getByCode(), assumes the types
 * still exist and skips re-adding them – so the rows stay gone. getByCode() keeps
 * returning a type whose id is no longer in the table, the alert is written with
 * a dangling alert_type_id and never displays (the display JOIN on alert_types
 * fails). That is the "team member reacted but I got no alert" symptom.
 *
 * deactivate() now refreshes the cache, which prevents the desync at the source;
 * this helper is the belt-and-braces safety net, verifying against the table
 * (not just the cache) every time an alert is fired.
 */
function teamorga_get_alert_type(string $code)
{
    global $db;

    if (!function_exists('myalerts_is_activated') || !myalerts_is_activated()
        || !class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        return null;
    }

    $manager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
    if (!$manager) {
        return null;
    }

    // Authoritative check: does the row actually exist in the table?
    $existing = $db->fetch_array(
        $db->simple_select('alert_types', 'id,enabled', "code='" . $db->escape_string($code) . "'")
    );

    if (!$existing) {
        // Missing from the table – resync the (possibly stale) cache first so
        // add() will insert, then recreate the type.
        $manager->getAlertTypes(true);
        $t = new MybbStuff_MyAlerts_Entity_AlertType();
        $t->setCode($code);
        $t->setEnabled(true);
        $t->setCanBeUserDisabled(true);
        $t->setDefaultUserEnabled(true);
        $manager->add($t);
    } elseif ((int) $existing['enabled'] !== 1) {
        return null; // exists but disabled by the admin
    } elseif ($manager->getByCode($code) === null) {
        // Row exists but the cache is stale and lacks it – resync.
        $manager->getAlertTypes(true);
    }

    $alertType = $manager->getByCode($code);
    if ($alertType && $alertType->getEnabled()) {
        return $alertType;
    }

    return null;
}

/**
 * Check whether a user may toggle a suggestion's active state.
 */
function teamorga_can_toggle_suggestion(array $suggestion, int $uid): bool
{
    global $db;

    if ((int) $suggestion["owner_uid"] === $uid || teamorga_can_always_edit()) {
        return true;
    }

    $trow = $db->fetch_array($db->simple_select("teamorga_topic", "organizer_uid", "id=" . (int) $suggestion["tt_id"]));
    return $trow && (int) $trow["organizer_uid"] === $uid;
}

/**
 * Return the teamorga_topic row for the given thread ID, or false.
 */
function teamorga_get_topic(int $tid)
{
    global $db;
    return $db->fetch_array($db->simple_select("teamorga_topic", "*", "tid={$tid}"));
}

/**
 * Return the topic-level participation row for the given user + teamorga topic.
 */
function teamorga_get_user_topic_status(int $uid, int $tt_id)
{
    global $db;
    return $db->fetch_array($db->simple_select("teamorga_topic_participants", "*", "uid={$uid} AND tt_id={$tt_id}"));
}

/**
 * Return the suggestion-level participation row for the given user + suggestion.
 */
function teamorga_get_user_suggestion_status(int $uid, int $tt_id)
{
    global $db;
    $sug = teamorga_get_newest_suggestion($tt_id);
    if (!$sug || empty($sug["id"])) {
        return [];
    }
    $sug_id = (int) $sug["id"];
    return $db->fetch_array($db->simple_select("teamorga_suggestion_participants", "*", "tt_id={$tt_id} AND uid={$uid} AND sug_id={$sug_id}"));
}

/**
 * Return the most recent active suggestion linked to a post for the given topic.
 */
function teamorga_get_newest_suggestion(int $tt_id, bool $skip_first = false)
{
    global $db;
    $limit  = $skip_first ? "LIMIT 1 OFFSET 1" : "LIMIT 1";
    $result = $db->query("
        SELECT * FROM " . TABLE_PREFIX . "teamorga_suggestions
        WHERE tt_id = {$tt_id} AND active = 1 AND pid IS NOT NULL
        ORDER BY id DESC
        {$limit}
    ");
    return $db->fetch_array($result);
}

/**
 * Check whether all active, linked suggestions for a thread have been reviewed (no pending/time/unanswered votes).
 */
function teamorga_is_approved(int $tid): bool
{
    global $db;
    $topic = teamorga_get_topic($tid);
    if (!$topic) {
        return false;
    }

    $open = $db->query("
        SELECT 1
        FROM " . TABLE_PREFIX . "teamorga_topic tt
        JOIN " . TABLE_PREFIX . "teamorga_suggestions s ON s.tt_id = tt.id
        JOIN " . TABLE_PREFIX . "posts po ON po.pid = s.pid
        JOIN " . TABLE_PREFIX . "teamorga_suggestion_participants p ON p.sug_id = s.id
        WHERE tt.tid = {$tid}
          AND s.active = 1
          AND s.pid IS NOT NULL
          AND p.status IN ('pending','time','unanswered')
        LIMIT 1
    ");
    return $db->num_rows($open) === 0;
}

/**
 * Sanitize a priority value to one of the allowed options.
 */
function teamorga_sanitize_prio(string $prio): string
{
    $allowed = ["low", "normal", "medium", "high", "inactive"];
    return in_array($prio, $allowed, true) ? $prio : "normal";
}

/**
 * Add all configured group members as participants to a teamorga topic.
 * The organizer gets status 'approved'; all others get 'unanswered'.
 */
function teamorga_add_participants(int $ttid, int $organizer_uid): void
{
    global $db, $mybb;

    $setting = trim($mybb->settings["teamorga_groups"] ?? "");

    // Collect positive group IDs; -1 means "all groups", empty means "no group" (organizer only)
    $groups = array_values(array_filter(
        array_map("intval", explode(",", $setting)),
        function ($g) { return $g > 0; }
    ));

    $all_allowed = ($setting === "-1" || in_array(-1, array_map("intval", explode(",", $setting)), true));

    if ($all_allowed && empty($groups)) {
        // -1 = all users are team members – fetch everyone
        $query = $db->query("SELECT uid FROM " . TABLE_PREFIX . "users WHERE uid > 0");
    } elseif (!empty($groups)) {
        $group_list = implode(",", $groups);
        $find_sets  = array_map(function (int $gid) { return "FIND_IN_SET({$gid}, additionalgroups)"; }, $groups);
        $query = $db->query("
            SELECT uid FROM " . TABLE_PREFIX . "users
            WHERE usergroup IN ({$group_list})
            OR " . implode(" OR ", $find_sets)
        );
    } else {
        // No usable configuration – add only the organizer
        $db->insert_query("teamorga_topic_participants", [
            "uid"    => $organizer_uid,
            "tt_id"  => $ttid,
            "status" => "approved"
        ]);
        return;
    }

    $added = [];
    while ($user = $db->fetch_array($query)) {
        $uid         = (int) $user["uid"];
        $added[$uid] = true;
        $db->insert_query("teamorga_topic_participants", [
            "uid"    => $uid,
            "tt_id"  => $ttid,
            "status" => ($uid === $organizer_uid) ? "approved" : "unanswered"
        ]);
    }

    // Organizer might not be in the configured groups – always ensure approved entry
    if (!isset($added[$organizer_uid])) {
        $db->insert_query("teamorga_topic_participants", [
            "uid"    => $organizer_uid,
            "tt_id"  => $ttid,
            "status" => "approved"
        ]);
    }
}

/**
 * Build sorted anchor links to all active suggestions in a topic.
 */
function teamorga_build_suggestion_links(int $ttid, int $tid): array
{
    global $db, $lang;

    $entries = [];
    $q = $db->query("
        SELECT s.id, s.pid, s.suggestion
        FROM " . TABLE_PREFIX . "teamorga_suggestions s
        JOIN " . TABLE_PREFIX . "teamorga_topic tt ON tt.id = s.tt_id
        LEFT JOIN " . TABLE_PREFIX . "posts p ON p.pid = s.pid
        WHERE s.active = 1
          AND s.pid IS NOT NULL
          AND tt.id = {$ttid}
          AND tt.tid = {$tid}
          AND p.tid = {$tid}
    ");

    while ($srow = $db->fetch_array($q)) {
        $sug_id  = (int) $srow["id"];
        $pid     = (int) $srow["pid"];
        $preview = htmlspecialchars_uni(mb_substr(strip_tags($srow["suggestion"]), 0, 80));

        if ($pid > 0) {
            $post_num = (int) $db->fetch_field(
                $db->simple_select("posts", "COUNT(*) AS cnt", "tid={$tid} AND pid<={$pid}"),
                "cnt"
            );
            $label = $post_num > 0 ? $post_num : $sug_id;
            // Use a full post link so the browser jumps to the correct page,
            // not just an in-page anchor (which fails when the suggestion is
            // on a different thread page).
            $href  = get_post_link($pid, $tid) . "#pid{$pid}";
        } else {
            $label = $sug_id;
            $href  = "showthread.php?tid={$tid}&amp;sug_id={$sug_id}";
        }

        $entries[] = [
            "label" => $label,
            "html"  => '<a href="' . $href . '" class="teamorga-sug-link" title="' . $preview . '">' . $lang->sprintf($lang->teamorga_suggestion_link, $label) . '</a>'
        ];
    }

    usort($entries, fn($a, $b) => $a["label"] <=> $b["label"]);
    return array_column($entries, "html");
}

// ============================================================================
// USERCP: "MY TEAM TASKS" PAGE + PRIVATE REMINDERS
// ============================================================================

/**
 * HTML of the UserCP navigation row (team members only, empty string otherwise).
 */
function teamorga_usercp_nav_row(): string
{
    global $mybb;

    if ((int) $mybb->user["uid"] < 1 || !teamorga_is_group_allowed()) {
        return "";
    }

    $lang = teamorga_load_lang();

    return '<tr><td class="trow1 smalltext"><a href="usercp.php?action=teamorga" class="usercp_nav_item usercp_nav_teamorga" style="padding-left: 0;">'
        . '<svg class="usercp_nav_icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" aria-hidden="true" style="vertical-align: -3px; margin-right: 6px;"><path d="M2.5 4.5l1.5 1.5 2.5-3M2.5 10.5L4 12l2.5-3M8.5 5h5M8.5 11h5" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        . htmlspecialchars_uni($lang->teamorga_ucp_nav) . '</a></td></tr>';
}

/**
 * Standard MyBB: append the row to the "Miscellaneous" block. Must run before the core
 * usercp_menu_misc() renders that template (priority 30), hence priority 24.
 * Skipped when the h4w RPG menu is present: there the row goes through teamorga_usercp_menu_rpg().
 */
function teamorga_usercp_menu(): void
{
    global $templates;

    if (function_exists("usercp_menu_rpg")) {
        return;
    }

    $item = teamorga_usercp_nav_row();
    if ($item === "") {
        return;
    }

    // get() makes sure the raw template is in the cache before we modify it
    $templates->get("usercp_nav_misc");
    $raw = isset($templates->cache["usercp_nav_misc"]) ? (string) $templates->cache["usercp_nav_misc"] : "";

    $pos = strripos($raw, "</tbody>");
    if ($pos !== false) {
        $raw = substr($raw, 0, $pos) . $item . substr($raw, $pos);
    } else {
        $raw .= $item;
    }
    $templates->cache["usercp_nav_misc"] = $raw;
}

/**
 * h4w: the RPG menu (usercp_nav_rpg plugin) collects extra rows in $usercp_nav_rpg_custom.
 * The hook only fires there, so it is a no-op on a standard MyBB.
 */
function teamorga_usercp_menu_rpg(&$usercp_nav_rpg_custom): void
{
    $usercp_nav_rpg_custom .= teamorga_usercp_nav_row();
}

/**
 * Render a plugin template with the given local variables.
 */
function teamorga_render_tpl(string $name, array $vars = []): string
{
    global $templates, $lang, $mybb, $theme, $headerinclude, $header, $footer, $usercpnav;

    extract($vars, EXTR_SKIP);
    $out = "";
    eval("\$out = \"" . $templates->get($name) . "\";");
    return $out;
}

/**
 * Format a stored 'Y-m-d' date as d.m.Y (same display format as the rest of the plugin).
 */
function teamorga_ucp_format_date(string $date): string
{
    $dt = DateTime::createFromFormat("Y-m-d", $date);
    return $dt ? $dt->format("d.m.Y") : $date;
}

/**
 * Validate a 'Y-m-d' date string.
 */
function teamorga_ucp_valid_date(string $date): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
        return false;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/**
 * Collect the open Teamorga tasks of a user: topics with an unanswered participation and/or
 * suggestions without the user's vote, grouped per topic.
 */
function teamorga_ucp_collect(int $uid): array
{
    global $db;

    $groups    = [];
    $perm_seen = [];

    $topic_cols = "tt.id AS ttid, tt.tid, tt.deadline, tt.prio, tt.owner, t.subject, t.fid";

    $add_group = function (array $row) use (&$groups, &$perm_seen) {
        $ttid = (int) $row["ttid"];
        if (isset($groups[$ttid])) {
            return true;
        }
        $fid = (int) $row["fid"];
        if (!isset($perm_seen[$fid])) {
            // Only threads in forums where Teamorga is enabled (the thread view shows no panel elsewhere)
            $fp = forum_permissions($fid);
            $perm_seen[$fid] = teamorga_is_forum_enabled($fid) && !empty($fp["canview"]);
        }
        if (!$perm_seen[$fid]) {
            return false;
        }
        $groups[$ttid] = [
            "ttid"        => $ttid,
            "tid"         => (int) $row["tid"],
            "subject"     => (string) $row["subject"],
            "deadline"    => (string) $row["deadline"],
            "prio"        => (string) $row["prio"],
            "owner"       => (string) ($row["owner"] ?? ""),
            "topic_open"  => false,
            "suggestions" => [],
        ];
        return true;
    };

    // Topics where the participation is still unanswered
    $q = $db->query("
        SELECT {$topic_cols}
        FROM " . TABLE_PREFIX . "teamorga_topic_participants tp
        JOIN " . TABLE_PREFIX . "teamorga_topic tt ON tt.id = tp.tt_id
        JOIN " . TABLE_PREFIX . "threads t ON t.tid = tt.tid
        WHERE tp.uid = {$uid}
          AND tp.status = 'unanswered'
          AND tt.prio != 'inactive'
          AND t.visible = 1
    ");
    while ($row = $db->fetch_array($q)) {
        if ($add_group($row)) {
            $groups[(int) $row["ttid"]]["topic_open"] = true;
        }
    }

    // Suggestions still waiting for the user's vote, in the order of their posts
    $q = $db->query("
        SELECT {$topic_cols}, s.id AS sug_id, s.pid, s.suggestion
        FROM " . TABLE_PREFIX . "teamorga_suggestion_participants sp
        JOIN " . TABLE_PREFIX . "teamorga_suggestions s ON s.id = sp.sug_id
        JOIN " . TABLE_PREFIX . "teamorga_topic tt ON tt.id = s.tt_id
        JOIN " . TABLE_PREFIX . "threads t ON t.tid = tt.tid
        JOIN " . TABLE_PREFIX . "posts p ON p.pid = s.pid AND p.tid = tt.tid
        WHERE sp.uid = {$uid}
          AND sp.status IN ('pending','unanswered','time')
          AND s.active = 1
          AND s.pid IS NOT NULL
          AND tt.prio != 'inactive'
          AND t.visible = 1
        ORDER BY p.dateline ASC, s.pid ASC
    ");
    while ($row = $db->fetch_array($q)) {
        if (!$add_group($row)) {
            continue;
        }
        $tid = (int) $row["tid"];
        $pid = (int) $row["pid"];
        $num = (int) $db->fetch_field(
            $db->simple_select("posts", "COUNT(*) AS cnt", "tid={$tid} AND pid<={$pid}"),
            "cnt"
        );
        $groups[(int) $row["ttid"]]["suggestions"][] = [
            "label"   => $num > 0 ? $num : (int) $row["sug_id"],
            "href"    => get_post_link($pid, $tid) . "#pid{$pid}",
            "preview" => mb_substr(strip_tags((string) $row["suggestion"]), 0, 80),
        ];
    }

    return array_values($groups);
}

/**
 * Handle the POST actions of the page (add / toggle / delete a reminder). Always redirects.
 */
function teamorga_ucp_handle_post(int $uid, string $do): void
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->get_input("my_post_key"));
    $url = "usercp.php?action=teamorga";

    if ($do === "add_reminder") {
        $title = trim($mybb->get_input("reminder_title"));
        $note  = trim($mybb->get_input("reminder_note"));
        $date  = trim($mybb->get_input("reminder_date"));

        if ($title === "") {
            error($lang->teamorga_ucp_err_title);
        }
        if (!teamorga_ucp_valid_date($date)) {
            error($lang->teamorga_ucp_err_date);
        }

        $db->insert_query("teamorga_reminders", [
            "uid"         => $uid,
            "title"       => $db->escape_string(mb_substr($title, 0, 255)),
            "note"        => $db->escape_string(mb_substr($note, 0, 2000)),
            "remind_date" => $db->escape_string($date),
            "done"        => 0,
            "dateline"    => TIME_NOW,
        ]);
        redirect($url, $lang->teamorga_ucp_msg_added);
    }

    $rid = $mybb->get_input("reminder_id", MyBB::INPUT_INT);
    $row = $db->fetch_array(
        $db->simple_select("teamorga_reminders", "id, done", "id={$rid} AND uid={$uid}", ["limit" => 1])
    );
    if (!$row) {
        error($lang->teamorga_ucp_err_not_found);
    }

    if ($do === "toggle_reminder") {
        $db->update_query("teamorga_reminders", ["done" => $row["done"] ? 0 : 1], "id={$rid} AND uid={$uid}");
        redirect($url, $lang->teamorga_ucp_msg_updated);
    }

    if ($do === "delete_reminder") {
        $db->delete_query("teamorga_reminders", "id={$rid} AND uid={$uid}");
        redirect($url, $lang->teamorga_ucp_msg_deleted);
    }

    redirect($url);
}

/**
 * UserCP page: usercp.php?action=teamorga
 */
function teamorga_usercp(): void
{
    global $mybb, $db, $lang, $templates, $theme, $headerinclude, $header, $footer, $usercpnav;

    if ($mybb->get_input("action") !== "teamorga") {
        return;
    }

    $uid = (int) $mybb->user["uid"];
    if ($uid < 1 || !teamorga_is_group_allowed()) {
        error_no_permission();
    }

    teamorga_load_lang();

    $do = $mybb->get_input("teamorga_do");
    if ($mybb->request_method === "post" && $do !== "") {
        teamorga_ucp_handle_post($uid, $do);
    }

    add_breadcrumb($lang->nav_usercp, "usercp.php");
    add_breadcrumb($lang->teamorga_ucp_title, "usercp.php?action=teamorga");

    $today = my_date("Y-m-d", TIME_NOW);
    $far   = "9999-12-31";

    // Build one sortable list from topics and open reminders
    $items = [];
    foreach (teamorga_ucp_collect($uid) as $g) {
        $valid = teamorga_ucp_valid_date($g["deadline"]);
        $items[] = [
            "type" => 0,
            "date" => $valid ? $g["deadline"] : $far,
            "id"   => $g["ttid"],
            "data" => $g,
        ];
    }

    $done_rows = [];
    $q = $db->simple_select("teamorga_reminders", "*", "uid={$uid}", ["order_by" => "remind_date ASC, id", "order_dir" => "ASC"]);
    while ($r = $db->fetch_array($q)) {
        if ((int) $r["done"] === 1) {
            $done_rows[] = $r;
            continue;
        }
        $items[] = [
            "type" => 1,
            "date" => teamorga_ucp_valid_date($r["remind_date"]) ? $r["remind_date"] : $far,
            "id"   => (int) $r["id"],
            "data" => $r,
        ];
    }

    usort($items, function ($a, $b) {
        if ($a["date"] !== $b["date"]) {
            return strcmp($a["date"], $b["date"]);
        }
        if ($a["type"] !== $b["type"]) {
            return $a["type"] <=> $b["type"];
        }
        return $a["id"] <=> $b["id"];
    });

    $badge = function (string $date) use ($today, $lang): string {
        if ($date === "" || !teamorga_ucp_valid_date($date)) {
            return "";
        }
        if ($date < $today) {
            return '<span class="teamorga-ucp-badge teamorga-ucp-overdue">' . htmlspecialchars_uni($lang->teamorga_ucp_overdue) . '</span>';
        }
        if ($date === $today) {
            return '<span class="teamorga-ucp-badge teamorga-ucp-today">' . htmlspecialchars_uni($lang->teamorga_ucp_today) . '</span>';
        }
        return "";
    };

    $task_list = "";
    foreach ($items as $it) {
        $d = $it["data"];

        if ($it["type"] === 0) {
            $suggestions = "";
            foreach ($d["suggestions"] as $s) {
                $suggestions .= teamorga_render_tpl("teamorga_usercp_suggestion", [
                    "sug_href"    => htmlspecialchars_uni($s["href"]),
                    "sug_label"   => htmlspecialchars_uni($lang->sprintf($lang->teamorga_suggestion_link, $s["label"])),
                    "sug_preview" => htmlspecialchars_uni($s["preview"]),
                ]);
            }

            $prio     = in_array($d["prio"], ["low", "normal", "medium", "high"], true) ? $d["prio"] : "normal";
            $prio_key = "teamorga_prio_" . $prio;
            $has_date = teamorga_ucp_valid_date($d["deadline"]);

            $task_list .= teamorga_render_tpl("teamorga_usercp_task", [
                "task_href"        => htmlspecialchars_uni(get_thread_link($d["tid"])),
                "task_subject"     => htmlspecialchars_uni($d["subject"]),
                "task_prio_class"  => "teamorga-ucp-prio-" . $prio,
                "task_prio_label"  => htmlspecialchars_uni($lang->$prio_key),
                "task_deadline"    => $has_date ? teamorga_ucp_format_date($d["deadline"]) : htmlspecialchars_uni($lang->teamorga_ucp_no_deadline),
                "task_badge"       => $badge($d["deadline"]),
                "task_owner"       => htmlspecialchars_uni($d["owner"]),
                "task_topic_open"  => $d["topic_open"] ? htmlspecialchars_uni($lang->teamorga_ucp_participation_open) : "",
                "task_suggestions" => $suggestions,
            ]);
        } else {
            $task_list .= teamorga_ucp_render_reminder($d, $badge($d["remind_date"]));
        }
    }

    if ($task_list === "") {
        $task_list = teamorga_render_tpl("teamorga_usercp_empty");
    }

    $done_list = "";
    foreach ($done_rows as $r) {
        $done_list .= teamorga_ucp_render_reminder($r, "");
    }

    $reminder_form = teamorga_render_tpl("teamorga_usercp_reminder_form", [
        "form_date" => htmlspecialchars_uni($today),
    ]);

    $page = teamorga_render_tpl("teamorga_usercp", [
        "task_list"     => $task_list,
        "done_list"     => $done_list,
        "reminder_form" => $reminder_form,
    ]);

    output_page($page);
    exit;
}

/**
 * Render a single reminder row.
 */
function teamorga_ucp_render_reminder(array $r, string $badge): string
{
    $done = (int) $r["done"] === 1;
    global $lang;

    return teamorga_render_tpl("teamorga_usercp_reminder", [
        "rem_id"         => (int) $r["id"],
        "rem_title"      => htmlspecialchars_uni($r["title"]),
        "rem_note"       => nl2br(htmlspecialchars_uni((string) $r["note"])),
        "rem_date"       => teamorga_ucp_format_date((string) $r["remind_date"]),
        "rem_badge"      => $badge,
        "rem_class"      => $done ? "teamorga-ucp-done" : "",
        "rem_delete_confirm" => htmlspecialchars_uni(addslashes($lang->teamorga_ucp_reminder_delete_confirm)),
        "rem_toggle_lbl" =>htmlspecialchars_uni($done ? $lang->teamorga_ucp_reminder_reopen : $lang->teamorga_ucp_reminder_done),
    ]);
}

// ============================================================================
// TEMPLATE LOADER CLASS
// ============================================================================

/**
 * Loads .tpl template files from inc/plugins/teamorga/templates/ into the DB.
 */
class TeamorgaTemplateLoader
{
    private string $template_dir;
    private string $extension = ".tpl";

    public function __construct()
    {
        $this->template_dir = MYBB_ROOT . "inc/plugins/teamorga/templates/";
    }

    public function autoLoad($db): void
    {
        foreach ($this->get_template_files() as $filename) {
            $title   = str_replace($this->extension, "", $filename);
            $content = file_get_contents($this->template_dir . $filename);
            // Delete ALL existing entries for this title regardless of sid
            // (theme-specific copies with sid != -1 would otherwise shadow the global one)
            $db->delete_query("templates", "title='" . $db->escape_string($title) . "'");
            $db->insert_query("templates", [
                "title"    => $db->escape_string($title),
                "template" => $db->escape_string($content),
                "sid"      => -1,
                "version"  => "",
                "dateline" => TIME_NOW
            ]);
        }
    }

    public function autoUnload($db): void
    {
        foreach ($this->get_template_files() as $filename) {
            $title = $db->escape_string(str_replace($this->extension, "", $filename));
            $db->delete_query("templates", "title='{$title}'");
        }
    }

    private function get_template_files(): array
    {
        if (!is_dir($this->template_dir)) {
            return [];
        }
        $files = [];
        if ($handle = opendir($this->template_dir)) {
            while (($entry = readdir($handle)) !== false) {
                if ($entry !== "." && $entry !== ".." && substr($entry, -strlen($this->extension)) === $this->extension) {
                    $files[] = $entry;
                }
            }
            closedir($handle);
        }
        return $files;
    }
}
