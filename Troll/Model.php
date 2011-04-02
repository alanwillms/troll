<?php
/**
 * Models abstract class
 * 
 * @author Alan Willms <alanwillms@gmail.com>
 * @since  11/03/2011
 *
 */
abstract class Troll_Model
{
	/**
	 * Data types
	 * @var integer
	 */
	const TYPE_UNKNOWN  = 0;
	const TYPE_STRING   = 1;
	const TYPE_INTEGER  = 2;
	const TYPE_FLOAT    = 3;
	const TYPE_DATETIME = 4;
	const TYPE_DATE     = 5;
	const TYPE_TIME     = 6;
	const TYPE_BOOLEAN  = 7;	
	
	/**
	 * Relationships
	 * 
	 * <code>
	 * array(
	 * 		'local_attribute' => array(
	 * 			'local_id'   => 'local_attribute_id',
	 * 			'class_name' => 'AttributeClass',
	 * 			'remote_id'  => 'id',
	 * 		)
	 * );
	 * </code>
	 * 
	 * @var array
	 */
	protected static $__relationships;
	
	/**
	 * Read only?
	 * @var boolean
	 */
	protected $_readOnly = false;
	
	/**
	 * Attributes data of the local object
	 * @var array
	 */
	protected $__attributesData;
	
	/**
	 * Attributes changing log
	 * @var array
	 */
	protected $__changedAttributes;
	
	/**
	 * Array with attributes of the model
	 * @var array
	 */
	protected static $__attributes;
	
	/**
	 * Validators of the attributes
	 * @var array
	 */
	protected static $__attributesValidators;
	
	/**
	 * Database table class name for autoloading
	 * @var string
	 */
	protected static $__databaseTableClassName;
	
	/**
	 * Database table where this model persist
	 * @var Zend_Db_Table_Abstract
	 */
	protected static $__databaseTable;
	
	/**
	 * Primary keys of model database table
	 * @var array
	 */
	protected static $__primaryKeys;
	
	/**
	 * Database table columns data type
	 * @var array
	 */
	protected static $__dataTypes;
	
	/**
	 * Underscore to camel-case filter
	 * @var Zend_Filter_Word_UnderscoreToCamelCase
	 */
	protected static $_underscoreToCamelCaseFilter;
	
	/**
	 * Flag pointig if this is a new object
	 * @var boolean
	 */
	protected $__isNew = true;
	
	/**
	 * Flag pointig if this is a valid object
	 * @var boolean
	 */
	protected $__isValid = true;
	
	/**
	 * Error messages
	 * @var array
	 */
	protected $__errors;
	
	/**
	 * Constructor
	 * @param Mixed Could be an array with attributes data, or in the case of 
	 * only one attribute, some value, or null for an empty object
	 */
	public function __construct($mixed = null, $isNew = true)
	{
		$calledClass = get_called_class();
		$calledClass::_setupAttributes();
		
		// Create local attributes data container
		if (isset($this)) {
			
			$this->__attributesData = array();
			foreach ($calledClass::getAttributes() as $attr) {
				$this->__attributesData[$attr] = null;
			}
			
			// If there are relationships
			if (isset($calledClass::$__relationships[$calledClass])) {
				foreach ($calledClass::$__relationships[$calledClass] as $attr => $data) {
					$this->__attributesData[$attr] = null;
				}
			}
		}
		
		if (null !== $mixed) {
			
			if (!is_array($mixed)) {
				if (!is_array($calledClass::getAttributes()) || !count($calledClass::getAttributes()) != 1) {
					throw new Troll_Model_Exception('Constructor received ' .
					                                 gettype($mixed) .
					                                 ' Expecting array or null'
					                                 );
				}
				$mixed = array(current($calledClass::getAttributes()) => $mixed);
			}
			
			// Populate
			foreach ($calledClass::getAttributes() as $attr) {
				
				if (isset($mixed[$attr]) && $mixed[$attr] !== null && $mixed[$attr] !== '') {
					
					// If it is a primary key and it is a database register
					if (!$isNew && in_array($attr, $calledClass::$__primaryKeys)) {
						$this->__attributesData[$attr] = $mixed[$attr];
					}
					else {
						$this->$attr = $mixed[$attr];
					}
				}
			}
			
			// If it is not a new object
			if (!$isNew) {
				$this->__isNew = false;
			}
		}
	}
	
	/**
	 * Return array representation of the object
	 * @return array
	 */
	public function __toArray()
	{
		return $this->toArray();
	}
	
