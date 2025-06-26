<?php
/* Copyright (C) 2005-2017  Laurent Destailleur         <eldy@users.sourceforge.net>
 * Copyright (C) 2010-2015  Regis Houssin               <regis.houssin@inodbox.com>
 * Copyright (C) 2013	    Florian Henry               <florian.henry@open-concept.pro.com>
 * Copyright (C) 2018       Ferran Marcet               <fmarcet@2byte.es>
 * Copyright (C) 2024       Frédéric France             <frederic.france@free.fr>
 * Copyright (C) 2024-2025	MDW							<mdeweerd@users.noreply.github.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/user/param_ihm.php
 *       \brief      Page to show user setup for display
 */

// Load Dolibarr environment
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by page
$langs->loadLangs(array('admin', 'users'));

// Security check
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');

if (!isset($id) || empty($id)) {
	accessforbidden();
}

// Retrieve needed GETPOSTS for this file
$toselect = GETPOST('toselect', 'array');

// $user is current user, $id is id of edited user
$canreaduser = ($user->admin || $user->hasRight("user", "user", "read"));
$caneditfield = ((($user->id == $id) && $user->hasRight("user", "self", "write"))
	|| (($user->id != $id) && $user->hasRight("user", "user", "write")));

// Security check
$socid = 0;
if ($user->socid > 0) {
	$socid = $user->socid;
}
$feature2 = (($socid && $user->hasRight("user", "self", "write")) ? '' : 'user');

$result = restrictedArea($user, 'user', $id, 'user&user', $feature2);
if ($user->id != $id && !$canreaduser) {
	accessforbidden();
}

$object = new User($db);
$object->fetch($id, '', '', 1);
$object->loadRights();

$form = new Form($db);
$formadmin = new FormAdmin($db);

/*
 * Actions
 */

$parameters = array('id' => $socid);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	if ($action == 'update' && ($caneditfield || !empty($user->admin))) {
		header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id);
		exit;
	}
}


/*
 * View
 */

$person_name = !empty($object->firstname) ? $object->lastname.", ".$object->firstname : $object->lastname;
$title = $person_name." - ".$langs->trans('Card');
$help_url = '';

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-user page-card_param_ihm');

$arrayofselected = is_array($toselect) ? $toselect : array();

$head = user_prepare_head($object);

$title = $langs->trans("User");

print dol_get_fiche_head($head, 'apitoken', $title, -1, 'user');

$linkback = '<a href="'.DOL_URL_ROOT.'/user/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

$morehtmlref = '<a href="'.DOL_URL_ROOT.'/user/vcard.php?id='.$object->id.'&output=file&file='.urlencode(dol_sanitizeFileName($object->getFullName($langs).'.vcf')).'" class="refid" rel="noopener">';
$morehtmlref .= img_picto($langs->trans("Download").' '.$langs->trans("VCard"), 'vcard.png', 'class="valignmiddle marginleftonly paddingrightonly"');
$morehtmlref .= '</a>';

$urltovirtualcard = '/user/virtualcard.php?id='.((int) $object->id);
$morehtmlref .= dolButtonToOpenUrlInDialogPopup('publicvirtualcard', $langs->transnoentitiesnoconv("PublicVirtualCardUrl").' - '.$object->getFullName($langs), img_picto($langs->trans("PublicVirtualCardUrl"), 'card', 'class="valignmiddle marginleftonly paddingrightonly"'), $urltovirtualcard, '', 'nohover');

