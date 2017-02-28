<?php

class SimpleContactPage extends Page {
	
	private static $icon = "simplecontactpage/images/icon.png";

	private static $db = array(
		'From' => 'Text',
		'To' => 'Text',
		'Bcc' => 'Text',
		'ConfirmationMessage' => 'HTMLText',
		'Recaptcha' => 'Boolean',
		'FileUpload' => 'Boolean',
	);
	
	
	private static $has_many = array(
		'SimpleContactSubmissions' => 'SimpleContactSubmission'
	);
	
	function getCMSFields() { 
	
		$fields = parent::getCMSFields(); 

		$conf=GridFieldConfig_RelationEditor::create();
		$conf->addComponent(new GridFieldDeleteAction('unlinkrelation'));
		$conf->removeComponentsByType($conf->getComponentByType('GridFieldAddNewButton'));
		$conf->removeComponentsByType($conf->getComponentByType('GridFieldDeleteAction'));
		
		$grid = new GridField('SimpleContactSubmission', _t('SimpleContactPage.SUBMISSIONS','Submissions'),
            		$this->SimpleContactSubmissions()->sort('Created',DESC), $conf
		);   
	
		$fields->addFieldToTab('Root.'. _t('SimpleContactPage.FORM','Form') , new EmailField('From', _t('SimpleContactPage.FROM','From:') ));
		$fields->addFieldToTab('Root.'. _t('SimpleContactPage.FORM','Form') , new EmailField('To', _t('SimpleContactPage.TO','TO:') ));
		$fields->addFieldToTab('Root.'. _t('SimpleContactPage.FORM','Form') , new EmailField('Bcc', _t('SimpleContactPage.BCC','Bcc to:') ));
		$fields->addFieldToTab('Root.'. _t('SimpleContactPage.FORM','Form') , new CheckboxField('Recaptcha', 'Recaptcha') );
		$fields->addFieldToTab('Root.'. _t('SimpleContactPage.FORM','Form') , new CheckboxField('FileUpload', _t('SimpleContactPage.UPLOADFILE','Upload a file')) );
		$fields->addFieldToTab('Root.'. _t('SimpleContactPage.FORM','Form') , $editor = new HTMLEditorField('ConfirmationMessage', _t('SimpleContactPage.CONFIRMATION','Confirmation message:') ));
			$editor->setRows(4);
		$fields->addFieldToTab('Root.'. _t('SimpleContactPage.SUBMISSIONS','Submissions') , $grid);


		     
		return $fields; 
	}
	
}

class SimpleContactPage_Controller extends Page_Controller {

	private static $allowed_actions = array(
		'ContactForm',
		'finished'
	);

	public function ContactForm() {
		
		$fields = new FieldList(
		TextField::create('Name', '')->setAttribute('placeholder',  _t('SimpleContactPage.NAME','Your name') ),
			EmailField::create('Email', '')->setAttribute('placeholder', _t('SimpleContactPage.EMAIL','Email') ),
			TextField::create('Subject', '')->setAttribute('placeholder', _t('SimpleContactPage.SUBJECT','Subject') ),
			TextAreaField::create('Message', '')->setAttribute('placeholder', _t('SimpleContactPage.MESSAGE','Your message') )->setRows(10)
		);
		
		if ($this->FileUpload) {
			$fields->push(FileField::create('File', '')->setAttribute('placeholder', 'File','File'));
		}
	
	
		$actions = new FieldList(
			FormAction::create("ContactFormSubmit")->setTitle(_t('SimpleContactPage.SUBMIT','Submit'))
		);
	
		$required = new RequiredFields(array(
			'Name',
			'Email',
			'Subject',
			'Message',
		));
	
		$form = new Form($this, 'ContactForm', $fields, $actions, $required);
	        
		if ($this->Recaptcha) {
			$form->enableSpamProtection()
			->fields()->fieldByName('Captcha')
			->setTitle(_t('SimpleContactPage.CAPTCHAPROTECTION', "Captcha Spam protection") );
		}
			
		return $form;
		
	}