	/**
	 * Setters
	 * 
	 * @param string $name
	 * @param string $value
	 * @throws Troll_Model_Exception
	 */
	public function __set($name, $value)
	{
		// Cannot change primary keys values @important
		if (false !== in_array($name, $this::$__primaryKeys)) {
			throw new Troll_Model_Exception('You cannot change primary keys values!');
		}
		
		$methods = get_class_methods($this);
		$method = 'set' . self::_getUnderscoreToCamelCaseFilter()->filter($name);
		
		// Before setting, cast the value
		$value = $this->cast($value, $this->_getTypeOf($name));
		
		// Try to load an setAttributeName method
		if (in_array($method, $methods)) {
			$this->$method($value);
		}
		elseif (array_key_exists($name, $this->__attributesData)) {
			
			$calledClass = get_called_class();
			
			// Test for a relationship
			if (isset($calledClass::$__relationships) && isset($calledClass::$__relationships[$calledClass])) {
				
				// Setting a relationship object
				if (false !== in_array($name, array_keys($calledClass::$__relationships[$calledClass]))) {
					
					// Test object type
					if (!$value instanceof $calledClass::$__relationships[$calledClass][$name]['class_name']) {
						throw new Class_Model_Exception('Value must be an instance '
						                              . 'of ' . $calledClass::$__relationships[$calledClass][$name]['class_name']
						                              . ' class');
					}
					
					// Set ID value
					$remoteIdAttribute = $calledClass::$__relationships[$calledClass][$name]['remote_id'];
					
					$this->__attributesData['id_' . $calledClass::$__relationships[$calledClass][$name]['local_id']] = $value->$remoteIdAttribute;
					
				}
				// The object will be set only if needed via __get - so it becomes null
				else {
					
					// If a change occurred
					if ($this->$name != $value) {
						foreach ($this::$__relationships[$calledClass] as $localName => $data) {
							if ($name == $data['local_id']) {
								$this->__attributesData[$localName] = null;
								break;
							}
						}
					}
				}
			}
			$this->__attributesData[$name] = $value;
		}
		else {
			throw new Troll_Model_Exception('Setting invalid attribute "' .
			                                 $name . '" of "' .
			                                 get_class($this) . '" class');
		}
		
		// Attributes changing log
		if (!$this->__isNew) {
			if (!isset($this->__changedAttributes)) {
				$this->__changedAttributes = array();
			}
			$this->__changedAttributes[$name] = $name;
		}
	}
	
	/**
	 * Getters
	 * 
	 * @param string $name
	 * @throws Troll_Model_Exception
	 */
	public function __get($name)
	{
		$methods = get_class_methods($this);
		$method = 'get' . self::_getUnderscoreToCamelCaseFilter()->filter($name);
		$calledClass = get_called_class();
			
		// Try to load an getAttributeName method
		if (in_array($method, $methods)) {
			return $this->$method();
		}
		elseif (array_key_exists($name, $this->__attributesData)) {
			
			// If there are relationships
			if (isset(self::$__relationships) && isset(self::$__relationships[$calledClass])) {
				
				if (!isset($this->__attributesData[$name])) {
				
					// If there is an unsetted object
					if (false !== in_array($name, array_keys($calledClass::$__relationships[$calledClass]))) {
						
						$id = $calledClass::$__relationships[$calledClass][$name]['local_id'];
						$class = $calledClass::$__relationships[$calledClass][$name]['class_name'];
						$remoteId = $calledClass::$__relationships[$calledClass][$name]['remote_id'];
						
						// Find object
						if (isset($this->__attributesData['id_' . $id])) {
							$this->__attributesData[$name] = $class::find(array($remoteId => $this->__attributesData['id_' . $id]));
						}
					}
				
				}
			}
			
			return $this->__attributesData[$name];
		}
		else {
			throw new Troll_Model_Exception('Getting invalid attribute "' .
			                                 $name . '" of "' .
			                                 get_class($this) . '" class');
		}
	}
	
	/**
	 * Isset?ers
	 * @param string $name
	 */
	public function __isset($name)
	{
		return isset($this->__attributesData[$name]);
	}
	
	/**
	 * Return current model database table
	 * @return null|Zend_Db_Table_Abstract
	 */
	public static function getDatabaseTable()
	{
		$calledClass = get_called_class();
		
		if (!isset($calledClass::$__databaseTable) || !isset($calledClass::$__databaseTable[$calledClass])) {
			$calledClass::_loadDatabaseTable();
		}
		
		return $calledClass::$__databaseTable[$calledClass];
	}
	
	/**
	 * Get database table column name for an object attribute
	 * @param string $name
	 * @return string
	 */
	public static function attributeToColumn($name)
	{
		$calledClass = get_called_class();
		
		if ($calledClass::hasAttributesMapping()) {
			$mapping = $calledClass::getAttributesMapping();
			if (isset($mapping[$name])) {
				return $mapping[$name];
			}
		}
		
		return $name;
	}
	
	/**
	 * Get object attribute for a database table column name
	 * @param string $name
	 * @return string
	 */
	public static function columnToAttribute($name)
	{
		$calledClass = get_called_class();
		
		if ($calledClass::hasAttributesMapping()) {
			$mapping = array_flip($calledClass::getAttributesMapping());
			if (isset($mapping[$name])) {
				return $mapping[$name];
			}
		}
		
		return $name;
	}
	
	/**
	 * Return attributes
	 * @return array
	 */
	public static function getAttributes()
	{
		$calledClass = get_called_class();
		
		if (!isset($calledClass::$__attributes) || !isset($calledClass::$__attributes[$calledClass])) {
			$calledClass::_setupAttributes();
		}
		return $calledClass::$__attributes[$calledClass];
	}
	
	/**
	 * Return attributes mapping
	 * @return array|null
	 */
	public static function getAttributesMapping()
	{
		$calledClass = get_called_class();
		return $calledClass::getDatabaseTable()->getAttributesMapping();
	}
	
