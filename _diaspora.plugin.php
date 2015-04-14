<?php

require_once 'class-api.php';

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

		$this->autoload_aspects = $this->get_user_setting('autoload_aspects');
		$this->full_posts = $this->get_user_setting('full_posts');
		$this->password = $this->get_user_setting('password');
		$this->pod = $this->get_user_setting('pod');
		$this->username = $this->get_user_setting('username');

		$this->name = $this->T_('Diaspora* Plugin for b2evolution');
		$this->short_desc = $this->T_('Post to your Diaspora* account when you post to your blog');
		$this->long_desc = sprintf(
			/* TRANS: Placeholder for the selected pod. */
			$this->T_('Posts to your Diaspora* account to update %s with details of your blog post.'),
			$this->pod);

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

		if (!$this->full_posts)
		{
			$text = str_replace(
				array('$excerpt$', '$title$', '$url$'),
				array(
					html_entity_decode($params['Item']->dget('excerpt', 'xml')),
					html_entity_decode($params['Item']->dget('title', 'xml')),
					html_entity_decode($params['Item']->get_tinyurl())
				),
				$this->get_user_setting('msg_format')
			);
		}
		else
		{
			require_once 'class-html-to-markdown.php';

			global $baseurl;
			$item = $params['Item'];
			$blog = $item->Blog;

			$text = new HTML_To_Markdown(
				$item->content,
				array(
					'header_style' => 'atx',
					'strip_tags' => TRUE,
				)
			);

			/* TRANS: Blog URL, title of Blog */
			$text .= $this->T_('<div>Original post posted on <a href="%s">%s</a></div>', $baseurl . $blog->siteurl, $blog->name);
		}

		$this->api->post($text, $this->get_user_setting('aspects'));
	}

	function get_coll_setting_definitions(& $params)
	{
		$r = array();

		if ($this->autoload_aspects)
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
			$r['account_status'] = array(
				'info' => $info,
				'label' => $this->T_('Diaspora* account status'),
				type => 'info',
			);
		}

		$r = array_merge(
			$r, array(
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
			)
		);

		if ($this->autoload_aspects)
			$r['aspects'] = array(
				'label' => $this->T_('Aspects'),
				'multiple' => 1,
				'options' => $aspects,
				'size' => 30,
				'type' => 'select',
			);
		else
			$r['aspects'] = array(
				'defaultvalue' => '',
				'label' => $this->T_('Aspects'),
				'note' => $this->T_('Comma-seperated list of aspect IDs for which the post will be visible.'),
				'size' => 30,
				'type'=> 'text',
			);
		$r['autoload_aspects'] = array(
			'defaultvalue' => 0,
			'label' => $this->T_('Autoload Aspects'),
			'note' => $this->T_('Automatically get your account status and list of aspects from the pod.  This can be very slow.'),
			'type' => 'checkbox',
		);
		$r['full_posts'] = array(
			'defaultvalue' => 0,
			'label' => $this->T_('Post full posts'),
			'note' => $this->T_('Whether to post full posts or just share the link.'),
			'type' => 'checkbox'
		);
		if (!$this->full_posts)
			$r['msg_format'] = array(
				'defaultvalue' => $this->T_('Just posted $title$ $url$ #b2p'),
				'label' => $this->T_('Message format'),
				'note' => $this->T_('$title$, $excerpt$ and $url$ will be replaced appropriately.'),
				'size' => 30,
				'type' => 'text',
			);

		return $r;
	}

	function init_diaspora()
	{
		if ($this->api && strlen($this->api->get_pod_url) < 9)
			unset($this->api);

		if (!is_object($this->api))
			$this->api = new WP2D_API($this->pod);

		if ($this->api->init() && !$this->api->is_logged_in())
		{
			$this->api->login($this->username, $this->password);
			$this->api->provider = $this->name;
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
