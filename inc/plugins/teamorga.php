<?php
/**
 * Teamorga – Team organisation plugin for MyBB 1.8
 *
 * @author   pand0rica
 * @version  1.2.0
 */

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// Only register hooks when the plugin is enabled (setting may not exist before install)
if (!isset($mybb->settings["teamorga_enabled"]) || $mybb->settings["teamorga_enabled"]) {
    $plugins->add_hook("index_start", "teamorga_index");
    $plugins->add_hook("datahandler_post_insert_post_end", "teamorga_on_post_insert");
    $plugins->add_hook("postbit", "teamorga_postbit");
    $plugins->add_hook("global_start", "teamorga_myalerts_register");

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

function teamorga_info()
{
    return [
        "name"        => "Teamorga",
        "description" => "Vereinfacht Absprache und Organisation im Team",
        "website"     => "",
        "author"      => "pand0rica",
        "authorsite"  => "",
        "version"     => "1.2.0",
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
        "description" => "Teamorganisation",
        "disporder"   => 50,
        "isdefault"   => 0
    ]);

    $settings = [
        [
            "name"        => "teamorga_enabled",
            "title"       => "Aktiviert?",
            "description" => "Das Plugin kann hier aktiviert oder deaktiviert werden.",
            "optionscode" => "onoff",
            "value"       => "1"
        ],
        [
            "name"        => "teamorga_forums",
            "title"       => "Foren",
            "description" => "In welchen Foren ist das Plugin verfügbar? Wähle -1 für alle.",
            "optionscode" => "forumselect",
            "value"       => ""
        ],
        [
            "name"        => "teamorga_groups",
            "title"       => "Gruppen",
            "description" => "Für welche Gruppen ist das Plugin in den ausgewählten Foren aktiviert? Wähle -1 für alle.",
            "optionscode" => "groupselect",
            "value"       => ""
        ],
        [
            "name"        => "teamorga_edit_groups",
            "title"       => "Editier-Gruppen",
            "description" => "Welche Gruppen dürfen Vorschläge immer bearbeiten, auch wenn sie nicht der Ersteller sind?",
            "optionscode" => "groupselect",
            "value"       => ""
        ],
        [
            "name"        => "teamorga_deadline_default",
            "title"       => "Standard-Deadline (Tage)",
            "description" => "Wie viele Tage nach heute soll die Deadline beim Erstellen eines Teamorga-Themas standardmäßig gesetzt werden?",
            "optionscode" => "numeric",
            "value"       => "14"
        ],
        [
            "name"        => "teamorga_memo_default",
            "title"       => "Erinnerungs-Vorlauf (Tage)",
            "description" => "Wie viele Tage vor der Deadline soll auf dem Index eine Erinnerung erscheinen?",
            "optionscode" => "numeric",
            "value"       => "3"
        ],
        [
            "name"        => "teamorga_id",
            "title"       => "Teamaccount-ID",
            "description" => "UID eines gemeinsam genutzten Teamaccounts. Dieser kann Themen erstellen, wird aber nicht als Abstimmender gewertet.",
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
    global $mybb, $templates, $teamorga, $teamorga_newreply_suggestion;

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
    static $teamorga_lang_loaded = false;

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

    if (!$teamorga_lang_loaded) {
        $lang->load('teamorga');
        $teamorga_lang_loaded = true;
    }

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
                    ? "&#9203; Deadline endet bald &ndash; Bitte reagieren!"
                    : "&#9755; Bitte Teilnahme best&auml;tigen")
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
                    ? "&#9654; Vorschlag wartet auf deine Stimme"
                    : "&#9654; {$pending_count} Vorschl&auml;ge warten auf deine Stimme";
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
            redirect("showthread.php?tid={$tid}", "Dieses Thema ist bereits in Teamorga.");
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
            redirect("showthread.php?tid={$tid}", "Dieses Thema ist bereits in Teamorga.");
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
        redirect("showthread.php?tid={$tid}", "Thema wurde zu Teamorga hinzugefügt.");
    }

    // --- Action: show edit form for topic meta ---
    if ($action === "edit_topic") {
        $topic = teamorga_get_topic($tid);
        if (!$topic) {
            error("Teamorga-Eintrag nicht gefunden.");
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
            error("Ungültiger Teamorga-Eintrag.");
        }

        // Verify ttid belongs to current thread and check permissions
        $ttid_topic = $db->fetch_array($db->simple_select("teamorga_topic", "id,organizer_uid", "id={$ttid} AND tid={$tid}"));
        if (!$ttid_topic) {
            error("Ungültiger Teamorga-Eintrag.");
        }
        if (!teamorga_can_always_edit() && (int) $ttid_topic["organizer_uid"] !== $uid) {
            error_no_permission();
        }

        $db->update_query("teamorga_topic", [
            "deadline" => $deadline,
            "prio"     => $prio
        ], "id={$ttid}");

        redirect("showthread.php?tid={$tid}", "Teamorga-Einstellungen aktualisiert.");
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
            error("Ungültiger Teamorga-Eintrag.");
        }

        $allowed      = ["approved", "abstain", "time", "pending", "unanswered", "disapproved"];
        $new_status   = $mybb->get_input("teamorga_status");

        if (!in_array($new_status, $allowed, true)) {
            error("Ungültiger Status.");
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
                $atm   = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
                $atype = $atm ? $atm->getByCode('teamorga_time_proposal') : null;
                if ($atype && $atype->getEnabled()) {
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

        redirect("showthread.php?tid={$tid}", "Dein Status wurde aktualisiert.");
    }

    // --- POST: update ownership ---
    if ($mybb->request_method === "post" && $action === "update_ownership") {
        verify_post_check($mybb->get_input("my_post_key"));

        $ttid_post    = $mybb->get_input("ttid", MyBB::INPUT_INT);
        $action_type  = $mybb->get_input("teamorga_ownership");

        // Verify ttid belongs to current thread
        if (!$db->fetch_field($db->simple_select("teamorga_topic", "id", "id={$ttid_post} AND tid={$tid}"), "id")) {
            error("Ungültiger Teamorga-Eintrag.");
        }

        if ($action_type === "ownership_add") {
            $db->update_query("teamorga_topic", ["owner" => $db->escape_string($mybb->user["username"])], "id={$ttid_post}");
        } elseif ($action_type === "ownership_remove") {
            $db->update_query("teamorga_topic", ["owner" => null], "id={$ttid_post}", 1, true);
        } else {
            error("Ungültige Ownership-Aktion.");
        }

        redirect("showthread.php?tid={$tid}", "Ownership wurde aktualisiert.");
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
            error("Ungültige Parameter.");
        }

        // Verify ttid belongs to current thread to prevent cross-thread manipulation
        if (!$db->fetch_field($db->simple_select("teamorga_topic", "id", "id={$ttid_post} AND tid={$tid}"), "id")) {
            error("Ungültiger Teamorga-Eintrag.");
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

        redirect("showthread.php?tid={$tid}", "Teilnehmer wurde entfernt.");
    }

    // --- POST: owner decides on a time proposal ---
    if ($mybb->request_method === "post" && $action === "decide_time_proposal") {
        verify_post_check($mybb->get_input("my_post_key"));

        $ttid_post   = $mybb->get_input("ttid", MyBB::INPUT_INT);
        $proposal_uid = $mybb->get_input("proposal_uid", MyBB::INPUT_INT);
        $decision    = $mybb->get_input("proposal_decision");

        if ($ttid_post <= 0 || $proposal_uid <= 0 || !in_array($decision, ["accept", "decline"], true)) {
            error("Ungültige Parameter.");
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
            redirect("showthread.php?tid={$tid}", "Deadline aktualisiert, Teilnehmer auf Mitmachen gesetzt.");
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
            redirect("showthread.php?tid={$tid}", "Zeitvorschlag abgelehnt, Teilnehmer auf Enthalten gesetzt.");
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

    $teamorga_list_mitmachen  = $list_mitmachen  ? implode(", ", $list_mitmachen)  : "<em>keine</em>";
    $teamorga_list_enthalten  = $list_enthalten  ? implode(", ", $list_enthalten)  : "<em>keine</em>";
    $teamorga_list_mehrzeit   = $list_mehrzeit   ? implode(", ", $list_mehrzeit)   : "<em>keine</em>";
    $teamorga_list_unanswered = $list_unanswered ? implode(", ", $list_unanswered) : "<em>keine</em>";

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
        $teamorga_owner = "<em>keine</em>";
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
    global $db, $mybb, $templates, $teamorga_newreply_suggestion;

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

    $tt_id         = (int) $topic["id"];
    $suggestion_id = $db->insert_query("teamorga_suggestions", [
        "tt_id"      => $tt_id,
        "suggestion" => $db->escape_string($new_suggestion),
        "owner_uid"  => $poster_uid,
        "pid"        => $pid,
        "dateline"   => TIME_NOW,
        "active"     => 1
    ]);

    // Add all non-abstaining topic participants; poster votes "approved" automatically
    $participants_q = $db->query("SELECT uid FROM " . TABLE_PREFIX . "teamorga_topic_participants WHERE tt_id={$tt_id} AND status!='abstain'");
    while ($prow = $db->fetch_array($participants_q)) {
        $participant_uid = (int) $prow["uid"];
        $db->insert_query("teamorga_suggestion_participants", [
            "uid"    => $participant_uid,
            "tt_id"  => $tt_id,
            "sug_id" => (int) $suggestion_id,
            "status" => ($participant_uid === $poster_uid) ? "approved" : "pending"
        ]);
    }
}

/**
 * postbit – Render voting controls on posts that have an active suggestion.
 */
function teamorga_postbit(&$post)
{
    global $mybb, $db, $templates;

    $post["teamorga"] = "";

    $suggestion = $db->fetch_array(
        $db->simple_select("teamorga_suggestions", "*", "pid=" . (int) $post["pid"])
    );
    if (!$suggestion) {
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
            error("Ungültiger Vorschlag.");
        }

        $sug_row = $db->fetch_array($db->simple_select("teamorga_suggestions", "id,owner_uid", "id={$sug_id}"));
        if (!$sug_row || ((int) $sug_row["owner_uid"] !== $uid && !teamorga_can_always_edit())) {
            error("Keine Berechtigung zum Bearbeiten.");
        }

        $db->update_query("teamorga_suggestions", [
            "suggestion" => $db->escape_string($mybb->get_input("suggestion"))
        ], "id={$sug_id}");

        redirect("showthread.php?tid={$post['tid']}&pid={$post['pid']}#pid{$post['pid']}", "Vorschlag aktualisiert.");
    }

    // --- POST: toggle suggestion active/inactive ---
    if ($mybb->request_method === "post" && $action === "toggle_suggestion_active") {
        verify_post_check($mybb->get_input("my_post_key"));

        $sug_id  = $mybb->get_input("sug_id", MyBB::INPUT_INT);
        if ($sug_id <= 0) {
            error("Ungültiger Vorschlag.");
        }
        $srow = $db->fetch_array($db->simple_select("teamorga_suggestions", "id,owner_uid,active,tt_id,pid", "id={$sug_id}"));
        if (!$srow) {
            error("Vorschlag nicht gefunden.");
        }
        if (!teamorga_can_toggle_suggestion($srow, $uid)) {
            error("Keine Berechtigung.");
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
        redirect("showthread.php?tid={$post['tid']}&pid={$sug_pid}#pid{$sug_pid}", "Vorschlag aktualisiert.");
    }

    // --- POST: review or withdraw review of suggestion ---
    $new_status = null;
    if ($mybb->request_method === "post" && $action === "review_suggestion") {
        verify_post_check($mybb->get_input("my_post_key"));

        $allowed_reviews = ["approved", "disapproved"];
        $decision        = $mybb->get_input("review_decision");
        if (!in_array($decision, $allowed_reviews, true)) {
            error("Ungültige Bewertungsauswahl.");
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
            error("Du bist nicht als Teilnehmer für diesen Vorschlag registriert.");
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
            $sug_row   = $db->fetch_array(
                $db->simple_select("teamorga_suggestions", "owner_uid,pid,tt_id", "id={$sug_id}")
            );
            $owner_uid = (int) ($sug_row["owner_uid"] ?? 0);

            // Don't alert yourself
            if ($owner_uid > 0 && $owner_uid !== $uid) {
                $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
                $alertType        = $alertTypeManager ? $alertTypeManager->getByCode('teamorga_suggestion_vote') : null;

                if ($alertType && $alertType->getEnabled()) {
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

        redirect("showthread.php?tid={$post['tid']}&pid={$post['pid']}#pid{$post['pid']}", "Bewertung gespeichert.");
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
        $teamorga_edit_button = '<a href="showthread.php?tid=' . $post["tid"] . '&amp;sug_id=' . $suggestion["id"] . '&amp;teamorga_action=edit_suggestion' . $page_param . '#pid' . $post["pid"] . '" class="teamorga-edit-link" title="Bearbeiten">&#9998;</a>';
    }

    // Toggle active/inactive button
    $teamorga_toggle_suggestion_button = "";
    if (teamorga_can_toggle_suggestion($suggestion, $uid)) {
        $is_active = !empty($suggestion["active"]);
        if ($is_active) {
            $title = "Vorschlag deaktivieren";
            eval("\$toggle_btn_content = \"" . $templates->get("teamorga_suggestion_toggle_inactive") . "\";");
        } else {
            $title = "Vorschlag aktivieren";
            eval("\$toggle_btn_content = \"" . $templates->get("teamorga_suggestion_toggle_active") . "\";");
        }
        eval("\$teamorga_toggle_suggestion_button = \"" . $templates->get("teamorga_suggestion_toggle") . "\";");
    }

    // Review controls based on user's current vote
    $teamorga_review_controls = "";
    if ($action === "edit_suggestion" && ((int) $suggestion["owner_uid"] === $uid || teamorga_can_always_edit())) {
        // Preserve the current thread page for the cancel link.
        $edit_page      = $mybb->get_input("page", MyBB::INPUT_INT);
        $teamorga_cancel_page_param = $edit_page > 1 ? "&amp;page=" . $edit_page : "";
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

    $teamorga_list_approved    = $list_approved    ? implode(", ", $list_approved)    : "<em>keine</em>";
    $teamorga_list_disapproved = $list_disapproved ? implode(", ", $list_disapproved) : "<em>keine</em>";
    $teamorga_list_pending     = $list_pending     ? implode(", ", $list_pending)     : "<em>keine</em>";

    eval("\$teamorga_suggestion_participant_list = \"" . $templates->get("teamorga_suggestion_participant_list") . "\";");

    if (!empty($suggestion["active"])) {
        eval("\$post[\"teamorga\"] = \"" . $templates->get("teamorga_suggestion_box") . "\";");
    } else {
        eval("\$post[\"teamorga\"] = \"" . $templates->get("teamorga_suggestion_box_inactive") . "\";");
    }
}

/**
 * index_start – Show alerts on the forum index for pending teamorga items.
 */
function teamorga_index()
{
    global $db, $mybb, $templates, $teamorga_index_alerts;

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

    if (empty($mybb->settings["teamorga_forums"]) || $mybb->settings["teamorga_forums"] == -1) {
        return true;
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

    if (empty($mybb->settings["teamorga_groups"]) || $mybb->settings["teamorga_groups"] == -1) {
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

    if (empty($mybb->settings["teamorga_edit_groups"]) || $mybb->settings["teamorga_edit_groups"] == -1) {
        return true;
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

    // Collect positive group IDs; -1 or empty means "all groups allowed"
    $groups = array_values(array_filter(
        array_map("intval", explode(",", $setting)),
        function ($g) { return $g > 0; }
    ));

    $all_allowed = ($setting === "" || $setting === "-1" || in_array(-1, array_map("intval", explode(",", $setting)), true));

    if ($all_allowed && empty($groups)) {
        // -1 / empty = all users are team members – fetch everyone
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
    global $db;

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
            "html"  => '<a href="' . $href . '" class="teamorga-sug-link" title="' . $preview . '">Vorschlag ' . $label . '</a>'
        ];
    }

    usort($entries, fn($a, $b) => $a["label"] <=> $b["label"]);
    return array_column($entries, "html");
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
