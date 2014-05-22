<?php
/*
	Content Mastering API
	$Id: mastering.api.php 159 2008-02-19 11:20:55Z misha $
	
	Producing a number of different format binaries from an master binary.
	Note that master binary keys (data types) are HARDCODED.
	I think it's a reasonable way for this weird bunch of file types.

	Using Binary class (storage.api.php) for data handling
	
	Procedural interface - this is just conversion routines, why we need any classes?
		
	Main Mastering handler:
		
	masterDeploy ( $content_id, $master_key, $safe_deploy = 0 )
		Main function: produces a bunch of binaries by given $content_id and
		$master_key and stores them in Binary storage under same $content_id.
		If $safe_deploy is TRUE then existing data will not be overwritten.
		Otherwise (and default) the deployer will overwrite any data with same keys.
		returns TRUE on success.
		
	Hardcoded solutions called by the main handler:
		
	deployJpeg ( $bin, $deploy_keys )
		$bin must be an implementation of Binary class initialized on master JPEG image.
		$deploy_keys is an array with Binary keys to produce.
		Note that there (and below) is no "safe" flags: old binaries with same 
		keys and $content_id will be overwritten.
	
	deployGif ( $bin, $deploy_keys )
		Same as above but for (animated and still) GIF images
		
*/

require_once 'storage.api.php';

$MBIN = '/usr/bin'; 	// Path to "convert" and "identify" routines from ImageMagick

// Conversions config:

