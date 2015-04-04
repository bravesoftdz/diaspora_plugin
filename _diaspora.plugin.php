<?php

require_once 'diaspora-api.php';

class diaspora_plugin extends Plugin
{
	var $author = 'Keith Bowes';
	var $code = 'evo_diaspora';
	var $priority = 50;
	var $version = '0.1';

	var $group = 'ping';
	var $number_of_installs = 1;

	function PluginInit(& $params)
	{
		// Check PHP version
		if (version_compare(phpversion(), '5.2.0', '<'))
			$this->set_status('disabled');

		// Must have cURL
		if (!extension_loaded('curl'))
			$this->set_status('disabled');

		$this->name = $this->T_('Diaspora* Plugin for b2evolution');
		$this->short_desc = $this->T_('Post to your Diaspora* account when you post to your blog');
		$this->long_desc = sprintf(
			/* TRANS: Placeholder for the selected pod. */
			$this->T_('Posts to your Diaspora* account to update %s with details of your blog post.'),
			$this->get_user_setting('pod'));

		$this->password = $this->get_user_setting('password');
		$this->pod = $this->get_user_setting('pod');
		$this->username = $this->get_user_setting('username');

		$this->ping_service_name = $this->get_user_setting('pod');
		$this->ping_service_note = $this->T_('Update your Diaspora* account with details about the new post.');
	}

	function GetDependencies()
	{
		return array(
			'requires' => array(
				'app_min' => '5.0',
			),
		);
	}

	function BeforeEnable()
	{
		if (empty($this->code))
		{
			return $this->T_('The plugin needs a non-empty code.');
		}

		if (version_compare(phpversion(), '5.2.0', '<'))
		{
			return $this->T_('This plugin requires PHP 5.2.0 or higher.');
		}

		if (!extension_loaded('curl'))
		{
			return $this->T_('This plugin requires the PHP cURL extension.');
		}

		return TRUE;
	}

	function ItemSendPing(& $params)
	{
		$this->init_diaspora();
		$text = str_replace(
			array('$excerpt$', '$title$', '$url$'),
			array(
				html_entity_decode($params['Item']->dget('excerpt', 'xml')),
				html_entity_decode($params['Item']->dget('title', 'xml')),
				html_entity_decode($params['Item']->get_tinyurl())
			),
			$this->get_user_setting('msg_format')
		);

		$this->api->post($text, $this->get_user_setting('aspects'));
	}

	function get_coll_setting_definitions(& $params)
	{
		$this->init_diaspora();

		if (!$this->api->last_error)
			$info = sprintf(
				# TRANS: Successfully logged in to pod %1$s as user %2$s
				$this->T_('Successfully logged in to <a href="%1$s">%1$s</a> as %2$s.'),
				$this->api->get_pod_url(),
				$this->username
			);
		else
			$info = $this->api->last_error;

		$aspects = $this->api->get_aspects();

		return array(
			'account_status' => array(
				'info' => $info,
				'label' => $this->T_('Diaspora* account status'),
				type => 'info',
			),
			'pod' => array(
				'defaultvalue' => 'joindiaspora.com',
				'label' => $this->T_('Diaspora* Pod'),
				'size' => 30,
				'type' => 'text',
			),
			'username' => array(
				'label' => $this->T_('Username'),
				'size' => 30,
				'type' => 'text',
			),
			'password' => array(
				'label' => $this->T_('Password'),
				'size' => 30,
				'type' => 'password',
			),
			'aspects' => array(
				'label' => $this->T_('Aspects'),
				'multiple' => 1,
				'options' => $aspects,
				'size' => 30,
				'type' => 'select',
			),
			'msg_format' => array(
				'defaultvalue' => T_('Just posted $title$ $url$ #b2p'),
				'label' => $this->T_('Message format'),
				'note' => $this->T_('$title$, $excerpt$ and $url$ will be replaced appropriately.'),
				'size' => 30,
				'type' => 'text',
			),
		);

	}

	function init_diaspora()
	{
		if (!is_object($this->api))
			$this->api = new WP2D_API($this->pod);
		$this->api->provider = $this->name;
		if ($this->api->init() && !$this->api->is_logged_in())
		{
			$this->api->login($this->username, $this->password);
		}
	}

	function get_user_setting($setting)
	{
		if ($this->status != 'enabled')
			return FALSE;

		global $Blog;
		if (is_object($Blog))
		{
			$set = $this->get_coll_setting($setting, $Blog);
		}
		else
			$set = NULL;

		return $set;

	}
}
