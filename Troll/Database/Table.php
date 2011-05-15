<?php
/**
 * Improved database table class
 */
class Troll_Database_Table extends Zend_Db_Table
{
	/**
	 * Attributes mapping
	 * @var array
	 */
	protected $_attributesMapping;
	
	/**
	 * Functions that should be applied at selecting
	 * 
	 * <code>
	 * array (
	 *     'column' => 'schema.function_name(?)',
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
}