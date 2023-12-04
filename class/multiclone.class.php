<?php

class multiclone
{
	public static function getFormConfirmClone($object)
	{
		dol_include_once('/core/class/html.form.class.php');
		global $langs, $db, $conf;
		$langs->loadLangs(array('multiclone@multiclone', 'salaries'));
		$form = new Form($db);

			$elem = $object->element;
			$filter = 's.client IN(1,'.($object->element === 'propal' ? '2,' : '').'3)';
			if (version_compare(DOL_VERSION, '18', '>=')) $filter = '(s.client:IN:1,'.($object->element === 'propal' ? '2,' : '').'3)';

            if ($elem == 'salary' || $elem == 'chargesociales'){
                $other_question = array('type' => 'other', 'name' => 'userid', 'label' => $langs->trans("SelectUser"), 'value' => $form->select_dolusers($object->fk_user, 'userid', 1));
            } else {
                $other_question = array('type' => 'other', 'name' => 'socid', 'label' => $langs->trans("SelectThirdParty"), 'value' => $form->select_company($object->socid, 'socid', $filter, '', 0, 0, array(), 0, 'minwidth300'));
            }
			$formquestion = array(
				array('type' => 'other', 'name' => 'cloneqty', 'label' => $langs->trans("CloneQty"), 'value' => '<input type="number" style="width: 100px;" id="cloneqty" step="1" min="1" max="' . getDolGlobalInt('MULTICLONE_MAX_AUTHORIZED_CLONE_VALUE').'">'),
				array('type' => 'other', 'name' => 'frequency', 'label' => $langs->trans("CloneFrequency"), 'value' => '<input type="number" style="width: 100px;" id="frequency" step="1" min="1">'),
                $other_question
            );

        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans("Clone"), $langs->trans("ConfirmClone$elem", $object->ref), 'confirm_multiclone', $formquestion, 'yes', 1);

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

		if($object->element == 'facture' && getDolGlobalInt('MULTICLONE_VALIDATE_INVOICE')) $object->validate($user);
		else if(($object->element == 'propal' && getDolGlobalInt('MULTICLONE_VALIDATE_PROPAL')) || ($object->element == 'commande' && getDolGlobalInt('MULTICLONE_VALIDATE_ORDER'))) $object->valid($user);

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

