<?php

class extension_resave_entries extends Extension
{
	public function about()
	{
		return array(
			'name' => 'Resave entries',
			'version' => '0.1',
			'release-date' => '2011-06-06',
			'author' => array(
				'name' => 'Marco Sampellegrini',
				'email' => 'm@rcosa.mp'
			)
		);
	}
	
	public function getSubscribedDelegates()
	{
		return array(
			array(
				'page' => '/system/preferences/',
				'delegate' => 'AddCustomPreferenceFieldsets',
				'callback' => 'AddCustomPreferenceFieldsets',
			),
			array(
				'page' => '/backend/',
				'delegate' => 'InitaliseAdminPageHead',
				'callback' => 'InitaliseAdminPageHead'
			)
		);
	}
	
	public function InitaliseAdminPageHead($context)
	{
		Administration::instance()->Page->addScriptToHead(
			URL . '/extensions/resave_entries/assets/resave_entries.publish.js',
			time()
		);
	}

	public function AddCustomPreferenceFieldsets($context)
	{
		if (isset($_REQUEST['action']['resave']))
		{
			$id = $_REQUEST['resave']['section'];
			$rate = $_REQUEST['resave']['rate'];
			$page = $_REQUEST['resave']['page'];
			$total = $_REQUEST['resave']['total'];
			
			if ($total && $page * $rate > $total)
				die(self::send(array('status' => 'success')));
			
			$limit = $start = null;
			if ($rate)
			{
				$limit = $rate;
				$start = ($page -1) * $rate;
			}
			
			require_once TOOLKIT. '/class.entrymanager.php';
			$engine = Symphony::Engine();

			$em = new EntryManager($engine);
			$sm = new SectionManager($engine);
			$fm = new FieldManager($engine);
			$ex = new ExtensionManager($engine);

			$entries = $em->fetch($entry_id = null, $id, $limit, $start);
			$fields  = $fm->fetch($field_id = null, $id);
			$section = $sm->fetch($id);
			
			if (!empty($entries)) $entries[0]->checkPostData(array());

			foreach ($entries as $e)
			{
				$ex->notifyMembers('EntryPreRender', '/publish/edit/', array('section' => $section, 'entry' => &$e, 'fields' => $fields));
				$ex->notifyMembers('EntryPreEdit', '/publish/edit/', array('section' => $section, 'entry' => &$e, 'fields' => $fields));

				$e->commit();
				$ex->notifyMembers('EntryPostEdit', '/publish/edit/', array('section' => $section, 'entry' => $e, 'fields' => $fields));
			}
			
			if ($rate && !$total)
				$total = $em->fetchCount($id) / $rate;
			
			if (!$rate) Administration::instance()->Page->pageAlert(__('Entries resaved succesfully.'), Alert::SUCCESS);
			else die(self::send(array('status' => 'processing', 'total' => $total)));
		}
		
		$group = new XMLElement('fieldset');
		$group->setAttribute('class', 'settings resave-entries');
		$group->appendChild(new XMLElement('legend', __('Resave entries'))); 
		
		$span = new XMLElement('span', NULL, array('class' => 'frame'));
		
		require_once TOOLKIT. '/class.sectionmanager.php';
		$sm = new SectionManager(Symphony::Engine());
		$sections = $sm->fetch();

		$options = array();
		foreach ($sections as $s)
		{
			$options[] = array(
				$s->get('id'), false, $s->get('name')
			);
		}
		
		$label = Widget::Label(__('Section'));
		$select = Widget::Select('resave[section]', $options);
		$label->appendChild($select);
		$group->appendChild($label);
		
		$label = Widget::Label(__('Entries to update per page'));
		$label->appendChild(Widget::Input('resave[per-page]', 50));
		$group->appendChild($label);
		
		$span->appendChild(new XMLElement('button', __('Resave Entries'), array_merge(array('name' => 'action[resave]', 'type' => 'submit'))));

		$group->appendChild($span);
		$context['wrapper']->appendChild($group);
	}

	public static function send($data)
	{
		header('content-type: application/json');
		return json_encode($data);
	}
}