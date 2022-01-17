<?php

class FormList
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/** @var FormListCol[]  */
	public $cols = array();

	/** @var array $params array of configuration */
	public $params = array();

	/**
	 * @var CommonObject $object
	 */
	public $object;

	/**
	 *  Constructor
	 *
	 *  @param      DoliDB		$db      Database handler
	 *  @param      string		$id      html id
	 */
	function __construct(&$db, $id)
	{
		$this->db = &$db;
		$this->id = $id;
	}


	/**
	 * @return string
	 */
	public function renderView()
	{
		global $hookmanager;

		$parameters=array();

		$result = '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';

		$reshook=$hookmanager->executeHooks('beforeListViewRender', $parameters, $this);    // Note that $action and $object may have been modified by hook

		if (empty($reshook)) {
			$result.= $hookmanager->resPrint;
			$result.= $this->render($this->paramSql, $this->params);
		} elseif ($reshook>0) {
			$result = $hookmanager->resPrint;
		}

		$reshook=$hookmanager->executeHooks('afterListViewRender', $parameters, $this);    // Note that $action and $object may have been modified by hook

		if (empty($reshook)) {
			$result.= $hookmanager->resPrint;
		} elseif ($reshook>0) {
			$result = $hookmanager->resPrint;
		}

		return $result;
	}

	/**
	 *   	Insert new column at relative position
	 *
	 *   	@param	string		$newColKey    	the new column key
	 *   	@param	string		$title    		the column title
	 *   	@param	string		$targetCol    	target column used to place the new column beside
	 *   	@param	bool		$insertAfterTarget    	insert before or after target column ?
	 *      @return	int         new rank on success and -1 on error
	 */
	function insertNewColumn($newColKey, $title, $targetCol = false, $insertAfterTarget = false)
	{
		// prepare wanted rank
		$rank = -1;

		// try to get rank from target column
		if (!empty($targetCol)) {
			$rank = $this->getColumnRank($targetCol);
			if ($rank>=0 && $insertAfterTarget) { $rank++; }
		}

		// error: no rank
		if ($rank<0) { return -1; }

		$this->params['title'] = array_slice($this->params['title'], 0, $rank, true) +
			array($newColKey => $title) +
			array_slice($this->params['title'], $rank, count($this->params['title']) - 1, true);

		return $rank;
	}

	/**
	 *   	move existing column at relative position
	 *
	 *   	@param	string		$colKey    	the new column key
	 *   	@param	string		$targetCol    	target column used to place the new column beside
	 *   	@param	bool		$insertAfterTarget    	insert before or after target column ?
	 *      @return	int         new rank on success and -1 on error
	 */
	function moveColumn($colKey, $targetCol = false, $insertAfterTarget = false)
	{
		// prepare wanted rank
		$rank = -1;

		if (!isset($this->params['title'][$colKey])) {
			return -1;
		}

		$title = $this->params['title'][$colKey];
		unset($this->params['title'][$colKey]);

		// try to get rank from target column
		if (!empty($targetCol)) {
			$rank = $this->getColumnRank($targetCol);
			if ($rank>=0 && $insertAfterTarget) { $rank++; }
		}

		// error: no rank
		if ($rank<0) { return -1; }

		$this->params['title'] = array_slice($this->params['title'], 0, $rank, true) +
			array($colKey => $title) +
			array_slice($this->params['title'], $rank, count($this->params['title']) - 1, true);

		return $rank;
	}

	/**
	 *   	get column position rank from column key
	 *
	 *   	@param	string		$colKey    		the column key
	 *      @return	int         rank on success and -1 on error
	 */
	function getColumnRank($colKey)
	{
		if (!isset($this->params['title'][$colKey])) return -1;
		return  array_search($colKey, array_keys($this->params['title']));
	}

	/**
	 * @param $objetClassName
	 * @param $fk_object
	 * @return bool
	 */
	public static function getObjectFromCache($objetClassName, $fk_object)
	{
		global $db, $TListViewObjectCache;

		if (!class_exists($objetClassName)) {
			// TODO : Add error log here
			return false;
		}

		if (empty($TListViewObjectCache[$objetClassName][$fk_object])) {
			$object = new $objetClassName($db);
			if ($object->fetch($fk_object, false) <= 0) {
				return false;
			}

			$TListViewObjectCache[$objetClassName][$fk_object] = $object;
		} else {
			$object = $TListViewObjectCache[$objetClassName][$fk_object];
		}

		return $object;
	}

	/**
	 * @param $objetClassName
	 * @param $fk_object
	 * @return bool
	 */
	public static function clearObjectFromCache($objetClassName, $fk_object)
	{
		global $TListViewObjectCache;

		if (!class_exists($objetClassName)) {
			// TODO : Add error log here
			return false;
		}

		if (isset($TListViewObjectCache[$objetClassName][$fk_object])) {
			$TListViewObjectCache[$objetClassName][$fk_object] = false; // force clear memory
			unset($TListViewObjectCache[$objetClassName][$fk_object]);
		}

		return true;
	}

	/**
	 * Return HTML string to show a field into a page
	 *
	 * @param $object CommonObject
	 * @param string $key Key of attribute
	 * @param string $moreparam To add more parameters on html input tag
	 * @param string $keysuffix Prefix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param string $keyprefix Suffix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param mixed $morecss Value for css to define size. May also be a numeric.
	 * @return string
	 */
	static public function showOutputFieldQuick($object, $key, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = '')
	{

		if ($key == 'status' && method_exists($object, 'getLibStatut')) {
			return $object->getLibStatut(2); // to fix dolibarr using 3 instead of 2
		}

		return $object->showOutputField($object->fields[$key], $key, $object->{$key}, $moreparam, $keysuffix, $keyprefix, $morecss);
	}

	/**
	 * @param $objetClassName
	 * @param $key
	 * @param int $fk_object
	 * @param string $val
	 * @return string
	 */
	public static function evalGetObjectOutputField($objetClassName, $key, $fk_object = 0, $val = '')
	{
		$object = self::getObjectFromCache($objetClassName, $fk_object);
		if (!$object && $fk_object>0) { return 'error'; }
		if (!$object) { return ''; }

		$methodVariable = array($object, 'showOutputFieldQuick');
		if (is_callable($methodVariable)) {
			return $object->showOutputFieldQuick($key);
		} else {
			return self::showOutputFieldQuick($object, $key);
		}
	}

	/**
	 * @param $objetClassName
	 * @param $key
	 * @param int $fk_object
	 * @return string
	 */
	public static function evalGetObjectExtrafieldOutputField($objetClassName, $key, $fk_object = 0)
	{
		global $extrafields;

		$object = self::getObjectFromCache($objetClassName, $fk_object);
		if (!$object) {return 'error';}

		$value = $object->array_options["options_".$key];

		return  $extrafields->showOutputField($key, $value);
	}

	public static function evalGetEntity($val = '')
	{
		global $db, $TEntityCache;

		if (empty($val)) {
			return '';
		}
		$val = intval($val);

		if (empty($TEntityCache[$val])) {
			if (!class_exists('DaoMulticompany')) {
				return '';
			}

			$daoMulticompany = new DaoMulticompany($db);
			if ($daoMulticompany->fetch(intval($val)) <= 0) {
				return '';
			}

			$TEntityCache[$val] = $daoMulticompany;
		} else {
			$daoMulticompany = $TEntityCache[$val];
		}

		return  htmlentities($daoMulticompany->name);
	}
}


