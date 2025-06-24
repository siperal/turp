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
$tokenid = GETPOST('tokenid', 'aZ09');
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
$caneditperms = ($user->admin || $user->hasRight("user", "user", "write"));

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

$sql = "SELECT ot.rowid as token_id, ot.token, ot.entity, ot.state, ot.datec, ot.tms";
$sql .= " FROM ".MAIN_DB_PREFIX."oauth_token as ot";
$sql .= " WHERE ot.rowid = ".((int) $tokenid);

$resql = $db->query($sql);

$object = new User($db);
$object->fetch($id, '', '', 1);
$object->loadRights();

$form = new Form($db);
$formadmin = new FormAdmin($db);
$token = $db->fetch_object($resql);

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

$linkback = '<a href="'.DOL_URL_ROOT.'/user/api_token/list.php?id='.$id.'">'.$langs->trans("BackToList").'</a>';

$morehtmlref = '<a href="'.DOL_URL_ROOT.'/user/vcard.php?id='.$object->id.'&output=file&file='.urlencode(dol_sanitizeFileName($object->getFullName($langs).'.vcf')).'" class="refid" rel="noopener">';
$morehtmlref .= img_picto($langs->trans("Download").' '.$langs->trans("VCard"), 'vcard.png', 'class="valignmiddle marginleftonly paddingrightonly"');
$morehtmlref .= '</a>';

$urltovirtualcard = '/user/virtualcard.php?id='.((int) $object->id);
$morehtmlref .= dolButtonToOpenUrlInDialogPopup('publicvirtualcard', $langs->transnoentitiesnoconv("PublicVirtualCardUrl").' - '.$object->getFullName($langs), img_picto($langs->trans("PublicVirtualCardUrl"), 'card', 'class="valignmiddle marginleftonly paddingrightonly"'), $urltovirtualcard, '', 'nohover');

dol_banner_tab($object, 'id', $linkback, $user->hasRight("user", "user", "read") || $user->admin, 'rowid', 'ref', $morehtmlref);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&tokenid='.$token->token_id.'">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="edit">';

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

//var_dump(showValueWithClipboardCPButton($token->token, 1, $token->token));

// Token
print '<tr><td class="titlefield">'.$langs->trans("ApiToken").'</td>';
print '<td>';
print '<input class="minwidth300 maxwidth400 widthcentpercentminusx" type="text" id="api_key" name="api_key" value="'.$token->token.'" autocomplete="off">';
if (!empty($conf->use_javascript_ajax)) {
	print img_picto($langs->transnoentities('Generate'), 'refresh', 'id="generate_api_key" class="linkobject paddingleft"');
}
print '</td>';
print '</tr>'."\n";

// Entity
print '<tr><td class="titlefield">'.$langs->trans("Entity").'</td>';
print '<td>';
print '<input type="text" id="entity" name="entity" value="'.$token->entity.'" readonly>';
print '</td>';
print '</tr>'."\n";

// Creation date
print '<tr><td class="titlefield">'.$langs->trans("DateCreation").'</td>';
print '<td>';
print '<input type="text" id="creation_date" name="creation_date" value="'.$token->datec.'" readonly>';
print '</td>';
print '</tr>'."\n";

// Modification date
print '<tr><td class="titlefield">'.$langs->trans("DateModification").'</td>';
print '<td>';
print '<input type="text" id="modification_date" name="modification_date" value="'.$token->tms.'" readonly>';
print '</td>';
print '</tr>'."\n";

print '</table>';
print '<div class="right">';
print '<input class="button button-save" type="submit" value="'.$langs->trans("Save").'">';
print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&tokenid='.$token->token_id.'&action=delete&token='.newToken().'">'.$langs->trans("Delete").'</a>';
print '</div>';
print '</div>';
print '</form>';

print dol_get_fiche_end();

print load_fiche_titre($langs->trans("ListOfRightsForToken"), '', '');




 // TODO : Rights part
print '<!-- Rights section -->'."\n";

if ($user->admin) {
	print info_admin($langs->trans("WarningOnlyPermissionOfActivatedModules"));
}

$db->begin();

// Search all modules with permission and reload permissions def.
$modules = array();
$modulesdir = dolGetModulesDirs();