$masterConv = array(
	'master/image.jpeg' => array(
		'master/image.gif' => array(
			'engine'=> "{$MBIN}/convert %infile %outfile",
			'ext'	=> 'gif',
			'mime'	=> 'image/gif',
			'title'	=> 'GIF'
		)
	),
	'master/image.gif' => array(
		'master/image.jpeg' => array(
			'engine'=> "{$MBIN}/convert %infile %outfile",
			'ext'	=> 'jpg',
			'mime'	=> 'image/jpeg',
			'title'	=> 'JPEG'
		)
	),
	'content/ringtone.midi.16' => array(
		'preview/audio.mp3' => array(
			'engine'=> CONFIG. "/conv/midi2mp3.pl %infile %outfile",
			'ext'	=> 'mp3',
			'mime'	=> 'audio/mpeg',
			'title'	=> 'MP3'
		)
	),
	'content/ringtone.midi.4' => array(
		'preview/audio.mp3' => array(
			'engine'=> CONFIG. "/conv/midi2mp3.pl %infile %outfile",
			'ext'	=> 'mp3',
			'mime'	=> 'audio/mpeg',
			'title'	=> 'MP3'
		)
	),
	'master/ringtone.mp3' => array(
		'preview/audio.mp3' => array(
			'engine'=> "/usr/bin/sox %infile -r 22050 -v 2 %outfile fade 0 00:00:20 00:00:02",
			'ext'	=> 'mp3',
			'mime'	=> 'audio/mpeg',
			'title'	=> 'MP3demo'
		),
		'content/ringtone.mp3_64_22_m' => array(
			'engine'=> "/usr/bin/lame %infile --cbr -F -b 64 -q 2 -m m --resample 22.05 --quiet %outfile",
			'ext'	=> 'mp3',
			'mime'	=> 'audio/mpeg',
			'title'	=> 'MP3mono'
		),
		'content/ringtone.mp3_64_22_s' => array(
			'engine'=> "/usr/bin/lame %infile --cbr -F -b 64 -q 2 -m s --resample 22.05 --quiet %outfile",
			'ext'	=> 'mp3',
			'mime'	=> 'audio/mpeg',
			'title'	=> 'MP3stereo'
		),
		'content/ringtone.amr_8_8' => array(
			'engine'=> SYS. "/bin/ffmpeg -i %infile -ab 8 -ar 8000 -ac 1 -acodec amr_nb %outfile",
			'ext'	=> 'amr',
			'mime'	=> 'audio/amr',
			'title'	=> 'AMR88'
		),	
		'content/ringtone.wav_ima_4_16_m' => array(
			'engine'=> SYS. "/bin/ffmpeg -i %infile -ar 16000 -ab 4 -ac 1 -acodec adpcm_ima_wav %outfile",
			'ext'	=> 'wav',
			'mime'	=> 'audio/wav',
			'title'	=> 'WAV'
		),
	),
	'master/ringtone.wav' => array(
		'preview/audio.mp3' => array(
			'engine'=> "/usr/bin/sox %infile -r 22050 -v 2 %outfile fade 0 00:00:20 00:00:02",
			'ext'	=> 'mp3',
			'mime'	=> 'audio/mpeg',
			'title'	=> 'MP3demo'
		),
		'content/ringtone.mp3_64_22_m' => array(
			'engine'=> "/usr/bin/lame %infile --cbr -F -b 64 -q 2 -m m --resample 22.05 --quiet %outfile",
			'ext'	=> 'mp3',
			'mime'	=> 'audio/mpeg',
			'title'	=> 'MP3mono'
		),
		'content/ringtone.mp3_64_22_s' => array(
			'engine'=> "/usr/bin/lame %infile --cbr -F -b 64 -q 2 -m s --resample 22.05 --quiet %outfile",
			'ext'	=> 'mp3',
			'mime'	=> 'audio/mpeg',
			'title'	=> 'MP3stereo'
		),
		'content/ringtone.amr_8_8' => array(
			'engine'=> SYS. "/bin/ffmpeg -i %infile -ab 8 -ar 8000 -ac 1 -acodec amr_nb %outfile",
			'ext'	=> 'amr',
			'mime'	=> 'audio/amr',
			'title'	=> 'AMR88'
		),	
		'content/ringtone.wav_ima_4_16_m' => array(
			'engine'=> SYS. "/bin/ffmpeg -i %infile -ar 16000 -ab 4 -ac 1 -acodec adpcm_ima_wav %outfile",
			'ext'	=> 'wav',
			'mime'	=> 'audio/wav',
			'title'	=> 'WAV'
		),
	),
	'deployMono' => array(
		'content/ringtone.sms.ems' => array(
			'engine'=> CONFIG. "/conv/rtttl2ems.pl %infile %outfile",
			'ext'	=> 'imy',
			'mime'	=> 'audio/imelody',
			'title'	=> 'EMS'
		),
		'content/ringtone.sms.nokia' => array(
			'engine'=> CONFIG. "/conv/rtttl2nokia.pl %infile %outfile",
			'ext'	=> 'nok',
			'mime'	=> 'audio/vnd.nok-ringingtone',
			'title'	=> 'Nokia'
		),
		'content/ringtone.sms.siemens' => array(
			'engine'=> CONFIG. "/conv/rtttl2midi.pl %infile %outfile",
			'ext'	=> 'mid',
			'mime'	=> 'audio/midi',
			'title'	=> 'Siemens'
		)
	),
	'deployMonoPreview' => array(
		'preview/audio.mp3' => array(
			'engine'=> CONFIG. "/conv/rtttl2mp3.pl %infile %outfile",
			'ext'	=> 'mp3',
			'mime'	=> 'audio/mpeg',
			'title'	=> 'MP3'
		)
	),
	'deployBW' => array(
		'content/image.sms.ems32' => array(
			'engine'=> CONFIG. "/conv/makemonobmp.pl %infile 32x32 %outfile",
			'ext'	=> 'bmp',
			'mime'	=> 'image/bmp',
			'title'	=> 'EMS 32x32'
		),
		'content/image.sms.ems72' => array(
			'engine'=> CONFIG. "/conv/makemonobmp.pl %infile 72x14 %outfile",
			'ext'	=> 'bmp',
			'mime'	=> 'image/bmp',
			'title'	=> 'EMS 72x14'
		),
		'content/image.sms.nokia-logo' => array(
			'engine'=> CONFIG. "/conv/makemonobmp.pl %infile 72x14 %outfile",
			'ext'	=> 'bmp',
			'mime'	=> 'image/bmp',
			'title'	=> 'Nokia Logo'
		),
		'content/image.sms.nokia-card' => array(
			'engine'=> CONFIG. "/conv/makemonobmp.pl %infile 72x28 %outfile",
			'ext'	=> 'bmp',
			'mime'	=> 'image/bmp',
			'title'	=> 'Nokia Card'
		),
		'content/image.sms.siemens46' => array(
			'engine'=> CONFIG. "/conv/makemonobmp.pl %infile 101x46 %outfile",
			'ext'	=> 'bmp',
			'mime'	=> 'image/bmp',
			'title'	=> 'Sie 101x46'
		),
		'content/image.sms.siemens29' => array(
			'engine'=> CONFIG. "/conv/makemonobmp.pl %infile 101x29 %outfile",
			'ext'	=> 'bmp',
			'mime'	=> 'image/bmp',
			'title'	=> 'Sie 101x29'
		)
	),
	'deployPreviewBW' => array(
		'preview/image.gif.30x30' => array(
			'engine'=> CONFIG. "/conv/makemonopreview.pl %infile 30x30 %outfile",
			'ext'	=> 'gif',
			'mime'	=> 'image/gif',
			'title'	=> 'Pre 30x30'
		),
		'preview/image.gif.72x14' => array(
			'engine'=> CONFIG. "/conv/makemonopreview.pl %infile 72x14 %outfile",
			'ext'	=> 'gif',
			'mime'	=> 'image/gif',
			'title'	=> 'Pre 72x14'
		),
		'preview/image.gif.72x28' => array(
			'engine'=> CONFIG. "/conv/makemonopreview.pl %infile 72x28 %outfile",
			'ext'	=> 'gif',
			'mime'	=> 'image/gif',
			'title'	=> 'Pre 72x28'
		),
		'preview/image.gif.101x29' => array(
			'engine'=> CONFIG. "/conv/makemonopreview.pl %infile 101x29 %outfile",
			'ext'	=> 'gif',
			'mime'	=> 'image/gif',
			'title'	=> 'Pre 101x29'
		),
		'preview/image.gif.101x46' => array(
			'engine'=> CONFIG. "/conv/makemonopreview.pl %infile 101x46 %outfile",
			'ext'	=> 'gif',
			'mime'	=> 'image/gif',
			'title'	=> 'Pre 101x46'
		),
	)
);



