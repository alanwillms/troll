<?php
/**
 * Validates time fields
 * @author Alan Willms <alanwillms@gmail.com>
 * @since  30/03/2011
 */
class Troll_Validate_Time extends Zend_Validate_Abstract
{
    const INVALID      = 'dateInvalid';
    const INVALID_TIME = 'dateInvalidDate';
    const FALSEFORMAT  = 'dateFalseFormat';

    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $_messageTemplates = array(
        self::INVALID      => "Invalid type given, value should be string, integer, array or Zend_Date",
        self::INVALID_TIME => "'%value%' does not appear to be a valid time",
        self::FALSEFORMAT  => "'%value%' does not fit the time format '%format%'",
    );

    /**
     * @var array
     */
    protected $_messageVariables = array(
        'format'  => '_format'
    );

    /**
     * Optional format
     *
     * @var string|null
     */
    protected $_format;

    /**
     * Optional locale
     *
     * @var string|Zend_Locale|null
     */
    protected $_locale;

    /**
     * Sets validator options
     *
     * @param  string|Zend_Config $options OPTIONAL
     * @return void
     */
    public function __construct($options = array())
    {
        if ($options instanceof Zend_Config) {
            $options = $options->toArray();
        } else if (!is_array($options)) {
            $options = func_get_args();
            $temp['format'] = array_shift($options);
            if (!empty($options)) {
                $temp['locale'] = array_shift($options);
            }

            $options = $temp;
        }

        if (array_key_exists('format', $options)) {
            $this->setFormat($options['format']);
        }

        if (!array_key_exists('locale', $options)) {
            require_once 'Zend/Registry.php';
            if (Zend_Registry::isRegistered('Zend_Locale')) {
                $options['locale'] = Zend_Registry::get('Zend_Locale');
            }
        }

        if (array_key_exists('locale', $options)) {
            $this->setLocale($options['locale']);
        }
    }

    /**
     * Returns the locale option
     *
     * @return string|Zend_Locale|null
     */
    public function getLocale()
    {
        return $this->_locale;
    }

    /**
     * Sets the locale option
     *
     * @param  string|Zend_Locale $locale
     * @return Zend_Validate_DATETIME provides a fluent interface
     */
    public function setLocale($locale = null)
    {
        require_once 'Zend/Locale.php';
        $this->_locale = Zend_Locale::findLocale($locale);
        return $this;
    }

    /**
     * Returns the locale option
     *
     * @return string|null
     */
    public function getFormat()
    {
        return $this->_format;
    }

    /**
     * Sets the format option
     *
     * @param  string $format
     * @return Zend_Validate_DATETIME provides a fluent interface
     */
    public function setFormat($format = null)
    {
        $this->_format = $format;
        return $this;
    }

    /**
     * Defined by Zend_Validate_Interface
     *
     * Returns true if $value is a valid date of the format YYYY-MM-DD
     * If optional $format or $locale is set the date format is checked
     * according to Zend_Date, see Zend_Date::isDate()
     *
     * @param  string|array|Zend_Date $value
     * @return boolean
     */
    public function isValid($value)
    {
        if (!is_string($value) && !is_int($value) && !is_float($value) &&
            !is_array($value) && !($value instanceof Zend_Date)) {
            $this->_error(self::INVALID);
            return false;
        }

        $this->_setValue($value);

        if (($this->_format !== null) || ($this->_locale !== null) || is_array($value) ||
             $value instanceof Zend_Date) {
            require_once 'Zend/Date.php';
            if (!Zend_Date::isDate($value, $this->_format, $this->_locale)) {
                if ($this->_checkFormat($value) === false) {
                    $this->_error(self::FALSEFORMAT);
                } else {
                    $this->_error(self::INVALID_TIME);
                }
                return false;
            }
        } else {
            if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
                $this->_format = 'HH:mm:ss';
                $this->_error(self::FALSEFORMAT);
                $this->_format = null;
                return false;
            }

            list($hour, $minutes, $seconds) = sscanf($value, '%d:%d:%d');

            if ($hour > 24 || $minutes > 59 || $seconds > 59) {
            	$this->_error(self::INVALID_TIME);
                return false;
            }
            if ($hour < 0 || $minutes < 0 || $seconds < 0) {
            	$this->_error(self::INVALID_TIME);
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the given date fits the given format
     *
     * @param  string $value  Date to check
     * @return boolean False when date does not fit the format
     */
    private function _checkFormat($value)
    {
        if (((strpos($this->_format, 'H') !== false) or (strpos($this->_format, 'h') !== false)) and
            (!isset($parsed['hour']))) {
            // Hour expected but not found
            return false;
        }

        if ((strpos($this->_format, 'm') !== false) and (!isset($parsed['minute']))) {
            // Minute expected but not found
            return false;
        }

        if ((strpos($this->_format, 's') !== false) and (!isset($parsed['second']))) {
            // Second expected  but not found
            return false;
        }

        // Date fits the format
        return true;
    }
}