	/**
	 * Is there attributes mapping?
	 * @return boolean
	 */
	public static function hasAttributesMapping()
	{
		$calledClass = get_called_class();
		$mapping = $calledClass::getDatabaseTable()->getAttributesMapping();
		return (isset($mapping) && count($mapping) > 0);
	}
	
	/**
	 * Return primary keys
	 * @return array|null
	 */
	public static function getPrimaryKeys()
	{
		$calledClass = get_called_class();
		
		if (!isset($calledClass::$__primaryKeys) || !isset($calledClass::$__primaryKeys[$calledClass])) {
			$calledClass::_setupAttributes();
		}
		
		return $calledClass::$__primaryKeys[$calledClass];
	}
	
	/**
	 * Return one object by SQL query
	 * @param string $query
	 * @param boolean $throwNotFoundException
	 * @throws Troll_Model_Exception
	 * @return Troll_Model|null
	 */
	public static function sqlFind($query, $throwNotFoundException = false)
	{
		$calledClass = get_called_class();
		
		// Consult
		$statement = $calledClass::getDatabaseTable()->getAdapter()->query($query);
		$row = $statement->fetch();
		
		if (!$row) {
			// Not found exception
			if ($throwNotFoundException) {
				throw new Troll_Model_Exception('Objects not found!');
			}
			return null;
		}
		
		// Columns to attributes mapping
		if ($calledClass::hasAttributesMapping()) {
			$data = array();
			foreach ($row as $column => $value) {
				$data[$calledClass::columnToAttribute($column)] = $value;
			}
			$row = $data;
			unset($data);
		}
		
		// Return new populated object
		return new $calledClass($row);
	}
	
	/**
	 * Return an array of objects by SQL query
	 * @param string $query
	 * @param boolean $throwNotFoundException
	 * @throws Troll_Model_Exception
	 * @return Troll_Model|null
	 */
	public static function sqlAll($query, $throwNotFoundException = false)
	{
		$calledClass = get_called_class();
		
		// Consult
		$statement = $calledClass::getDatabaseTable()->getAdapter()->query($query);
		$rows = $statement->fetchAll();
		
		if (!is_array($rows) || !count($rows)) {
			// Not found exception
			if ($throwNotFoundException) {
				throw new Troll_Model_Exception('Objects not found!');
			}
			return null;
		}
		
		$all = array();
		
		foreach ($rows as $row) {
		
			// Columns to attributes mapping
			if ($calledClass::hasAttributesMapping()) {
				$data = array();
				foreach ($row as $column => $value) {
					$data[$calledClass::columnToAttribute($column)] = $value;
				}
				$row = $data;
				unset($data);
			}
			
			// Remove unsetted attributes
			foreach ($row as $attribute => $value) {
				if (false === in_array($attribute, $calledClass::getAttributes())) {
					unset($row[$attribute]);
				}
			}
			
			// Create new object
			$all[] = new $calledClass($row, false);
		}
		
		// Return array of populated objects
		return $all;
	}
	
	/**
	 * TODO Return a tree of objects
	 * @param array $options
	 * @throws Troll_Model_Exception
	 */
	/*public static function tree(array $options)
	{
		if (!isset($options['super'])) {
			throw new Troll_Model_Exception('You must define a super key to get a tree!');
		}
		if (!isset($options['level'])) {
			$options['level'] = 'level';
		}
		
		$calledClass = get_called_class();
		$db = $calledClass::getDatabaseTable()->getAdapter();
		
		if ($db instanceof Zend_Db_Adapter_Pdo_Pgsql) {
			
			// TODO Implementar SQL para Oracle/PostgreSQL, e exceções para os demais bancos
			return $calledClass::sqlAll("with recursive t(node, path, titulo, subtitulo, url, acessos) as
			(
			select id, array[id], titulo, subtitulo, url, acessos from pagina where id_pai is null
			union all
			select p.id, t.path || array[p.id] , p.titulo, p.subtitulo, p.url, p.acessos
			from pagina p 
			join t on (p.id_pai = t.node)
			where id <> any(t.path)
			)
			select t.*, node as id, array_length(path, 1) as " . $options['level'] . " from t order by path
			");
			
		}
	}*/
	
	/**
	 * Find one object exclusively by primary keys.
	 * @param Mixed $ids
	 * @throws Troll_Model_Exception
	 * @return null|Troll_Model
	 */
	public static function find($ids = null, $throwNotFoundException = false)
	{
		$calledClass = get_called_class();
		return $calledClass::all($ids, $throwNotFoundException, 1);
	}
	