foreach ($modulesdir as $dir) {
	$handle = @opendir(dol_osencode($dir));
	if (is_resource($handle)) {
		while (($file = readdir($handle)) !== false) {
			if (is_readable($dir.$file) && substr($file, 0, 3) == 'mod' && substr($file, dol_strlen($file) - 10) == '.class.php') {
				$modName = substr($file, 0, dol_strlen($file) - 10);

				if ($modName) {
					include_once $dir.$file;
					$objMod = new $modName($db);
					'@phan-var-force DolibarrModules $objMod';

					// Load all lang files of module
					if (isset($objMod->langfiles) && is_array($objMod->langfiles)) {
						foreach ($objMod->langfiles as $domain) {
							$langs->load($domain);
						}
					}
					// Load all permissions
					if ($objMod->rights_class) {
						$ret = $objMod->insert_permissions(0, $token->entity);
						$modules[$objMod->rights_class] = $objMod;
						//print "modules[".$objMod->rights_class."]=$objMod;";
					}
				}
			}
		}
	}
}

$db->commit();

// Load perms ids for the user
$permsuser = array();

$sql = "SELECT ot.state";
$sql .= " FROM ".MAIN_DB_PREFIX."oauth_token as ot";
$sql .= " WHERE ot.rowid = ".((int) $token->token_id);

dol_syslog("get user perms", LOG_DEBUG);
$result = $db->query($sql);

if ($result) {
	$obj = $db->fetch_object($result);
	$permsuser = explode(",", $obj->state);
	$db->free($result);
} else {
	dol_print_error($db);
}

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Module").'</td>';
if ($caneditperms) {
	print '<td class="center nowrap">';
	print '<a class="reposition commonlink addexpandedmodulesinparamlist" title="'.dol_escape_htmltag($langs->trans("All")).'" alt="'.dol_escape_htmltag($langs->trans("All")).'" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=addrights&token='.newToken().'&entity='.$entity.'&module=allmodules&confirm=yes">'.$langs->trans("All")."</a>";
	print ' / ';
	print '<a class="reposition commonlink addexpandedmodulesinparamlist" title="'.dol_escape_htmltag($langs->trans("None")).'" alt="'.dol_escape_htmltag($langs->trans("None")).'" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delrights&token='.newToken().'&entity='.$entity.'&module=allmodules&confirm=yes">'.$langs->trans("None")."</a>";
	print '</td>';
} else {
	print '<td></td>';
}
print '<td></td>';
//print '<td></td>';
print '<td class="right nowrap" colspan="2">';
print '<a class="showallperms" title="'.dol_escape_htmltag($langs->trans("ShowAllPerms")).'" alt="'.dol_escape_htmltag($langs->trans("ShowAllPerms")).'" href="#">'.img_picto('', 'folder-open', 'class="paddingright"').'<span class="hideonsmartphone">'.$langs->trans("ExpandAll").'</span></a>';
print ' | ';
print '<a class="hideallperms" title="'.dol_escape_htmltag($langs->trans("HideAllPerms")).'" alt="'.dol_escape_htmltag($langs->trans("HideAllPerms")).'" href="#">'.img_picto('', 'folder', 'class="paddingright"').'<span class="hideonsmartphone">'.$langs->trans("UndoExpandAll").'</span></a>';
print '</td>';
print '</tr>'."\n";

// Load modules rights and correct position if needed
$sql = "SELECT r.id, r.libelle as label, r.module, r.perms, r.subperms, r.module_position, r.bydefault";
$sql .= " FROM ".MAIN_DB_PREFIX."rights_def as r";
$sql .= " WHERE r.libelle NOT LIKE 'tou%'"; // On ignore droits "tous"
$sql .= " AND r.entity = ".((int) $entity);
$sql .= " ORDER BY r.family_position, r.module_position, r.module, r.id";