function masterDeploy ( $content_id, $master_key, $preview_deploy = 0, $safe_deploy = 0 ) {

	global $status, $masterConv;

	$known = array(
		'master/image.jpeg' => array(
			'like' 		=> 'content/image.jpeg%',
			'preview'	=> 'preview/image.gif%',
			'handler'	=> 'deployJpeg',
			'prehandler'=> 'deployPreviewGif'
		),
		'master/image.gif' => array(
			'like' 		=> 'content/image.gif%',
			'preview'	=> 'preview/image.gif%',
			'handler'	=> 'deployGif',
			'prehandler'=> 'deployPreviewGif'
		),
		'master/image.72x14' => array(
			'like' 		=> 'content/image.sms%',
			'preview'	=> 'preview/image.gif%',
			'handler'	=> 'deployBW',
			'prehandler'=> 'deployPreviewBW'
		),
		'master/image.72x28' => array(
			'like' 		=> 'content/image.sms%',
			'preview'	=> 'preview/image.gif%',
			'handler'	=> 'deployBW',
			'prehandler'=> 'deployPreviewBW'
		),
		'master/image.32x32' => array(
			'like' 		=> 'content/image.sms%',
			'preview'	=> 'preview/image.gif%',
			'handler'	=> 'deployBW',
			'prehandler'=> 'deployPreviewBW'
		),
		'master/image.101x46' => array(
			'like' 		=> 'content/image.sms%',
			'preview'	=> 'preview/image.gif%',
			'handler'	=> 'deployBW',
			'prehandler'=> 'deployPreviewBW'
		),
		'master/image.101x29' => array(
			'like' 		=> 'content/image.sms%',
			'preview'	=> 'preview/image.gif%',
			'handler'	=> 'deployBW',
			'prehandler'=> 'deployPreviewBW'
		),
		'master/ringtone.sms.rtttl' => array(
			'like'		=> 'content/ringtone.sms%',
			'preview'	=> 'preview/audio.mp3',
			'handler'	=> 'deployMono',
			'prehandler'=> 'deployPreviewRtttl'
		)
	);
	if(isset($known[$master_key])){
		if($bin = new Binary()){
			if(!$preview_deploy){
			
				// Deploying CONTENT data
			
				if($dt = masterGetDatatypes($content_id, $known[$master_key]['like'])){
				
					// Checking what binaries exists already - on $safe_deploy
					if($safe_deploy){
						if($existing = $bin->listBinaries($known[$master_key]['like'])){
							$newdt = array();
							foreach($dt as $key)
								if(!in_array($key, $existing, true))
									$newdt[] = $dt;
							$dt = $newdt;
						}
					}
					
					// Run the deployer
					if($dt)
						if($bin->seekByKey($content_id, $master_key))
							return $known[$master_key]['handler']($bin, $dt);
						else
							warn("Binary [$master_key] doesn't exists for content $content_id");
				}else
					warn('No datatpl defined for this master key');
			
			}else{
			
				// Deploying PREVIEW data
			
				if($dt = masterGetDatatypes($content_id, $known[$master_key]['preview'])){
				
					if($safe_deploy){
						if($existing = $bin->listBinaries($known[$master_key]['preview'])){
							$newdt = array();
							foreach($dt as $key)
								if(!in_array($key, $existing, true))
									$newdt[] = $dt;
							$dt = $newdt;
						}
					}
					
					if($dt)
						if($bin->seekByKey($content_id, $master_key))
							return $known[$master_key]['prehandler']($bin, $dt);
						else
							warn("Binary [$master_key] doesn't exists for content $content_id");
				}else
					warn('No Preview datatpl defined for this master key');
			}
					
		}else
			warn('Can not init Binary');
	}elseif(isset($masterConv[$master_key])){
		if($bin = new Binary()){
			if($bin->seekByKey($content_id, $master_key))
				return masterConvert($bin->id,'');
			else
				warn("Binary [$master_key] doesn't exists for content $content_id");
		}else
			warn('Can not init Binary / masterConv');
	}else
		warn("Unknown master key [$master_key]");
	return false;
}