	public function ContactFormSubmit($data, $form) {
		
		if ($this->From && $this->To) {
			
			$dbsubmit = new SimpleContactSubmission();
			$dbsubmit->Name = $data['Name'];
			$dbsubmit->Email = $data['Email'];
			$dbsubmit->Message = $data['Message'];
			$dbsubmit->Subject = $data['Subject'];
			$dbsubmit->PageID = $this->ID;
			$dbsubmit->write();
				
			// Send a mail
				
			$subject=_t('SimpleContactPage.SUBJECTFROM','Message coming from page: ').$this->Title;
					
			$body = _t('SimpleContactPage.EMAILNAME','Name: ').$data['Name']." (".$data['Email'].") <br><br>".
				_t('SimpleContactPage.EMAILSUBJECT','Subject: ').$data['Subject']."<br><br>".
				"Message: ".$data['Message'];


			$email = new Email();
			if ($this->From) {
				$email->setFrom($this->From);	
				$email->addCustomHeader('Reply-To', $data['Email']);
			} else {
				$email->setFrom($data['Email']);	
				
			}
			$email->setTo($this->To);
		
			if ($this->FileUpload) {
				if (isset($_FILES["File"]) && is_uploaded_file($_FILES["File"]["tmp_name"])) {
					$email->attachFile($_FILES["File"]["tmp_name"], $_FILES["File"]["name"]);
				}		
			}
			

			if ($this->Bcc) {
				$email->setBcc($this->Bcc);
			}
				
			$email->setSubject($subject);
			$email->setBody($body);
				
				//->setTemplate('MyCustomEmail')
				//->populateTemplate(new ArrayData(array(
				//	'Member' => Member::currentUser(),
				//	'Link' => $link
				//)))

			$email->send();

			if ($this->ConfirmationMessage) {
				$Content = $this->ConfirmationMessage;
				$_SESSION['Content']=$Content;
			} else {
				$_SESSION['Content']=$Content = _t('SimpleContactPage.CONFIRMATIONMESSAGE','Thank you, we have receive your message');
			}

			$items = array(
				'Content' =>  $_SESSION['Content'],
				'ContactForm' => "",
				'data' => "",
			);

			$items = array( 
				'Content' => $content,
				'ContactForm' => "",
			);

			$_SESSION['SUBMIT']=true;
	
			//return $this->customise($items)->renderWith(array('Page'));
			$this->redirect($this->Link( _t('SimpleContactPage.CONTROLLER', 'finished') ));
				
		} else {
				
			$content =  _t('SimpleContactPage.PROBLEM', 'A problem occurred during submission. Please check the configuration.');
				
			$items = array(
				'Content' => $content,
				'ContactForm' => "",
			);

			return $this->customise($items)->renderWith(array('Page'));
				
		}
			
	}
	
	
	public function finished() {
		
		if ($_SESSION['SUBMIT']) {

			$items = array( 
				'Content' => $_SESSION['Content'],
				'ContactForm' => "",
			);
						
			unset($_SESSION['SUBMIT']);
				
			return $this->customise($items)->renderWith('Page','SimpleContactPage');
				
		} else {
				
			return $this->redirectBack();	
			
		}

	}

	public function init() {

		parent::init();
	
		$translatedAction	= _t('SimpleContactPage.CONTROLLER', 'finished');
		$urlHandlers		= $this->config()->url_handlers;
		
		$translatedUrlHandlers = array(
			$translatedAction   => 'finished',
		);
		
		Config::inst()->update(
			$this->class, 
			'url_handlers', 
			$translatedUrlHandlers + $urlHandlers
		);
	
	}
	
}



class SimpleContactSubmission extends DataObject {
	
	private static $db = array(
		"Name" => "Text",
		"Subject" => "Text",
		"Message" => "Text",
		"Email" => "Text",
	);	

	private static $has_one = array(
		"Page" => "Page",
	);	


	public static $summary_fields = array(
		'ID',
		'Email',
		'Subject',
		'Created',
	);
  
	function getCMSFields() { 
		
		$fields = parent::getCMSFields(); 
		     
	        $fields->addFieldToTab('Root.Main', new ReadonlyField('Created', 'Created'));
	        $fields->addFieldToTab('Root.Main', new ReadonlyField('Name', 'Name'));
	        $fields->addFieldToTab('Root.Main', new ReadonlyField('Email', 'Email'));
	        $fields->addFieldToTab('Root.Main', new ReadonlyField('Subject', 'Subject'));
	        $fields->addFieldToTab('Root.Main', new ReadonlyField('Message', 'Message'));
		
	
	
		return $fields; 
	}


}
