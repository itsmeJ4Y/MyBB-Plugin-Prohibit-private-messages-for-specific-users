<?php
/*
Plugin "Restrict PM" 22.04.2019
2019 (c) itsmeJAY
Plugin by itsmeJAY - if you have questions or found bugs, please write me!
Version tested: 1.8.20 by itsmeJAY
*/

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB")) {
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("private_start", "restrictpm_private_work");
$plugins->add_hook('member_login_end','restrictpm_system_work');
$plugins->add_hook('admin_formcontainer_output_row', 'restrictpm_add_setting');
$plugins->add_hook('admin_user_users_edit_commit','restrictpm_user_update');

function restrictpm_info() {
    // Sprachdatei laden
    global $lang;
    $lang->load("restrictpm");

	return array(
		"name"			=> "$lang->rpm_title",
		"description"	=> "$lang->rpm_desc",
		"website"		=> "https://www.mybb.de/forum/user-10220.html",
		"author"		=> "itsmeJAY from MyBB.de",
		"authorsite"	=> "https://www.mybb.de/forum/user-10220.html",
		"version"		=> "1.0.0",
	);
}

function restrictpm_install() {
    global $db, $mybb, $lang;

    // Sprachdatei laden
    $lang->load("restrictpm");

    if(!$db->field_exists('pmforbidden', "users")) {
		$db->query("ALTER TABLE `".TABLE_PREFIX."users` ADD `pmforbidden` INT( 1 ) NOT NULL DEFAULT '0';");
		$db->query("ALTER TABLE `".TABLE_PREFIX."users` ADD `pmsystemforbidden` INT( 1 ) NOT NULL DEFAULT '0';");
    }

        $setting_group = array(
        'name' => 'restrict_pm',
        'title' => "$lang->rpm_title",
        'description' => "$lang->rpm_desc",
        'disporder' => 5,
        'isdefault' => 0
    );
    
    $gid = $db->insert_query("settinggroups", $setting_group);
    
    // Einstellungen
  
    $setting_array = array(
      'rpm_warn_text_q' => array(
          'title' => "$lang->rpm_warn_text_q_title",
          'description' => "$lang->rpm_warn_text_q_desc",
          'optionscode' => 'yesno',
          'value' => 1, // Default
          'disporder' => 1
      ),
       'rpm_warn_text' => array(
          'title' => "$lang->rpm_warn_text_title",
          'description' => "$lang->rpm_warn_text_desc",
          'optionscode' => 'textarea',
          'value' => "$lang->rpm_warn_text_example", // Default
          'disporder' => 2
      ),
  );  
  
  // Einstellungen in Datenbank speichern
  foreach($setting_array as $name => $setting)
  {
      $setting['name'] = $name;
      $setting['gid'] = $gid;
  
      $db->insert_query('settings', $setting);
  }

  // Rebuild Settings! :-)
  rebuild_settings();

}

function restrictpm_uninstall() {
    global $db;
	$db->query("ALTER TABLE `".TABLE_PREFIX."users` DROP `pmforbidden`;");
	$db->query("ALTER TABLE `".TABLE_PREFIX."users` DROP `pmsystemforbidden`;");
    $db->delete_query('settings', "name IN ('rpm_warn_text_q','rpm_warn_text')");
    $db->delete_query('settinggroups', "name = 'restrict_pm'");
    
    // Rebuild Settings! :-)
    rebuild_settings();
}

function restrictpm_activate() {
    global $db, $mybb, $lang;

    require MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("private", "#" . preg_quote('{$limitwarning}') . "#i",'{$rpmwarning}{$limitwarning}');

}

function restrictpm_deactivate() {
    require MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("private", "#" . preg_quote('{$rpmwarning}') . "#i",'');
}

function restrictpm_is_installed() {
    global $db;
    if($db->field_exists('pmforbidden', "users")) {
        return true;
    } else {
        return false;
    }
}


//Funktionen

function restrictpm_add_setting(&$pluginargs)
{
   global $mybb, $lang, $form, $user;
   $lang->load("restrictpm");

   if ($pluginargs['title'] == $lang->messaging_and_notification && $lang->messaging_and_notification) {
       $user_setting = array($form->generate_check_box(
               'restrict_pm',
               1,
               "$lang->rpm_title_setting",
               array('checked' => $user['pmforbidden'], 'id' => 'pmforbidden')
               )
       );
       $pluginargs['content'] .= "</td></tr><tr class=\"first\"><td class=\"first\"><div class=\"forum_settings_bit\">"
           .implode("</div><div class=\"forum_settings_bit\">", $user_setting)."<div class=\"description\">$lang->rpm_setting_desc</div></div></td></tr>";
   }

      if ($pluginargs['title'] == $lang->messaging_and_notification && $lang->messaging_and_notification) {
       $user_setting = array($form->generate_check_box(
               'restrict_pm_system',
               1,
               "$lang->rpm_title_setting_system",
               array('checked' => $user['pmsystemforbidden'], 'id' => 'pmsystemforbidden')
               )
       );
       $pluginargs['content'] .= "</td></tr><tr class=\"first\"><td class=\"first\"><div class=\"forum_settings_bit\">"
           .implode("</div><div class=\"forum_settings_bit\">", $user_setting)."<div class=\"description\">$lang->rpm_setting_desc_system</div></div></td></tr>";
   }
}

function restrictpm_user_update()
{
   global $db, $mybb, $cache, $user;

   $uid = (int)$user['uid'];
   $update_array = array(
       "pmforbidden" =>  $db->escape_string($mybb->input['restrict_pm']),
   );


    $db->update_query("users", $update_array, "uid='{$uid}'");

   $update_array_system = array(
       "pmsystemforbidden" =>  $db->escape_string($mybb->input['restrict_pm_system']),
   );


    $db->update_query("users", $update_array_system, "uid='{$uid}'");

} 

function restrictpm_private_work() {
	global $db, $mybb, $cache, $rpmwarning;

	if ($mybb->user['pmforbidden'] == '1') {
	$mybb->usergroup['cansendpms'] = 0;
	}

	if ($mybb->user['pmsystemforbidden'] == '1') {
		error_no_permission();
	}

	if ($mybb->settings['rpm_warn_text_q'] == '1' && $mybb->user['pmforbidden'] == '1') {
		$rpmwarning = "<div class=\"red_alert\" id=\"pm_notice\"><div>".$mybb->settings['rpm_warn_text']."</div></div>";
	}
}

?>