$result = $db->query($sql);
if ($result) {
	$num = $db->num_rows($result);
	$i = 0;
	$oldmod = '';

	while ($i < $num) {
		$obj = $db->fetch_object($result);

		// If line is for a module that does not exist anymore (absent of includes/module), we ignore it
		if (!isset($obj->module) || empty($modules[$obj->module])) {
			$i++;
			continue;
		}

		// Special cases
		if (isModEnabled("reception")) {
			// The 2 permissions in fournisseur modules are replaced by permissions into reception module
			if ($obj->module == 'fournisseur' && $obj->perms == 'commande' && $obj->subperms == 'receptionner') {
				$i++;
				continue;
			}
			if ($obj->module == 'fournisseur' && $obj->perms == 'commande_advance' && $obj->subperms == 'check') {
				$i++;
				continue;
			}
		}

		$objMod = $modules[$obj->module];

		// Save field module_position in database if value is wrong
		if (empty($obj->module_position) || (is_object($objMod) && $objMod->isCoreOrExternalModule() == 'external' && $obj->module_position < 100000)) {
			if (is_object($modules[$obj->module]) && ($modules[$obj->module]->module_position > 0)) {
				// TODO Define familyposition
				//$familyposition = $modules[$obj->module]->family_position;
				$familyposition = 0;

				$newmoduleposition = $modules[$obj->module]->module_position;

				// Correct $newmoduleposition position for external modules
				$objMod = $modules[$obj->module];
				if (is_object($objMod) && $objMod->isCoreOrExternalModule() == 'external' && $newmoduleposition < 100000) {
					$newmoduleposition += 100000;
				}

				$sqlupdate = 'UPDATE '.MAIN_DB_PREFIX."rights_def SET module_position = ".((int) $newmoduleposition).",";
				$sqlupdate .= " family_position = ".((int) $familyposition);
				$sqlupdate .= " WHERE module_position = ".((int) $obj->module_position)." AND module = '".$db->escape($obj->module)."'";

				$db->query($sqlupdate);
			}
		}
	}
}

// Load and show all the perms grouped by module

//print "xx".$conf->global->MAIN_USE_ADVANCED_PERMS;
$sql = "SELECT r.id, r.libelle as label, r.module, r.perms, r.subperms, r.module_position, r.bydefault";
$sql .= " FROM ".MAIN_DB_PREFIX."rights_def as r";
$sql .= " WHERE r.libelle NOT LIKE 'tou%'"; // On ignore droits "tous"
$sql .= " AND r.entity = ".((int) $entity);
if (!getDolGlobalString('MAIN_USE_ADVANCED_PERMS')) {
	$sql .= " AND r.perms NOT LIKE '%_advance'"; // Hide advanced perms if option is not enabled
}
$sql .= " ORDER BY r.family_position, r.module_position, r.module, r.id";