function deployJpeg ( $bin, $deploy_keys ){
	global $MBIN;
	if($deploy_keys)
		if($master = $bin->getFilename()){
			$content_id = $bin->content_id;
			$catch = array();
			exec("$MBIN/identify $master",$catch);
			$ident = explode(' ',$catch[0]);
			if($ident[1] === 'JPEG'){
				list($mX,$mY) = explode('x',$ident[2]);
				if($mX && $mY && ($tX<$mX) && ($tY<$mY)){
					$retval = false;
					foreach($deploy_keys as $key)
						if(preg_match('/^(\w+)\/image\.jpeg\.(\d+)x(\d+)$/',$key,$matches)){
							$media = $matches[1];
							$tX = $matches[2];
							$tY = $matches[3];
							
							
							
							if($tX && $tY){
								/* if($mY*$tX/$mX >= $tY){
									$sizeX = $tX;
									$sizeY = $tY * 1000;
									$dX = 0;
									$dY = intval(($mY*$tX/$mX - $tY)/2);
								}else{
									$sizeX = $tX * 1000;
									$sizeY = $tY;
									$dX = intval(($mX*$tY/$mY - $tX)/2);
									$dY = 0;
								} */
								// tempnam() is dumb due to lack of extensions (.jpg)
								$tmpfname = "/tmp/deployJpeg-{$content_id}-{$tX}x{$tY}.jpg";
								
								exec(CONFIG."/conv/makepic.pl {$master} {$tX}x{$tY} {$tmpfname}");
								
								/* exec(
									"$MBIN/convert $master -resize {$sizeX}x{$sizeY}".
									" -crop {$tX}x{$tY}+{$dX}+{$dY} -page {$tX}x{$tY}+0+0 -quality 80 {$tmpfname}"); */
								if(is_file($tmpfname)){
									while($bin->seekByKey($content_id,$key))
										$bin->remove();
									if($bin->createFromFile($content_id,$key,'image/jpeg',$tmpfname)){
										$retval = true;
										$status .= "$key OK : ";
									}else
										$status .= "$key READ ERROR : ";
									unlink($tmpfname);
								}else
									$status .= "$key FAILED : ";
							}
						}
					return $retval;
				}else
					$status .= "Bad image geometry : ";
			}else
				$status .= "Not a JPEG file : ";
		}else
			warn("Can't get master filename!");
	else
		return true;	// All done OK if we have nothing to do, sure?
	return false;
}