	/**
	 * Find objects exclusively by primary keys.
	 * <code>
	 * // Notations:
	 * $ids = array('attribute equals to' => 'value');
	 * $ids = array('attribute like ?' => $valor); // Could be in, not like, etc.
	 * $ids = 5461; // Will become array('primary_key' => 5461);
	 * $ids = $select; // FIXME Como mexer no object? 
	 * $ids = array('key' => 123, 'another_key' => 456, ':order' => 'name);
	 * 
	 * // Zend_Db_Select#fetchAll
	 * 
	 * // :where => $where
	 * // :order => $order
	 * // :offset => $offset
	 * // :limit => $limit
	 * 
	 * // TODO Includes - solve N+1 problem
	 * 
	 * // :include => 'relationship'
	 * // :include => array('relationship' => array('col1', 'col2'), 'other_relationship')
	 * // :include => array('relationship' => array('col1', 'col2', ':include' => 'subrelationship'))
	 * </code>
	 * @param Mixed $ids
	 * @throws Troll_Model_Exception
	 * @return null|Troll_Model
	 */
	public static function all($ids = null, $throwNotFoundException = false, $limit = null)
	{
		$calledClass = get_called_class();
		
		// Select object
		$select = null;
		
		// Create select object
		if (isset($ids) || isset($limit)) {
			
			$select = $calledClass::getDatabaseTable()->select();
			
			// To be one object
			if ($limit) {
				$select->limit($limit);
			}
			
		}
		
		// If there are conditions
		if (isset($ids)) {
		
			// If it is not an array or a Select object, get primary key
			if (!is_array($ids) && !$ids instanceof Zend_Db_Select) {
				
				if (count($calledClass::getPrimaryKeys()) == 1) {
					$ids = array(current($calledClass::getPrimaryKeys()) => $ids);
				}
				else {
					throw new Troll_Model_Exception('You informed 1 value, but' .
					                                 ' your database table has' .
					                                 count($calledClass::getPrimaryKeys()) .
					                                 ' primary keys' 
					);
				}
			}
			if (is_array($ids)) {
				
				if (!isset($ids[':cols'])) {
					$ids[':cols'] = $calledClass::getAttributes();
				}
				
				// Create where statement
				foreach ($ids as $expression => $value) {
					
					// Special operations of fetchAll
					if ($expression == ':where') {
						$select->where($value);
					}
					else if ($expression == ':order') {
						$select->order($value);
					}
					else if ($expression == ':limit') {
						$select->limit($value);
					}
					else if ($expression == ':offset') {
						$select->offset($value);
					}
					else if ($expression == ':cols') {
						
						// Get column names for attributes
						$value = (array) $value;
						$columns = array();
						foreach ($value  as $k => $v) {
							$v = $calledClass::attributeToColumn($v);
							$newV = $calledClass::getDatabaseTable()->getSelectFilter($calledClass::attributeToColumn($v));
							$columns[$v] = $newV;
						}
						
						// Reset select *
						$select->reset(Zend_Db_Select::FROM);
						$select->reset(Zend_Db_Select::COLUMNS);
						
						// Select :cols from
						$select->from($calledClass::getDatabaseTable()->info(Zend_Db_Table::NAME), $columns);
					}
					else if ($expression == ':include') {
						// Nothing to do now!
					}
					else {
						
						// If it is a real zend_db_select "where" expression
						if (false !== strpos($expression, '?')) {
							$select->where($expression, $value);
						}
						else {
							
							// Test if the key exists
							if (false === in_array($expression, $calledClass::getAttributes())) {
								throw new Troll_Model_Exception('Undefined "' . $expression . '" attribute!');
							}
							
							// Attributes to columns mapping
							if ($calledClass::hasAttributesMapping()) {
								$expression = $calledClass::attributeToColumn($expression);
							}
							
							$select->where($expression . ' = ?', $value);
						}
					}
				}
				
			}
		}
		
		// Search
		$all  = array();
		$rows = $calledClass::getDatabaseTable()->fetchAll($select);
		
		// Not found exception
		if (!count($rows) && $throwNotFoundException) {
			throw new Troll_Model_Exception('Objects not found!');
		}
		
		foreach ($rows as $row) {
			
			// Columns to attributes mapping
			$data = array();
			
			if ($calledClass::hasAttributesMapping()) {
				foreach ($row->toArray() as $col => $value) {
					$data[$calledClass::columnToAttribute($col)] = $value;
				}
			}
			else {
				$data = $row->toArray();
			}
			
			$all[] = new $calledClass($data, false);
		}
		
		// Includes
		if (array_key_exists(':include', $ids)) {
			
			$calledClass::_setupRelationships();
			
			$ids[':include'] = (array) $ids[':include'];
			
			foreach ($ids[':include'] as $k => $v) {
				
				// Is it :include => array('table') ou :include => array('table' => ###') ?
				$rel = (is_numeric($k)) ? $v : $k;
				$rel = $options = null;
				
				if (is_numeric($k)) {
					$rel = $v;
					$options = array();
				}
				else {
					$rel = $k;
					$options = $v;
				}
			
				// Get relationship
				$relData = $calledClass::$__relationships[$calledClass][$rel];
				
				$localIdColumn = 'id_' . $relData['local_id']; 
				
				// Get local IDS // TODO Array mapping?
				$select->reset(Zend_Db_Select::FROM);
				$select->reset(Zend_Db_Select::COLUMNS);
				$select->distinct();
				$select->from($calledClass::getDatabaseTable()->info(Zend_Db_Table::NAME), $localIdColumn);
				
				$localIdsRows = $calledClass::getDatabaseTable()->fetchAll($select);
				$localIds = array();
				
				foreach ($localIdsRows as $row) {
					$localIds[] = $row->$localIdColumn;
				}
				
				unset($localIdsRows);
				
				// Fetch all relationships with these IDs
				$options['id in (?)'] = $localIds;
				$rels = $relData['class_name']::all($options);
				$relationships = array();
				
				foreach ($rels as $row) {
					$relationships[$row->$relData['remote_id']] = $row;
				}
				
				// Insert the objects
				foreach ($all as $row) {
					$row->$rel = $relationships[$row->$localIdColumn];
				}
			}
			
		}
		
		// If limit is only 1, must be an object and not an array
		if ($limit == 1) {
			if (count($all)) {
				return current($all);
			}
			else {
				return null;
			}
		}
		
		return $all;
	}
	
