<?php   //      PROJECT:        nibelung
        //      MODULE:         Partners CMS class
        //      $Id: partners.webgui.php 159 2008-02-19 11:20:55Z misha $

include_once 'simple.webgui.php'; 

class CMSPartners extends CMS {

	function CMSPartners($ad = 0) {
		$this->CMS('partners','partners',array(
			'title'=>'',
			'id'=>'0',
			'login'=>'',
			'person'=>'',
			'email'=>'',
			'url'=>'http://',
			'phone'=>'+380',
			'password'=>''
		),$ad);
		$this->tasks['access'] = '$this->redir("list",$this->access());';
	}
  
	function access(){
		$pass_file = CONFIG.'/.partners';
		$data = db_get("select login,password from partners where password!=''");
		if($data)
			if($pf = fopen($pass_file,'w')){
				foreach($data as $rec)
					$i = fwrite($pf,$rec['login'].':'.crypt($rec['password'])."\n");
				fclose($pf);
				return translate('access_granted');
			}else
				return translate('file_error');
		else
			return translate('data_error');
	}
}

// Triggers: Just declaration

class CMSTriggers extends CMS {
	function CMSTriggers($ad = 1) {
		$this->CMS('triggers','triggers',array(
			'partner_id' => 'id=>title from partners order by title',
			'trigger' => '000',
			'id' => '0',
			'title' => '',
			'rate_min' => '0',
			'rate_percent' => '0',
			'tariff' => '0',
			'vat' => '0'
		),$ad,1);
	}
}

?>