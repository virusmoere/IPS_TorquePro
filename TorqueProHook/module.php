<?

	class TorqueProHook extends IPSModule
	{
		
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			
			$this->RegisterPropertyString("allowedIds", "");
			$this->RegisterPropertyBoolean("debug", false);
		}
	
		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
			
			$hook_script = <<<EOF
<? 
//Do not delete or modify.
include(IPS_GetKernelDirEx()."scripts/__ipsmodule.inc.php");
include("../modules/IPSModules/TorqueProHook/module.php");
(new TorqueProHook($this->InstanceID))->ProcessHookData();
EOF;
			
			$sid = $this->RegisterScript("Hook", "Hook", $hook_script);
			$this->RegisterHook("/hook/torque", $sid);
			
		}
		
		private function RegisterHook($Hook, $TargetID)
		{
			$ids = IPS_GetInstanceListByModuleID("{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}");
			if(sizeof($ids) > 0) {
				$hooks = json_decode(IPS_GetProperty($ids[0], "Hooks"), true);
				$found = false;
				foreach($hooks as $index => $hook) {
					if($hook['Hook'] == "/hook/torque") {
						if($hook['TargetID'] == $TargetID)
							return;
						$hooks[$index]['TargetID'] = $TargetID;
						$found = true;
					}
				}
				if(!$found) {
					$hooks[] = Array("Hook" => "/hook/torque", "TargetID" => $TargetID);
				}
				IPS_SetProperty($ids[0], "Hooks", json_encode($hooks));
				IPS_ApplyChanges($ids[0]);
			}
		}
		
		/**
		* This function will be available automatically after the module is imported with the module control.
		* Using the custom prefix this function will be callable from PHP and JSON-RPC through:
		*
		* TORQUEH_ProcessHookData($id);
		*
		*/
		public function ProcessHookData()
		{
			$error = false;
			
			$data =array();
			foreach ($_GET as $key => $value) {
				$data[$key]  = $value;
			}
			
			if(isset($data['id']) || array_key_exists('id', $data))
			{
				if($data['id'] !== NULL)
				{
					$allowedIds = $this->ReadPropertyString("allowedIds");
					if($allowedIds != "")
					{
						$Ids = explode(",", $allowedIds);
						$i = 0;
						foreach($Ids as $Id) {
							if((string)$data['id'] == md5($Id))
								$i++;
						}
						if(!$i)
						{
							IPS_LogMessage("TorqueProHook", "Unauthorized ID: ".(string)$data['id']);
							$error = true;
						}
					}
				} else {
					IPS_LogMessage("TorqueProHook", "Invalid Request: Id invalid");
					$error = true;
				}
			} else {
				IPS_LogMessage("TorqueProHook", "Invalid Request: Id not existant");
				$error = true;
			}
			
			if(!$error)
			{
				$torqueProAppInstances = IPS_GetInstanceListByModuleID("{34747681-b29d-47e2-97ae-cfb6cd41a41c}");
				
				$torqueIDInstance = false;
				foreach($torqueProAppInstances as $torqueIDInstance){
					if($data['id'] === IPS_GetObject($torqueIDInstance)['ObjectIdent'])
						$torqueIDInstance = true;
				}
				if($torqueIDInstance === false)
				{
					// create TorqueProApp Instance for new ID
					IPS_LogMessage("TorqueProHook", "Creating instance for new TorqueID...");
					$torqueIDInstance = IPS_CreateInstance("{34747681-b29d-47e2-97ae-cfb6cd41a41c}");
					IPS_SetIdent($torqueIDInstance, $data['id']);
					IPS_SetName($torqueIDInstance, "New Torque ID: ".$data['id']);
				}
				
				IPS_LogMessage("TorqueProHook", "Sending data to childs...");
				// send data to connected TorqueProApp instances
				$JSONString = json_encode(Array("DataID" => "{0d72a65a-d79f-48cf-8699-59af0953719c}", "Buffer" => $data));
				IPS_SendDataToChildren($this->InstanceID, $JSONString);
				
				// Required by Torque Pro App
				print "OK!";
			} else {
				print "NOK!";
			}
			if($this->ReadPropertyBoolean("debug"))
				IPS_LogMessage("TorqueProHook", "Query String: ".$_SERVER['QUERY_STRING']);
		}
	}
?>