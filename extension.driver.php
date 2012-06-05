<?php
	/*
	Copyight: Solutions Nitriques 2011
	License: MIT, see the LICENCE file
	*/

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	/**
	 *
	 * Duplicate Section Decorator/Extension
	 * Permits admin to duplicate/clone a section data model
	 * @author nicolasbrassard
	 *
	 */
	class extension_duplicate_section extends Extension {

		/*
		 * Name of the extension
		 * @var string
		 *
		const EXT_NAME = 'Duplicate Section';

		/*
		 * Credits for the extension
		 *
		public function about() {
			return array(
				'name'			=> self::EXT_NAME,
				'version'		=> '1.0',
				'release-date'	=> '2011-07-08',
				'author'		=> array(
					'name'			=> 'Solutions Nitriques',
					'website'		=> 'http://www.nitriques.com/open-source/',
					'email'			=> 'open-source (at) nitriques.com'
				),
				'description'	=> __('Easily duplicate/clone your section parameters and fields'),
				'compatibility' => array(
					'2.2.1' => true,
					'2.2' => true
				)
	 		);
		}*/

		/**
		 *
		 * Symphony utility function that permits to
		 * implement the Observer/Observable pattern.
		 * We register here delegate that will be fired by Symphony
		 */
		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/backend/',
					'delegate' => 'AppendElementBelowView',
					'callback' => 'appendElementBelowView'
				),
				array(
					'page' => '/backend/',
					'delegate' => 'AdminPagePreGenerate',
					'callback' => '__action'
				)
			);
		}
		
		/**
		 * 
		 * Delegate fired when it's time to append elements into the backend page
		 * @param array $context
		 */
		public function appendElementBelowView(Array &$context) {
			$c = Administration::instance()->getPageCallback();
			
			// when editing a section
			if ($c['driver'] == 'blueprintssections' && $c['context'][0] == 'edit') {
				
				$form = Administration::instance()->Page->Form;
				
				$button_wrap = new XMLELement('div', NULL, array(
					'id' => 'duplicate-section',
				));
				
				
				$btn = new XMLElement('button', __('Clone'), array(
					'id' => 'duplicate-section-clone',
					'class' => 'button',
					'name' => 'action[clone]',
					'type' => 'submit',
					'title' => __('Duplicate this section'),
					'style' => 'margin-left: 10px; background: #81B934'
				));
				
				$button_wrap->appendChild($btn);
				
				// add content to the right div
				$div_action = self::getChildrenWithClass($form, 'div', 'actions');
				
				if ($div_action != NULL) {
					$div_action->appendChild($button_wrap);
				}
			}

		}
		
		/**
		 * 
		 * Recursive search for an Element with the right name and css class.
		 * Stops at fists match
		 * @param XMLElement $rootElement
		 * @param string $tagName
		 * @param string $className
		 */
		private static function getChildrenWithClass($rootElement, $tagName, $className) {
			if (! ($rootElement) instanceof XMLElement) {
				return NULL; // not and XMLElement
			}
			
			// contains the right css class and the right node name
			if (strpos($rootElement->getAttribute('class'), $className) > -1 && $rootElement->getName() == $tagName) {
				return $rootElement;
			}
			
			// recursive search in child elements
			foreach ($rootElement->getChildren() as $child) {
				$res = self::getChildrenWithClass($child, $tagName, $className);
				
				if ($res != NULL) {
					return $res;
				}
			}
			
			return NULL;
		}

		
		/**
		 * 
		 * Method that handles the click of the 'clone' button
		 * @param array $context
		 */
		public function __action(Array &$context) {	

			// if the clone button was hit
			if (is_array($_POST['action']) && isset($_POST['action']['clone'])) {
				$c = Administration::instance()->getPageCallback();
				
				$section_id = $c['context'][1];
				
				$sm = new SectionManager($context['parent']);
				$fm = new FieldManager($context['parent']);
				
				$section = $sm->fetch($section_id);
				
				if ($section != null) {
					$section_settings = $section->get();
				
					// remove id
					unset($section_settings['id']);
					
					// new name
					$section_settings['name'] .= ' ' . time();
					$section_settings['handle'] = Lang::createHandle($section_settings['name']);
					
					// save it
					$new_section_id = $sm->add($section_settings);
					
					
					// if the create new section was successful
					if ( is_numeric($new_section_id) && $new_section_id > 0) {
						
					
						// get the fields of the section
						$fields = $section->fetchFields();
						
						// if we have some
						if (is_array($fields)) {
		
							// copy each field
							foreach ($fields as &$field) {
								
								// get field settings
								$fs = $field->get();
								
								// un set the current id
								unset($fs['id']);
								
								// set the new section as the parent
								$fs['parent_section'] = $new_section_id;
								
								// create the new field
								$f = $fm->create($fs['type']);
								
								// set its settings
								$f->setArray($fs);
								
								// save
								$f->commit();
							}
						}

						// redirect to the new cloned section
						redirect(sprintf(
							'%s/blueprints/sections/edit/%s/',
							SYMPHONY_URL,
							$new_section_id
						));
						
						// stop everything now
						exit;
					}
				}
			}
		}
	}