<?
/* Defaults for content.webgui.php (class init)
 * $Id: content.webgui.defaults.php 159 2008-02-19 11:20:55Z misha $
 */
		$this->profile = 'default';
		$this->profiles = array(
			'default' => array(
				'class'         => 'class',
				'mapping'       => 'short',
				'meta'          => 'list',
				'__meta-list'   => 'artist,title',
				'data'          => 'types'
			),
			'pictures' => array(
				'class'         => 'class',
				'mapping'       => 'short',
				'meta'          => 'list',
				'__meta-list'   => 'artist,title,keywords',
				'data'          => 'picture'
			),
			'royalty' => array(
				'royalty'       => 'rights',
				'class'         => 'class',
				'meta'          => 'list',
				'__meta-list'   => 'artist,title,author,authors_music,authors_text',
				'data'          => 'types'
			),
			'full' => array(
				'class'         => 'class',
				'mapping'       => 'long',
				'meta'          => 'values',
				'data'          => 'types'
			)
		);
		$this->order = '-created';
		$this->orderby = array(
			'id' => 'content.id,',
			'-id' => 'content.id desc,',
			'class' => 'content.class_id,',
			'-class' => 'content.class_id desc,',
			'created' => 'content.created,',
			'-created' => 'content.created desc,',
			'modified' => 'content.modified,',
			'-modified' => 'content.modified desc,'
		);
		$this->picturetypes = array(
			'content/image.jpeg.128x128'	=> '',
			'content/image.jpeg.101x80'		=> '',
			'content/image.jpeg.96x65'		=> '',
			'content/image.gif.128x128'		=> '',
			'content/image.gif.101x80'		=> '',
			'content/image.gif.96x65'		=> '',
			'content/image.sms.ems72'		=> '',
			'content/image.sms.ems32'		=> '',
			'content/image.sms.nokia-card'	=> '',
			'content/image.sms.nokia-logo'	=> '',
			'content/image.sms.siemens29'	=> '',
			'content/image.sms.siemens46'	=> '',
			'preview/image.agif.128x128'	=> '',
			'preview/image.gif.128x128'	=> '',
			'preview/image.gif.93x64'	=> '',
			'preview/image.gif.102x102'	=> '',
			'preview/image.gif.64x64'	=> '',
			'preview/image.gif.32x32'	=> '',
			'preview/image.gif.30x30'	=> '',
			'preview/audio.mp3'				=> '/img/listen.gif'
		);
?>
