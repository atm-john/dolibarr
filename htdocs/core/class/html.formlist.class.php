<?php
/* Copyright (C) 2021  John BOTELLA    <john.botella@atm-consulting.fr>
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
 * This class help you create setup render
 */
class FormList
{

	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/** @var FormListColumns[]  */
	public $columns = array();

	/** @var FormListRow[] */
	public $rows = array();

	/** @var Translate */
	public $langs;

	/** @var Form */
	public $form;

	/** @var int */
	protected $maxColumnRank;

	/**
	 * this is an html string display before output form
	 * @var string
	 */
	public $htmlBeforeOutputForm = '';

	/**
	 * this is an html string display after output form
	 * @var string
	 */
	public $htmlAfterOutputForm = '';

	/**
	 * this is an html string display on buttons zone
	 * @var string
	 */
	public $htmlOutputMoreButton = '';


	/**
	 *
	 * @var array
	 */
	public $formAttributes = array(
		'action' => '', // set in __construct
		'method' => 'POST'
	);

	/**
	 * an list of hidden inputs used only in edit mode
	 * @var array
	 */
	public $formHiddenInputs = array();


	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 * @param Translate $outputLangs if needed can use another lang
	 */
	public function __construct($db, $outputLangs = false)
	{
		global $langs;
		$this->db = $db;
		$this->form = new Form($this->db);
		$this->formAttributes['action'] = $_SERVER["PHP_SELF"];

		$this->formHiddenInputs['token'] = newToken();
		$this->formHiddenInputs['action'] = 'update';


		if ($outputLangs) {
			$this->langs = $outputLangs;
		} else {
			$this->langs = $langs;
		}
	}

	// TODO : Créer un trait ?
	/**
	 * Generate an attributes string form an input array
	 *
	 * @param 	array 	$attributes 	an array of attributes keys and values,
	 * @return 	string					attribute string
	 */
	static public function generateAttributesStringFromArray($attributes)
	{
		$Aattr = array();
		if (is_array($attributes)) {
			foreach ($attributes as $attribute => $value) {
				if (is_array($value) || is_object($value)) {
					continue;
				}
				$Aattr[] = $attribute.'="'.dol_escape_htmltag($value).'"';
			}
		}

		return !empty($Aattr)?implode(' ', $Aattr):'';
	}


