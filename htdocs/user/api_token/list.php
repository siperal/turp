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

if (!isset($id) || empty($id)) {
	accessforbidden();
}

// Retrieve needed GETPOSTS for this file
// Action / Massaction
$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$confirm    = GETPOST('confirm', 'alpha');
$toselect = GETPOST('toselect', 'array');

// List filters
$search_token = GETPOST('search_token', 'alpha');
$search_entity = GETPOST('search_entity', 'alpha');
$search_datec_startday = GETPOSTINT('search_datec_startday');
$search_datec_startmonth = GETPOSTINT('search_datec_startmonth');
$search_datec_startyear = GETPOSTINT('search_datec_startyear');
$search_datec_endday = GETPOSTINT('search_datec_endday');
$search_datec_endmonth = GETPOSTINT('search_datec_endmonth');
$search_datec_endyear = GETPOSTINT('search_datec_endyear');
$search_datec_start = dol_mktime(0, 0, 0, $search_datec_startmonth, $search_datec_startday, $search_datec_startyear);
$search_datec_end = dol_mktime(23, 59, 59, $search_datec_endmonth, $search_datec_endday, $search_datec_endyear);
$search_tms_startday = GETPOSTINT('search_tms_startday');
$search_tms_startmonth = GETPOSTINT('search_tms_startmonth');
$search_tms_startyear = GETPOSTINT('search_tms_startyear');
$search_tms_endday = GETPOSTINT('search_tms_endday');
$search_tms_endmonth = GETPOSTINT('search_tms_endmonth');
$search_tms_endyear = GETPOSTINT('search_tms_endyear');
$search_tms_start = dol_mktime(0, 0, 0, $search_tms_startmonth, $search_tms_startday, $search_tms_startyear);
$search_tms_end = dol_mktime(23, 59, 59, $search_tms_endmonth, $search_tms_endday, $search_tms_endyear);

// Pagination
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT("page");
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if (!$sortfield) {
	$sortfield = 'ot.token';
}
if (!$sortorder) {
	$sortorder = 'DESC';
}

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

$arrayfields = array(
	'ot.token' => array('label' => "ApiToken", 'checked' => '1'),
	'ot.entity' => array('label' => "Entity", 'checked' => '1'),
	'ot.datec' => array('label' => "DateCreation", 'checked' => '1'),
	'ot.tms' => array('label' => "DateModification", 'checked' => '1'),
);

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
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
		$search_token = '';
		$search_entity = '';
		$search_datec_start = '';
		$search_datec_end = '';
		$search_tms_start = '';
		$search_tms_end = '';

		$toselect = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

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

$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	/* The fast and low memory method to get and count full list converts the sql into a sql count */
	$sqlforcount = 'SELECT COUNT(*) as nbtotalofrecords';
	$sqlforcount .= " FROM ".MAIN_DB_PREFIX."oauth_token as ot";
	$sqlforcount .= " WHERE entity IN (".$conf->entity.") AND fk_user = ".$id;
	$resql = $db->query($sqlforcount);
	if ($resql) {
		$objforcount = $db->fetch_object($resql);
		$nbtotalofrecords = $objforcount->nbtotalofrecords;
	} else {
		dol_print_error($db);
	}

	if (($page * $limit) > $nbtotalofrecords) {	// if total resultset is smaller then paging size (filtering), goto and load page 0
		$page = 0;
		$offset = 0;
	}
	$db->free($resql);
}

$sql = "SELECT ot.rowid as token_id, ot.token, ot.entity, ot.state as rights, ot.datec as date_creation, ot.tms as date_modification";
$sql .= " FROM ".MAIN_DB_PREFIX."oauth_token as ot";
$sql .= " WHERE ot.fk_user = ".((int) $object->id)." AND entity IN (".$conf->entity.")";
if ($search_token) {
	$sql .= natural_search('ot.token', $search_token);
}
if ($search_entity) {
	$sql .= natural_search('ot.entity', $search_entity);
}
if ($search_datec_start) {
	$sql .= " AND ot.datec >= '".$db->idate($search_datec_start)."'";
}
if ($search_datec_end) {
	$sql .= " AND ot.datec <= '".$db->idate($search_datec_end)."'";
}
if ($search_tms_start) {
	$sql .= " AND ot.tms >= '".$db->idate($search_tms_start)."'";
}
if ($search_tms_end) {
	$sql .= " AND ot.tms <= '".$db->idate($search_tms_end)."'";
}
$sql .= $db->order($sortfield, $sortorder);
if ($limit) {
	$sql .= $db->plimit($limit + 1, $offset);
}

