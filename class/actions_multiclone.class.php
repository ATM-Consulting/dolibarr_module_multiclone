<?php

/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_multiclone.class.php
 * \ingroup multiclone
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class Actionsmulticlone
 */
require_once __DIR__ . '/../backport/v19/core/class/commonhookactions.class.php';

class Actionsmulticlone extends multiclone\RetroCompatCommonHookActions
{

	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
		
	}

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param array()         $parameters     Hook metadatas (context, etc...)
     * @param CommonObject    &$object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param string          &$action Current action (if set). Generally create or edit or null
     * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    function doActions($parameters, &$object, &$action, $hookmanager) {
        global $conf, $langs, $db;

        if ((in_array('ordercard', explode(':', $parameters['context'])) && getDolGlobalInt('MULTICLONE_ACTIVATE_FOR_ORDER'))
            || (in_array('invoicecard', explode(':', $parameters['context'])) && getDolGlobalInt('MULTICLONE_ACTIVATE_FOR_INVOICE'))
            || (in_array('invoicesuppliercard', explode(':', $parameters['context'])) && getDolGlobalInt('MULTICLONE_ACTIVATE_FOR_SUPPLIER_INVOICE'))
            || (in_array('propalcard', explode(':', $parameters['context'])) && getDolGlobalInt('MULTICLONE_ACTIVATE_FOR_PROPAL'))
            || (in_array('salarycard', explode(':', $parameters['context'])) && getDolGlobalInt('MULTICLONE_ACTIVATE_FOR_SALARY'))
            || (in_array('taxcard', explode(':', $parameters['context'])) && getDolGlobalInt('MULTICLONE_ACTIVATE_FOR_TAX'))) {
            // Passage à l'action multiclone dès lors que l'action clone est encleché
            // Pas de traitement de l'action clone : remplacé par le traitement de l'action multiclone
            if ($action === 'clone') {
                $action = 'multiclone';
            } elseif ($action === 'confirm_multiclone') {
                dol_include_once('/multiclone/class/multiclone.class.php');

                $qty = GETPOST('cloneqty', 'int');
                $frequency = GETPOST('frequency', 'int');
                $socid = GETPOST('socid', 'int');
                if (empty($socid)){
                    $idToSend = GETPOST('userid', 'int');
                } else {
                    $idToSend = $socid;
                }

                multiclone::multiCreateFromClone($object, $qty, $frequency, $idToSend);
            }
        }
    }

    function formConfirm($parameters, &$object, &$action, $hookmanager) {
        global $langs;

        dol_include_once('multiclone/class/multiclone.class.php');
        if (in_array('ordercard', explode(':', $parameters['context']))
            || in_array('invoicecard', explode(':', $parameters['context']))
            || in_array('invoicesuppliercard', explode(':', $parameters['context']))
            || in_array('propalcard', explode(':', $parameters['context']))
            || in_array('salarycard', explode(':', $parameters['context']))
            || in_array('taxcard', explode(':', $parameters['context']))) {
            if ($action == 'multiclone') {
                $langs->load('multiclone@multiclone');
                //On check que les date soit rempli, sinon pas de traitement de la fréquence
                switch ($object->element) {
                    case 'commande':
                        if (empty($object->delivery_date)) {
                            $messageKey = 'WarningNoDeliveryDateSet';
                            setEventMessage($langs->trans($messageKey), 'warnings');
                        }
                        break;
                    case 'facture':
                    case 'invoice_supplier':
                        if (empty($object->date)) {
                            $messageKey = 'WarningNoInvoiceDateSet';
                            setEventMessage($langs->trans($messageKey), 'warnings');
                        }
                        break;
                    case 'propal':
                        if (empty($object->date)) {
                            $messageKey = 'WarningNoPropalDateSet';
                            setEventMessage($langs->trans($messageKey), 'warnings');
                        }
                        break;
                    // Pour ceux-là, les champs de date sont nécéssaires à la création, pas besoin de vérifier
                    case 'salary':
                    case 'chargesociales':
                    default:
                        break;

                }
                print multiclone::getFormConfirmClone($object);
                return 1;
            }
        }
    }
}
