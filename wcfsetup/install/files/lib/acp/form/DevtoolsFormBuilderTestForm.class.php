<?php
namespace wcf\acp\form;
use wcf\form\AbstractForm;
use wcf\system\exception\UserInputException;
use wcf\system\form\builder\container\FormContainer;
use wcf\system\form\builder\container\TabFormContainer;
use wcf\system\form\builder\container\TabMenuFormContainer;
use wcf\system\form\builder\field\data\CustomFormFieldDataProcessor;
use wcf\system\form\builder\field\TextFormField;
use wcf\system\form\builder\field\validation\FormFieldValidationError;
use wcf\system\form\builder\field\validation\FormFieldValidator;
use wcf\system\form\builder\field\BooleanFormField;
use wcf\system\form\builder\field\IntegerFormField;
use wcf\system\form\builder\FormDocument;
use wcf\system\form\builder\IFormDocument;
use wcf\system\request\LinkHandler;
use wcf\system\WCF;

/**
 * Test form for testing the new form builder API
 * 
 * TODO: delete file again after finishing the form builder API
 * 
 * @author	Matthias Schmidt
 * @copyright	2001-2018 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WoltLabSuite\Core\Acp\Form
 * @since	3.2
 */
class DevtoolsFormBuilderTestForm extends AbstractForm {
	/**
	 * form data
	 * @var	array
	 */
	public $data;
	
	/**
	 * @var	IFormDocument
	 */
	public $form;
	
	/**
	 * @inheritDoc
	 */
	public function __run() {
		$this->buildForm();
		
		parent::__run();
	}
	
	/**
	 * @inheritDoc
	 */
	public function assignVariables() {
		parent::assignVariables();
		
		WCF::getTPL()->assign([
			'data' => $this->data,
			'form' => $this->form
		]);
	}
	
	/**
	 * Builds the form.
	 */
	public function buildForm() {
		$this->form = FormDocument::create('testForm')
			->action(LinkHandler::getInstance()->getLink('DevtoolsFormBuilderTest'))
			->attribute('data-foo', 'bar')
			->attribute('data-baz', true)
			->attribute('data-bar', 12)
			->addClass('formContainer');
		
		$this->form->appendChildren([
			FormContainer::create('general')
				->label('wcf.global.title')
				->description('wcf.global.description')
				->addClass('someSection')
				->appendChildren([
					TextFormField::create('title')
						->label('wcf.global.title')
						->i18n()
						->i18nRequired()
						->required(),
					BooleanFormField::create('isDisabled')
						->label('Foo is Disabled')
						->description('If Foo is disabled, it is indeed disabled.')
						->addValidator(new FormFieldValidator('notSelected', function(BooleanFormField $field) {
							if (!$field->getValue()) {
								$field->addValidationError(new FormFieldValidationError(
									'foo',
									'You have to select Yes for this field!'
								));
							}
						}))
				]),
			TabMenuFormContainer::create('tabMenu')
				->appendChildren([
					TabFormContainer::create('tab1')
						->label('Tab 1')
						->appendChild(
							FormContainer::create('fooGeneral')
								->appendChildren([
									IntegerFormField::create('counter')
										->label('Some Counter')
										->minimum(10)
										->maximum(100)
										->value(20)
										->suffix('wcf.acp.option.suffix.days')
								])
						),
					TabFormContainer::create('tab2')
						->label('Tab 2')
				])
		]);
		
		$this->form->build();
		
		$this->form->getDataHandler()->add(new CustomFormFieldDataProcessor('isDisabledToString', function(IFormDocument $document, array $parameters) {
			unset($parameters['data']['isDisabled']);
			
			/** @var null|BooleanFormField $node */
			$node = $document->getNodeById('isDisabled');
			$parameters['isDisabled'] = $node->getValue() ? 'true' : 'false';
			
			return $parameters;
		}));
	}
	
	/**
	 * @inheritDoc
	 */
	public function readFormParameters() {
		parent::readFormParameters();
		
		$this->form->readValues();
	}
	
	/**
	 * @inheritDoc
	 */
	public function save() {
		parent::save();
		
		$this->data = $this->form->getData();
		
		$this->buildForm();
		
		WCF::getTPL()->assign('success', true);
	}
	
	/**
	 * @inheritDoc
	 */
	public function validate() {
		parent::validate();
		
		$this->form->validate();
		
		if ($this->form->hasValidationErrors()) {
			throw new UserInputException($this->form->getPrefixedId());
		}
	}
}