function deployGif ( $bin, $deploy_keys ){
	global $MBIN, $status;
	
	if($deploy_keys)
		if($master = $bin->getFilename()){
			$content_id = $bin->content_id;
			$catch = array();
			exec("$MBIN/identify $master",$catch);

			$ident = explode(' ',$catch[0]);
			if($ident[1] === 'GIF'){
				list($mX,$mY) = explode('x',$ident[2]);
				if($mX && $mY){
					$retval = false;
					foreach($deploy_keys as $key)
						if(preg_match('/^(\w+)\/image\.gif\.(\d+)x(\d+)$/',$key,$matches)){
							$media = $matches[1];
							$tX = $matches[2];
							$tY = $matches[3];
							if($tX && $tY && ($tX<$mX) && ($tY<$mY)){
								/* if($mY*$tX/$mX >= $tY){
									$sizeX = $tX;
									$sizeY = $tY * 1000;
									$dX = 0;
									$dY = intval(($mY*$tX/$mX - $tY)/2);
								}else{
									$sizeX = $tX * 1000;
									$sizeY = $tY;
									$dX = intval(($mX*$tY/$mY - $tX)/2);
									$dY = 0;
								}*/
								// tempnam() is dumb due to lack of extensions (.jpg)
								$tmpfname = "/tmp/deployGif-{$content_id}-{$tX}x{$tY}.gif";
								exec(CONFIG."/conv/makepic.pl {$master} {$tX}x{$tY} {$tmpfname}");
								/* exec("$MBIN/convert $master -dispose Previous -coalesce".
									" -sample {$sizeX}x{$sizeY}".
									" -crop {$tX}x{$tY}+{$dX}+{$dY}".
									" -page {$tX}x{$tY}+0+0".
									" -deconstruct {$tmpfname}"); */
								if(is_file($tmpfname)){
									while($bin->seekByKey($content_id,$key))
										$bin->remove();
									if($bin->createFromFile($content_id,$key,'image/gif',$tmpfname)){
										$retval = true;
										$status .= "$key OK : ";
									}else
										$status .= "$key READ ERROR : ";
									unlink($tmpfname);
								}else
									$status .= "$key FAILED : ";
							}
						}
					return $retval;
				}else
					$status .= "Bad image geometry : ";
			}else
				$status .= "Image isn't GIF : ";
		}else
			warn("Can't get master filename!");
	else
		return true;	// All done OK if we have nothing to do, sure?
	return false;
}



function deployPreviewGif ( $bin, $deploy_keys ){
	global $status;
	if($deploy_keys)
		if($master = $bin->getFilename()){
			$content_id = $bin->content_id;
			$retval = false;
			foreach($deploy_keys as $key)
				if(preg_match('/^preview\/image\.gif\.(\d+)x(\d+)$/',$key,$matches)){
					$tX = $matches[1];
					$tY = $matches[2];
					$tmpfname = "/tmp/deployGif-{$content_id}-{$tX}x{$tY}.gif";
					$execline = CONFIG."/conv/makepreview.pl {$master} {$tX}x{$tY} {$tmpfname}";
					exec($execline);
					if(is_file($tmpfname)){
						while($bin->seekByKey($content_id,$key))
							$bin->remove();
						if($bin->createFromFile($content_id,$key,'image/gif',$tmpfname)){
							$retval = true;
							$status .= "$key OK : ";
						}else
							$status .= "$key READ ERROR : ";
						unlink($tmpfname);
					}else
						$status .= "$key FAILED : ";
				}
			return $retval;
		}else
			warn("Can't get master filename!");
	else
		return true;	// All done OK if we have nothing to do, sure?
	return false;
}

