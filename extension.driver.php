<?php
	/*
	Copyight: Deux Huit Huit 2012-2013
	Solutions Nitriques 2011
	License: MIT, see the LICENCE file
	*/

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	/**
	 *
	 * Duplicate Section Decorator/Extension
	 * Permits admin to duplicate/clone a section data model
	 * @author nicolasbrassard, pascalpiche
	 *
	 */
	class extension_duplicate_section extends Extension {

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
					'delegate' => 'AdminPagePreGenerate',
					'callback' => '__action'
				)
			);
		}
		
		/**
		 * 
		 * Fired on each backend page, detect when it's time to append elements into the backend page
		 * @param array $context
		 */
		public function appendElementBelowView(Array &$context) {
			// only if logged in
			// this prevents the clone button from appearing on the login screen
			if (Administration::instance()->isLoggedIn()) {
				
				$c = Administration::instance()->getPageCallback();
				
				// when editing a section
				if ($c['driver'] == 'blueprintssections' && $c['context'][0] == 'edit') {
					
					$form = Administration::instance()->Page->Form;
					
					$button_wrap = new XMLELement('div', NULL, array(
						'id' => 'duplicate-section'
					));
					
					$btn = new XMLElement('button', __('Clone'), array(
						'id' => 'duplicate-section-clone',
						'class' => 'button',
						'name' => 'action[clone]',
						'type' => 'submit',
						'title' => __('Duplicate this section'),
						'style' => 'margin-left: 10px; background: #81B934',
						'onclick' => "jQuery('fieldset.settings').empty();return true;"
					));
					
					$button_wrap->appendChild($btn);
					
					// add content to the right div
					$div_action = self::getChildrenWithClass($form, 'div', 'actions');
					
					if ($div_action != NULL) {
						$div_action->appendChild($button_wrap);
					}
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
		 * This method search to the field referenced by the $handle param
		 * and returns its id.
		 *
		 * @param array $fields
		 * @param string $handle
		 */
		private static function getNewFieldId(Array &$fields, $handle) {
			foreach ($fields as &$field) {
				var_dump($field->get());
				if ($field->get('element_name') == $handle) {
					return intval($field->get('id'));
				}
			}
			return NULL;
		}
		
		/**
		 * 
		 * Delegate AdminPagePreGenerate that handles the click of the 'clone' button and append the button in the form
		 * @param array $context
		 */
		public function __action(Array &$context) {	
			// append button
			self::appendElementBelowView($context);
			
			// if the clone button was hit
			if (is_array($_POST['action']) && isset($_POST['action']['clone'])) {
				$c = Administration::instance()->getPageCallback();
				
				$section_id = $c['context'][1];
				
				// original section
				$section = SectionManager::fetch($section_id);
				
				if ($section != null) {
					// get its settings
					$section_settings = $section->get();
					
					// remove id
					unset($section_settings['id']);
					
					// new name
					$section_settings['name'] .= ' ' . time();
					$section_settings['handle'] = Lang::createHandle($section_settings['name']);
					
					// save it
					$new_section_id = SectionManager::add($section_settings);
					
					// if the create new section was successful
					if (is_numeric($new_section_id) && $new_section_id > 0) {
						
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
								$f = FieldManager::create($fs['type']);
								
								// set its settings
								$f->setArray($fs);
								
								// save
								$f->commit();
							}
						}
						
						// get this section relations
						$relationships = SectionManager::fetchAssociatedSections($section_id, false);
						
						// fetch the new fields
						$new_section = SectionManager::fetch($new_section_id);
						$new_fields = $new_section->fetchFields();
						
						if (is_array($relationships)) {
							// re-create all of those relations
							foreach ($relationships as $relation) {
								var_dump($relation);die;
								
								$new_section_field_id = self::getNewFieldId($new_fields, $relation['handle']);
								
								if (is_numeric($new_section_field_id) && $new_section_field_id > 0) {
									SectionManager::createSectionAssociation(
										$new_section_id, // the new parent
										$relation['child_field_id'],
										$new_section_field_id, // the new parent field
										$relation['show_association']
									);
									var_dump($child_field_id);
								} else {
									throw new Exception(sprintf("Could not find the '%s' field", $relation['handle']));
								}
							}
						}
						die;
						
						// redirect to the new cloned section
						redirect(sprintf(
							'%s/blueprints/sections/edit/%s/',
							SYMPHONY_URL,
							$new_section_id
						));
						
						// stop everything now
						exit;
						
					} else {
						throw new Exception("Could not create a new section");
					}
				} else {
					Throw new Exception("Section not found");
				}
			} // end clonde button
		}
	}