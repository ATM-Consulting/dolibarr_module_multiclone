<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2021 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    multiclone/admin/setup.php
 * \ingroup multiclone
 * \brief   Multiclone setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if(! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if(! $res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if(! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
// Try main.inc.php using relative path
if(! $res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if(! $res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if(! $res) die("Include of main fails");

global $langs, $user, $conf;
$inputCount = 1;

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/multiclone.lib.php';

// Translations
$langs->loadLangs(array("multiclone@multiclone", "bills", "propal", "orders", "salaries", "compta", "admin"));

// Access control
if(! $user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');
$value = GETPOST('value', 'alpha');

/*
 * Actions
 */

if((float) DOL_VERSION >= 6) {
    include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';
}

/*
 * View
 */
$page_name = "multicloneSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'. $langs->trans("BackToModuleList") . '</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = multicloneAdminPrepareHead();
print dol_get_fiche_head($head,'settings', $langs->trans("ModulemulticloneName"), -1,"multiclone@multiclone");

// Setup page goes here
$var = 0;

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';

print '<table class="noborder" width="100%">';

_setupPrintTitle($langs->trans("Parameters"));

_printOnOff('MULTICLONE_VALIDATE_PROPAL', $langs->trans("ValidatePropalOnClone"));
_printOnOff('MULTICLONE_VALIDATE_ORDER', $langs->trans("ValidateOrderOnClone"));
_printOnOff('MULTICLONE_VALIDATE_INVOICE', $langs->trans("ValidateInvoiceOnClone"));

_setupPrintTitle($langs->trans("AdvancedParameters"));

if (!empty($conf->propal->enabled)) {
    _printOnOff('MULTICLONE_ACTIVATE_FOR_PROPAL', $langs->trans("ActivateForObject", $langs->trans('Proposals')));
}

if (!empty($conf->commande->enabled)) {
    _printOnOff('MULTICLONE_ACTIVATE_FOR_ORDER', $langs->trans("ActivateForObject", $langs->trans('Orders')));
}

if (!empty($conf->facture->enabled)) {
    _printOnOff('MULTICLONE_ACTIVATE_FOR_INVOICE', $langs->trans("ActivateForObject", $langs->trans('Invoices')));
}

if (!empty($conf->fournisseur->enabled)) {
    _printOnOff('MULTICLONE_ACTIVATE_FOR_SUPPLIER_INVOICE', $langs->trans("ActivateForObject", $langs->trans('BillsSuppliers')));
}

if (floatval(DOL_VERSION) < 16.0) {
    _printInputFormPart('', $langs->trans("ActivateForObject", $langs->trans('SocialContributions')), '', array(), '', $langs->trans('FeatureNotAvailableForThisDolVersion'));
} else if(empty($conf->tax->enabled)) {
    _printInputFormPart('', $langs->trans("ActivateForObject", $langs->trans('SocialContributions')), '', array(), '', $langs->trans('FeatureRequireModTaxEnabled'));
} else {
    _printOnOff('MULTICLONE_ACTIVATE_FOR_TAX', $langs->trans("ActivateForObject", $langs->trans('SocialContributions')));
}

if (floatval(DOL_VERSION) < 16.0) {
    _printInputFormPart('', $langs->trans("ActivateForObject", $langs->trans('Salaries')), '', array(), '', $langs->trans('FeatureNotAvailableForThisDolVersion'));
} else if(empty($conf->salaries->enabled)) {
    _printInputFormPart('', $langs->trans("ActivateForObject", $langs->trans('Salaries')), '', array(), '', $langs->trans('FeatureRequireModSalariesEnabled'));
} else {
    _printOnOff('MULTICLONE_ACTIVATE_FOR_SALARY', $langs->trans("ActivateForObject", $langs->trans('Salaries')));
}



if (!getDolGlobalInt('MULTICLONE_MAX_AUTHORIZED_CLONE_VALUE')) {
    dolibarr_set_const($db, 'MULTICLONE_MAX_AUTHORIZED_CLONE_VALUE', 100);
}

$metas = array('type' => 'number', 'min' => 0, 'placeholder' => 100);
_printInputFormPart('MULTICLONE_MAX_AUTHORIZED_CLONE_VALUE', $langs->trans("MaxAuthorizedCloneValue"), '', $metas);


print '</table>';

_updateBtn();

print '</form>';

llxFooter();

$db->close();

/**
 * Display title
 *
 * @param string $title
 */
function _setupPrintTitle($title = "", $width = 300) {
    global $langs;
    print '<tr class="liste_titre">';
    print '<th colspan="3">'.$langs->trans($title).'</th>'."\n";
    print '</tr>';
}

/**
 * Print an update button
 *
 * @return void
 */
function _updateBtn() {
    global $langs;
    print '<div style="text-align: right;margin-right: 15px" >';
    print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
    print '</div>';
}

/**
 * Print a On/Off button
 *
 * @param string $confkey the conf key
 * @param bool   $title   Title of conf
 * @param string $desc    Description
 *
 * @return void
 */
function _printOnOff($confkey, $title = false, $desc = '') {
    global $var, $bc, $langs;
    print '<tr class="oddeven">';
    print '<td>'.($title ? $title : $langs->trans($confkey));
    if(! empty($desc)) {
        print '<br><small>'.$langs->trans($desc).'</small>';
    }
    print '</td>';
    print '<td class="center" width="20">&nbsp;</td>';
    print '<td class="right">';
    print ajax_constantonoff($confkey);
    print '</td></tr>';
}

/**
 * Print a form part
 *
 * @param string $confkey the conf key
 * @param bool   $title   Title of conf
 * @param string $desc    Description of
 * @param array  $metas   html meta
 * @param string $type    type of input textarea or input
 * @param bool   $help    help description
 *
 * @return void
 */
function _printInputFormPart($confkey, $title = false, $desc = '', $metas = [], $type = 'input', $help = false, $moreHtmlBefore = '', $moreHtmlAfter = '') {
    global $var, $bc, $langs, $conf, $db, $inputCount;
    $var = ! $var;
    _curentInputIndex(true);
    $form = new Form($db);

    $defaultMetas = [
        'name' => _curentInputValue()
    ];

    if($type != 'textarea') {
        $defaultMetas['type'] = 'text';
        $defaultMetas['value'] = getDolGlobalString($confkey);
    }

    $metas = array_merge($defaultMetas, $metas);
    $metascompil = '';
    foreach($metas as $key => $values) {
        $metascompil .= ' '.$key.'="'.$values.'" ';
    }

    print '<tr '.$bc[$var].'>';
    print '<td>';

    if(! empty($help)) {
        print $form->textwithtooltip(($title ? $title : $langs->trans($confkey)), $langs->trans($help), 2, 1, img_help(1, ''));
    }
    else {
        print $title ? $title : $langs->trans($confkey);
    }

    if(! empty($desc)) {
        print '<br><small>'.$langs->trans($desc).'</small>';
    }

    print '</td>';
    print '<td class="center" width="20">&nbsp;</td>';
    print '<td class="right">';

    print $moreHtmlBefore;

    print _curentParam($confkey);

    print '<input type="hidden" name="action" value="setModuleOptions">';
    if($type == 'textarea') {
        print '<textarea '.$metascompil.'  >'.dol_htmlentities(getDolGlobalString($confkey)).'</textarea>';
    }
    else if($type == 'input') {
        print '<input '.$metascompil.'  />';
    }
    else {
        print $type;
    }

    print $moreHtmlAfter;

    print '</td></tr>';
}

function _curentInputIndex($next = false) {
    global $inputCount;

    if(empty($inputCount)) {
        $inputCount = 1;
    }

    if($next) {
        $inputCount++;
    }

    return $inputCount;
}

function _curentParam($confkey) {
    return '<input type="hidden" name="param'._curentInputIndex().'" value="'.$confkey.'">';
}

function _curentInputValue($offset = 0) {
    return 'value'.(_curentInputIndex() + $offset);
}