$result = $db->query($sql);
if ($result) {
	$num = $db->num_rows($result);
	$i = 0;
	$j = 0;
	$oldmod = '';

	$cookietohidegroup = (empty($_COOKIE["DOLUSER_PERMS_HIDE_GRP"]) ? '' : preg_replace('/^,/', '', $_COOKIE["DOLUSER_PERMS_HIDE_GRP"]));
	$cookietohidegrouparray = explode(',', $cookietohidegroup);
	//var_dump($cookietohidegrouparray);

	while ($i < $num) {
		$obj = $db->fetch_object($result);

		// If line is for a module that does not exist anymore (absent of includes/module), we ignore it
		if (empty($modules[$obj->module])) {
			$i++;
			continue;
		}

		// Special cases
		if (isModEnabled("reception")) {
			// The 2 permission in fournisseur modules has been replaced by permissions into reception module
			if ($obj->module == 'fournisseur' && $obj->perms == 'commande' && $obj->subperms == 'receptionner') {
				$i++;
				continue;
			}
			if ($obj->module == 'fournisseur' && $obj->perms == 'commande_advance' && $obj->subperms == 'check') {
				$i++;
				continue;
			}
		}

		$objMod = $modules[$obj->module];

		if (GETPOSTISSET('forbreakperms_'.$obj->module)) {
			$ishidden = GETPOSTINT('forbreakperms_'.$obj->module);
		} elseif (in_array($j, $cookietohidegrouparray)) {	// If j is among list of hidden group
			$ishidden = 1;
		} else {
			$ishidden = 0;
		}
		$isexpanded = ! $ishidden;
		//var_dump("isexpanded=".$isexpanded);

		$permsgroupbyentitypluszero = array();
		if (!empty($permsgroupbyentity[0])) {
			$permsgroupbyentitypluszero = array_merge($permsgroupbyentitypluszero, $permsgroupbyentity[0]);
		}
		if (!empty($permsgroupbyentity[$entity])) {
			$permsgroupbyentitypluszero = array_merge($permsgroupbyentitypluszero, $permsgroupbyentity[$entity]);
		}
		//var_dump($permsgroupbyentitypluszero);

		// Break found, it's a new module to catch
		if (isset($obj->module) && ($oldmod != $obj->module)) {
			$oldmod = $obj->module;

			$j++;
			if (GETPOSTISSET('forbreakperms_'.$obj->module)) {
				$ishidden = GETPOSTINT('forbreakperms_'.$obj->module);
			} elseif (in_array($j, $cookietohidegrouparray)) {	// If j is among list of hidden group
				$ishidden = 1;
			} else {
				$ishidden = 0;
			}
			$isexpanded = ! $ishidden;
			//var_dump('$obj->module='.$obj->module.' isexpanded='.$isexpanded);

			// Break detected, we get objMod
			$objMod = $modules[$obj->module];
			$picto = ($objMod->picto ? $objMod->picto : 'generic');

			// Show break line
			print '<tr class="oddeven trforbreakperms trforbreaknobg" data-hide-perms="'.$obj->module.'" data-j="'.$j.'">';
			// Picto and label of module
			print '<td class="maxwidthonsmartphone tdoverflowmax200 tdforbreakperms" data-hide-perms="'.dol_escape_htmltag($obj->module).'" title="'.dol_escape_htmltag($objMod->getName()).'">';
			print '<input type="hidden" name="forbreakperms_'.$obj->module.'" id="idforbreakperms_'.$obj->module.'" css="cssforfieldishiden" data-j="'.$j.'" value="'.($isexpanded ? '0' : "1").'">';
			print img_object('', $picto, 'class="pictoobjectwidth paddingright"').' '.$objMod->getName();
			print '<a name="'.$objMod->getName().'"></a>';
			print '</td>';

			// Permission and tick (2 columns)
			if (($caneditperms && empty($objMod->rights_admin_allowed)) || empty($object->admin)) {
				if ($caneditperms) {
					print '<td class="tdforbreakperms tdforbreakpermsifnotempty center width50 nowraponall" data-hide-perms="'.dol_escape_htmltag($obj->module).'">';
					print '<span class="permtohide_'.dol_escape_htmltag($obj->module).'" '.(!$isexpanded ? ' style="display:none"' : '').'>';
					print '<a class="reposition alink addexpandedmodulesinparamlist" title="'.dol_escape_htmltag($langs->trans("All")).'" alt="'.dol_escape_htmltag($langs->trans("All")).'" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=addrights&token='.newToken().'&entity='.$entity.'&module='.$obj->module.'&confirm=yes&updatedmodulename='.$obj->module.'">'.$langs->trans("All")."</a>";
					print ' / ';
					print '<a class="reposition alink addexpandedmodulesinparamlist" title="'.dol_escape_htmltag($langs->trans("None")).'" alt="'.dol_escape_htmltag($langs->trans("None")).'" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delrights&token='.newToken().'&entity='.$entity.'&module='.$obj->module.'&confirm=yes&updatedmodulename='.$obj->module.'">'.$langs->trans("None")."</a>";
					print '</span>';
					print '</td>';
					print '<td class="tdforbreakperms" data-hide-perms="'.dol_escape_htmltag($obj->module).'">';
					print '</td>';
				} else {
					print '<td class="tdforbreakperms" data-hide-perms="'.dol_escape_htmltag($obj->module).'"></td>';
					print '<td class="tdforbreakperms" data-hide-perms="'.dol_escape_htmltag($obj->module).'"></td>';
				}
			} else {
				if ($caneditperms) {
					print '<td class="tdforbreakperms center wraponsmartphone" data-hide-perms="'.dol_escape_htmltag($obj->module).'">';
					/*print '<a class="reposition alink" title="'.dol_escape_htmltag($langs->trans("All")).'" alt="'.dol_escape_htmltag($langs->trans("All")).'" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=addrights&token='.newToken().'&entity='.$entity.'&module='.$obj->module.'&confirm=yes&updatedmodulename='.$obj->module.'">'.$langs->trans("All")."</a>";
					print ' / ';
					print '<a class="reposition alink" title="'.dol_escape_htmltag($langs->trans("None")).'" alt="'.dol_escape_htmltag($langs->trans("None")).'" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delrights&token='.newToken().'&entity='.$entity.'&module='.$obj->module.'&confirm=yes&updatedmodulename='.$obj->module.'">'.$langs->trans("None")."</a>";
					*/
					print '</td>';
					print '<td class="tdforbreakperms" data-hide-perms="'.dol_escape_htmltag($obj->module).'">';
					print '</td>';
				} else {
					print '<td class="right tdforbreakperms" data-hide-perms="'.dol_escape_htmltag($obj->module).'"></td>';
					print '<td class="tdforbreakperms" data-hide-perms="'.dol_escape_htmltag($obj->module).'"></td>';
				}
			}

			// Description of permission (2 columns)
			print '<td class="tdforbreakperms" data-hide-perms="'.dol_escape_htmltag($obj->module).'"></td>';
			print '<td class="maxwidthonsmartphone right tdforbreakperms" data-hide-perms="'.dol_escape_htmltag($obj->module).'">';

			print '<div class="switchfolderperms inline-block marginrightonly folderperms_'.dol_escape_htmltag($obj->module).'"'.($isexpanded ? ' style="display:none;"' : '').'>';
			print img_picto('', 'folder', 'class="marginright"');
			print '</div>';
			print '<div class="switchfolderperms inline-block marginrightonly folderopenperms_'.dol_escape_htmltag($obj->module).'"'.(!$isexpanded ? ' style="display:none;"' : '').'>';
			print img_picto('', 'folder-open', 'class="marginright"');
			print '</div>';

			print '</td>'; //Add picto + / - when open en closed
			print '</tr>'."\n";
		}

		$permlabel = (getDolGlobalString('MAIN_USE_ADVANCED_PERMS') && ($langs->trans("PermissionAdvanced".$obj->id) != "PermissionAdvanced".$obj->id) ? $langs->trans("PermissionAdvanced".$obj->id) : (($langs->trans("Permission".$obj->id) != "Permission".$obj->id) ? $langs->trans("Permission".$obj->id) : $langs->trans($obj->label)));

		print '<!-- '.$obj->module.'->'.$obj->perms.($obj->subperms ? '->'.$obj->subperms : '').' -->'."\n";
		print '<tr class="oddeven trtohide_'.$obj->module.'"'.(!$isexpanded ? ' style="display:none"' : '').'>';

		// Picto and label of module
		print '<td class="maxwidthonsmartphone">';
		print '</td>';

		// Permission and tick (2 columns)
		if (!empty($object->admin) && !empty($objMod->rights_admin_allowed)) {    // Permission granted because admin
			print '<!-- perm is a perm allowed to any admin -->';
			if ($caneditperms) {
				print '<td class="center nowrap">';
				print img_picto($langs->trans("AdministratorDesc"), 'star', 'class="paddingleft valignmiddle"');
				print '</td>';
			} else {
				print '<td class="center nowrap">';
				print img_picto($langs->trans("Active"), 'switch_on', '', 0, 0, 0, '', 'opacitymedium');
				print '</td>';
			}
			print '<td>';
			print '</td>';
		} elseif (in_array($obj->id, $permsuser)) {					// Permission granted by user
			print '<!-- user has perm -->';
			if ($caneditperms) {
				print '<td class="center nowrap">';
				print '<a class="reposition addexpandedmodulesinparamlist" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delrights&token='.newToken().'&entity='.$entity.'&rights='.$obj->id.'&confirm=yes&updatedmodulename='.$obj->module.'">';
				//print img_edit_remove($langs->trans("Remove"));
				print img_picto($langs->trans("Remove"), 'switch_on');
				print '</a>';
				print '</td>';
			} else {
				print '<td class="center nowrap">';
				print img_picto($langs->trans("Active"), 'switch_on', '', 0, 0, 0, '', 'opacitymedium');
				print '</td>';
			}
			print '<td>';
			print '</td>';
		} elseif (isset($permsgroupbyentitypluszero) && is_array($permsgroupbyentitypluszero)) {
			print '<!-- permsgroupbyentitypluszero -->';
			if (in_array($obj->id, $permsgroupbyentitypluszero)) {	// Permission granted by group
				print '<td class="center nowrap">';
				print img_picto($langs->trans("Active"), 'switch_on', '', 0, 0, 0, '', 'opacitymedium');
				//print img_picto($langs->trans("Active"), 'tick');
				print '</td>';
				print '<td class="center nowrap">';
				print $form->textwithtooltip($langs->trans("Inherited"), $langs->trans("PermissionInheritedFromAGroup"));
				print '</td>';
			} else {
				// Do not own permission
				if ($caneditperms) {
					print '<td class="center nowrap">';
					print '<a class="reposition addexpandedmodulesinparamlist" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=addrights&entity='.$entity.'&rights='.$obj->id.'&confirm=yes&token='.newToken().'&updatedmodulename='.$obj->module.'">';
					//print img_edit_add($langs->trans("Add"));
					print img_picto($langs->trans("Add"), 'switch_off');
					print '</a>';
					print '</td>';
				} else {
					print '<td class="center nowrap">';
					print img_picto($langs->trans("Disabled"), 'switch_off', '', 0, 0, 0, '', 'opacitymedium');
					print '</td>';
				}
				print '<td>';
				print '</td>';
			}
		} else {
			// Do not own permission
			print '<!-- do not own permission -->';
			if ($caneditperms) {
				print '<td class="center nowrap">';
				print '<a class="reposition addexpandedmodulesinparamlist" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=addrights&entity='.$entity.'&rights='.$obj->id.'&confirm=yes&token='.newToken().'&updatedmodulename='.$obj->module.'">';
				//print img_edit_add($langs->trans("Add"));
				print img_picto($langs->trans("Add"), 'switch_off');
				print '</a>';
				print '</td>';
			} else {
				print '<td class="center nowrap">';
				print img_picto($langs->trans("Disabled"), 'switch_off', '', 0, 0, 0, '', 'opacitymedium');
				print '</td>';
			}
			print '<td>';
			print '</td>';
		}

		// Description of permission (1 or 2 columns)
		if (!$user->admin) {
			print '<td colspan="2">';
		} else {
			print '<td>';
		}

		print $permlabel;
		$idtouse = $obj->id;
		if (in_array($idtouse, array(121, 122, 125, 126))) {	// Force message for the 3 permission on third parties
			$idtouse = 122;
		}
		if ($langs->trans("Permission".$idtouse.'b') != "Permission".$idtouse.'b') {
			print '<br><span class="opacitymedium">'.$langs->trans("Permission".$idtouse.'b').'</span>';
		}
		if ($langs->trans("Permission".$obj->id.'c') != "Permission".$obj->id.'c') {
			print '<br><span class="opacitymedium">'.$langs->trans("Permission".$obj->id.'c').'</span>';
		}
		if (getDolGlobalString('MAIN_USE_ADVANCED_PERMS')) {
			if (preg_match('/_advance$/', $obj->perms)) {
				print ' <span class="opacitymedium">('.$langs->trans("AdvancedModeOnly").')</span>';
			}
		}
		// Special warning case for the permission "Allow to modify other users password"
		if ($obj->module == 'user' && $obj->perms == 'user' && $obj->subperms == 'password') {
			if ((!empty($object->admin) && !empty($objMod->rights_admin_allowed)) ||
				in_array($obj->id, $permsuser) /* if edited user owns this permissions */ ||
				(isset($permsgroupbyentitypluszero) && is_array($permsgroupbyentitypluszero) && in_array($obj->id, $permsgroupbyentitypluszero))) {
				print ' '.img_warning($langs->trans("AllowPasswordResetBySendingANewPassByEmail"));
			}
		}
		// Special warning case for the permission "Create/modify other users, groups and permissions"
		if ($obj->module == 'user' && $obj->perms == 'user' && ($obj->subperms == 'creer' || $obj->subperms == 'create')) {
			if ((!empty($object->admin) && !empty($objMod->rights_admin_allowed)) ||
				in_array($obj->id, $permsuser) /* if edited user owns this permissions */ ||
				(isset($permsgroupbyentitypluszero) && is_array($permsgroupbyentitypluszero) && in_array($obj->id, $permsgroupbyentitypluszero))) {
				print ' '.img_warning($langs->trans("AllowAnyPrivileges"));
			}
		}
		// Special case for reading bank account when you have permission to manage Chart of account
		if ($obj->module == 'banque' && $obj->perms == 'lire') {
			if (isModEnabled("accounting") && $object->hasRight('accounting', 'chartofaccount')) {
				print ' '.img_warning($langs->trans("WarningReadBankAlsoAllowedIfUserHasPermission"));
			}
		}

		print '</td>';

		// Permission id
		if ($user->admin) {
			print '<td class="right">';
			$htmltext = $langs->trans("ID").': '.$obj->id;
			$htmltext .= '<br>'.$langs->trans("Permission").': user->hasRight(\''.dol_escape_htmltag($obj->module).'\', \''.dol_escape_htmltag($obj->perms).'\''.($obj->subperms ? ', \''.dol_escape_htmltag($obj->subperms).'\'' : '').')';
			print $form->textwithpicto('', $htmltext, 1, 'help', 'inline-block marginrightonly');
			//print '<span class="opacitymedium">'.$obj->id.'</span>';
			print '</td>';
		}

		print '</tr>'."\n";

		$i++;
	}
} else {
	dol_print_error($db);
}
print '</table>';
print '</div>';