	/**
	 * Return array presentation of the object
	 * @return array
	 */
	public function toArray()
	{
		return $this->__attributesData;
	}
	
	/**
	 * Delete model register in the database table
	 * @return boolean
	 */
	public function delete()
	{
		// Is it read only?
		if ($this->_readOnly) {
			throw new Troll_Model_Exception('This object is read only!');
		}
		
		// Test if it is not a new register
		if ($this->__isNew) {
			throw new Troll_Model_Exception(
				'This object was not persisted yet. Cannot delete. '
			);
		}
		
		// Select object
		$select = $this::getDatabaseTable()->select();
		
		// Test if all primary keys are filled
		foreach ($this::getPrimaryKeys() as $pk) {
			
			if (!isset($this->$pk) || !$this->$pk) {
				throw new Troll_Model_Exception(
					'All primary keys must be filled. Cannot delete. '
				);
			}
			
			$select->where($pk . ' = ?', $this->$pk);
		}
		
		$where = '(' . implode(') and (', $select->getPart(Zend_Db_Select::WHERE)) . ')';
		
		// Delete
		$this->_beforeDelete();
		$return = $this::getDatabaseTable()->delete($where);
		$this->_afterDelete();
		
		// Set as a new object
		$this->__isNew = true;
		
		// Return affected database table rows
		return $return;
	}
	
	/**
	 * Persist the object. "insert or update"
	 */
	public function save($validates = true)
	{
		// Is it read only?
		if ($this->_readOnly) {
			throw new Troll_Model_Exception('This object is read only!');
		}
		
		$calledClass = get_called_class();
		
		// Validation
		if ($validates) {
			
			// Init validations
			$this->validate();
			
			if (!$this->__isValid) {
				return false;
			}
		}
		
		$data = $this->__attributesData;
		
		// If there is attributes mapping with database table
		if ($calledClass::hasAttributesMapping()) {
			$trueData = array();
			foreach ($data as $key => $value) {
				$trueData[$calledClass::attributeToColumn($key)] = $value;
			}
			$data = $trueData;
			unset($trueData);
		}
		
		// Unset keys that will not be saved
		$excludedCols = array_diff(array_keys($data), $calledClass::_getDatabaseTableColumns());
		
		foreach ($excludedCols as $col) {
			unset($data[$col]);
		}
		
		// It's updating?
		$inserting   = true;
		$updating    = 0;
		$primaryKeys = $this->getPrimaryKeys();
		
		foreach ($primaryKeys as $pk) {
			if (isset($data[$pk]) && $data[$pk] > 0) {
				$updating++;
			}
		}
		
		// Exclusive for boolean types
		foreach ($data as $col => $value) {
			if ($this->_getTypeOf($col) == Troll_Model::TYPE_BOOLEAN) {
				$data[$col] = ($value) ? new Zend_Db_Expr('true') : new Zend_Db_Expr('false');
			}
		}
		
		// Try to update
		if (count($primaryKeys) == $updating) {
			
			$select = $this->getDatabaseTable()->select();
			
			foreach ($primaryKeys as $pk) {
				
				// Update "where"
				$select->where($pk . ' = ?', '' . $data[$pk] . '');
			}
			
			
			// Update only changed columns
			$updateData = array();
			
			foreach ($this->__changedAttributes as $attr) {
				$attr = $calledClass::attributeToColumn($attr);
				$updateData[$attr] = $data[$attr];
			}
			
			$this->_beforeUpdate();
			$updatedRows = $this->getDatabaseTable()->update($updateData, implode(' ', $select->getPart(Zend_Db_Table_Select::WHERE)));
			$this->_afterUpdate();
			
			// If it does not fail to update
			if ($updatedRows > 0) {
				$inserting = false;
			}
		}
		
		// Insert
		if ($inserting) {
			
			$this->_beforeInsert();
			
			$pk = $this->getDatabaseTable()->insert($data);
			
			if (is_array($pk)) {
				foreach ($pk as $key => $value) {
					$this->__attributesData[$key] =  $value;
				}
			}
			else {
				$this->__attributesData[array_pop($primaryKeys)] = $pk;
			}
			
			$this->_afterInsert();
		}
		
		return true;
	}
	
	/**
	 * FIXME Set Zend_Form errors
	 * @param Zend_Form $form
	 * @param string    $subform Name of the subform
	 */
	public function setFormErrors(Zend_Form $form, $subform = null)
	{
		$errors = $pagina->errors();
				
		foreach ($errors as $field => $messages) {
			
			$element = ($subform) ? $form->$subform->getElement($field) : $form->getElement($field);
			
			if ($element) {
				$element->setErrors($messages);
			}
		}
	}
	
