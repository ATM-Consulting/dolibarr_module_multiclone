<?php

class multiclone
{
	public static function getFormConfirmClone($object)
	{
		dol_include_once('/core/class/html.form.class.php');
		global $langs, $db;
		$langs->load('multiclone@multiclone');
		$form = new Form($db);

			$elem = $object->element;
            $other_question = array('type' => 'other', 'name' => 'socid', 'label' => $langs->trans("SelectThirdParty"), 'value' => $form->select_company($object->socid, 'socid', '(s.client IN(1,'.($object->element === 'propal' ? '2,' : '').'3))', '', 0, 0, array(), 0, 'minwidth300'));
            if ($elem == 'salary' || $elem == 'chargesociales'){
                $other_question = array('type' => 'other', 'name' => 'userid', 'label' => $langs->trans("SelectUser"), 'value' => $form->select_dolusers($object->fk_user));
            }
			$formquestion = array(
				array('type' => 'text', 'name' => 'cloneqty', 'label' => $langs->trans("CloneQty"), 'value' => 1),
				array('type' => 'text', 'name' => 'frequency', 'label' => $langs->trans("CloneFrequency"), 'value' => 0),
                $other_question
            );

		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans("Clone$elem"), $langs->trans("ConfirmClone$elem", $object->ref), 'confirm_multiclone', $formquestion, 'yes', 1);

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

    static function multiCreateFromClone($object,$qty,$frequency,$socid)
	{
        global $db, $langs, $user;

        $db->begin();
        $compteur = 0;
        $error = 0;
        $objFrom = clone $object;
        while ($compteur<$qty){
            $compteur++;
            switch ($object->element) {
                case 'salary':
                    $salary = new Salary($db);
                    $salary->fetch($object->id);
                    //On vide l'id et la ref (comme le fais l'action confirm_clone du module salary)
                    //Ces champs seront rempli grace à la fonction create
                    $salary->id = $salary->ref = null;
                    //Nommage des nouveau Salaire en fonction de la quantité demandée
                    $salary->label = $langs->trans("CopyOf") . ' ' . $object->label . ' (' . $compteur . ')';
                    //On calcule et enregistre les nouvelles dates
                    $newdatestart = strtotime(date('Y-m-d', $salary->datesp) . ' +' . $frequency * $compteur. ' month');
                    $newdateend = strtotime(date('Y-m-d', $salary->dateep) . ' +' . $frequency * $compteur. ' month');
                    $salary->datesp = $newdatestart;
                    $salary->dateep = $newdateend;
                    //On défini le salarié concerné par le/les clones
                    $salary->fk_user = $socid;

                    //On crée le clone
                    $id_clone = $salary->create($user);
                    if ($id_clone <= 0) {
                        $error++;
                        break;
                    }
                    break;

                case 'chargesociales':
                    $charges = new ChargeSociales($db);
                    $charges->fetch($object->id);
                    //On vide l'id et la ref (comme le fais l'action confirm_clone du module salary)
                    //Ces champs seront rempli grace à la fonction create
                    $charges->id = $charges->ref = null;
                    //Nommage des nouveau Salaire en fonction de la quantité demandée
                    $charges->label = $langs->trans("CopyOf") . ' ' . $object->label . ' (' . $compteur . ')';
                    //On calcule et enregistre les nouvelles dates
                    $newdate_ech = strtotime(date('Y-m-d', $charges->date_ech) . ' +' . $frequency * $compteur . ' month');
                    $newdate_periode = strtotime(date('Y-m-d', $charges->periode) . ' +' . $frequency * $compteur . ' month');
                    $charges->date_ech = $newdate_ech;
                    $charges->periode = $newdate_periode;
                    //On défini le salarié concerné par le/les clones
                    $charges->fk_user = $socid;

                    //On crée le clone
                    $id_clone = $charges->create($user);
                    if ($id_clone <= 0) {
                        $error++;
                        break;
                    }
                    break;

                case 'propal':
                    $propal = $object;
                    $id_clone = $propal->createFromClone($user);
                    if ($id_clone > 0) {
                        $propal_clone = new Propal($db);
                        $propal_clone->fetch($id_clone);
                        //On calcule et enregistre les nouvelles dates
                        $newdate_date = strtotime(date('Y-m-d', $propal->date) . ' +' . $frequency * $compteur . ' month');
                        //Modification de la date de fin de validité (semble logique de l'accorder avec la date propale)
                        $newdate_fin = strtotime(date('Y-m-d', $propal->fin_validite) . ' +' . $frequency * $compteur . ' month');
                        $propal_clone->date = $newdate_date;
                        $propal_clone->fin_validite = $newdate_fin;
                        //Unset de la date de livraison car elle deviendrait incohérente en fonction de la fréquence et de la quantité de clone
                        $propal_clone->delivery_date = null;
                        //On défini le tiers concerné par le/les clones
                        $propal_clone->socid = $socid;

                        $res_update = $propal_clone->update($user);
                        if ($res_update<0) {
                            $error++;
                            break;
                        }
                    } else {
                        $error++;
                        break;
                    }
                    break;
                case 'facture':
                    //TODO Résoudre : pas de clone des lignes de facture
                    $facture = $object;
                    $id_clone = $facture->createFromClone($user);
                    if ($id_clone > 0) {
                        $facture_clone = new Facture($db);
                        $facture_clone->fetch($id_clone);
                        //On calcule et enregistre les nouvelles dates
                        $newdate_date = strtotime(date('Y-m-d', $facture->date) . ' +' . $frequency * $compteur . ' month');
                        $facture_clone->date = $newdate_date;
                        //On défini le tiers concerné par le/les clones
                        $facture_clone->socid = $socid;
                        //Cond et mode réglement ne sont pas cloné par la fonction creatFromClone
                        $facture_clone->cond_reglement_id = $facture->cond_reglement_id;
                        $facture_clone->mode_reglement_id = $facture->mode_reglement_id;
                        $facture_clone->date_lim_reglement = null;

                        $res_update = $facture_clone->update($user);
                        if ($res_update<0) {
                            $error++;
                            break;
                        }
                    } else {
                        $error++;
                        break;
                    }
                    break;
                case 'invoice_supplier':
                    break;
                case 'commande':
                    break;
                default:
                    break;
            }
        }

        if ($error>0){

            $db->rollback();
            setEventMessage("ErrorMulticlone", 'errors');
        } else {
            $db->commit();
            $db->close();

            header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id_clone);
            exit;
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