dol_banner_tab($object, 'id', $linkback, $user->hasRight("user", "user", "read") || $user->admin, 'rowid', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';

// Login
print '<tr><td class="titlefield">'.$langs->trans("Login").'</td>';
if (!empty($object->ldap_sid) && $object->status == 0) {
	print '<td class="error">';
	print $langs->trans("LoginAccountDisableInDolibarr");
	print '</td>';
} else {
	print '<td>';
	$addadmin = '';
	if (property_exists($object, 'admin')) {
		if (isModEnabled('multicompany') && !empty($object->admin) && empty($object->entity)) {
			$addadmin .= img_picto($langs->trans("SuperAdministratorDesc"), "redstar", 'class="paddingleft valignmiddle"');
		} elseif (!empty($object->admin)) {
			$addadmin .= img_picto($langs->trans("AdministratorDesc"), "star", 'class="paddingleft valignmiddle"');
		}
	}
	print showValueWithClipboardCPButton($object->login).$addadmin;
	print '</td>';
}
print '</tr>'."\n";
print '</table>';
print '</div>';

print dol_get_fiche_end();

print '<!-- Token section -->'."\n";

$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete");
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

$morehtmlright = '';
//if (!empty($moreoptions['showhideaddbutton']) && $conf->use_javascript_ajax) {
$tmpurlforbutton = DOL_URL_ROOT.'/user/api_token/card.php?id='.$id.'&action=create';
//	TODO Permissions ? $morehtmlright .= dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $tmpurlforbutton, '', $permtoeditline);
$morehtmlright .= dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $tmpurlforbutton);
//}

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print load_fiche_titre($langs->trans("ListOfTokensForUser"), $morehtmlright, '', 0, '', '', $massactionbutton);
print '</form>';

// TODO : Build the hook management
// Other form for add user to group
//$parameters = array('caneditgroup' => $permissiontoeditgroup, 'groupslist' => $groupslist, 'exclude' => $exclude);
//$reshook = $hookmanager->executeHooks('formAddUserToGroup', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
//print $hookmanager->resPrint;

if (empty($reshook)) {

	print '<!-- List of tokens of the user -->';
	print '<table class="noborder centpercent">';

	print '<tr class="liste_titre">';
	if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<th class="wrapcolumntitle center maxwidthsearch liste_titre">';
		print $form->showCheckAddButtons('checkforselect', 1);
		print '</th>';
	}
	print '<th class="liste_titre">'.$langs->trans("ApiToken").'</th>';
	print '<th class="liste_titre">'.$langs->trans("Entity").'</th>';
	print '<th class="liste_titre">'.$langs->trans("NumberOfPermissions").'</th>';
	print '<th class="liste_titre">'.$langs->trans("DateCreation").'</th>';
	print '<th class="liste_titre">'.$langs->trans("DateModification").'</th>';
	print '</tr>';

	$sql = "SELECT ot.rowid as token_id, ot.token, ot.entity, ot.state as rights, ot.datec as date_creation, ot.tms as date_modification";
	$sql .= " FROM ".MAIN_DB_PREFIX."oauth_token as ot";
	$sql .= " WHERE ot.fk_user = ".((int) $object->id);

	$resql = $db->query($sql);

	// List of groups of user
	if ($db->num_rows($resql) > 0) {
		while ($obj = $db->fetch_object($resql)) {
			// Compute number of perms
			$numperms = 0;
			if (!empty($obj->rights)) {
				$numperms = count(explode(",", $obj->rights));
			}
			print '<tr class="oddeven">';
			// Action column
			if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
				print '<td class="nowrap center">';
				if ($massactionbutton || $massaction) {   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
					$selected = 0;
					if (in_array($obj->token_id, $arrayofselected)) {
						$selected = 1;
					}
					print '<input id="cb'.$obj->token_id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->token_id.'"'.($selected ? ' checked="checked"' : '').'>';
				}
				print '</td>';
			}
			print '<td>';
			print '<a href="'.DOL_URL_ROOT.'/user/api_token/card.php?id='.$object->id.'&tokenid='.$obj->token_id.'">';
			print $obj->token;
			print '</a>';
			print '</td>';
			print '<td>';
			print $obj->entity;
			print '</td>';
			print '<td>';
			print $numperms;
			print '</td>';
			print '<td>';
			print $obj->date_creation;
			print '</td>';
			print '<td>';
			print $obj->date_modification;
			print '</td>';
			print '</tr>';
		}
	} else {
		print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	}

	print "</table>";
	print "<br>";
}

// End of page
llxFooter();
$db->close();
