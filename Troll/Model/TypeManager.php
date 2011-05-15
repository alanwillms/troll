<?php
/**
 * Manage types conversion between PHP and different databases
 * 
 * @author Alan Willms <alanwillms@gmail.com>
 */
class Troll_Model_TypeManager
{
	/**
	 * PHP data types
	 * @var integer
	 */
	const UNKNOWN_TYPE  = 'unknown_type';
	const STRING_TYPE   = 'string';
	const INTEGER_TYPE  = 'integer';
	const FLOAT_TYPE    = 'float';
	const DATETIME_TYPE = 'date_time';
	const DATE_TYPE     = 'date';
	const TIME_TYPE     = 'time';
	const BOOLEAN_TYPE  = 'boolean';
	const OBJECT_TYPE   = 'object';
	const ARRAY_TYPE    = 'array';
	
	public static function cast($value, $type)
	{
		// Returning null
		if ($value == null && $value !== 0 && $value !== '0' && $value !== false) {
			if ($type == self::ARRAY_TYPE) {
				return array();
			}
			return null;
		}
		// Other model
		if ($value instanceof Troll_Model) {
			return $value;
		}
		
		// Casting
		switch ($type) {
			
			case self::STRING_TYPE:
				return (string) $value;
				break;
			
			case self::INTEGER_TYPE:
				return (int) $value;
				break;
				
			case self::FLOAT_TYPE:
				// If locale has "," as decimal separator
				return (float) str_replace(',', '.', (string) $value);
				break;
			
			case self::DATE_TYPE:
			case self::DATETIME_TYPE:
			case self::TIME_TYPE:
				return ($value instanceof Zend_Date) ? $value : new Zend_Date($value);
				break;
			
			case self::BOOLEAN_TYPE:
				return (boolean) $value;
				break;
				
			case self::ARRAY_TYPE:
				return (array) $value;
				break;
				
			default:
				return (string) $value;
				break;
			
		}
		
		return $value;
	}
	
	/**
	 * Convert a database type to a PHP type
	 * @param array           $colMetaData     Meta data from Zend_Db_Table->info('metadata')
	 */
	public static function toPhp($colMetaData)
	{
		// Start as "unknown"
		$type = self::UNKNOWN_TYPE;
		
		// String
		if ((false !== strpos($colMetaData['DATA_TYPE'], 'char'))
			|| (false !== strpos($colMetaData['DATA_TYPE'], 'text'))
			|| ($colMetaData['DATA_TYPE'] == 'VARCHAR2')
			) {
			$type = self::STRING_TYPE;
		}
		// Integer
		elseif (false !== strpos($colMetaData['DATA_TYPE'], 'int')) {
			
			// FIXME Quando resolver arrays, resolver isso
			if (preg_match('/^_/', $colMetaData['DATA_TYPE'])) {
				$type = self::STRING_TYPE;
			}
			else {
				$type = self::INTEGER_TYPE;
			}
		}
		// Float
		elseif ((false !== strpos($colMetaData['DATA_TYPE'], 'float'))
			|| (false !== strpos($colMetaData['DATA_TYPE'], 'double'))
			|| (false !== strpos($colMetaData['DATA_TYPE'], 'NUMBER') && $colMetaData['PRECISION'] > 0)
			) {
			$type = self::FLOAT_TYPE;
		}
		// Time
		elseif ($colMetaData['DATA_TYPE'] == 'time' || $colMetaData['DATA_TYPE'] == 'timetz') {
			$type = self::TIME_TYPE;
		}
		// Date
		elseif ($colMetaData['DATA_TYPE'] == 'date' || $colMetaData['DATA_TYPE'] == 'DATE') {
			$type = self::DATE_TYPE;
		}
		// Date time
		elseif ((false !== strpos($colMetaData['DATA_TYPE'], 'datetime'))
			|| (false !== strpos($colMetaData['DATA_TYPE'], 'timestamp'))
			) {
			$type = self::DATETIME_TYPE;
		}
		// Boolean
		elseif (in_array($colMetaData['DATA_TYPE'], array('boolean', 'bool'))) {
			$type = self::BOOLEAN_TYPE;
		}
		
		return $type;
	}
}