	/**
	 * Return validation errors
	 * @return array
	 */
	public function errors()
	{
		return $this->__errors;
	}
	
	/**
	 * Flag indicating if it is a new object
	 * @return boolean
	 */
	public function isNew()
	{
		return $this->__isNew;
	}
	
	/**
	 * Validates the object
	 * @return boolean
	 */
	public function validate()
	{
		$this->_customValidations();
		$this->_initValidations();
		
		return $this->__isValid;
	}
	
	/**
	 * You should override if it is necessary,
	 * using $this->__isValid flag
	 */
	protected function _customValidations()
	{
		
	}
	
	/**
	 * You should override if it is necessary,
	 */
	protected function _beforeInsert()
	{
		
	}
	
	/**
	 * You should override if it is necessary,
	 */
	protected function _afterInsert()
	{
		
	}
	
	/**
	 * You should override if it is necessary,
	 */
	protected function _beforeUpdate()
	{
		
	}
	
	/**
	 * You should override if it is necessary,
	 */
	protected function _afterUpdate()
	{
		
	}
	
	/**
	 * You should override if it is necessary,
	 */
	protected function _beforeDelete()
	{
		
	}
	
	/**
	 * You should override if it is necessary,
	 */
	protected function _afterDelete()
	{
		
	}
	
	/**
	 * Object validations
	 * @return boolean
	 */
	protected function _initValidations()
	{
		$calledClass = get_called_class();
		
		foreach ($calledClass::$__attributesValidators[$calledClass] as $attribute => $validators) {
			
			// First of all, tries not empty
			$validator = new Zend_Validate_NotEmpty();
			
			// Empty?
			if (!$validator->isValid($this->__attributesData[$attribute])) {
			
				// If there is a "NotEmpty" validator, add the error message
				if (false !== ($pos = in_array('NotEmpty', $validators))) {
					$this->__isValid = false;
					$this->_addError($attribute, $validator->getMessages());
				}
			}
			// If it is not empty, do other validations
			else {
				
				// Do not repeat!
				if (false !== ($pos = in_array('NotEmpty', $validators))) {
					unset($calledClass::$__attributesValidators[$calledClass][$attribute][$pos]);
				}
			
				foreach ($validators as $validatorName => $options) {
					
					// If validator name is not the key, there is no options
					if (is_numeric($validatorName)) {
						$validatorName = $options;
						$options = null;
					}
					// If it is not validator full name
					if (false === strpos($validatorName, 'Validate_')) {
						$validatorName = 'Zend_Validate_' . $validatorName;
					}
					$validator = (null == $options) ? new $validatorName() : new $validatorName($options);
					
					if (!$validator->isValid($this->__attributesData[$attribute])) {
						$this->__isValid = false;
						$this->_addError($attribute, $validator->getMessages());
					}
					
				}
			
			}
		}
		
		return $this->__isValid;
	}
	
	/**
	 * Add a new error message
	 * @param string $attribute
	 * @param string $validator
	 * @param array $messages
	 */
	protected function _addError($attribute, $messages)
	{
		if (!isset($this->__errors[$attribute])) {
			$this->__errors[$attribute] = array();
		}
		$this->__errors[$attribute] = array_merge($this->__errors[$attribute], $messages);
	}
	