	/**
	 * generateOutput
	 *
	 * @return 	string				html output
	 */
	public function generateOutput()
	{
		global $hookmanager, $action;
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

		$parameters = array();
		$reshook = $hookmanager->executeHooks('formSetupBeforeGenerateOutput', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		if ($reshook > 0) {
			return $hookmanager->resPrint;
		} else {
			$out = '<!-- Start generateOutput from FormSetup class  -->';
			$out.= $this->htmlBeforeOutputForm;

			$out.= '<form ' . self::generateAttributesStringFromArray($this->formAttributes) . ' >';

			// generate hidden values from $this->formHiddenInputs
			if (!empty($this->formHiddenInputs) && is_array($this->formHiddenInputs)) {
				foreach ($this->formHiddenInputs as $hiddenKey => $hiddenValue) {
					$out.= '<input type="hidden" name="'.dol_escape_htmltag($hiddenKey).'" value="' . dol_escape_htmltag($hiddenValue) . '">';
				}
			}

			// generate output table
			$out .= $this->generateTableOutput();

			$reshook = $hookmanager->executeHooks('formListAfterGenerateTableOutput', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
			if ($reshook < 0) {
				setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
			}

			if ($reshook > 0) {
				return $hookmanager->resPrint;
			} else {
				$out .= '</form>';
				$out.= $this->htmlAfterOutputForm;
			}

			return $out;
		}
	}

	/**
	 * generateTableOutput
	 *
	 * @param 	bool 	$editMode 	true will display output on edit mod
	 * @return 	string				html output
	 */
	public function generateTableOutput($editMode = false)
	{
		global $hookmanager, $action;
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

		$parameters = array(
			'editMode' => $editMode
		);
		$reshook = $hookmanager->executeHooks('formSetupBeforeGenerateTableOutput', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		if ($reshook > 0) {
			return $hookmanager->resPrint;
		} else {
			$out = '<table class="noborder centpercent">';
			$out .= '<thead>';
			$out .= '<tr class="liste_titre">';
			$out .= '	<td>' . $this->langs->trans("Parameter") . '</td>';
			$out .= '	<td>' . $this->langs->trans("Value") . '</td>';
			$out .= '</tr>';
			$out .= '</thead>';

			// Sort items before render
			$this->sortingItems();

			$out .= '<tbody>';
			foreach ($this->columns as $item) {
				$out .= $this->generateLineOutput($item, $editMode);
			}
			$out .= '</tbody>';

			$out .= '</table>';
			return $out;
		}
	}

	/**
	 * generateLineOutput
	 *
	 * @param 	FormListColumns $item     the setup item
	 * @param 	bool            $editMode Display as edit mod
	 * @return 	string 						the html output for an setup item
	 */
	public function generateLineOutput($item, $editMode = false)
	{

		$out = '';
		if ($item->enabled==1) {
			$trClass = 'oddeven';
			if ($item->getType() == 'title') {
				$trClass = 'liste_titre';
			}

			$this->setupNotEmpty++;
			$out.= '<tr class="'.$trClass.'">';

			$out.= '<td class="col-setup-title">';
			$out.= '<span id="helplink'.$item->confKey.'" class="spanforparamtooltip">';
			$out.= $this->form->textwithpicto($item->getNameText(), $item->getHelpText(), 1, 'info', '', 0, 3, 'tootips'.$item->confKey);
			$out.= '</span>';
			$out.= '</td>';

			$out.= '<td>';

			if ($editMode) {
				$out.= $item->generateInputField();
			} else {
				$out.= $item->generateOutputField();
			}

			if (!empty($item->errors)) {
				// TODO : move set event message in a methode to be called by cards not by this class
				setEventMessages(null, $item->errors, 'errors');
			}

			$out.= '</td>';
			$out.= '</tr>';
		}

		return $out;
	}




	/**
	 * Create a new column
	 * the tagret is useful with hooks : that allow externals modules to add setup items on good place
	 *
	 * @param string $columnKey            the conf key used in database
	 * @param string $targetColKey      target item used to place the new col beside
	 * @param bool   $insertAfterTarget insert before or after target col ?
	 * @return FormListColumns the new setup item created
	 */
	public function newColumn($columnKey, $targetColKey = false, $insertAfterTarget = false)
	{
		$item = new FormListColumns($columnKey);

		// set item rank if not defined as last item
		if (empty($item->rank)) {
			$item->rank = $this->getCurrentColumnMaxRank() + 1;
			$this->setColumnMaxRank($item->rank); // set new max rank if needed
		}

		// try to get rank from target column, this will override item->rank
		if (!empty($targetColKey)) {
			if (isset($this->columns[$targetColKey])) {
				$targetItem = $this->columns[$targetColKey];
				$item->rank = $targetItem->rank; // $targetItem->rank will be increase after
				if ($targetItem->rank >= 0 && $insertAfterTarget) {
					$item->rank++;
				}
			}

			// calc new rank for each item to make place for new item
			foreach ($this->columns as $fItem) {
				if ($item->rank <= $fItem->rank) {
					$fItem->rank = $fItem->rank + 1;
					$this->setColumnMaxRank($fItem->rank); // set new max rank if needed
				}
			}
		}

		$this->columns[$item->confKey] = $item;
		return $this->columns[$item->confKey];
	}

	/**
	 * Sort items according to rank
	 *
	 * @return bool
	 */
	public function sortingItems()
	{
		// Sorting
		return uasort($this->columns, array($this, 'colSort'));
	}

	/**
	 * getCurrentItemMaxRank
	 *
	 * @param bool $cache To use cache or not
	 * @return int
	 */
	public function getCurrentColumnMaxRank($cache = true)
	{
		if (empty($this->columns)) {
			return 0;
		}

		if ($cache && $this->maxColumnRank > 0) {
			return $this->maxColumnRank;
		}

		$this->maxColumnRank = 0;
		foreach ($this->columns as $item) {
			$this->maxColumnRank = max($this->maxColumnRank, $item->rank);
		}

		return $this->maxColumnRank;
	}


	/**
	 * set new max rank if needed
	 *
	 * @param 	int 		$rank 	the item rank
	 * @return 	int|void			new max rank
	 */
	public function setColumnMaxRank($rank)
	{
		$this->maxColumnRank = max($this->maxColumnRank, $rank);
	}


	/**
	 * get item position rank from item key
	 *
	 * @param	string $colId the item key
	 * @return	int         				rank on success and -1 on error
	 */
	public function getColRank($colId)
	{
		if (!isset($this->columns[$colId]->rank)) {
			return -1;
		}
		return  $this->columns[$colId]->rank;
	}


	/**
	 *  uasort callback function to Sort params cols
	 *
	 *  @param	FormListColumns $a FormListCol item
	 *  @param	FormListColumns $b FormListCol item
	 *  @return	int					Return compare result
	 */
	public function colSort(FormListColumns $a, FormListColumns $b)
	{
		if (empty($a->rank)) {
			$a->rank = 0;
		}
		if (empty($b->rank)) {
			$b->rank = 0;
		}
		if ($a->rank == $b->rank) {
			return 0;
		}
		return ($a->rank < $b->rank) ? -1 : 1;
	}
}


/**
 * This class help to create item for class FormList
 */
class FormListRow
{
	/** @var string $key  */
	public $key = '';
}

/**
 * This class help to create col for class FormList
 */
class FormListColumns
{
	/** @var string $key  */
	public $key = '';

	/** @var int $rank  */
	public $rank = 0;

	/** @var float $sum of rows of this col  */
	public $sum = 0;

	/**
	 * @var string $errors
	 */
	public $errors = array();

	/**
	 * @var bool
	 */
	public $enabled = true;

	/**
	 * @var bool
	 */
	public $selected = false;

	/**
	 * Constructor
	 *
	 * @param string $key the column identifier
	 */
	public function __construct($key)
	{
		$this->key = $key;
	}
}
