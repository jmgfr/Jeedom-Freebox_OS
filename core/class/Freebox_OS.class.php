<?php
/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
class Freebox_OS extends eqLogic {
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'Freebox_OS';	
		$cache = cache::byKey('Freebox_OS::SessionToken');
		//if(config::byKey('FREEBOX_SERVER_SESSION_TOKEN','Freebox_OS')!='')
		if(is_object($cache)&&$cache->getValue('')!='')
			$return['state'] = 'ok';
		else
			$return['state'] = 'nok';
		if(trim(config::byKey('FREEBOX_SERVER_IP','Freebox_OS'))!=''&&config::byKey('FREEBOX_SERVER_APP_TOKEN','Freebox_OS')!=''&&trim(config::byKey('FREEBOX_SERVER_APP_ID','Freebox_OS'))!='')
			$return['launchable'] = 'ok';
		else
			$return['launchable'] = 'nok';
		return $return;
	}
	public static function deamon_start($_debug = false) {
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') 
			return;
		log::remove('Freebox_OS');
		self::deamon_stop();
		self::open_session();
		$cron = cron::byClassAndFunction('Freebox_OS', 'RefreshInformation');
		if (!is_object($cron)) {
			$cron = new cron();
			$cron->setClass('Freebox_OS');
			$cron->setFunction('RefreshInformation');
			$cron->setEnable(1);
			$cron->setDeamon(1);
			$cron->setSchedule('* * * * *');
			$cron->setTimeout('999999');
			$cron->save();
		}
		$cron->start();
		$cron->run();
	}
	public static function deamon_stop() {
		$cron = cron::byClassAndFunction('Freebox_OS', 'RefreshInformation');
		if (is_object($cron)) {
			$cron->stop();
			$cron->remove();
		}
		self::close_session();
	}
	public function track_id() 	{
		$serveur		=trim(config::byKey('FREEBOX_SERVER_IP','Freebox_OS'));
		$app_id 		=trim(config::byKey('FREEBOX_SERVER_APP_ID','Freebox_OS'));
		$app_name 		=trim(config::byKey('FREEBOX_SERVER_APP_NAME','Freebox_OS'));
		$app_version 	=trim(config::byKey('FREEBOX_SERVER_APP_VERSION','Freebox_OS'));
		$device_name 	=trim(config::byKey('FREEBOX_SERVER_DEVICE_NAME','Freebox_OS'));
		$http = new com_http($serveur . '/api/v3/login/authorize/');
		$http->setPost(
                	json_encode(
				array(
					'app_id' => $app_id,
					'app_name' => $app_name,
					'app_version' => $app_version,
					'device_name' => $device_name
				)
			)
		);
		$result = $http->exec(30, 2);
		if (is_json($result)) {
            return json_decode($result, true);
        }
        return $result;
	}
	public function ask_track_authorization(){
		$serveur		=trim(config::byKey('FREEBOX_SERVER_IP','Freebox_OS'));
		$track_id 		=config::byKey('FREEBOX_SERVER_TRACK_ID','Freebox_OS');
		$http = new com_http($serveur . '/api/v3/login/authorize/' . $track_id);
		$result = $http->exec(30, 2);
	        if (is_json($result)) {
	            return json_decode($result, true);
	        }
	        return $result;
	}
	public function open_session(){
		$serveur=trim(config::byKey('FREEBOX_SERVER_IP','Freebox_OS'));
		$app_token=config::byKey('FREEBOX_SERVER_APP_TOKEN','Freebox_OS');
		$app_id =trim(config::byKey('FREEBOX_SERVER_APP_ID','Freebox_OS'));
		
		$http = new com_http($serveur . '/api/v3/login/');
		$json=$http->exec(30, 2);
		$json_retour = json_decode($json, true);
		
		$challenge = $json_retour['result']['challenge'];
		$password = hash_hmac('sha1', $challenge, $app_token);
		
		$http = new com_http($serveur . '/api/v3/login/session/');
		$http->setPost( json_encode( array(
				'app_id' => $app_id,
				'password' => $password
				)
			)
		);
		$json=$http->exec(30, 2);
		$json_connect=json_decode($json, true);
		if ($json_connect['success']){
			cache::set('Freebox_OS::SessionToken', $json_connect['result']['session_token'], 0);
			//config::save('FREEBOX_SERVER_SESSION_TOKEN', $json_connect['result']['session_token'],'Freebox_OS');
		}
		else
			return false;
		return true;
	}
	public function fetch($api_url,$params=array(), $method=null) {
	        if (!$method) 
	            	$method=(!$params)?'GET':'POST';
		$serveur=trim(config::byKey('FREEBOX_SERVER_IP','Freebox_OS'));
		$cache = cache::byKey('Freebox_OS::SessionToken');
		$session_token = $cache->getValue('');
		//$session_token=config::byKey('FREEBOX_SERVER_SESSION_TOKEN','Freebox_OS');
		log::add('Freebox_OS','debug','Connexion ' . $method .' sur la l\'adresse '. $serveur.$api_url .'('.json_encode($params).')');
	        $ch = curl_init();
	        curl_setopt($ch, CURLOPT_URL, $serveur.$api_url);
	        curl_setopt($ch, CURLOPT_HEADER, false);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
	        if ($method=="POST") {
	            curl_setopt($ch, CURLOPT_POST, true);
	        } elseif ($method=="DELETE") {
	            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
	        } elseif ($method=="PUT") {
	            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
	        }
	        if ($params)
	            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
	        curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Fbx-App-Auth: $session_token"));
	        $content = curl_exec($ch);
	        curl_close($ch);
			log::add('Freebox_OS','debug', $content);
			return json_decode($content, true);	
    	}
	public function close_session(){
		$serveur=trim(config::byKey('FREEBOX_SERVER_IP','Freebox_OS'));
		$http = new com_http($serveur . '/api/v3/login/logout/');
		$http->setPost(array());
		$json_close=$http->exec(2,2);
		$cache = cache::byKey('Freebox_OS::SessionToken');
		$cache->remove();
		//config::save('FREEBOX_SERVER_SESSION_TOKEN','','Freebox_OS');
		return $json_close;
	}
	public function WakeOnLAN($Mac){
			$return=self::fetch('/api/v3/lan/wol/pub/',array("mac"=> $Mac,"password"=> ""),"POST");	
			return $return['success'];
	}
    	public function Downloads($Etat){
			$List_DL=self::fetch('/api/v3/downloads/',null);
			$nbDL=count($List_DL['result']);
                        for($i = 0; $i < $nbDL; ++$i)
			{
				if ($Etat==0)
	        	                $Downloads=self::fetch('/api/v3/downloads/'.$List_DL['result'][$i]['id'],array("status"=>"stopped"),"PUT");
				if ($Etat==1)
					$Downloads=self::fetch('/api/v3/downloads/'.$List_DL['result'][$i]['id'],array("status"=>"downloading"),"PUT");
                        }        
                                if($Downloads['success'])
                                        return $Downloads['success'];
                                else
                                        return false;                                                                                                                                                                                     
	}
	public function DownloadStats(){
		$DownloadStats = self::fetch('/api/v3/downloads/stats/',null);
          		
			if($DownloadStats['success'])
				return $DownloadStats['result'];
			else
				return false;
	}
	public function PortForwarding($Port){
			$PortForwarding = self::fetch('/api/v3/fw/redir/',null);
			
                        $nbPF=count($PortForwarding['result']);
                        for($i = 0; $i < $nbPF; ++$i)
			{
				if ($PortForwarding['result'][$i]['wan_port_start'] == $Port)
					if ($PortForwarding['result'][$i]['enabled'])
        	                		$PortForwarding=self::fetch('/api/v3/fw/redir/'.$PortForwarding['result'][$i]['id'],array("enabled"=>false),"PUT");
					else
						$PortForwarding=self::fetch('/api/v3/fw/redir/'.$PortForwarding['result'][$i]['id'],array("enabled"=>true),"PUT");
                                        
			}
			if($PortForwarding['success'])	
				return $PortForwarding['result'];
			else
				return false;
	}
	public function disques($logicalId=''){
			$reponse = self::fetch('/api/v3/storage/disk/',null);
			
			if($reponse['success']){
				$nbDD=count($reponse['result']);
				$countDD=0;
				$return=0;
				while($countDD < $nbDD)
				{
					$total_bytes=$reponse['result'][$countDD]['partitions'][0]['total_bytes'];
					$used_bytes=$reponse['result'][$countDD]['partitions'][0]['used_bytes'];
					$value=round($used_bytes/$total_bytes*100);
					if($reponse['result'][$countDD]['id']==$logicalId){
						$return=$value;
					}
					else {	
						$Disque=self::AddEqLogic('Disque Dur','Disque');
						$commande=self::AddCommande($Disque,'Occupation ['.$reponse['result'][$countDD]['type'].'] - '.$reponse['result'][$countDD]['id'],$reponse['result'][$countDD]['id'],"info",'numeric','Freebox_OS_Disque','%');
						$commande->event($value);
					}
					$countDD++;
				}
			return $return;
			}
			else
				return false;
	}
	public function wifi(){
			$data_json = self::fetch('/api/v3/wifi/config/',null);
			
			if($data_json['success']){
				$value=0;
				if($data_json['result']['enabled'])
					$value=1;
				log::add('Freebox_OS','debug','L\'état du wifi est '.$value);
				return $value;
			}
			else
				return false;
	}
	public function wifiPUT($parametre) {
			log::add('Freebox_OS','debug','Mise dans l\'état '.$parametre.' du wifi');
			if ($parametre==1)
				$return=self::fetch('/api/v3/wifi/config/',array("enabled" => true),"PUT");	
			else
				$return=self::fetch('/api/v2/wifi/config/',array("enabled" => false),"PUT");	
			
			if($return['success'])
			{
				return $return['result']['enabled'];
			}
	}
	public function reboot() {
			$content=self::fetch('/api/v3/system/reboot/',null,"POST");	
			
			if($content['success'])
				return $content;
			else
				return false;
	}
	public function ringtone_on() {
			$content=self::fetch('/api/v3/phone/dect_page_start/',"","POST");	
			
			if($content['success'])
				return $content;
			else
				return false;
	}
	public function ringtone_off() {
			$content=self::fetch('/api/v3/phone/dect_page_stop/',"","POST");	
			
			if($content['success'])
				return $content;
			else
				return false;
	}
	public function system() {		
			$systemArray = self::fetch('/api/v3/system/',null);
			
			if($systemArray['success']){	
				return $systemArray['result'];
			}
			else
				return false;
	}	
	public function UpdateSystem() {		
		$System=self::AddEqLogic('Système','System');
		$Commande=self::AddCommande($System,'Update','update',"action",'other','Freebox_OS_System');
		log::add('Freebox_OS','debug','Vérification d\'une mise a jours du serveur');
		$firmwareOnline=file_get_contents("http://dev.freebox.fr/blog/?cat=5");
		preg_match_all('|<h1><a href=".*">Mise à jour du Freebox Server (.*)</a></h1>|U', $firmwareOnline , $parseFreeDev, PREG_PATTERN_ORDER);			
		if(intval($Commande->execCmd()) < intval($parseFreeDev[1][0]))
			self::reboot();
	}
	public function adslStats(){	
			$adslRateJson = self::fetch('/api/v3/connection/',null);
			if($adslRateJson['success']){		
				$vdslRateJson = self::fetch('/api/v3/connection/xdsl/',null);				
				if($vdslRateJson['result']['status']['modulation'] == "vdsl")
					$adslRateJson['result']['media'] = $vdslRateJson['result']['status']['modulation'];
				
				$retourFbx = array(	'rate_down' 	=> round($adslRateJson['result']['rate_down']/1024,2), 
									'rate_up' 		=> round($adslRateJson['result']['rate_up']/1024,2), 
									'bandwidth_up' 	=> round($adslRateJson['result']['bandwidth_up']/1000000,2), 
									'bandwidth_down' => round($adslRateJson['result']['bandwidth_down']/1000000,2), 
									'media'			=> $adslRateJson['result']['media'],
									'state' 		=> $adslRateJson['result']['state'] );
				return $retourFbx;
			}
			else
				return false;
	}
	public function freeboxPlayerPing(){
			$listEquipement = self::fetch('/api/v3/lan/browser/pub/',null);
			
			if($listEquipement['success']){
				$Reseau=Freebox_OS::AddEqLogic('Réseau','Reseau');
				foreach($listEquipement['result'] as $Equipement)
				{
					if($Equipement['primary_name']!='')
					{
						$Commande=Freebox_OS::AddCommande($Reseau,$Equipement['primary_name'],$Equipement['id'],"info",'binary','Freebox_OS_Reseau');
						$Commande->setConfiguration('host_type',$Equipement['host_type']);
						if (isset($result['l3connectivities']))
						{
							foreach($Equipement['l3connectivities'] as $Ip){
								if ($Ip['active']){
									if($Ip['af']=='ipv4')
										$Commande->setConfiguration('IPV4',$Ip['addr']);
									else
										$Commande->setConfiguration('IPV6',$Ip['addr']);
								}
							}
						}
						if($Commande->execCmd() != $Equipement['active']){
							$Commande->setCollectDate('');
							$Commande->event($Equipement['active']);
						}
						$Commande->save();	
					}
				}
			}
			return true;
	}
	public function ReseauPing($id=''){
			$Ping = self::fetch('/api/v3/lan/browser/pub/'.$id,null);
			
			if($Ping['success'])
				return $Ping['result'];
			else
				return false;
	}
	public function nb_appel_absence() {
			$listNumber_missed='';
			$listNumber_accepted='';
			$listNumber_outgoing='';
			$pre_check_con = self::fetch('/api/v3/call/log/',null);
			if($pre_check_con['success']){			
				$timestampToday = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
				if(isset($pre_check_con['result'])){
					$nb_call = count($pre_check_con['result']);
					
					$cptAppel_outgoing = 0;
					$cptAppel_missed = 0;
					$cptAppel_accepted = 0;
					for ($k=0; $k<$nb_call; $k++) 
					{
						$jour = $pre_check_con['result'][$k]['datetime'];
						
						$time = date('H:i', $pre_check_con['result'][$k]['datetime']);
						if ($timestampToday <= $jour) 
						{
							if($pre_check_con['result'][$k]['name']==$pre_check_con['result'][$k]['number'])
							{
								$name="N.C.";
							}
							else						
							{
								$name=$pre_check_con['result'][$k]['name'];
							}
							
							if ($pre_check_con['result'][$k]['type'] == 'missed')
							{
								$cptAppel_missed++;
								$listNumber_missed.= $pre_check_con['result'][$k]['number'].": ".$name." à ".$time." - de ".$pre_check_con['result'][$k]['duration']."s<br>";
							}
							if ($pre_check_con['result'][$k]['type'] == 'accepted')
							{
								$cptAppel_accepted++;
								$listNumber_accepted.= $pre_check_con['result'][$k]['number'].": ".$name." à ".$time." - de ".$pre_check_con['result'][$k]['duration']."s<br>";
							}
							if ($pre_check_con['result'][$k]['type'] == 'outgoing')
							{
								$cptAppel_outgoing++;
								$listNumber_outgoing.= $pre_check_con['result'][$k]['number'].": ".$name." à ".$time." - de ".$pre_check_con['result'][$k]['duration']."s<br>";
							}
						}
					}
					$retourFbx = array('missed' => $cptAppel_missed, 'list_missed' => $listNumber_missed, 'accepted' => $cptAppel_accepted, 'list_accepted' => $listNumber_accepted, 'outgoing' => $cptAppel_outgoing,'list_outgoing' => $listNumber_outgoing );
				}
				else	
					$retourFbx = array('missed' => 0, 'list_missed' => "", 'accepted' => 0, 'list_accepted' => "", 'outgoing' => 0,'list_outgoing' => "" );
				
				return $retourFbx;
			}
			else
				return false;
	}
	public function send_cmd_fbxtv($key){
		$serveur		=trim($this->getConfiguration('FREEBOX_TV_IP'));
		$tv_code		=trim($this->getConfiguration('FREEBOX_TV_CODE'));
		
		$http = new com_http($serveur.'/pub/remote_control?code='.$tv_code.'&key='.$key);
		$result=$http->exec(2,2);
		return $result;
	}
	public function airmediaConfig() {
			$parametre["enabled"]=$this->getIsEnable();
			$parametre["password"]=$this->getConfiguration('password');
	        	$return=self::fetch('/api/v3/airmedia/config/',$parametre,"PUT");   
	         	
			if($return['success'])
	                	return $return['result'];
	                else
	                        return false;
	}
	public static function airmediaReceivers() {
	        	$return=self::fetch('/api/v3/airmedia/receivers/',null);   
	         	
			if($return['success'])
	                	return $return['result'];
	                else
	                        return false;
	}
	public function AirMediaAction($receiver,$action,$media_type,$media=null) {
		if($receiver!=""&&$media_type!=null){
	        	log::add('Freebox_OS','debug','AirMedia Start Video: '.$media);
	        	$parametre["action"]=$action;
	        	$parametre["media_type"]=$media_type;
	        	if($media!=null)
	        		$parametre["media"]=$media;
	        	$parametre["password"]=$this->getConfiguration('password');
	        	$return=self::fetch('/api/v3/airmedia/receivers/'.($receiver).'/',$parametre,'POST');
			if($return['success'])
	                	return true;
	                else
	                        return false;
		}
		else
		       return false;
	}
	public function log($_type = 'INFO', $_message) {
    	log::add('Freebox_OS', $_type, '['.$this->name.'-'.$this->getId().'] '.getmypid().' '.$_message, $this->name);
    }
	public static function AddEqLogic($Name,$_logicalId) {
			$EqLogic = self::byLogicalId($_logicalId, 'Freebox_OS');
			if (!is_object($EqLogic)) {
				$EqLogic = new Freebox_OS();
				$EqLogic->setLogicalId($_logicalId);
				$EqLogic->setObject_id(null);
				$EqLogic->setEqType_name('Freebox_OS');
				$EqLogic->setIsEnable(1);
				$EqLogic->setIsVisible(0);
			}
			$EqLogic->setName($Name);
			$EqLogic->save();
			return $EqLogic;
		}
	public static function AddCommande($eqLogic,$Name,$_logicalId,$Type="info", $SubType='binary', $Template='', $unite='') {
		$Commande = $eqLogic->getCmd(null,$_logicalId);
		if (!is_object($Commande))
		{
			$VerifName=$Name;
			$Commande = new Freebox_OSCmd();
			$Commande->setId(null);
			$Commande->setLogicalId($_logicalId);
			$Commande->setEqLogic_id($eqLogic->getId());
			$count=0;
			while (is_object(cmd::byEqLogicIdCmdName($eqLogic->getId(),$VerifName)))
			{
				$count++;
				$VerifName=$Name.'('.$count.')';
			}
			$Commande->setName($VerifName);
			$Commande->setUnite($unite);
		}
		$Commande->setType($Type);
		$Commande->setSubType($SubType);
		$Commande->setTemplate('dashboard',$Template);
		$Commande->setTemplate('mobile', $Template);
		$Commande->save();
		return $Commande;
	}
	public static function CreateArchi() {
		// ADSL
		$ADSL=self::AddEqLogic('ADSL','ADSL');
		self::AddCommande($ADSL,'Freebox rate down','rate_down',"info",'numeric','Freebox_OS_Adsl');
		self::AddCommande($ADSL,'Freebox rate up','rate_up',"info",'numeric','Freebox_OS_Adsl');
		self::AddCommande($ADSL,'Freebox bandwidth up','bandwidth_up',"info",'numeric','Freebox_OS_Adsl');
		self::AddCommande($ADSL,'Freebox bandwidth down','bandwidth_down',"info",'numeric','Freebox_OS_Adsl');
		self::AddCommande($ADSL,'Freebox media','media',"info",'string','Freebox_OS_Adsl');
		self::AddCommande($ADSL,'Freebox state','state',"info",'string','Freebox_OS_Adsl');
		$System=self::AddEqLogic('Système','System');
		self::AddCommande($System,'Update','update',"action",'other','Freebox_OS_System');
		self::AddCommande($System,'Reboot','reboot',"action",'other','Freebox_OS_System');
		$StatusWifi=self::AddCommande($System,'Status du wifi','wifiStatut',"info",'binary','Freebox_OS_Wifi');
		$StatusWifi->setIsVisible(0);
		$StatusWifi->save();
		$ActiveWifi=self::AddCommande($System,'Active/Désactive le wifi','wifiOnOff',"action",'other','Freebox_OS_Wifi');
		$ActiveWifi->setValue($StatusWifi->getId());
		$ActiveWifi->save();
		$WifiOn=self::AddCommande($System,'Wifi On','wifiOn',"action",'other','Freebox_OS_Wifi');
		$WifiOn->setIsVisible(0);
		$WifiOn->save();
		$WifiOff=self::AddCommande($System,'Wifi Off','wifiOff',"action",'other','Freebox_OS_Wifi');
		$WifiOff->setIsVisible(0);
		$WifiOff->save();
		self::AddCommande($System,'Freebox firmware version','firmware_version',"info",'string','Freebox_OS_System');
		self::AddCommande($System,'Mac','mac',"info",'string','Freebox_OS_System');
		self::AddCommande($System,'Vitesse ventilateur','fan_rpm',"info",'string','Freebox_OS_System','tr/min');
		self::AddCommande($System,'temp sw','temp_sw',"info",'string','Freebox_OS_System','°C');
		self::AddCommande($System,'Allumée depuis','uptime',"info",'string','Freebox_OS_System');
		self::AddCommande($System,'board name','board_name',"info",'string','Freebox_OS_System');
		self::AddCommande($System,'temp cpub','temp_cpub',"info",'string','Freebox_OS_System','°C');
		self::AddCommande($System,'temp cpum','temp_cpum',"info",'string','Freebox_OS_System','°C');
		self::AddCommande($System,'serial','serial',"info",'string','Freebox_OS_System');
		$cmdPF=self::AddCommande($System,'Redirection de ports','port_forwarding',"action",'message','Freebox_OS_System');
	        $cmdPF->setIsVisible(0);
                $cmdPF->save(); 
		$Disque=self::AddEqLogic('Disque Dur','Disque');
		$Phone=self::AddEqLogic('Téléphone','Phone');
		self::AddCommande($Phone,'Nombre Appels Manqués','nbAppelsManquee',"info",'numeric','Freebox_OS_Phone');
		self::AddCommande($Phone,'Nombre Appels Reçus','nbAppelRecus',"info",'numeric','Freebox_OS_Phone');
		self::AddCommande($Phone,'Nombre Appels Passés','nbAppelPasse',"info",'numeric','Freebox_OS_Phone');
		self::AddCommande($Phone,'Liste Appels Manqués','listAppelsManquee',"info",'string','Freebox_OS_Phone');
		self::AddCommande($Phone,'Liste Appels Reçus','listAppelsRecus',"info",'string','Freebox_OS_Phone');
		self::AddCommande($Phone,'Liste Appels Passés','listAppelsPasse',"info",'string','Freebox_OS_Phone');
		self::AddCommande($Phone,'Faire sonner les téléphones DECT','sonnerieDectOn',"action",'other','Freebox_OS_Phone');
		self::AddCommande($Phone,'Arrêter les sonneries des téléphones DECT','sonnerieDectOff',"action",'other','Freebox_OS_Phone');
                $Downloads=self::AddEqLogic('Téléchargements','Downloads');
                self::AddCommande($Downloads,'Nombre de tâche(s)','nb_tasks',"info",'string','Freebox_OS_Downloads');  
                self::AddCommande($Downloads,'Nombre de tâche(s) active','nb_tasks_active',"info",'string','Freebox_OS_Downloads');
		self::AddCommande($Downloads,'Nombre de tâche(s) en extraction','nb_tasks_extracting',"info",'string','Freebox_OS_Downloads');
		self::AddCommande($Downloads,'Nombre de tâche(s) en réparation','nb_tasks_repairing',"info",'string','Freebox_OS_Downloads');
		self::AddCommande($Downloads,'Nombre de tâche(s) en vérification','nb_tasks_checking',"info",'string','Freebox_OS_Downloads');
                self::AddCommande($Downloads,'Nombre de tâche(s) en attente','nb_tasks_queued',"info",'string','Freebox_OS_Downloads');
                self::AddCommande($Downloads,'Nombre de tâche(s) en erreur','nb_tasks_error',"info",'string','Freebox_OS_Downloads');
                self::AddCommande($Downloads,'Nombre de tâche(s) stoppée(s)','nb_tasks_stopped',"info",'string','Freebox_OS_Downloads');
                self::AddCommande($Downloads,'Nombre de tâche(s) terminée(s)','nb_tasks_done',"info",'string','Freebox_OS_Downloads');
		self::AddCommande($Downloads,'Téléchargement en cours','nb_tasks_downloading',"info",'string','Freebox_OS_Downloads');
		self::AddCommande($Downloads,'Vitesse réception','rx_rate',"info",'string','Freebox_OS_Downloads','Mo/s');
		self::AddCommande($Downloads,'Vitesse émission','tx_rate',"info",'string','Freebox_OS_Downloads','Mo/s');
                self::AddCommande($Downloads,'Start DL','start_dl',"action",'other','Freebox_OS_Downloads');
                self::AddCommande($Downloads,'Stop DL','stop_dl',"action",'other','Freebox_OS_Downloads');
                $AirPlay=self::AddEqLogic('AirPlay','AirPlay');
		self::AddCommande($AirPlay,'Player actuel AirMedia','ActualAirmedia',"info",'string','Freebox_OS_AirMedia_Recever');
		self::AddCommande($AirPlay,'AirMedia Start','airmediastart',"action",'message','Freebox_OS_AirMedia_Start');
		self::AddCommande($AirPlay,'AirMedia Stop','airmediastop',"action",'message','Freebox_OS_AirMedia_Start');
		log::add('Freebox_OS','debug',config::byKey('FREEBOX_SERVER_APP_TOKEN'));
		log::add('Freebox_OS','debug',config::byKey('FREEBOX_SERVER_TRACK_ID'));
		if(config::byKey('FREEBOX_SERVER_TRACK_ID')!='')
		{
			self::disques();
			self::wifi();
			self::system();
			self::adslStats();
			self::nb_appel_absence();
			self::freeboxPlayerPing();
			self::DownloadStats();
		}
    }
	public function toHtml($_version = 'mobile') {
		$_version = jeedom::versionAlias($_version);
		$replace = array(
			'#id#' => $this->getId(),
			'#name#' => ($this->getIsEnable()) ? $this->getName() : '<del>' . $this->getName() . '</del>',
			'#eqLink#' => $this->getLinkToConfiguration(),
			'#background#' => $this->getBackgroundColor($_version),		
			'#height#' => $this->getDisplay('height', 'auto'),
			'#width#' => $this->getDisplay('width', '250'),
			'#cmd#' => ""
		);
		if($this->getLogicalId()=='Reseau'||$this->getLogicalId()=='System')
		{
			if ($this->getIsEnable()) {
				$EquipementsHtml='';
				foreach ($this->getCmd(null, null, true) as $cmd) {
					$replaceCmd['#host_type#'] = $cmd->getConfiguration('host_type');
					$replaceCmd['#IPV4#'] = $cmd->getConfiguration('IPV4');
					$replaceCmd['#IPV6#'] = $cmd->getConfiguration('IPV6');
					$EquipementsHtml.=template_replace($replaceCmd, $cmd->toHtml($_version));
				}
			}     
			$replace['#Equipements#'] = $EquipementsHtml;
		}
		elseif($this->getLogicalId()=='Disque')
		{
			if ($this->getIsEnable()) {
				foreach ($this->getCmd(null, null, true) as $cmd) {
					 $replace['#cmd#'] .= $cmd->toHtml($_version);
				}
			}     
		}
		else
		{
			if ($this->getIsEnable()) {
				foreach ($this->getCmd(null, null, true) as $cmd) {
					 $replace['#'.$cmd->getLogicalId().'#'] = $cmd->toHtml($_version);
				}
			}     
		}
		if ($_version == 'dview' || $_version == 'mview') {
			$object = $this->getObject();
			$replace['#name#'] = (is_object($object)) ? $object->getName() . ' - ' . $replace['#name#'] : $replace['#name#'];
		}
		
		return template_replace($replace, getTemplate('core', $_version, $this->getLogicalId(),'Freebox_OS'));
	}
	public function preSave() {	
		switch($this->getLogicalId())	{
			case 'FreeboxTv':
				$ActionPower=self::AddCommande($this,'Power','power',"action",'other','Freebox_Tv');
				$InfoPower=self::AddCommande($this,' Statut Power','powerstat',"info",'binary','Freebox_Tv');
				$ActionPower->setValue($InfoPower->getId());
				$ActionPower->save();
				self::AddCommande($this,'Volume +','vol_inc',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Volume -','vol_dec',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Programme +','prgm_inc',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Programme -','prgm_dec',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Home','home',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Mute','mute',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Enregister','rec',"action",'other','Freebox_Tv');
				self::AddCommande($this,'1','1',"action",'other','Freebox_Tv');
				self::AddCommande($this,'2','2',"action",'other','Freebox_Tv');
				self::AddCommande($this,'3','3',"action",'other','Freebox_Tv');
				self::AddCommande($this,'4','4',"action",'other','Freebox_Tv');
				self::AddCommande($this,'5','5',"action",'other','Freebox_Tv');
				self::AddCommande($this,'6','6',"action",'other','Freebox_Tv');
				self::AddCommande($this,'7','7',"action",'other','Freebox_Tv');
				self::AddCommande($this,'8','8',"action",'other','Freebox_Tv');
				self::AddCommande($this,'9','9',"action",'other','Freebox_Tv');
				self::AddCommande($this,'0','0',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Precedent','prev',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Lecture','play',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Suivant','next',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Rouge','red',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Vert','green',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Bleu','blue',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Jaune','yellow',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Ok','ok',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Haut','up',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Bas','down',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Gauche','left',"action",'other','Freebox_Tv');
				self::AddCommande($this,'Droite','right',"action",'other','Freebox_Tv');
			break;
			case 'AirPlay':
				$this->airmediaConfig();
			break;
		}
		if($this->getLogicalId()=='')
			$this->setLogicalId('FreeboxTv');
	}
	public static function cronHourly() {
      	self::disques();
		self::freeboxPlayerPing();
	}	
	public static function RefreshInformation() {
		foreach(eqLogic::byType('Freebox_OS') as $Equipement){
			if($Equipement->getIsEnable()){
				foreach($Equipement->getCmd('info') as $Commande){
					$Commande->execute();
				}
			}
		}
	}
	public static function dependancy_info() {
		$return = array();
		$return['log'] = 'Freebox_OS_update';
		$return['progress_file'] = '/tmp/compilation_Freebox_OS_in_progress';
		if (exec('dpkg -s netcat | grep -c "Status: install"') ==1)
				$return['state'] = 'ok';
		else
			$return['state'] = 'nok';
		return $return;
	}
	public static function dependancy_install() {
		if (file_exists('/tmp/compilation_Freebox_OS_in_progress')) {
			return;
		}
		log::remove('Freebox_OS_update');
		$cmd = 'sudo /bin/bash ' . dirname(__FILE__) . '/../../ressources/install.sh';
		$cmd .= ' >> ' . log::getPathToLog('Freebox_OS_update') . ' 2>&1 &';
		exec($cmd);
	}
}
class Freebox_OSCmd extends cmd {
	public function execute($_options = array())	{
		log::add('Freebox_OS','debug','Connexion sur la freebox pour '.$this->getName());
		switch ($this->getEqLogic()->getLogicalId())
		{
			case 'ADSL':
				$result = $this->getEqLogic()->adslStats();
				if($result!=false){
					switch ($this->getLogicalId()) 
					{
						case "rate_down":
							$return= $result['rate_down'];
							break;
						case "rate_up":
							$return= $result['rate_up'];
							break;
						case "bandwidth_up":
							$return= $result['bandwidth_up'];
							break;
						case "bandwidth_down":
							$return= $result['bandwidth_down'];
							break;
						case "media":
							$return= $result['media'];
							break;
						case "state":
							$return= $result['state'];
							break;
					}
				}
			break;
			case 'Downloads':
				$result = $this->getEqLogic()->DownloadStats();
				if($result!=false){
					switch ($this->getLogicalId())
					{
						case "nb_tasks":
							$return= $result['nb_tasks'];
							break;
						case "nb_tasks_downloading":
                                                        $return= $result['nb_tasks_downloading'];
                                                        break;
						case "nb_tasks_done":
                                                        $return= $result['nb_tasks_done'];
                                                        break;
						case "rx_rate":
                                                        $return= $result['rx_rate'];
                                                        if(function_exists('bcdiv'))
                                                		$return= bcdiv($return,1048576,2);
                                                        break;
						case "tx_rate":
                                                        $return= $result['tx_rate'];
                                                        if(function_exists('bcdiv'))
                                                		$return= bcdiv($return,1048576,2);
							break;
                                                case "nb_tasks_active":
                                                        $return= $result['nb_tasks_active'];
                                                        break;
                                                case "nb_tasks_stopped":
                                                        $return= $result['nb_tasks_stopped'];
                                                        break;
                                                case "nb_tasks_queued":
                                                        $return= $result['nb_tasks_queued'];
                                                        break;
                                                case "nb_tasks_repairing":
                                                        $return= $result['nb_tasks_repairing'];
                                                        break;
                                                case "nb_tasks_extracting":
                                                        $return= $result['nb_tasks_extracting'];
                                                        break;
                                                case "nb_tasks_error":
                                                        $return= $result['nb_tasks_error'];
                                                        break;    
                                                case "nb_tasks_checking":
                                                        $return= $result['nb_tasks_checking'];
                                                        break;    
                                                case "stop_dl":
                                                        $this->getEqLogic()->Downloads(0);
	                                                break;  
                                                case "start_dl":
                                                        $this->getEqLogic()->Downloads(1);
                                                        break;  
					}
				}
			break;
			case 'System':
				if($this->getLogicalId()=="wifiStatut"||$this->getLogicalId()=="wifiOnOff"||$this->getLogicalId()=='wifiOn'||$this->getLogicalId()=='wifiOff')
					$result = $this->getEqLogic()->wifi();
				else
					$result = $this->getEqLogic()->system();
					switch ($this->getLogicalId()) 
					{
						case "reboot":
							$this->getEqLogic()->reboot();
							break;
						case "update":
							$this->getEqLogic()->UpdateSystem();
							break;
						case "mac":
							$return= $result['mac'];
							break;
						case "fan_rpm":
							$return= $result['fan_rpm'];
							break;
						case "temp_sw":
							$return= $result['temp_sw'];
							break;
						case "uptime":
							$return= $result['uptime'];
							$return=str_replace(' heure ','h ',$return);
							$return=str_replace(' heures ','h ',$return);
							$return=str_replace(' minute ','min ',$return);
							$return=str_replace(' minutes ','min ',$return);
							$return=str_replace(' secondes','s',$return);
							$return=str_replace(' seconde','s',$return);
							break;
						case "board_name":
							$return= $result['board_name'];
							break;
						case "temp_cpub":
							$return= $result['temp_cpub'];
							break;
						case "temp_cpum":
							$return= $result['temp_cpum'];
							break;
						case "serial":
							$return= $result['serial'];
							break;
						case "firmware_version":
							$return= $result['firmware_version'];
							break;
						case "wifiStatut":
							$return= $result;
							break;
						case "wifiOnOff":
							if($result==true) 
								$this->getEqLogic()->wifiPUT(0);
							else
								$this->getEqLogic()->wifiPUT(1);
						break;
						case 'wifiOn':
							$this->getEqLogic()->wifiPUT(1);
						break;
						case 'wifiOff':
							$this->getEqLogic()->wifiPUT(0);
						break;
						case 'port_forwarding':
							$this->getEqLogic()->PortForwarding($_options['message']);
				}		break;
			break;
			case 'Disque':		
				$result = $this->getEqLogic()->disques($this->getLogicalId());
				if($result!=false)			
					$return= $result;
			break;
			case 'Phone':
				$result = $this->getEqLogic()->nb_appel_absence();
				if($result!=false){
					switch ($this->getLogicalId()) 
					{
						case "nbAppelsManquee":
							$return= $result['missed'];
							//$return= $result;
							break;
						case "nbAppelRecus":
							$return= $result['accepted'];
							break;
						case "nbAppelPasse":
							$return= $result['outgoing'];
							break;
						case "listAppelsManquee":
							$return= $result['list_missed'];
							break;
						case "listAppelsRecus":
							$return= $result['list_accepted'];
							break;
						case "listAppelsPasse":
							$return= $result['list_outgoing'];
							break;
						case "sonnerieDectOn":
							$this->getEqLogic()->ringtone_on();
							break;
						case "sonnerieDectOff":
							$this->getEqLogic()->ringtone_off();
							break;
					}
				}
			break;
			case'Reseau':
				$result=$this->getEqLogic()->ReseauPing($this->getLogicalId());
				if($result!=false){
					if (isset($result['l3connectivities']))
					{
						foreach($result['l3connectivities'] as $Ip){
							if ($Ip['active']){
								if($Ip['af']=='ipv4')
									$this->setConfiguration('IPV4',$Ip['addr']);
								else
									$this->setConfiguration('IPV6',$Ip['addr']);
							}
						}
					}
					$this->setConfiguration('host_type',$result['host_type']);
					$this->save();
					if (isset($result['active']))
						$return=$result['active'];
					else
						$return=false;
				}
			break;
			case'FreeboxTv':
				switch($this->getLogicalId()){
					case 'powerstat':
						$return=exec('sudo nc -zv '.$this->getEqLogic()->getConfiguration('FREEBOX_TV_IP').' 7000 2>&1 | grep -E "open|succeeded" | wc -l');
						log::add('Freebox_OS','debug','Etat du player freebox '.$this->getEqLogic()->getConfiguration('FREEBOX_TV_IP').' '.$return);
					break;
					case 'power':
						$result=$this->getEqLogic()->send_cmd_fbxtv($this->getLogicalId());
						cmd::byLogicalId('powerstat')->execute();
					break;
					default:
						$result=$this->getEqLogic()->send_cmd_fbxtv($this->getLogicalId());
					break;
				}
			break;
			case'AirPlay':
				$receiver=$this->getEqLogic()->getCmd(null,"ActualAirmedia")->execCmd();
				switch($this->getLogicalId()){
					case "airmediastart":
						$return = $this->getEqLogic()->AirMediaAction($receiver,"start",$_options['titre'],$_options['message']);
					break;
					case "airmediastop":
						$return = $this->getEqLogic()->AirMediaAction($receiver,"stop",$_options['titre'],$_options['message']);
					break;
				}
			break;
		}		
		if(isset($return)&&$this->execCmd() !=$return){
			$this->setCollectDate('');
			$this->event($return);
			$this->getEqLogic()->refreshWidget();
			return $return;
		}
	}
}