	/**
	 * Setup attributes array
	 */
	protected static function _setupAttributes()
	{
		$calledClass = get_called_class();
		
		// If attributes are not set
		if (!isset($calledClass::$__attributes)) {
			$calledClass::$__attributes = array();
			$calledClass::$__dataTypes  = array();
		}
		
		// If class attributes are not set
		if (!isset($calledClass::$__attributes[$calledClass])) {
			
			$calledClass::$__attributes[$calledClass] = array();
			$calledClass::$__dataTypes[$calledClass]  = array();
			
			// Detects main type (int, float, string, date, time, datetime)
			$info = $calledClass::getDatabaseTable()->info(Zend_Db_Table_Abstract::METADATA);
			
			foreach ($info as $col => $data) {
				
				if ($calledClass::hasAttributesMapping()) {
					$col = $calledClass::columnToAttribute($col);
				}
				
				$type = Troll_Model::TYPE_UNKNOWN;
				
				if ((false !== strpos($data['DATA_TYPE'], 'char'))
					|| (false !== strpos($data['DATA_TYPE'], 'text'))
					) {
					$type = Troll_Model::TYPE_STRING;
				}
				elseif (false !== strpos($data['DATA_TYPE'], 'int')) {
					$type = Troll_Model::TYPE_INTEGER;
				}
				elseif ((false !== strpos($data['DATA_TYPE'], 'float'))
					|| (false !== strpos($data['DATA_TYPE'], 'double'))) {
					$type = Troll_Model::TYPE_FLOAT;
				}
				elseif ($data['DATA_TYPE'] == 'time' || $data['DATA_TYPE'] == 'timetz') {
					$type = Troll_Model::TYPE_TIME;
				}
				elseif ($data['DATA_TYPE'] == 'date') {
					$type = Troll_Model::TYPE_DATE;
				}
				elseif ((false !== strpos($data['DATA_TYPE'], 'datetime'))
					|| (false !== strpos($data['DATA_TYPE'], 'timestamp'))
					) {
					$type = Troll_Model::TYPE_DATETIME;
				}
				elseif ($data['DATA_TYPE'] == 'boolean' || $data['DATA_TYPE'] == 'bool') {
					$type = Troll_Model::TYPE_BOOLEAN;
				}
				
				// Add column data
				$calledClass::$__dataTypes[$calledClass][$col] = $type; 
					
			}
			
			// If there is attributes mapping
			if ($calledClass::hasAttributesMapping()) {
				
				// Load primary keys
				$primaryKeys = $calledClass::getDatabaseTable()->info(Zend_Db_Table_Abstract::PRIMARY);
				
				if (!count($calledClass)) {
					throw new Troll_Model_Exception('Primary keys are not defined!');
				}
				if (!isset($calledClass::$__primaryKeys)) {
					$calledClass::$__primaryKeys = array();
				}
				if (!isset($calledClass::$__primaryKeys[$calledClass])) {
					$calledClass::$__primaryKeys[$calledClass] = array();
				}
				foreach ($primaryKeys as $pk) {
					$calledClass::$__primaryKeys[$calledClass][] = $calledClass::columnToAttribute($pk);
				}
				
				$columns = $calledClass::getDatabaseTable()->info(Zend_Db_Table_Abstract::COLS);
				
				// If there is a different number of columns, it is merged
				$mapping = $calledClass::getAttributesMapping();
				
				if (count($columns) != count($mapping)) {
					
					$calledClass::$__attributes[$calledClass] = array_merge(
						array_keys($mapping),
						array_diff($columns, $mapping)
					);
				}
				else {
					$calledClass::$__attributes[$calledClass] = array_keys($mapping);
				}
			}
			// Otherwise, get database table columns
			else {
				
				// Load database table columns
				$cols = $calledClass::_getDatabaseTableColumns();
				$calledClass::$__attributes[$calledClass] = $cols;
				
				// Load validators
				if (!isset($calledClass::$__attributesValidators)) {
					$calledClass::$__attributesValidators = array();
				}
				if (!isset($calledClass::$__attributesValidators[$calledClass])) {
					$calledClass::$__attributesValidators[$calledClass] = array();
				}
				foreach ($cols as $col) {
					if (!isset($calledClass::$__attributesValidators[$calledClass][$col])) {
						$calledClass::$__attributesValidators[$calledClass][$col] = array();
					}
				}
				
				// Load primary keys
				if (!isset($calledClass::$__primaryKeys)) {
					$calledClass::$__primaryKeys = array();
				}
				if (!isset($calledClass::$__primaryKeys[$calledClass])) {
					$calledClass::$__primaryKeys[$calledClass] = array();
				}
				$calledClass::$__primaryKeys[$calledClass] = $calledClass::getDatabaseTable()->info(Zend_Db_Table_Abstract::PRIMARY);
				
				if (!count($calledClass::$__primaryKeys[$calledClass])) {
					throw new Troll_Model_Exception('Primary keys are not defined!');
				}
			}
			
			// Validators and data types
			$info = $calledClass::getDatabaseTable()->info(Zend_Db_Table_Abstract::METADATA);
			
			foreach ($info as $col => $data) {
				
				// Get true attribute name
				if ($calledClass::hasAttributesMapping()) {
					$col = $calledClass::columnToAttribute($col);
				}
				
				// Detects column sizes and adds validations: smallint, bigint. etc.
				
				// TODO Detectar somente primary keys COM autoincrement
				if (!$data['PRIMARY']) {
					
					// It is not "nullable" and there is no "default value"
					if (!$data['NULLABLE'] && ($data['DEFAULT'] === null || $data['DEFAULT'] === '')) {
						$calledClass::$__attributesValidators[$calledClass][$col][] = 'NotEmpty';
					}
					if ($data['LENGTH']) {
						// String length for string values
						if ($calledClass::$__dataTypes[$calledClass][$col] == Troll_Model::TYPE_STRING) {
							$calledClass::$__attributesValidators[$calledClass][$col]['StringLength'] = array('max' => $data['LENGTH']);
						}
					}
					// Types validators
					$type = $calledClass::$__dataTypes[$calledClass][$col];
					
					if ($type == Troll_Model::TYPE_INTEGER) {
						$calledClass::$__attributesValidators[$calledClass][$col][] = 'Int';
					}
					elseif ($type == Troll_Model::TYPE_FLOAT) {
						$calledClass::$__attributesValidators[$calledClass][$col][] = 'Float';
					}
					elseif ($type == Troll_Model::TYPE_DATE) {
						$calledClass::$__attributesValidators[$calledClass][$col][] = 'Date';
					}
					elseif ($type == Troll_Model::TYPE_DATETIME) {
						$calledClass::$__attributesValidators[$calledClass][$col][] = 'Troll_Validate_DateTime';
					}
					elseif ($type == Troll_Model::TYPE_TIME) {
						$calledClass::$__attributesValidators[$calledClass][$col][] = 'Troll_Validate_Time';
					}
				}
			}
		}
		
		$calledClass::_setupRelationships();
	}
	