print '<script>';
print '$(".tdforbreakperms:not(.alink)").on("click", function(){
	console.log("Click on tdforbreakperms");
	moduletohide = $(this).data("hide-perms");
	j = $(this).data("j");
	if ($("#idforbreakperms_"+moduletohide).val() == 1) {
		console.log("idforbreakperms_"+moduletohide+" has value hidden=1, so we show all lines");
		$(".trtohide_"+moduletohide).show();
		$(".permtoshow_"+moduletohide).hide();
		$(".permtohide_"+moduletohide).show();
		$(".folderperms_"+moduletohide).hide();
		$(".folderopenperms_"+moduletohide).show();
		$("#idforbreakperms_"+moduletohide).val("0");
	} else if (! $(this).hasClass("tdforbreakpermsifnotempty")) {
		console.log("idforbreakperms_"+moduletohide+" has value hidden=0, so we hide all lines");
		$(".trtohide_"+moduletohide).hide();
		$(".folderopenperms_"+moduletohide).hide();
		$(".folderperms_"+moduletohide).show();
		$(".permtoshow_"+moduletohide).show();
		$(".permtohide_"+moduletohide).hide();
		$("#idforbreakperms_"+moduletohide).val("1");
	}

	// Now rebuild the value for cookie
	var hideuserperm="";
	$(".trforbreakperms").each(function(index) {
		//console.log( index + ": " + $( this ).data("j") + " " + $( this ).data("hide-perms") + " " + $("input[data-j="+(index+1)+"]").val());
		if ($("input[data-j="+(index+1)+"]").val() == 1) {
			hideuserperm=hideuserperm+","+(index+1);
		}
	});
	// set cookie by js
	date = new Date(); date.setTime(date.getTime()+(30*86400000));
	if (hideuserperm) {
		console.log("set cookie DOLUSER_PERMS_HIDE_GRP="+hideuserperm);
		document.cookie = "DOLUSER_PERMS_HIDE_GRP=" + hideuserperm + "; expires=" + date.toGMTString() + "; path=/ ";
	} else {
		console.log("delete cookie DOLUSER_PERMS_HIDE_GRP");
		document.cookie = "DOLUSER_PERMS_HIDE_GRP=; expires=Thu, 01-Jan-70 00:00:01 GMT; path=/ ";
	}
});';
print "\n";

