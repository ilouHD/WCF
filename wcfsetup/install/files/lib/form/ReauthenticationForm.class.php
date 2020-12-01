<?php
namespace wcf\form;
use wcf\form\AbstractFormBuilderForm;
use wcf\system\application\ApplicationHandler;
use wcf\system\exception\IllegalLinkException;
use wcf\system\form\builder\field\TextFormField;
use wcf\system\form\builder\field\validation\FormFieldValidationError;
use wcf\system\form\builder\field\validation\FormFieldValidator;
use wcf\system\request\LinkHandler;
use wcf\system\WCF;
use wcf\util\HeaderUtil;

/**
 * Represents the reauthentication form.
 *
 * @author	Tim Duesterhus
 * @copyright	2001-2020 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WoltLabSuite\Core\Form
 * @since	5.4
 */
class ReauthenticationForm extends AbstractFormBuilderForm {
	const AVAILABLE_DURING_OFFLINE_MODE = true;
	
	/**
	 * @inheritDoc
	 */
	public $formAction = 'authenticate';
	
	/**
	 * @var string
	 */
	public $redirectUrl;
	
	/**
	 * @inheritDoc
	 */
	public function readParameters() {
		parent::readParameters();
		
		if (!empty($_GET['url']) && ApplicationHandler::getInstance()->isInternalURL($_GET['url'])) {
			$this->redirectUrl = $_GET['url'];
		}
		else {
			throw new IllegalLinkException();
		}
		
		if (!WCF::getSession()->needsReauthentication()) {
			$this->performRedirect();
		}
	}
	
	/**
	 * @inheritDoc
	 */
	protected function createForm() {
		parent::createForm();
		
		$this->form->appendChild(
			// TODO: Use a proper password field here.
			TextFormField::create('password')
				->addValidator(new FormFieldValidator('password', function (TextFormField $field) {
					// TODO: Ratelimit
					if (!WCF::getUser()->checkPassword($field->getValue())) {
						$field->addValidationError(
							new FormFieldValidationError('false', 'wcf.user.password.error.false')
						);
					}
				}))
		);
	}
	
	/**
	 * @inheritDoc
	 */
	public function save() {
		AbstractForm::save();
		
		WCF::getSession()->registerReauthentication();
		
		$this->saved();
	}
	
	/**
	 * @inheritDoc
	 */
	public function saved() {
		AbstractForm::saved();
		
		$this->performRedirect();
	}
	
	/**
	 * Returns to the redirectUrl.
	 */
	protected function performRedirect() {
		HeaderUtil::redirect($this->redirectUrl);
		exit;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function setFormAction() {
		$this->form->action(LinkHandler::getInstance()->getControllerLink(static::class, [
			'url' => $this->redirectUrl,
		]));
	}
	
	/**
	 * @inheritDoc
	 */
	public function assignVariables() {
		parent::assignVariables();
		
		WCF::getTPL()->assign([
			'redirectUrl' => $this->redirectUrl,
		]);
	}
}