$resql = $db->query($sql);

$num = $db->num_rows($resql);

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-user page-card_param_ihm');

$param = '&id='.$id; // We always need the id of the user
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}
if ($search_datec_startday) {
	$param .= '&search_date_startday='.urlencode((string) ($search_datec_startday));
}
if ($search_datec_startmonth) {
	$param .= '&search_date_startmonth='.urlencode((string) ($search_datec_startmonth));
}
if ($search_datec_startyear) {
	$param .= '&search_date_startyear='.urlencode((string) ($search_datec_startyear));
}
if ($search_datec_endday) {
	$param .= '&search_date_endday='.urlencode((string) ($search_datec_endday));
}
if ($search_datec_endmonth) {
	$param .= '&search_date_endmonth='.urlencode((string) ($search_datec_endmonth));
}
if ($search_datec_endyear) {
	$param .= '&search_date_endyear='.urlencode((string) ($search_datec_endyear));
}
if ($search_tms_startday) {
	$param .= '&search_date_startday='.urlencode((string) ($search_tms_startday));
}
if ($search_tms_startmonth) {
	$param .= '&search_date_startmonth='.urlencode((string) ($search_tms_startmonth));
}
if ($search_tms_startyear) {
	$param .= '&search_date_startyear='.urlencode((string) ($search_tms_startyear));
}
if ($search_tms_endday) {
	$param .= '&search_date_endday='.urlencode((string) ($search_tms_endday));
}
if ($search_tms_endmonth) {
	$param .= '&search_date_endmonth='.urlencode((string) ($search_tms_endmonth));
}
if ($search_tms_endyear) {
	$param .= '&search_date_endyear='.urlencode((string) ($search_tms_endyear));
}

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
if (GETPOSTINT('nomassaction') || in_array($massaction, array('presend', 'predelete'))) {
	$arrayofmassactions = array();
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

$morehtmlright = '';
//if (!empty($moreoptions['showhideaddbutton']) && $conf->use_javascript_ajax) {
$tmpurlforbutton = DOL_URL_ROOT.'/user/api_token/card.php?id='.$id.'&action=create';
//	TODO Permissions ? $morehtmlright .= dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $tmpurlforbutton, '', $permtoeditline);
$morehtmlright .= dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $tmpurlforbutton);
//}

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';

