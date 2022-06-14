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


// TODO separate class in multiple files to follow php directive

// todo : shift click or ctrl click for multiselect https://codepen.io/astrotim/pen/OMBPqd


/**
 * This class help you create setup render
 */
class FormList
{

	use traitCommonHtmlItemTools;

	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/** @var FormListColumns[]  */
	public $columns = array();

	/** @var FormListRow[] */
	public $bodyRows = array();

	/** @var FormListRow[] */
	public $headerRows = array();

	/** @var FormListRow[] */
	public $footerRows = array();

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
	 * this is an html string display in output form
	 * @var string
	 */
	public $htmlInOutputForm = '';

	/**
	 * this is an html class used for table container
	 * You can use div-table-responsive-no-min if you dont need reserved height for your table
	 * @var string
	 */
	public $tableContainerClass = 'div-table-responsive';

	/**
	 * this is an html class used for table
	 * @var string
	 */
	public $tableClass = 'tagtable nobottomiftotal liste';

	/**
	 * this is an html string display after output form
	 * @var string
	 */
	public $htmlAfterOutputForm = '';

	//  /**
	//   * this is an html string display on buttons zone
	//   * @var string
	//   */
	//  public $htmlOutputMoreButton = '';


	/**
	 * an list of hidden inputs used only in edit mode
	 * @var array
	 */
	public $formHiddenInputs = array();

	/**
	 * @var array $search
	 */
	public $search = array();

	/**
	 * @var string $sortField
	 */
	public $sortField = '';

	/**
	 * @var string for sort order
	 */
	public $sortOrder = 'ASC';


	/**
	 * TODO : convert it to an array
	 * an url params list to add on generated link for action like sorting or page navigation
	 * @var string $param
	 */
	public $param = '';



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

		$this->attributes['action'] = $_SERVER["PHP_SELF"];
		$this->attributes['method'] = 'POST';

		$this->formHiddenInputs['token'] = newToken();
		$this->formHiddenInputs['action'] = 'update';


