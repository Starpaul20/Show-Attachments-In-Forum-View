<?php
/**
 * Show Attachments in Forum View
 * Copyright 2012 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
if(defined('THIS_SCRIPT'))
{
	if(THIS_SCRIPT == 'misc.php')
	{
		global $templatelist;
		if(isset($templatelist))
		{
			$templatelist .= ',';
		}
		$templatelist .= 'misc_showattachments,misc_showattachments_attachement,misc_showattachments_no_attachments';
	}
}

// Tell MyBB when to run the hooks
$plugins->add_hook("misc_start", "showattachmentsforum_run");
$plugins->add_hook("fetch_wol_activity_end", "showattachmentsforum_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "showattachmentsforum_online_location");

// The information that shows up on the plugin manager
function showattachmentsforum_info()
{
	global $lang;
	$lang->load("showattachmentsforum", true);

	return array(
		"name"				=> $lang->showattachmentsforum_info_name,
		"description"		=> $lang->showattachmentsforum_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.1",
		"codename"			=> "showattachmentsforum",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is activated.
function showattachmentsforum_activate()
{
	global $db;

	// Insert templates
	$insert_array = array(
		'title'		=> 'misc_showattachments',
		'template'	=> $db->escape_string('<div class="modal">
<div style="overflow-y: auto; max-height: 400px;">
<table width="100%" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" border="0" class="tborder">
<tr>
<td class="thead" colspan="3"><strong>{$lang->attachments}</strong></td>
</tr>
<tr>
<td class="tcat" align="left"><span class="smalltext"><strong>{$lang->attachment}</strong></span></td>
<td class="tcat" align="center"><span class="smalltext"><strong>{$lang->file_size}</strong></span></td>
<td class="tcat" align="center"><span class="smalltext"><strong>{$lang->downloads}</strong></span></td>
</tr>
{$attachment_bit}
<tr>
<td class="tfoot" colspan="3">
<div align="center"><span class="smalltext"><strong><a href="{$thread[\'threadlink\']}" onclick="opener.location=(\'{$thread[\'threadlink\']}\'); self.close();">{$lang->close_window_open_thread}</a></strong></span></div>
</td>
</tr>
</table>
</div>
</div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'misc_showattachments_attachement',
		'template'	=> $db->escape_string('<tr>
<td class="{$alt_bg}" align="left">{$attachment[\'icon\']}  <a href="attachment.php?aid={$attachment[\'aid\']}" target="_blank">{$attachment[\'filename\']}</a></td>
<td class="{$alt_bg}" align="center">{$attachment[\'filesize\']}</td>
<td class="{$alt_bg}" align="center">{$attachment[\'downloads\']}</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'misc_showattachments_no_attachments',
		'template'	=> $db->escape_string('<tr>
<td class="trow1" colspan="3" align="center">{$lang->no_attachments}</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	// Update templates
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("forumdisplay_thread_attachment_count", "#".preg_quote('<img src="{$theme[\'imgdir\']}/paperclip.png" alt="" title="{$attachment_count}" />')."#i", '<a href="javascript:void(0)" onclick="MyBB.popupWindow(\'/misc.php?action=showattachments&amp;tid={$thread[\'tid\']}\'); return false;"><img src="{$theme[\'imgdir\']}/paperclip.png" alt="" title="{$attachment_count}" /></a>');
}

// This function runs when the plugin is deactivated.
function showattachmentsforum_deactivate()
{
	global $db;
	$db->delete_query("templates", "title IN('misc_showattachments','misc_showattachments_attachement','misc_showattachments_no_attachments')");

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("forumdisplay_thread_attachment_count", "#".preg_quote('<a href="javascript:void(0)" onclick="MyBB.popupWindow(\'/misc.php?action=showattachments&amp;tid={$thread[\'tid\']}\'); return false;"><img src="{$theme[\'imgdir\']}/paperclip.png" alt="" title="{$attachment_count}" /></a>')."#i", '<img src="{$theme[\'imgdir\']}/paperclip.png" alt="" title="{$attachment_count}" />');
	find_replace_templatesets("forumdisplay_thread_attachment_count", "#".preg_quote('<a href="javascript:;" onclick="MyBB.popupWindow(\'/misc.php?action=showattachments&amp;tid={$thread[\'tid\']}\'); return false;"><img src="{$theme[\'imgdir\']}/paperclip.png" alt="" title="{$attachment_count}" /></a>')."#i", '<img src="{$theme[\'imgdir\']}/paperclip.png" alt="" title="{$attachment_count}" />'); // Included just in case
}

// Show attachments pop-up
function showattachmentsforum_run()
{
	global $db, $mybb, $lang, $templates, $theme;
	$lang->load("showattachmentsforum");

	if($mybb->input['action'] == "showattachments")
	{
		$tid = $mybb->get_input('tid', MyBB::INPUT_INT);
		$thread = get_thread($tid);

		// Is the currently logged in user a moderator of this forum?
		if(is_moderator($thread['fid'], "canviewunapprove"))
		{
			$ismod = true;
			$visible = "AND (p.visible='0' OR p.visible='1')";
		}
		else
		{
			$ismod = false;
			$visible = "AND p.visible='1'";
		}

		if(!$thread['tid'] || ($thread['visible'] == 0 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
		{
			error($lang->error_invalidthread);
		}

		// Does the thread belong to a valid forum?
		$forum = get_forum($thread['fid']);
		if(!$forum || $forum['type'] != "f")
		{
			error($lang->error_invalidforum);
		}

		$forumpermissions = forum_permissions($thread['fid']);

		// Does the user have permission to view this thread?
		if($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
		{
			error_no_permission();
		}

		if($forumpermissions['canonlyviewownthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
		{
			error_no_permission();
		}

		// Check if this forum is password protected and we have a valid password
		check_forum_password($forum['fid']);

		// Fetch the attachements for this thread
		$attachment_bit = '';
		$query = $db->query("
			SELECT a.*
			FROM ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
			WHERE p.tid='{$thread['tid']}' AND a.visible='1' {$visible}
			ORDER BY a.dateuploaded asc
		");
		while($attachment = $db->fetch_array($query))
		{
			$alt_bg = alt_trow();

			$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
			$attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
			$attachment['filesize'] = get_friendly_size($attachment['filesize']);
			$attachment['downloads'] = my_number_format($attachment['downloads']);

			eval("\$attachment_bit .= \"".$templates->get("misc_showattachments_attachement")."\";");
		}

		if(!$attachment_bit)
		{
			eval("\$attachment_bit = \"".$templates->get("misc_showattachments_no_attachments")."\";");
		}

		$thread['threadlink'] = get_thread_link($thread['tid']);

		eval("\$showattachments = \"".$templates->get("misc_showattachments", 1, 0)."\";");
		echo $showattachments;
		exit;
	}
}

// Online activity
function showattachmentsforum_online_activity($user_activity)
{
	global $user, $tid_list, $parameters;
	if(my_strpos($user['location'], "misc.php?action=showattachments") !== false)
	{
		if(is_numeric($parameters['tid']))
		{
			$tid_list[] = $parameters['tid'];
		}

		$user_activity['activity'] = "misc_showattachments";
		$user_activity['tid'] = $parameters['tid'];
	}

	return $user_activity;
}

function showattachmentsforum_online_location($plugin_array)
{
	global $lang, $parameters, $threads;
	$lang->load("showattachmentsforum");

	if($plugin_array['user_activity']['activity'] == "misc_showattachments")
	{
		if($threads[$parameters['tid']])
		{
			$plugin_array['location_name'] = $lang->sprintf($lang->viewing_attachments2, get_thread_link($plugin_array['user_activity']['tid']), $threads[$parameters['tid']]);
		}
		else
		{
			$plugin_array['location_name'] = $lang->viewing_attachments;
		}
	}

	return $plugin_array;
}