function deployByConfig ( $bin, $deploy_keys, $figure ){
	global $status, $masterConv;
	$retval = false;
	if($deploy_keys)
		if($master = $bin->getFilename()){
			$content_id = $bin->content_id;
			$master_key = $bin->key;
			if(isset($masterConv[$figure])){
				$conv = $masterConv[$figure];
				foreach($deploy_keys as $target_key)
					if(isset($conv[$target_key])){
						$tmpfname = "/tmp/$figure-{$content_id}.{$conv[$target_key]['ext']}";
						$execline = template($conv[$target_key]['engine'], array(
							'infile'	=> $master,
							'outfile'	=> $tmpfname
						));
						exec($execline);
						if(is_file($tmpfname)){
							while($bin->seekByKey($content_id,$target_key))
								$bin->remove();
							if($bin->createFromFile($content_id,$target_key,$conv[$target_key]['mime'],$tmpfname)){
								$status .= "{$conv[$target_key]['title']} OK : ";
								$retval = true;
							}else
								$status .= "{$conv[$target_key]['title']} Read error! : ";
							unlink($tmpfname);
						}else
							$status .= "{$conv[$target_key]['title']} Failed : ";
					}
			}else{
				$status .= "$master_key unknown : ";
			}
		}else
			warn("Can't get master filename!");
	else
		return true;	// All done OK if we have nothing to do, sure?
	return $retval;
}

function deployBW ( $bin, $deploy_keys ){
	return deployByConfig ( $bin, $deploy_keys, 'deployBW' );
}

function deployPreviewBW ( $bin, $deploy_keys ){
	return deployByConfig ( $bin, $deploy_keys, 'deployPreviewBW' );
}


function deployPreviewRtttl ( $bin, $deploy_keys ){
	return deployByConfig ( $bin, $deploy_keys, 'deployMonoPreview' );
}

function deployMono ( $bin, $deploy_keys ){
	return deployByConfig ( $bin, $deploy_keys, 'deployMono' );
}


function masterConvert ( $data_id, $convert_to ) {
	global $MBIN, $masterConv, $status;
	
	$retval = false;
	
	if($bin = new Binary())
		if($bin->seekById($data_id))
			if($master = $bin->getFilename()){
				$content_id = $bin->content_id;
				$master_key = $bin->key;
				if(isset($masterConv[$master_key]))
					foreach($masterConv[$master_key] as $target_key => $conv){
						if(isset($convert_to) && ($convert_to != ''))
						    if($target_key != $convert_to)
							continue;
						$tmpfname = "/tmp/masterConvert-{$content_id}.{$conv['ext']}";
						$execline = template($conv['engine'], array(
							'infile'	=> $master,
							'outfile'	=> $tmpfname
						));
						exec($execline);
						if(is_file($tmpfname)){
							while($bin->seekByKey($content_id,$target_key))
								$bin->remove();
							if($bin->createFromFile($content_id,$target_key,$conv['mime'],$tmpfname)){
								$status .= "{$conv['title']} OK : ";
								$retval = true;
							}else
								$status .= "{$conv['title']} Read error! : ";
							unlink($tmpfname);
						}else
							$status .= "{$conv['title']} Failed : ";
					}
			}
			
	return $retval;
}

function masterGetConversions ( $master_key ) {
	global $masterConv;
	$cvs = array();
	if(isset($masterConv[$master_key]))
		foreach($masterConv[$master_key] as $target_key => $conv)
			$cvs[$target_key] = $conv['title'];
	return $cvs;
}

function masterGetDatatypes ( $content_id, $template ) {
	if($data = db_get("
		select datatypes.type 
			from content 
				left join datatpl on content.class_id = datatpl.class_id 
				left join datatypes on datatpl.type_id = datatypes.id 
			where content.id=$content_id
				and datatypes.type like '$template%';
	")){
		$dt = array();
		foreach($data as $rec)
			$dt[] = $rec['type'];
		return $dt;
	}
	return array();
}

?>
