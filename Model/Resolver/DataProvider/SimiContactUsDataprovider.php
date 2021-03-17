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

class SimiContactUsDataprovider
{
    private $dataPersistor;
    private $mail;
    private $formKey;


    public function __construct(
        ConfigInterface $contactsConfig,
        MailInterface $mail,
        DataPersistorInterface $dataPersistor,
        \Magento\Framework\Data\Form\FormKey $formKey
    )
    {
        $this->mail = $mail;
        $this->dataPersistor = $dataPersistor;
        $this->formKey = $formKey;
    }

    public function contactUs($input)
    {
        $thanks_message = [];
        try {
            $this->sendEmail($input);
        } catch (LocalizedException $e) {
        }
        $thanks_message['success_message'] = __('Thanks for contacting us with your comments and questions. We\'ll respond to you very soon.');

        return $thanks_message;
    }

    private function sendEmail($input)
    {
        $form_data = $input;
        $form_data['hideit'] = "";
        $form_data['form_key'] = $this->getFormKey();

        $this->mail->send(
            $input['email'],
            ['data' => new DataObject($form_data)]
        );
    }

    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }
}