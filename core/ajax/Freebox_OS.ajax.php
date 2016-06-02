<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */
try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }	
    if (init('action') == 'sendToBdd') 	{
		config::save('FREEBOX_SERVER_TRACK_ID', init('track_id'),'Freebox_OS');
		config::save('FREEBOX_SERVER_APP_TOKEN', init('app_token'),'Freebox_OS');
		ajax::success(true);
    }
	if (init('action') == 'connect') 	{
		ajax::success(Freebox_OS::track_id());
    }	
	if (init('action') == 'ask_track_authorization') 	{
		ajax::success(Freebox_OS::ask_track_authorization());
    }
	if (init('action') == 'SearchReseau') 	{
		ajax::success(Freebox_OS::freeboxPlayerPing());
    }
	if (init('action') == 'AddPortForwarding')	{
		$PortForwarding=array(
			"enabled"		=> 	init('enabled'),
			"comment"		=> 	init('comment'),
			"lan_port"		=> 	init('lan_port'),
			"wan_port_end"	=> 	init('wan_port_end'),
			"wan_port_start"=> 	init('wan_port_start'),
			"lan_ip" 		=>	init('lan_ip'),
			"ip_proto" 		=> 	init('ip_proto'),
			"src_ip"		=> 	init('src_ip'));
		ajax::success();
    }	
	if (init('action') == 'PortForwarding')	{
		ajax::success();
    }	
	if (init('action') == 'WakeOnLAN')	{
		$Commande=cmd::byId(init('id'));
		if(is_object($Commande)){
			$Mac=str_replace ('ether-','',$Commande->getLogicalId());
			ajax::success(Freebox_OS::WakeOnLAN($Mac));
		}
		ajax::success(false);
    }	
	if (init('action') == 'sendCmdPlayer')	{
		$Player=eqLogic::byId(init('id'));
		if(is_object($Player)){
			$Cmd=$Player->getCmd('action',init('cmd'));
			if(is_object($Cmd))
				ajax::success($Cmd->execute());
		}
		ajax::success(false);
    }	
    throw new Exception(__('Aucune methode correspondante à : ', __FILE__) . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayExeption($e), $e->getCode());
}
?>
