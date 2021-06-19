<?php

require_once __DIR__ . '/webnotification.class.php';

/**
 * Class for ActionCommReminderSubscription
 */
class ActionCommReminderSubscription extends CommonObject
{
	/**
	 * @var DoliDb		Database handler (result of a new DoliDB)
	 */
	public $db;

	/**
	 * @var int The object identifier
	 */
	public $id;

	/** @var string $table_element Table name in SQL */
	public $table_element = 'actioncomm_reminder_subscription';

	/** @var string $element Name of the element */
	public $element = 'actioncomm_reminder_subscription';

	/** @var string $picto a picture file in [@...]/img/object_[...@].png */
	public $picto = 'webnotification@webhost';

	/** @var int $date_creationObject creation date */
	public $date_creation;

	/** @var int entity */
	public $entity;

	/** @var int fk_user */
	public $fk_user;

	/** @var string $trigger */
	public $fk_trigger;

	/** @var string $send_method */
	public $send_method;

	/** @var int fk_object */
	public $fk_object;




	/**
	 *  'type' is the field format.
	 *  'label' the translation key.
	 *  'enabled' is a condition when the field must be managed.
	 *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). Using a negative value means field is not shown by default on list but can be selected for viewing)
	 *  'noteditable' says if field is not editable (1 or 0)
	 *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
	 *  'default' is a default value for creation (can still be replaced by the global setup of default values)
	 *  'index' if we want an index in database.
	 *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
	 *  'position' is the sort order of field.
	 *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
	 *  'isameasure' must be set to 1 if you want to have a total on list for this field. Field type must be summable like integer or double(24,8).
	 *  'css' is the CSS style to use on field. For example: 'maxwidth200'
	 *  'help' is a string visible as a tooltip on field
	 *  'comment' is not used. You can store here any text of your choice. It is not used by application.
	 *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
	 *  'arraykeyval' to set list of value if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel")
	 */

	public $fields = array(
		'rowid' => array('type' => 'integer' ),
		'date_creation' => array('type'=>'date'),
		'entity' => array('type' => 'integer', 'default' => 1, 'notnull' => 1),
		'fk_user' => array('type' => 'integer'),
		'fk_trigger' => array('type' => 'varchar(60)', 'length' => 60),
		'fk_object' => array('type' => 'integer', 'length' => 11),
		'send_method' => array('type' => 'varchar(20)', 'length' => 60)
	);


	/**
	 * WebInstance constructor.
	 * @param DoliDB    $db    Database connector
	 */
	public function __construct($db)
	{
		global $conf;
		$this->db = $db;
		$this->entity = $conf->entity;
	}

	/**
	 *	fetch object from database
	 *
	 *	@param      int			$id       		Id of object to load
	 *  @param      string      $ref            Ref
	 *  @param      string      $morewhere		more sql where options
	 *	@return     int         				>0 if OK, <0 if KO, 0 if not found
	 */
	public function fetch($id, $ref = null, $morewhere = '')
	{
		$res = $this->fetchCommon($id, $ref, $morewhere);

		if (!empty($this->isextrafieldmanaged)) {
			$this->fetch_optionals();
		}

		return $res;
	}

	/**
	 *    fetch object from database
	 *
	 * @param int $fk_user id of user
	 * @param string $fk_trigger id of trigger
	 * @param int $fk_object  id of object
	 * @param string $send_method send method
	 * @return     int                        >0 if OK, <0 if KO, 0 if not found
	 */
	public function fetchSubscribtion($fk_user, $fk_trigger, $fk_object = 0, $send_method = 'push')
	{
		$sql = ' AND fk_user = '.intval($fk_user);
		$sql.= ' AND fk_trigger = "'.$this->db->escape($fk_trigger).'"';
		$sql.= ' AND fk_object = '.intval($fk_object).' ' ;
		$sql.= ' AND send_method = "'.$this->db->escape($send_method).'"';
		return $this->fetch(null, null, $sql);
	}

	/**
	 * @param User $user User object
	 * @return int
	 */
	public function save($user)
	{
		return $this->create($user);
	}

	/**
	 * Function to create object in database
	 *
	 * @param   User    $user		user object
	 * @param	bool	$notrigger	false=launch triggers after, true=disable triggers
	 * @return  int                 < 0 if ko, > 0 if ok
	 */
	public function create(User &$user, $notrigger = false)
	{
		if ($this->id > 0) return $this->update($user, $notrigger);
		return $this->createCommon($user, $notrigger);
	}


	/**
	 * Function to update object or create or delete if needed
	 *
	 * @param   User    $user   	user object
	 * @param	bool	$notrigger	false=launch triggers after, true=disable triggers
	 * @return  int                 < 0 if ko, > 0 if ok
	 */
	public function update(User &$user, $notrigger = false)
	{
		if (empty($this->id)) return $this->create($user, $notrigger); // To test, with that, no need to test on high level object, the core decide it, update just needed
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * @param User $user User object
	 * @param bool $notrigger to disable triggers
	 * @return int
	 */
	public function delete(User &$user, $notrigger = false)
	{
		return parent::deleteCommon($user, $notrigger);
	}

	/**
	 * @param User $user the user
	 * @param $fk_trigger the trigger key
	 * @param string $label a short notification description could be an html string
	 * @param int $fk_object the object->id  || 0 for all objects
	 * @param string $send_method the send method
	 * @param array $param for future hook... or other stuff
	 * @return string
	 */
	public static function getNotifyMeBtn(User $user, $fk_trigger, $label, $fk_object = 0, $send_method = 'push', $param = array())
	{
		global $db, $langs;
		$fk_object = intval($fk_object);

		$class = '';

		$subscription = new self($db);
		$searchRes = $subscription->fetchSubscribtion($user->id, $fk_trigger, $fk_object, $send_method);
		if ($searchRes>0) {
			$class.= " --subscribed" ;
			$label = $langs->trans('UnsubcribFrom'). ' : ' . $label;
		} else {
			$label = $langs->trans('SubcribTo'). ' : ' . $label;
		}

		return  '<span class="notification-subscrib-btn notification-alert-icon classfortooltip '.$class.'" data-trigger="'.$fk_trigger.'" title="'.dol_htmlentities($label, ENT_QUOTES).'" data-fk_object="'.$fk_object.'" ></span>';
	}

	/**
	 * @param User $user the User
	 * @param string $fk_trigger the trigger key
	 * @param int $fk_object the object id
	 * @param string $send_method the send method
	 * @return int
	 */
	public function unSubscrib(User $user, $fk_trigger, $fk_object = 0, $send_method = 'push')
	{
		$result = $this->fetchSubscribtion($user->id, $fk_trigger, $fk_object, $send_method);
		if ($result > 0) {
			return $this->delete($user);
		}

		return $result;
	}

	/**
	 * @param User $user the User
	 * @param string $fk_trigger the trigger key
	 * @param int $fk_object the object id
	 * @param string $send_method the send method
	 * @return int
	 */
	public function subscrib(User $user, $fk_trigger, $fk_object = 0, $send_method = 'push')
	{
		$result = $this->fetchSubscribtion($user->id, $fk_trigger, $fk_object, $send_method);
		if ($result > 0) {
			// already subscribed nothing to do
			return 1;
		} elseif (empty($result)) {
			// currently no subscription
			$this->fk_user = $user->id;
			$this->fk_object = $fk_object;
			$this->fk_trigger = $fk_trigger;
			$this->send_method = $send_method;
			return $this->save($user);
		} else {
			return 1;
		}
	}
}
