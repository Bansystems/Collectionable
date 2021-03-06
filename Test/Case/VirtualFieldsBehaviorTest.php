<?php

class VirualFieldsBehaviorTest extends CakeTestCase {

	public $Behavior;
	public $Model;
	public $db;

	public $fixtures = array(
		'plugin.collectionable.virtual_fields_post',
		'plugin.collectionable.virtual_fields_user',
	);

	public function setUp() {
		parent::setUp();
		$this->_reset();
	}

	protected function _reset($settings = array(), $model = null) {

		$model = $model === null ? 'VirtualFieldsBehaviorMockModel' : $model;
		App::import('TestSuite/Mock', 'Collectionable.' . $model);
		$this->Model = ClassRegistry::init($model);
		$this->Model->Behaviors->attach('Collectionable.VirtualFields', $settings);
		$this->Behavior = $this->Model->Behaviors->VirtualFields;

	}

	public function testUndefined() {

		$virtualFields = array('not_be_defined');
		$this->Behavior->beforeFind($this->Model, compact('virtualFields'));
		$result = $this->Model->virtualFields;
		$expects = array();
		$this->assertEqual($result, $expects);

		$this->Behavior->afterFind($this->Model);
		$this->Model->virtualFields = array(
			'full_name' => "CONCAT(User.first_name, ' ', User.last_name)",
		);
		$virtualFields = array('not_be_defined');
		$this->Behavior->beforeFind($this->Model, compact('virtualFields'));
		$result = $this->Model->virtualFields;
		$expects = array('full_name' => "CONCAT(User.first_name, ' ', User.last_name)");
		$this->assertEqual($result, $expects);

		$this->Behavior->afterFind($this->Model);
		$this->Model->virtualFields = array();
		$this->Model->virtualFieldsCollection = array(
			'posts_count' => 'COUNT(Post.id)',
		);
		$virtualFields = array('not_be_defined');
		$this->Behavior->beforeFind($this->Model, compact('virtualFields'));
		$result = $this->Model->virtualFields;
		$expects = array();
		$this->assertEqual($result, $expects);

	}

	public function testDynamicVirtualField() {

		$virtualFields = array('user_count' => 'COUNT(User.id)');
		$this->Behavior->beforeFind($this->Model, compact('virtualFields'));
		$result = $this->Model->virtualFields;
		$expects = array('user_count' => 'COUNT(User.id)');
		$this->assertEqual($result, $expects);

		$this->Behavior->afterFind($this->Model);
		$this->assertEqual($this->Model->virtualFields, array());

		$this->Model->virtualFields = array(
			'full_name' => "CONCAT(User.first_name, ' ', User.last_name)",
		);
		$virtualFields = array('full_name' => "CONCAT(User.last_name, ' ', User.first_name)",);
		$this->Behavior->beforeFind($this->Model, compact('virtualFields'));
		$result = $this->Model->virtualFields;
		$expects = array('full_name' => "CONCAT(User.last_name, ' ', User.first_name)");
		$this->assertEqual($result, $expects);

		$this->Behavior->afterFind($this->Model);
		$this->assertEqual($this->Model->virtualFields, array(
			'full_name' => "CONCAT(User.first_name, ' ', User.last_name)",
		));

		$this->Model->virtualFields = array();
		$this->Model->virtualFieldsCollection = array(
			'posts_count' => 'COUNT(Post.id)',
		);
		$virtualFields = array('posts_count' => 'SUM(Post.id)');
		$this->Behavior->beforeFind($this->Model, compact('virtualFields'));
		$result = $this->Model->virtualFields;
		$expects = array('posts_count' => 'SUM(Post.id)');
		$this->assertEqual($result, $expects);

		$this->Behavior->afterFind($this->Model);
		$this->assertEqual($this->Model->virtualFields, array());
		$this->assertEqual($this->Model->virtualFieldsCollection, array(
			'posts_count' => 'COUNT(Post.id)',
		));

	}

	public function testFind() {

		$this->_reset(false, 'VirtualFieldsUser');
		$this->skipIf(!preg_match('|Mysql|i', $this->db->config['datasource']), "%s This tests belonges to MySQL('s SQL expression)");

		$this->Model->virtualFields = array(
			'full_name' => "CONCAT(User.first_name, ' ', User.last_name)",
		);
		$this->Model->virtualFieldsCollection = array(
			'posts_count' => 'COUNT(Post.id)',
		);
		$this->Model->recursive = -1;

		$result = $this->Model->find('first', array('fields' => array('full_name')));
		$expected = array('User' => array('full_name' => 'yamada ichirou'));
		$this->assertEqual($result, $expected);

		$virtualFields = array('posts_count');
		$joins = array(
			array(
				'table' => $this->Model->Post->table,
				'alias' => 'Post',
				'type' => 'LEFT',
				'conditions' => array('User.id = Post.user_id')
			)
		);
		$group = 'User.id';
		$fields = array('User.full_name', 'User.posts_count');
		$result = $this->Model->find('first', compact('fields', 'virtualFields', 'joins', 'group'));
		$expected = array('User' => array('full_name' => 'yamada ichirou', 'posts_count' => 2));
		$this->assertEqual($result, $expected);

		// ensure virtualFields of model was reset
		$result = $this->Model->find('first', array('fields' => array('User.full_name')));
		$expected = array('User' => array('full_name' => 'yamada ichirou'));
		$this->assertEqual($result, $expected);

		$this->Model->virtualFieldsCollection['full_name'] = "CONCAT(User.first_name, '.', User.last_name)";
		$result = $this->Model->find('first', array('fields' => array('User.full_name'), 'virtualFields' => 'full_name'));
		$expected = array('User' => array('full_name' => 'yamada.ichirou'));
		$this->assertEqual($result, $expected);

		$result = $this->Model->find('first', array('fields' => array('User.full_name')));
		$expected = array('User' => array('full_name' => 'yamada ichirou'));
		$this->assertEqual($result, $expected);

	}

	public function testBlackList() {

		$this->Model->virtualFields = array(
			'full_name' => "CONCAT(User.first_name, ' ', User.last_name)",
			'posts_count' => 'COUNT(Post.id)',
		);

		$this->Behavior->beforeFind($this->Model);
		$result = $this->Model->virtualFields;
		$expected = array('full_name' => "CONCAT(User.first_name, ' ', User.last_name)", 'posts_count' => 'COUNT(Post.id)');
		$this->assertEqual($result, $expected);

		$this->Behavior->afterFind($this->Model);
		$virtualFields = array('posts_count' => false);
		$this->Behavior->beforeFind($this->Model, compact('virtualFields'));
		$result = $this->Model->virtualFields;
		$expected = array('full_name' => "CONCAT(User.first_name, ' ', User.last_name)");
		$this->assertEqual($result, $expected);

		$this->Behavior->afterFind($this->Model);
		$result = $this->Model->virtualFields;
		$expected = array('full_name' => "CONCAT(User.first_name, ' ', User.last_name)", 'posts_count' => 'COUNT(Post.id)');
		$this->assertEqual($result, $expected);

	}

	public function testSettings() {

	}

}