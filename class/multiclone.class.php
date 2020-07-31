<?php

class multiclone
{
	public static function getFormConfirmClone($object)
	{
		dol_include_once('/core/class/html.form.class.php');
		global $langs, $db;
		$langs->load('multiclone@multiclone');
		$form = new Form($db);
//		if ($object->element == 'commande')
//		{
			$elem = "Order";
			$formquestion = array(
				// 'text' => $langs->trans("ConfirmClone"),
				// array('type' => 'checkbox', 'name' => 'clone_content', 'label' => $langs->trans("CloneMainAttributes"), 'value' =>
				// 1),
				array('type' => 'text', 'name' => 'cloneqty', 'label' => $langs->trans("CloneQty"), 'value' => 1),
				array('type' => 'text', 'name' => 'frequency', 'label' => $langs->trans("CloneFrequency"), 'value' => 0),
				array('type' => 'other', 'name' => 'socid', 'label' => $langs->trans("SelectThirdParty"), 'value' => $form->select_company($object->socid, 'socid', '(s.client IN(1,'.($object->element === 'propal' ? '2,' : '').'3))', '', 0, 0, array(), 0, 'minwidth300')));
			// Paiement incomplet. On demande si motif = escompte ou autre
//		}
		/*else
		{
			if($object->element == 'facture')$elem="Invoice";
			if($object->element == 'propal')$elem="Propal";
			$formquestion = array(
				// 'text' => $langs->trans("ConfirmClone"),
				// array('type' => 'checkbox', 'name' => 'clone_content', 'label' => $langs->trans("CloneMainAttributes"), 'value' =>
				// 1),
				array('type' => 'text', 'name' => 'cloneqty', 'label' => $langs->trans("CloneQty"), 'value' => 1),
				array('type' => 'other', 'name' => 'socid', 'label' => $langs->trans("SelectThirdParty"), 'value' => $form->select_company(GETPOST('socid', 'int'), 'socid', '(s.client=1 OR s.client=3)', '', 0, 0, array(), 0, 'minwidth300')));
		}*/
		// Paiement incomplet. On demande si motif = escompte ou autre
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans("Clone$elem"), $langs->trans("ConfirmClone$elem", $object->ref), 'confirm_clone', $formquestion, 'yes', 1);

		return $formconfirm;
	}

	static function createFromCloneCustom($socid = 0, $object,$frequency=0)
	{
		global $user, $hookmanager,$conf;

		$error = 0;

		$object->context['createfromclone'] = 'createfromclone';
        $object->context['createfromclonecustom'] = 'createfromclone';

		$object->db->begin();

		// get extrafields so they will be clone
		foreach ($object->lines as $line)
			$line->fetch_optionals($line->rowid);

		// Load source object
		$objFrom = clone $object;

		// Change socid if needed
		if (!empty($socid) && $socid != $object->socid)
		{
			$objsoc = new Societe($object->db);

			if ($objsoc->fetch($socid) > 0)
			{
				$object->socid = $objsoc->id;
				$object->cond_reglement_id = (!empty($objsoc->cond_reglement_id) ? $objsoc->cond_reglement_id : 0);
				$object->mode_reglement_id = (!empty($objsoc->mode_reglement_id) ? $objsoc->mode_reglement_id : 0);
				$object->fk_project = '';
				$object->fk_delivery_address = '';
			}

			// TODO Change product price if multi-prices
		}

		$object->id = 0;
		$object->ref = '';
		$object->statut = 0;

		// Clear fields
		$object->user_author_id = $user->id;
		$object->user_valid = '';
		$object->date = dol_now();
		if($object->element == 'facture' && ! empty($frequency))$object->date = strtotime("+$frequency month", $objFrom->date);
		if($object->element == 'commande')$object->date_commande = dol_now();
		$object->date_creation = '';
		$object->date_validation = '';
		$object->ref_client = '';
		
		// Create clone
		$result = $object->create($user);
		$object->add_object_linked($object->element, $objFrom->id);

		if($object->element == 'facture' && $conf->global->MULTICLONE_VALIDATE_INVOICE) $object->validate($user);
		else if(($object->element == 'propal' && $conf->global->MULTICLONE_VALIDATE_PROPAL) || ($object->element == 'commande' && $conf->global->MULTICLONE_VALIDATE_ORDER)) $object->valid($user);
		
		if ($result < 0)
			$error++;



		unset($object->context['createfromclone']);

		// End
		if (!$error)
		{
			$object->db->commit();
			return $object->id;
		}
		else
		{
			$object->db->rollback();
			return -1;
		}
	}
	
	static function setFactureDate($objFrom,$object,$frequency)
	{
		global $db;
		$old_date_lim_reglement = $objFrom->date_lim_reglement;
		
	    $object->date=strtotime("+$frequency month", $objFrom->date);
		$new_date_lim_reglement = $object->calculate_date_lim_reglement();
		if ($new_date_lim_reglement > $old_date_lim_reglement) $object->date_lim_reglement = $new_date_lim_reglement;
		if ($object->date_lim_reglement < $object->date) $object->date_lim_reglement = $object->date;
		
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'facture SET datef="'.$db->idate($object->date).'", date_lim_reglement="'. $db->idate($object->date_lim_reglement).'" WHERE rowid='.$object->id;
		$resql = $db->query($sql);
		
	}

}