    /**
     * @param Object $object
     * @param int $qty
     * @param int $frequency
     * @param int $socid
     * @return void
     * @throws Exception
     */
    static function multiCreateFromClone($object, $qty, $frequency, $socid)
	{
        global $db, $langs, $user, $conf;

        $db->begin();
        $compteur = 0;
        $error = 0;
        $langs->load('multiclone@multiclone');

        if (getDolGlobalInt('MULTICLONE_MAX_AUTHORIZED_CLONE_VALUE') && $qty > getDolGlobalInt('MULTICLONE_MAX_AUTHORIZED_CLONE_VALUE')) {
            setEventMessage($langs->trans('MulticloneMaxAuthorizedValueReached', $qty, getDolGlobalInt('MULTICLONE_MAX_AUTHORIZED_CLONE_VALUE')), 'errors');
            header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
            exit;
        }

        // Récupération des dates devant être clonées en fonction de l'objet
        $TDatesToClone = self::getDateToClone($object);

        // Contrainte d'unicité sur la référence fournisseur (la référence fournisseur doit être unique si même tiers et même entité)
        if ($object->element == 'invoice_supplier') $ref_supplier = $object->ref_supplier;

        while ($compteur<$qty){
            $compteur++;
            switch ($object->element) {
                case 'propal':
                    $propal = $object;
                    $id_clone = $propal->createFromClone($user);
                    if ($id_clone > 0) {
                        $propal_clone = new Propal($db);
                        $res = $propal_clone->fetch($id_clone);
                        if ($res < 0 ) {
                            $error++;
                            break;
                        }

                        $TNewDates = self::calcNewDate($TDatesToClone, $frequency, $compteur);

                        $propal_clone->date = $TNewDates[0];

                        //Unset de la date de livraison (la spec ne demande pas que cette date soit prise en compte dans le clone)
                        $propal_clone->delivery_date = null;

                        //On définit le tiers concerné par le/les clones
                        $propal_clone->socid = $socid;

                        if (getDolGlobalInt('MULTICLONE_VALIDATE_PROPAL')) $propal_clone->valid($user);

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

                case 'commande':
                    $order = $object;
                    $id_clone = $order->createFromClone($user);
                    if ($id_clone > 0) {
                        $order_clone = new Commande($db);
                        $res = $order_clone->fetch($id_clone);
                        if ($res < 0 ) {
                            $error++;
                            break;
                        }

                        $TNewDates = self::calcNewDate($TDatesToClone, $frequency, $compteur);

                        $order_clone->date_commande = $TNewDates[0];
                        $order_clone->delivery_date = $TNewDates[1] ?? null;

                        //On définit le tiers concerné par le/les clones
                        $order_clone->socid = $socid;

                        if (getDolGlobalInt('MULTICLONE_VALIDATE_ORDER')) $order_clone->valid($user);

                        $res_update = $order_clone->update($user);
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
                    $facture = $object;
                    $id_clone = $facture->createFromClone($user, $object->id);
                    if ($id_clone > 0) {
                        $facture_clone = new Facture($db);
                        $res = $facture_clone->fetch($id_clone);
                        if ($res < 0 ) {
                            $error++;
                            break;
                        }

                        $TNewDates = self::calcNewDate($TDatesToClone, $frequency, $compteur);

                        $facture_clone->date = $TNewDates[0];
                        $facture_clone->date_lim_reglement = $TNewDates[1];

                        //On définit le tiers concerné par le/les clones
                        $facture_clone->socid = $socid;
                        //Conditions et mode de règlement ne sont pas clonés par la fonction CreateFromClone
                        $facture_clone->cond_reglement_id = $facture->cond_reglement_id;
                        $facture_clone->mode_reglement_id = $facture->mode_reglement_id;

                        if(getDolGlobalInt('MULTICLONE_VALIDATE_INVOICE')) $facture_clone->validate($user);

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
                    $supplier_invoice = $object;
                    $supplier_invoice->ref_supplier = $ref_supplier.'-'.$compteur;
                    $id_clone = $supplier_invoice->createFromClone($user, $object->id);

                    if ($id_clone > 0) {
                        $supplier_invoice_clone = new FactureFournisseur($db);
                        $res = $supplier_invoice_clone->fetch($id_clone);
                        if ($res < 0 ) {
                            $error++;
                            break;
                        }

                        $TNewDates = self::calcNewDate($TDatesToClone, $frequency, $compteur);

                        $supplier_invoice_clone->date = $TNewDates[0];

                        //On définit le tiers concerné par le/les clones
                        $supplier_invoice_clone->socid = $socid;

                        $res_update = $supplier_invoice_clone->update($user);
                        if ($res_update<0) {
                            $error++;
                            break;
                        }
                    } else {
                        $error++;
                        break;
                    }
                    break;

                case 'salary':
                    $salary = new Salary($db);
                    $res = $salary->fetch($object->id);
                    if ($res < 0 ) {
                        $error++;
                        break;
                    }

                    //On vide l'id et la ref (comme le fait l'action confirm_clone du module salary)
                    //Ces champs seront remplis grâce à la fonction create
                    $salary->id = $salary->ref = null;
                    //Nommage des nouveaux salaires en fonction de la quantité demandée
                    $salary->label = $langs->trans("CopyOf") . ' ' . $object->label . ' (' . $compteur . ')';

                    $TNewDates = self::calcNewDate($TDatesToClone, $frequency, $compteur);

                    $salary->datesp = $TNewDates[0];
                    $salary->dateep = $TNewDates[1];

                    //On définit le salarié concerné par le/les clones
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
                    $res = $charges->fetch($object->id);
                    if ($res < 0 ) {
                        $error++;
                        break;
                    }

                    //On vide l'id et la ref (comme le fait l'action confirm_clone du module chargesociales)
                    //Ces champs seront remplis grâce à la fonction create
                    $charges->id = $charges->ref = null;
                    //Nommage des nouvelles charges sociales en fonction de la quantité demandée
                    $charges->label = $object->label;

                    $TNewDates = self::calcNewDate($TDatesToClone, $frequency, $compteur);

                    $charges->date_ech = $TNewDates[0];
                    $charges->periode = $TNewDates[1];

                    //On définit le salarié concerné par le/les clones
                    $charges->fk_user = $socid;

                    //On crée le clone
                    $id_clone = $charges->create($user);
                    if ($id_clone <= 0) {
                        $error++;
                        break;
                    }
                    break;

                default:
                    break;
            }
        }

        if ($error>0){
            $db->rollback();
            setEventMessage($langs->trans("ErrorMulticlone", $db->lasterror()), 'errors');
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

    /**
     * @param array $TDatesToClone
     * @param int $frequency
     * @param int $compteur
     * @return array
     * @throws Exception
     */
    static function calcNewDate($TDatesToClone, $frequency, $compteur)
    {
        //On calcule les nouvelles dates
        if (! empty($TDatesToClone)) {

            $TNewDates = array();
            foreach ($TDatesToClone as $i => $dateToClone) {
				if (!empty($dateToClone)) {
					$object_date_origin[$i] = date('Y-m-d', intval($dateToClone));
					$last_day_of_this_month[$i] = date("Y-m-t", intval($dateToClone));

					// Utilisation de l'objet DateTime plus performant
					$object_date_to_clone[$i] = new DateTime($object_date_origin[$i]);
					$object_last_day_of_month[$i] = new DateTime($last_day_of_this_month[$i]);

					if ($object_date_to_clone[$i] == $object_last_day_of_month[$i]) $object_newdate = $object_date_to_clone[$i]->modify('last day of +' . $frequency * $compteur. ' month');
					else $object_newdate = $object_date_to_clone[$i]->modify('+' . $frequency * $compteur. ' month');

					$TNewDates[] = $object_newdate->getTimestamp();
				}
            }
        }

        return $TNewDates;
    }

    /**
     * @param Object $object
     * @return array
     */
    static function getDateToClone($object)
    {
        $TDatesToClone = array();

        switch ($object->element) {
            case 'propal' :
                $TDatesToClone['origin_date'] = $object->date;
                break;

            case 'commande':
                $TDatesToClone['origin_date'] = $object->date;
                $TDatesToClone['origin_delivery_date'] = $object->delivery_date;
                break;

            case 'facture' :
                $TDatesToClone['origin_date'] = $object->date;
                $TDatesToClone['origin_date_lim_reglement'] = $object->date_lim_reglement;
                break;

            case 'invoice_supplier':
                $TDatesToClone['origin_date'] = $object->date;
                break;

            case 'salary' :
                $TDatesToClone['origin_datesp'] = $object->datesp;
                $TDatesToClone['origin_dateep'] = $object->dateep;
                break;

            case 'chargesociales' :
                $TDatesToClone['origin_dateech'] = $object->date_ech;
                $TDatesToClone['origin_period'] = $object->periode;
                break;

            default:
                break;
        }

        return $TDatesToClone;
    }
}