	/**
	 * Load models relationships
	 */
	protected static function _setupRelationships()
	{
		$calledClass = get_called_class();
		
		if (!isset($calledClass::$__relationships)) {
			$calledClass::$__relationships = array();
		}
		
		// If attributes is not set
		if (!isset($calledClass::$__relationships[$calledClass])) {
			
			// Try to autoload attributes
			$references = $calledClass::getDatabaseTable()->info(Zend_Db_Table_Abstract::REFERENCE_MAP);
			
			if (is_array($references) && count($references)) {
				
				$calledClass::$__relationships[$calledClass] = array();
				
				foreach ($references as $data) {
					
					// Try to recreate class name
					$className = explode('_', $data['refTableClass']);
					if (($pos = array_search('DbTable', $className)) !== false) {
						unset($className[$pos]);
					}
					$className = implode('_', $className);
					
					// Local ID
					$localId = is_array($data['columns']) ? current($data['columns']) : $data['columns'];
					$localName = explode('_', $localId);
					if (($pos = array_search('id', $localName)) !== false) {
						unset($localName[$pos]);
					}
					$localName = implode('_', $localName);
					
					// Set a relationship
					$calledClass::$__relationships[$calledClass][$localName] = array(
						'local_id'   => $localId,
						'class_name' => $className,
						'remote_id'  => is_array($data['refColumns']) ? current($data['refColumns']) : $data['refColumns'],
					);
					
				}
				
			}
		}
	}
	
	/**
	 * Load database table object
	 * @throws Troll_Model_Exception
	 */
	protected static function _loadDatabaseTable()
	{
		$calledClass = get_called_class();
		
		// Start the arrays
		if (!isset($calledClass::$__databaseTableClassName)) {
			$calledClass::$__databaseTableClassName = array();
		}
		if (!isset($calledClass::$__databaseTable)) {
			$calledClass::$__databaseTable = array();
		}
		
		// Load database table name
		if (!isset($calledClass::$__databaseTableClassName[$calledClass])) {
			
			// Set "DbTable" directory as penultimate element
			$fullClassName = explode('_', $calledClass);
			$className   = array_pop($fullClassName);
			$fullClassName[] = 'DbTable';
			$fullClassName[] = $className;
			
			$calledClass::$__databaseTableClassName[$calledClass] = implode(
				'_',
				$fullClassName
			);
		}
		
		$exists = false;
		
		// If class exists
		if (!$exists = class_exists($calledClass::$__databaseTableClassName[$calledClass])) {
			$exists = Zend_Loader_Autoloader::autoload(
				$calledClass::$__databaseTableClassName[$calledClass]
			);
		}
		
		if ($exists) {
			$fullClassName = $calledClass::$__databaseTableClassName[$calledClass];
			$calledClass::$__databaseTable[$calledClass] = new $fullClassName();
		}
		else {
			throw new Troll_Model_Exception(
				$calledClass . ' database table not found! Tryed to load: ' .
				$calledClass::$__databaseTableClassName[$calledClass]
			);
		}
	}
	
	/**
	 * Casting values to PHP type (it is also for Zend_Db_Table inserts/update)
	 * @param Mixed $value
	 * @param int $type
	 */
	public static function cast($value, $type)
	{
		// Returning null
		if ($value == null && $value !== 0 && $value !== '0' && $value !== false) {
			return null;
		}
		// Other model
		if ($value instanceof Troll_Model) {
			return $value;
		}
		
		// Casting
		switch ($type) {
			
			case Troll_Model::TYPE_STRING:
				return (string) $value;
				break;
			
			case Troll_Model::TYPE_INTEGER:
				return (int) $value;
				break;
				
			case Troll_Model::TYPE_FLOAT:
				// If locale has "," as decimal separator
				return (float) str_replace(',', '.', (string) $value);
				break;
			
			case Troll_Model::TYPE_DATE:
			case Troll_Model::TYPE_DATETIME:
			case Troll_Model::TYPE_TIME:
				return ($value instanceof Zend_Date) ? $value : new Zend_Date($value);
				break;
			
			case Troll_Model::TYPE_BOOLEAN:
				return (boolean) $value;
				break;
				
			default:
				return (string) $value;
				break;
			
		}
	}
	
	/**
	 * Get the type of some attribute
	 * @param string $attribute
	 * @return int
	 */
	protected function _getTypeOf($attribute)
	{
		$calledClass = get_called_class();
		
		if (!isset($calledClass::$__dataTypes[$calledClass][$attribute])) {
			return Troll_Model::TYPE_UNKNOWN;
		}
		
		return $calledClass::$__dataTypes[$calledClass][$attribute];
	}
	
	/**
	 * Return current database table columns
	 * @return array
	 */
	protected static function _getDatabaseTableColumns()
	{
		$calledClass = get_called_class();
		return $calledClass::getDatabaseTable()->info(Zend_Db_Table_Abstract::COLS);
	}
	
	/**
	 * Retorna o filtro de underscore para camelcase
	 * 
	 * @return Zend_Filter_Word_UnderscoreToCamelCase
	 */
	protected static function _getUnderscoreToCamelCaseFilter()
	{
		if (null == self::$_underscoreToCamelCaseFilter) {
			self::$_underscoreToCamelCaseFilter = new Zend_Filter_Word_UnderscoreToCamelCase();
		}
		
		return self::$_underscoreToCamelCaseFilter;
	}
}