print_barre_liste($langs->trans("ListOfTokensForUser"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, '', 0, $morehtmlright, '', $limit, 0, 0, 1);

// TODO : Build the hook management
// Other form for add user to group
//$parameters = array('caneditgroup' => $permissiontoeditgroup, 'groupslist' => $groupslist, 'exclude' => $exclude);
//$reshook = $hookmanager->executeHooks('formAddUserToGroup', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
//print $hookmanager->resPrint;

if (empty($reshook)) {

	print '<!-- List of tokens of the user -->';
	print '<table class="noborder centpercent">';

	print '<tr class="liste_titre_filter">';

	// Action buttons
	if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<td class="liste_titre center">';
		$searchpicto = $form->showFilterButtons('left');
		print $searchpicto;
		print '</td>';
	}

	// Token string
	if (!empty($arrayfields['ot.token']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" type="text" name="search_token" value="'.dol_escape_htmltag($search_token).'">';
		print '</td>';
	}

	// Entity
	if (!empty($arrayfields['ot.entity']['checked']) && isModEnabled('multicompany')) {
		print '<td class="liste_titre">';
		print '<input class="flat maxwidth100" type="text" name="search_entity" value="'.dol_escape_htmltag($search_entity).'"'.($socid > 0 ? " disabled" : "").'>';
		print '</td>';
	}

	// Number of perms
	// We don't search out number of perms because it is a string field,
	// and we don't want to count into it with sql query
	print '<td class="liste_titre"></td>';

	// Date creation
	if (!empty($arrayfields['ot.datec']['checked'])) {
		print '<td class="liste_titre center">';
		print '<div class="nowrapfordate">';
		print $form->selectDate($search_datec_start ? $search_datec_start : -1, 'search_datec_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
		print '</div>';
		print '<div class="nowrapfordate">';
		print $form->selectDate($search_datec_end ? $search_datec_end : -1, 'search_datec_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
		print '</div>';
		print '</td>';
	}

	// Date modification
	if (!empty($arrayfields['ot.tms']['checked'])) {
		print '<td class="liste_titre center">';
		print '<div class="nowrapfordate">';
		print $form->selectDate($search_tms_start ? $search_tms_start : -1, 'search_tms_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
		print '</div>';
		print '<div class="nowrapfordate">';
		print $form->selectDate($search_tms_end ? $search_tms_end : -1, 'search_tms_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
		print '</div>';
		print '</td>';
	}

	// Action buttons
	if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<td class="liste_titre center">';
		$searchpicto = $form->showFilterButtons('left');
		print $searchpicto;
		print '</td>';
	}

	print "</tr>";

	print '<tr class="liste_titre">';
	if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<th class="wrapcolumntitle center maxwidthsearch liste_titre">';
		print $form->showCheckAddButtons('checkforselect', 1);
		print '</th>';
	}
	if (!empty($arrayfields['ot.token']['checked'])) {
		print_liste_field_titre($arrayfields['ot.token']['label'], $_SERVER["PHP_SELF"], 'ot.token', '', $param, '', $sortfield, $sortorder);
	}
	if (!empty($arrayfields['ot.entity']['checked']) && isModEnabled('multicompany')) {
		print_liste_field_titre($arrayfields['ot.entity']['label'], $_SERVER["PHP_SELF"], 'ot.entity', '', $param, '', $sortfield, $sortorder);
	}
	print '<th class="liste_titre right">'.$langs->trans("NumberOfPermissions").'</th>';
	if (!empty($arrayfields['ot.datec']['checked'])) {
		print_liste_field_titre($arrayfields['ot.datec']['label'], $_SERVER["PHP_SELF"], 'ot.datec', '', $param, '', $sortfield, $sortorder, 'center ');
	}
	if (!empty($arrayfields['ot.tms']['checked'])) {
		print_liste_field_titre($arrayfields['ot.tms']['label'], $_SERVER["PHP_SELF"], 'ot.tms', '', $param, '', $sortfield, $sortorder, 'center ');
	}
	if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<th class="wrapcolumntitle center maxwidthsearch liste_titre">';
		print $form->showCheckAddButtons('checkforselect', 1);
		print '</th>';
	}
	print '</tr>';

	// List of tokens of user
	$i = 0;
	$imaxinloop = ($limit ? min($num, $limit) : $num);
	if ($num > 0) {
		while ($i < $imaxinloop) {
			// Compute number of perms
			$obj = $db->fetch_object($resql);
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
			if (isModEnabled('multicompany')) {
				print '<td>';
				print $obj->entity;
				print '</td>';
			}
			print '<td class="right">';
			print $numperms;
			print '</td>';
			print '<td class="center">';
			print dol_print_date($db->jdate($obj->date_creation), 'day');
			print '</td>';
			print '<td class="center">';
			print dol_print_date($db->jdate($obj->date_modification), 'day');
			print '</td>';
			if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
				print '<td class="nowrap center">';
				if ($massactionbutton || $massaction) {
					$selected = 0;
					if (in_array($obj->token_id, $arrayofselected)) {
						$selected = 1;
					}
					print '<input id="cb'.$obj->token_id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->token_id.'"'.($selected ? ' checked="checked"' : '').'>';
				}
				print '</td>';
			}
			print '</tr>';
			$i++;
		}
	} else {
		$colspan = 5; // Base colspan
		if (isModEnabled('multicompany')) {
			$colspan++;
		}
		print '<tr class="oddeven"><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	}

	print "</table>";
	print '</form>';
}

// End of page
llxFooter();
$db->close();
