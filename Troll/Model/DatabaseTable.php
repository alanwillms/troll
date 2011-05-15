<?php
/**
 * Troll Model database table class
 * @author Alan Willms <alanwillms@gmail.com>
 */
class Troll_Model_DatabaseTable extends Zend_Db_Table
{
	/**
	 * Attributes mapping
	 * @var array
	 */
	protected $_attributesMapping;
	
	/**
	 * Validators of the columns
	 * @var array
	 */
	protected $_columnsValidators;
	
	/**
	 * PHP types of the columns
	 * @var array
	 */
	protected $_columnsTypes;
	
	/**
	 * Functions that should be applied at selecting
	 * 
	 * <code>
	 * array (
	 *     'column'  => 'schema.function_name(?)',
	 *     'column2' => "to_char(?, 'dd/mm/rrrr')",
	 * )
	 * </code>
	 * 
	 * @var array
	 */
	protected $_selectFilters;
	
	/**
	 * Return attributes mapping
	 * @return null|rray
	 */
	public function getAttributesMapping()
	{
		return $this->_attributesMapping;
	}
	
	/**
	 * Returns Zend validators
	 * @return array
	 */
	public function getValidators()
	{
		if (!isset($this->_columnsValidators)) {
			$this->_loadValidators();
		}
		return $this->_columnsValidators;
	}
	
	/**
	 * Get filter function for a column
	 * @param string $column
	 */
	public function getSelectFilter($column)
	{
		if (isset($this->_selectFilters[$column])) {
			return str_replace('?', $column, $this->_selectFilters[$column]);
		}
		
		return $column;
	}
	
	/**
	 * Returns the PHP type of
	 * @param unknown_type $column
	 */
	public function getTypeOf($column)
	{
		if (!isset($this->_columnsTypes)) {
			$this->_loadTypes();
		}
		
		if (!isset($this->_columnsTypes[$column])) {
			throw new Exception('Column "' . $column . '" does not exist');
		}
		
		return $this->_columnsTypes[$column];
	}
	
	
	/**
	 * Load columns PHP types
	 * @return array
	 */
	protected function _loadTypes()
	{
		$this->_columnsTypes = array();
		
		// Load database table columns
		$cols = $this->info(Zend_Db_Table_Abstract::METADATA);
		
		// Load types
		foreach ($cols as $col => $metaData) {
			$this->_columnsTypes[$col] = Troll_Model_TypeManager::toPhp($metaData, $this->getAdapter());
		}
		
		// Load relationships types
		if (is_array($this->_referenceMap) && count($this->_referenceMap)) {
			
			foreach ($this->_referenceMap as $col => $info) {
				
				if (isset($info['refType']) && ($info['refType'] == 'manyToMany' || $info['refType'] == 'oneToMany')) {
					$this->_columnsTypes[$col] = Troll_Model_TypeManager::ARRAY_TYPE; 
				}
				else {
					if (!isset($info['refClass'])) {
						throw new Exception('Undefined reference class for ' . $col);
					}
					$this->_columnsTypes[$col] = $info['refClass']; 
				}
			}
		}
	}
	
	/**
	 * Load validators classes
	 * @return array
	 */
	protected function _loadValidators()
	{
		// Load database table columns
		$cols = $this->info(Zend_Db_Table_Abstract::METADATA);
		
		// Load columns types
		$this->_loadTypes();
		
		// Load validators
		foreach ($cols as $col => $data) {
			
			if (!isset($this->_columnsValidators[$col])) {
				$this->_columnsValidators[$col] = array();
			}
			
			// TODO Detect only primary keys WITH autoincrement or sequences
			if (!$data['PRIMARY']) {
				
				// It is not "nullable" and there is no "default value"
				if (!$data['NULLABLE'] && ($data['DEFAULT'] === null || $data['DEFAULT'] === '')) {
					$this->_columnsValidators[$col][] = 'NotEmpty';
				}
				if ($data['LENGTH']) {
					
					// String length for string values
					if ($this->_columnsTypes[$col] == Troll_Model_TypeManager::STRING_TYPE) {
						// May be it returns -1 - I will cut "0" too
						if ($data['LENGTH'] > 0) {
							$this->_columnsValidators[$col]['StringLength'] = array('max' => $data['LENGTH']);
						}
					}
				}
				
				// Types validators
				$type = $this->_columnsTypes[$col];
				
				if ($type == Troll_Model_TypeManager::INTEGER_TYPE) {
					$this->_columnsValidators[$col][] = 'Int';
				}
				elseif ($type == Troll_Model_TypeManager::FLOAT_TYPE) {
					$this->_columnsValidators[$col][] = 'Float';
				}
				elseif ($type == Troll_Model_TypeManager::DATE_TYPE) {
					$this->_columnsValidators[$col][] = 'Date';
				}
				elseif ($type == Troll_Model_TypeManager::DATETIME_TYPE) {
					$this->_columnsValidators[$col][] = 'Troll_Validate_DateTime';
				}
				elseif ($type == Troll_Model_TypeManager::TIME_TYPE) {
					$this->_columnsValidators[$col][] = 'Troll_Validate_Time';
				}
			}
			
		}
	}
}