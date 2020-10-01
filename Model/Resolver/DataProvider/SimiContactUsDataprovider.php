<?php

namespace Simi\SimiconnectorGraphQl\Model\Resolver\DataProvider;

use Magento\Contact\Model\ConfigInterface;
use Magento\Contact\Model\MailInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class SimiContactUsDataprovider {
	private $dataPersistor;
	private $mail;
	private $formKey;


	public function __construct(
		ConfigInterface $contactsConfig,
		MailInterface $mail,
		DataPersistorInterface $dataPersistor,
		\Magento\Framework\Data\Form\FormKey $formKey
	) {
		$this->mail          = $mail;
		$this->dataPersistor = $dataPersistor;
		$this->formKey       = $formKey;
	}

	public function contactUs( $name, $email, $phone, $message, $company ) {
		$thanks_message = [];
		try {
			$this->sendEmail( $name, $email, $phone, $message, $company );
		}
		catch ( LocalizedException $e ) {
		}
		$thanks_message['success_message'] = __( 'Thanks for contacting us with your comments and questions. We\'ll respond to you very soon.' );

		return $thanks_message;
	}

	private function sendEmail( $name, $email, $phone, $message, $company ) {
		$form_data              = [];
		$form_data['name']      = $name;
		$form_data['email']     = $email;
		$form_data['telephone'] = $phone;
		$form_data['comment']   = $message;
		$form_data['company']   = $company;
		$form_data['hideit']    = "";
		$form_data['form_key']  = $this->getFormKey();

		$this->mail->send(
			$email,
			[ 'data' => new DataObject( $form_data ) ]
		);
	}

	public function getFormKey() {
		return $this->formKey->getFormKey();
	}
}