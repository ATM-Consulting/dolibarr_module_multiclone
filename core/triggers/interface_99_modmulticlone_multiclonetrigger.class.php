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
	 * 	@param		string		$action		Event action code
	 * 	@param		Object		$object		Object
	 * 	@param		User		$user		Object user
	 * 	@param		Translate	$langs		Object langs
	 * 	@param		conf		$conf		Object conf
	 * 	@return		int						<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function run_trigger($action, $object, $user, $langs, $conf)
	{
		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action
		// Users
		if ($action == 'ORDER_CLONE' || $action == 'PROPAL_CLONE'||($action == 'BILL_CLONE') )
		{
			global $user,$db, $conf;
			
			dol_include_once('/multiclone/class/multiclone.class.php');
			
			$qty = GETPOST('cloneqty');
			$frequency = GETPOST('frequency');
			$socid = GETPOST('socid');

			if (!empty($object->date_livraison))
				$object->set_date_livraison($user, strtotime("+$frequency month", $object->date_livraison));
			if ($object->element == 'facture')
			{
				$id_source = GETPOST('id');
				$objFrom = new Facture($db);
				$objFrom->fetch($id_source);
				multiclone::setFactureDate($objFrom, $object, $frequency);
				if ($conf->global->MULTICLONE_VALIDATE_OBJECTS)
				{
					$object->validate($user);
				}
			}
			else if ($conf->global->MULTICLONE_VALIDATE_OBJECTS && $object->element != 'facture')
			{
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
				$redirect = str_replace('card.php','list.php?socid='.$socid,$_SERVER["PHP_SELF"]);
				$db->commit();
				Header('Location: '.$redirect);
				exit;
			}
		}
	

		return 0;
	}

}
