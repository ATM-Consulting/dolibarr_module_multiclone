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
 * 	\file		core/triggers/interface_99_modMyodule_multiclonetrigger.class.php
 * 	\ingroup	multiclone
 * 	\brief		Sample trigger
 * 	\remarks	You can create other triggers by copying this one
 * 				- File name should be either:
 * 					interface_99_modMulticlone_Mytrigger.class.php
 * 					interface_99_all_Mytrigger.class.php
 * 				- The file must stay in core/triggers
 * 				- The class name must be InterfaceMytrigger
 * 				- The constructor method must be named InterfaceMytrigger
 * 				- The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class Interfacemulticlonetrigger
{

	private $db;

	/**
	 * Constructor
	 *
	 * 	@param		DoliDB		$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "demo";
		$this->description = "Triggers of this module are empty functions."
			."They have no effect."
			."They are provided for tutorial purpose only.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = 'development';
		$this->picto = 'multiclone@multiclone';
	}

	/**
	 * Trigger name
	 *
	 * 	@return		string	Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * 	@return		string	Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}

	/**
	 * Trigger version
	 *
	 * 	@return		string	Version of trigger file
	 */
	public function getVersion()
	{
		global $langs;
		$langs->load("admin");

		if ($this->version == 'development')
		{
			return $langs->trans("Development");
		}
		elseif ($this->version == 'experimental')
			return $langs->trans("Experimental");
		elseif ($this->version == 'dolibarr')
			return DOL_VERSION;
		elseif ($this->version)
			return $this->version;
		else
		{
			return $langs->trans("Unknown");
		}
	}

	/**
	 * Function called when a Dolibarrr business event is done.
     * All functions "run_trigger" are triggered if file
     * is inside directory core/triggers
     *
     *     @param        string                     $action     Event action code
     *     @param        Commande|Facture|Propal    $object     Object
     *     @param        User                       $user       Object user
     *     @param        Translate                  $langs      Object langs
     *     @param        conf                       $conf       Object conf
     *     @return       int                        <0 if KO, 0 if no triggered ran, >0 if OK
     */
	public function run_trigger($action, $object, $user, $langs, $conf)
	{
		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action
		// Users
        global $user, $db, $conf;
        $action_matches = array();
        if (!preg_match( '/^(ORDER|BILL|PROPAL)_(CREATE|CLONE)$/', $action, $action_matches)) {
            return 0;
        }
        $object_type = $action_matches[1]; // ORDER, BILL or PROPAL
        $action_verb = $action_matches[2]; // either CLONE or CREATE

        $is_in_a_cloning_context = $object->context['createfromclone'];
        $is_in_a_custom_cloning_context = $object->context['createfromclonecustom'];
        if ($is_in_a_custom_cloning_context) {
            // avoid recursive trigger calls & infinite loop
            return 0;
        }
        if (!(($action_verb == 'CREATE' && $is_in_a_cloning_context) || $action_verb == 'CLONE')) {
            return 0;
        }

        dol_include_once('/multiclone/class/multiclone.class.php');

        $qty = GETPOST('cloneqty');
        $frequency = GETPOST('frequency');
        $socid = GETPOST('socid');

        // adjust the first clone’s informations
        if (!empty($object->date_livraison))
            $object->set_date_livraison($user, strtotime("+$frequency month", $object->date_livraison));
        if ($object->element == 'facture')
        {
            $id_source = GETPOST('id');
            $objFrom = new Facture($db);
            $objFrom->fetch($id_source);
            multiclone::setFactureDate($objFrom, $object, $frequency);
            if ($conf->global->MULTICLONE_VALIDATE_INVOICE) $object->validate($user);
        }
        else if (
            ($conf->global->MULTICLONE_VALIDATE_PROPAL && $object->element == 'propal') ||
            ($conf->global->MULTICLONE_VALIDATE_ORDER && $object->element == 'commande')
        ) {
            $object->valid($user);
        }

        if (($qty > 1))
        {
            for ($i = 1; $i < $qty; $i++)
            {

                if($object->element == 'facture'){
                    $ret = multiclone::createFromCloneCustom($socid, $object,$frequency);
                }else {
                    if(!empty($object->date_livraison))$object->date_livraison = strtotime("+$frequency month", $object->date_livraison);
                    $ret = multiclone::createFromCloneCustom($socid, $object);
                }
            }
        }
        if($ret > 0){
            // redirect to list.php
            $redirect = preg_replace_callback(
                '=(.*)/(card|facture\.php)(.*)=',
                function ($matches) use ($socid, $object) {
                    $sortfield = 'sortfield=f.date_lim_reglement';
                    if ($matches[2] == 'facture.php') {
                        // old Dolibarr versions don't use card.php
                        return $matches[1] . '/facture/list.php?sortfield=f.date_lim_reglement&socid=' . $socid;
                    }
                    $sortfields = array(
                        'facture' => 'f.date_lim_reglement',
                        'commande' => 'c.date_livraison',
                        'propal' => 'ddelivery'
                    );
                    return $matches[1] . '/list.php?sortfield=' . $sortfields[$object->element] . '&socid=' . $socid;
                },
                $_SERVER['PHP_SELF']
            );

            $db->commit(); // there is one extra db->begin() somewhere outside the scope of this action
            $db->commit();
            Header('Location: '.$redirect);
            exit;
        }


		return 0;
	}

}
