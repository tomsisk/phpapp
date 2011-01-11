<?php
	class BaseUser extends Model {

		public function configure() {
			$this->id = new IntegerField('Id', array('pk' => true));
			$this->username = new CharField('Username', 50, array('required' => true, 'readonly' => true, 'unique' => true, 'blank' => false));
			$this->password = new PasswordField('Password', 50, array('required' => true));
			$this->email = new EmailField('Email', array('required' => true));

			$this->verified = new BooleanField('Verified', array('default' => false));
			$this->active = new BooleanField('Active', array('default' => true));
			$this->staff = new BooleanField('Staff', array('default' => false));
			$this->created = new DateTimeField('Created', array('readonly' => true, 'default' => CURRENT_TIMESTAMP));
			$this->last_login = new DateTimeField('Last Login', array('readonly' => true));

			$this->preferences = new OneToManyField('Preferences', 'UserPreference');
			$this->groups = new ManyToManyField('Groups', 'Group', 'UserGroup');
			$this->roles = new ManyToManyField('Roles', 'Role', 'UserRole');

			$this->setDefaultOrder('+username');
		}

		public function __toString() {
			return strval($this->username);
		}

	}