// Button expand / collapse all
print '$(".showallperms").on("click", function(){
	console.log("Click on showallperms");

	console.log("delete cookie DOLUSER_PERMS_HIDE_GRP from showallperms click");
	document.cookie = "DOLUSER_PERMS_HIDE_GRP=; expires=Thu, 01-Jan-70 00:00:01 GMT; path=/ ";
	$(".tdforbreakperms").each( function(){
		moduletohide = $(this).data("hide-perms");
		//console.log(moduletohide);
		if ($("#idforbreakperms_"+moduletohide).val() != 0) {
			$(this).trigger("click");	// emulate the click, so the cooki will be resaved
		}
	})
});

$(".hideallperms").on("click", function(){
	console.log("Click on hideallperms");

	$(".tdforbreakperms").each( function(){
		moduletohide = $(this).data("hide-perms");
		//console.log(moduletohide);
		if ($("#idforbreakperms_"+moduletohide).val() != 1) {
			$(this).trigger("click");	// emulate the click, so the cooki will be resaved
		}
	})
});';
print "\n";
print '</script>';

print '<style>';
print '.switchfolderperms{
	cursor: pointer;
}';
print '</style>';

// TODO : Build the hook management
// Other form for add user to group
//$parameters = array('caneditgroup' => $permissiontoeditgroup, 'groupslist' => $groupslist, 'exclude' => $exclude);
//$reshook = $hookmanager->executeHooks('formAddUserToGroup', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
//print $hookmanager->resPrint;

include_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
print dolJSToSetRandomPassword('api_key', 'generate_api_key', 1);

// End of page
llxFooter();
$db->close();
