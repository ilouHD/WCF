<?php
declare(strict_types=1);
namespace wcf\system\form\builder\field;
use wcf\data\DatabaseObjectList;
use wcf\data\ITitledObject;
use wcf\system\form\builder\field\validation\FormFieldValidationError;
use wcf\system\WCF;
use wcf\util\ClassUtil;

/**
 * Provides default implementations of `ISelectionFormField` methods.
 * 
 * @author	Matthias Schmidt
 * @copyright	2001-2018 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WoltLabSuite\Core\System\Form\Builder\Field
 * @since	3.2
 */
trait TSelectionFormField {
	/**
	 * possible options to select
	 * @var	null|array
	 */
	protected $__options;
	
	/**
	 * possible values of the selection
	 * @var	array 
	 */
	protected $possibleValues = [];
	
	/**
	 * Returns the possible options of this field.
	 * 
	 * @return	array
	 * 
	 * @throws	\BadMethodCallException		if no options have been set
	 */
	public function getOptions(): array {
		return $this->__options;
	}
	
	/**
	 * Returns the field value saved in the database.
	 * 
	 * This method is useful if the actual returned by `getValue()`
	 * cannot be stored in the database as-is. If the return value of
	 * `getValue()` is, however, the actual value that should be stored
	 * in the database, this method is expected to call `getValue()`
	 * internally.
	 * 
	 * @return	mixed
	 */
	public function getSaveValue() {
		if (empty($this->getValue()) && array_search($this->getValue(), $this->possibleValues) === 0 && $this instanceof INullableFormField && $this->isNullable()) {
			return null;
		}
		
		return parent::getSaveValue();
	}
	
	/**
	 * Returns `true` if this node is available and returns `false` otherwise.
	 * 
	 * If the node's availability has not been explicitly set, `true` is returned.
	 * 
	 * @return	bool
	 * 
	 * @see		IFormNode::available()
	 */
	public function isAvailable(): bool {
		// selections without any possible values are not available
		return !empty($this->possibleValues) && parent::isAvailable();
	}
	
	/**
	 * Sets the possible options of this selection and returns this field.
	 * 
	 * Note: If PHP considers the key of the first selectable option to be empty
	 * and the this field is nullable, then the save value of that key is `null`
	 * instead of the given empty value.
	 * 
	 * @param	array|callable		$options	selectable options or callable returning the options
	 * @return	static					this field
	 * 
	 * @throws	\InvalidArgumentException		if given options are no array or callable or otherwise invalid
	 * @throws	\UnexpectedValueException		if callable does not return an array
	 */
	public function options($options): ISelectionFormField {
		if (!is_array($options) && !is_callable($options) && !($options instanceof DatabaseObjectList)) {
			throw new \InvalidArgumentException("The given options are neither an array, a callable nor an instance of '" . DatabaseObjectList::class . "', " . gettype($options) . " given.");
		}
		
		if (is_callable($options)) {
			$options = $options();
			
			if (!is_array($options) && !($options instanceof DatabaseObjectList)) {
				throw new \UnexpectedValueException("The options callable is expected to return an array or an instance of '" . DatabaseObjectList::class . "', " . gettype($options) . " returned.");
			}
			
			return $this->options($options);
		}
		else if ($options instanceof DatabaseObjectList) {
			// automatically read objects
			if ($options->objectIDs === null) {
				$options->readObjects();
			}
			
			$dboOptions = [];
			foreach ($options as $object) {
				if (!ClassUtil::isDecoratedInstanceOf($object, ITitledObject::class)) {
					throw new \InvalidArgumentException("The database objects in the passed list must implement '" . ITitledObject::class . "'.");
				}
				if (!$object::getDatabaseTableIndexIsIdentity()) {
					throw new \InvalidArgumentException("The database objects in the passed list must must have an index that identifies the objects.");
				}
				
				$dboOptions[$object->getObjectID()] = $object->getTitle();
			}
			
			$options = $dboOptions;
		}
		
		// validate options and read possible values
		$validateOptions = function(array &$array) use (&$validateOptions) {
			foreach ($array as $key => $value) {
				if (is_array($value)) {
					if (static::supportsNestedOptions()) {
						$validateOptions($value);
					}
					else {
						throw new \InvalidArgumentException("Option '{$key}' must not be an array.");
					}
				}
				else {
					if (!is_string($value) && !is_numeric($value)) {
						throw new \InvalidArgumentException("Options contain invalid label of type " . gettype($value) . ".");
					}
					
					if (in_array($key, $this->possibleValues)) {
						throw new \InvalidArgumentException("Options values must be unique, but '" . $key . "' appears at least twice as value.");
					}
					
					$this->possibleValues[] = $key;
					
					// fetch language item value
					if (preg_match('~^([a-zA-Z0-9-_]+\.){2,}[a-zA-Z0-9-_]+$~', (string) $value)) {
						$array[$key] = WCF::getLanguage()->getDynamicVariable($value);
					}
				}
			}
		};
		
		$validateOptions($options);
		
		$this->__options = $options;
		
		return $this;
	}
	
	/**
	 * @inheritDoc
	 */
	public function readValue(): IFormField {
		if ($this->getDocument()->hasRequestData($this->getPrefixedId())) {
			$value = $this->getDocument()->getRequestData($this->getPrefixedId());
			
			if (is_string($value)) {
				$this->__value = $value;
			}
		}
		
		return $this;
	}
	
	/**
	 * @inheritDoc
	 */
	public function validate() {
		if (!in_array($this->getValue(), $this->possibleValues)) {
			$this->addValidationError(new FormFieldValidationError('invalidValue', 'wcf.global.form.error.noValidSelection'));
		}
		
		parent::validate();
	}
	
	/**
	 * @inheritDoc
	 */
	public function value($value): IFormField {
		// ignore `null` as value which can be passed either for nullable
		// fields or as value if no options are available
		if ($value === null) {
			return $this;
		}
		
		if (!in_array($value, $this->possibleValues)) {
			throw new \InvalidArgumentException("Unknown value '{$value}'");
		}
		
		return parent::value($value);
	}
	
	/**
	 * Returns `true` if the field class supports nested options and `false` otherwise.
	 * 
	 * @return	bool
	 */
	protected static function supportsNestedOptions(): bool {
		return true;
	}
}