		if ($outputLangs) {
			$this->langs = $outputLangs;
		} else {
			$this->langs = $langs;
		}
	}

	/**
	 * @return void
	 */
	public function clearInputs()
	{
		$this->clearSearch();
	}

	/**
	 * @return void
	 */
	public function clearSearch()
	{
		$this->search = array();
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
		$reshook = $hookmanager->executeHooks('formListBeforeGenerateOutput', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		if ($reshook > 0) {
			return $hookmanager->resPrint;
		} else {
			$out = '<!-- Start generateOutput from FormList class  -->';
			$out.= $this->htmlBeforeOutputForm;

			$out.= '<form ' . $this->generateAttributesString() . ' >';

			// generate hidden values from $this->formHiddenInputs
			if (!empty($this->formHiddenInputs) && is_array($this->formHiddenInputs)) {
				foreach ($this->formHiddenInputs as $hiddenKey => $hiddenValue) {
					$out.= '<input type="hidden" name="'.dol_escape_htmltag($hiddenKey).'" value="' . dol_escape_htmltag($hiddenValue) . '">';
				}
			}

			$out.= $this->htmlInOutputForm;

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
		$reshook = $hookmanager->executeHooks('formListBeforeGenerateTableOutput', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		if ($reshook > 0) {
			return $hookmanager->resPrint;
		} else {
			$out = '<div class="'.dol_escape_htmltag($this->tableContainerClass).'">';
			$out.= '<table class="'.dol_escape_htmltag($this->tableClass).'">';


			// Sort columns before render
			$this->sortingColumns();

			if (!empty($this->headerRows)) {
				$out.= '<thead>';
				foreach ($this->headerRows as $row) {
					$out.= $row->generateOutput($this->columns);
				}
				$out.= '</thead>';
			}

			if (!empty($this->bodyRows)) {
				$out.= '<tbody>';
				foreach ($this->bodyRows as $row) {
					$out.= $row->generateOutput($this->columns);
				}
				$out.= '</tbody>';
			}

			if (!empty($this->footerRows)) {
				$out.= '<tfooter>';
				foreach ($this->footerRows as $row) {
					$out.= $row->generateOutput($this->columns);
				}
				$out.= '</tfooter>';
			}


			$out.= '</table>';
			$out.= '</div>';
			return $out;
		}
	}

	/**
	 * generateLineOutput
	 *
	 * @param 	$lineKey	the line key
	 * @return 	string		the html output
	 */
	public function generateLineOutput($lineKey)
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
	 * Create a new row item
	 *
	 * @param string $rowKey    the id of row
	 * @param string $target    target table type header, boby, footer
	 * @return FormListRow the new row item created
	 */
	public function newRow($rowKey, $target = 'body')
	{
		$item = new FormListRow($rowKey);

		if ($target == 'header') {
			$this->headerRows[$item->confKey] = $item;
		} elseif ($target == 'footer') {
			$this->footerRows[$item->confKey] = $item;
		} else {
			$this->bodyRows[$item->confKey] = $item;
		}

		return $item;
	}

	/**
	 * Create a new column
	 * the tagret is useful with hooks : that allow externals modules to add setup items on good place
	 *
	 * @param string $columnKey the conf key used in database
	 * @param int $rank the rank of column
	 * @param bool $targetColKey target item used to place the new col beside
	 * @param bool $insertAfterTarget insert before or after target col ?
	 * @return FormListColumns the new setup item created
	 */
	public function newColumn($columnKey, $rank = 0, $targetColKey = false, $insertAfterTarget = false)
	{
		$item = new FormListColumns($columnKey);

		// set item rank if not defined as last item
		$item->rank = intval($rank);
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
	public function sortingColumns()
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
	 * usefully for external modules
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

	/**
	 * add hidden imputs
	 *
	 * @param array $inputs an array of input
	 * @return false|void
	 */
	public function setFormHiddenInputs($inputs)
	{

		if (!is_array($inputs) || empty($inputs)) {
			return false;
		}

		foreach ($inputs as $key => $value) {
			$this->setFormHiddenInput($key, $value);
		}
	}

	/**
	 * add hidden imput
	 *
	 * @param $name the attribute name of input
	 * @param $value of hidden input
	 * @return false|void
	 */
	public function setFormHiddenInput($name, $value)
	{

		if (empty($name)) {
			return false;
		}

		$this->formHiddenInputs[$name] = $value;
	}
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


/**
 * This class help to create item for class FormList
 */
class FormListRow
{
	use traitCommonHtmlItemTools;

	/** @var string $rowKey  */
	public $rowKey = '';

	/**
	 * @var FormListCell[]
	 */
	public $cells = array();


	/** @var bool|string set this var to override output */
	public $outputOverride = false;

	/**
	 * @param string $col the column key
	 * @return FormListCell
	 */
	public function newCell($col)
	{
		$item = new FormListCell($col);


		$this->cells[$col] = $item;
		return $this->cells[$col];
	}

	/**
	 * Constructor
	 *
	 * @param string $rowKey the row identifier
	 */
	public function __construct($rowKey)
	{
		if (empty($rowKey)) return false;

		$this->rowKey = $rowKey;
	}

	/**
	 * @param FormListColumns[] $columns the list of defined columns of table to export
	 * @return string
	 */
	public function generateOutput($columns)
	{
		$out='';
		if (!empty($columns) && is_array($columns)) {
			foreach ($columns as $colKey => $column) {
				if (isset($this->cells[$colKey])) {
					$cell = $this->cells[$colKey];
				} else {
					$cell = new FormListCell($colKey);
				}

				$cell->setAttribute('data-col', $colKey);
				$out.= $cell->generateOutput();
			}
		}

		return $out;
	}
}

/**
 * This class help to create cell item for class FormList
 */
class FormListCell
{
	use traitCommonHtmlItemTools;

	/** @var string $colKey  */
	public $colKey = '';

	public $cellType = 'td';

	/** @var string set this var to override output */
	public $outputOverride = false;


	/**
	 * @return string
	 */
	public function generateOutput()
	{
		if ($this->outputOverride) {
			return  $this->outputOverride;
		}

		$out='<'.$this->cellType . ' ' . $this->generateAttributesString() . '>';

		$out.='</'.$this->cellType . '>';

		return $out;
	}

	/**
	 * Constructor
	 *
	 * @param string $colKey the row identifier
	 */
	public function __construct($colKey)
	{
		if (empty($colKey)) return false;

		$this->colKey = $colKey;
	}
}

/**
 * add some html tools and format for class
 */
trait traitCommonHtmlItemTools
{


	/**
	 * an array of attributes used in this html element
	 * @var array
	 */
	public $attributes = array();

	/**
	 * Generate an attributes string form an input array
	 *
	 * @return 	string					attribute string
	 */
	public function generateAttributesString()
	{
		$attrs = array();
		if (is_array($this->attributes)) {
			foreach ($this->attributes as $attribute => $value) {
				if (is_array($value) || is_object($value)) {
					continue;
				}
				$attrs[] = $attribute.'="'.dol_escape_htmltag($value).'"';
			}
		}

		return !empty($attrs)?implode(' ', $attrs):'';
	}

	/**
	 * Add attribute value to curent html item
	 * @param $key the attribute key
	 * @param $value the attribute value
	 * @return void
	 */
	public function setAttribute($key, $value)
	{
		if (empty($key)) {
			return false;
		}

		$this->attributes[$key] = $value;
	}


	/**
	 * @param $key the attribute key
	 * @return void
	 */
	public function delAttribute($key)
	{
		if (isset($this->attributes[$key])) {
			unset($this->attributes[$key]);
		}
	}
}



/**
 * Pour plus tard
 */
class CommonList extends FormList
{

	/**
	 * list of selected rows ids
	 * @var array
	 */
	public $toSelect = array();

	/**
	 * @var array list of mass actions
	 */
	public $arrayOfMassActions = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 * @param Translate $outputLangs if needed can use another lang
	 */
	public function __construct($db, $outputLangs = false)
	{
		$this->attributes['id'] = 'searchFormList';

		parent::__construct($db, $outputLangs);
	}

	/**
	 * @return void
	 */
	public function clearInputs()
	{
		$this->clearMassAction();
	}

	/**
	 * @return void
	 */
	public function clearMassAction()
	{
		$this->arrayOfMassActions = array();
	}

	//  /**
	//   * @param $object
	//   * @param $tablePrefix table prefix used (t in most case)
	//   * @param bool $isDefault set this new object as default or primary element of list (for default sort values, checkBoxes etc...)
	//   * @return void
	//   */
	//  public function addCommonObject($object, $tablePrefix, $isDefault = true){
	//
	//  }
}