/**
 * This class help to create col for class formList
 */
class FormListCol
{
	/** @var Translate */
	public $langs;

	/** @var string $colKey the column key used to identify col */
	public $colKey;

	/** @var string|false $nameText  */
	public $nameText = false;

	/** @var string $helpText  */
	public $helpText = '';

	/** @var bool|string set this var to override field output will override $fieldInputOverride and $fieldOutputOverride too */
	public $fieldOverride = false;

	/** @var bool|string set this var to override field input */
	public $fieldInputOverride = false;

	/** @var bool|string set this var to override field output */
	public $fieldOutputOverride = false;

	/** @var int $rank  */
	public $rank = 0;

	/**
	 * @var string $errors
	 */
	public $errors = array();

	public $enabled = 1;

	public $cssClass = '';

	/**
	 * Constructor
	 *
	 * @param string $colKey the conf key used in database
	 */
	public function __construct($colKey)
	{
		global $langs;
		$this->langs = $langs;
		$this->colKey = $colKey;
	}


	/**
	 * Get help text or generate it
	 * @return string
	 */
	public function getHelpText()
	{
		if (!empty($this->helpText)) { return $this->langs->trans($this->helpText); }
		return '';
	}

	/**
	 * Get field name text or generate it
	 * @return string
	 */
	public function getNameText()
	{
		if (!empty($this->nameText)) { return $this->langs->trans($this->nameText); }
		return '';
	}

	/**
	 * generate input field
	 * @return bool|string
	 */
	public function generateSearchField()
	{
		global $conf;

		if (!empty($this->fieldOverride)) {
			return $this->fieldOverride;
		}

		if (!empty($this->fieldInputOverride)) {
			return $this->fieldInputOverride;
		}

		$this->fieldAttr['name'] = $this->confKey;
		$this->fieldAttr['id'] = 'setup-'.$this->confKey;
		$this->fieldAttr['value'] = $this->fieldValue;

		$out = '';


		return $out;
	}

	/**
	 * Add error
	 * @param array|string $errors the error text
	 * @return null
	 */
	public function setErrors($errors)
	{
		if (is_array($errors)) {
			if (!empty($errors)) {
				foreach ($errors as $error) {
					$this->setErrors($error);
				}
			}
		} elseif (!empty($errors)) {
			$this->errors[] = $errors;
		}
	}
}
