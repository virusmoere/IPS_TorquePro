<?

	class TorqueProApp extends IPSModule
	{
		
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			
			$this->RegisterPropertyBoolean("forwardRequests", false);
			$this->RegisterPropertyString("forwardRequestsURL", "http://ian-hawkins.com/torque.php");
 
			$this->Reconnect();
		}
	
		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
			
			IPS_SetHidden($this->RegisterScript("Torque_Keys", "Torque Keys", file_get_contents(__DIR__ . "/keys.txt")), true);;
		}
		
		public function Reconnect()
		{
			// connect to TorqueProHook-Instance if not already	
			$this->ConnectParent("{77274c9a-9e36-4ee4-b153-a5dfd65a3828}");
			IPS_LogMessage("TorqueProApp ".$this->InstanceID, "(Re-)connected to parent");	
		}
		
		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString);
			$data = (array)$data->Buffer;
			if($data['id'] === IPS_GetObject($this->InstanceID)['ObjectIdent'])
				$this->ProcessData($data);
		}
		
		private function ForwardRequest($RequestURI)
		{
			/* Forward to Ian's Torque API: */
			$forwardRequestsURL = $this->ReadPropertyString("forwardRequestsURL");
			if ($forwardRequestsURL != "")
			{
				$ch = curl_init();
				$RequestURI = str_replace("/hook/torque", "", $RequestURI);
				$url = $forwardRequestsURL.$RequestURI;
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_FAILONERROR, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				if(curl_exec($ch) === false)
				{
					IPS_LogMessage("TorqueProApp ".$this->InstanceID, "Forwarding request failed. cURL: ".curl_error($ch));
				}
				curl_close($ch);
			} else {
				IPS_LogMessage("TorqueProApp ".$this->InstanceID, "Forwarding request failed. URL empty!");
			}
		}
		
		private function Map($lat, $long, $torqueID)
		{
			$iframe = <<<EOF
<iframe 
	style="width: 600px; height: 350px; margin: 0 auto; display:block;"
	width="600"
	height="350"
	frameborder="0"
	src="https://www.bing.com/maps/embed/viewer.aspx?v=3&amp;cp=$lat~$long&amp;lvl=18&amp;w=600&amp;h=350&amp;sty=h&amp;typ=d&amp;pp=~~$lat~$long&amp;ps=&amp;dir=0&amp;mkt=de-de&amp;src=SHELL&amp;form=BMEMJS">
</iframe>
EOF;

			$variable = @IPS_GetObjectIDByIdent('GoogleMaps', $torqueID);
			if($variable === false)
			{
				$variable = IPS_CreateVariable(3);// create string var
				IPS_SetName($variable, 'Position'); // name var by key name
				IPS_SetIdent($variable, 'GoogleMaps');
				IPS_SetVariableCustomProfile($variable, "~HTMLBox");
				IPS_SetParent($variable, $torqueID); // set var parent
			}	
			SetValueString($variable, $iframe);
		}
		
		/**
		* This function will be available automatically after the module is imported with the module control.
		* Using the custom prefix this function will be callable from PHP and JSON-RPC through:
		*
		* TORQUEA_ProcessHookData($id);
		*
		*/
		private function ProcessData($data)
		{
			include_once(IPS_GetKernelDir()."scripts/".IPS_GetScriptFile($this->GetIDForIdent("Torque_Keys")));
			$torqueID = $this->InstanceID;
			
			IPS_LogMessage("TorqueProApp ".$this->InstanceID, "Processing data from TorqueProHook");
			if($this->ReadPropertyBoolean("forwardRequests"))
				$this->ForwardRequest($_SERVER['REQUEST_URI']);
	
			foreach($data as $key => $value){
				unset($variable);
				if (array_key_exists($key, $key_names)) {
					$friendly_name = $key_names[$key];
					$variable = @IPS_GetObjectIDByIdent($key, $torqueID);
					if($variable === false){
						if (preg_match("/^k/", $key)) {
							// float
							$variable = IPS_CreateVariable(2);// create float var
							IPS_SetName($variable, $friendly_name); // name var by key name
							IPS_SetIdent($variable, $key);
							IPS_SetParent($variable, $torqueID); // set var parent
							SetValueFloat($variable, floatval($value)); // set value
						} else if ($key == "time" || $key == "session") {
							$variable = IPS_CreateVariable(1);// create int var
							IPS_SetName($variable, $friendly_name); // name var by key name
							IPS_SetIdent($variable, $key);
							IPS_SetVariableCustomProfile($variable, "~UnixTimestamp");
							IPS_SetParent($variable, $torqueID); // set var parent
						} else if ($key != "profileName") {
							//string
							$variable = IPS_CreateVariable(3);// create string var
							IPS_SetName($variable, $friendly_name); // name var by key name
							IPS_SetIdent($variable, $key);
							IPS_SetParent($variable, $torqueID); // set var parent
						}
					} 
					
					// set instance name to profileName
					if(isset($data['profileName']))
					{
						IPS_SetName($torqueID, utf8_decode($data['profileName']));
					}
					
					// Variable exists -> just set value
					if (preg_match("/^k/", $key))
					{
							SetValue($variable, floatval($value));
						} else if ($key == "time" || $key == "session") {
							SetValue($variable, $value/1000);
						} else if ($key != "profileName") {
							SetValue($variable, utf8_decode($value));
						}
					IPS_SetName($variable, $friendly_name);
				}
			}
			
			// Map (GPS long, GPS lat)
			if(isset($data['kff1005']) && isset($data['kff1006']))
			{
				$this->Map($data['kff1006'], $data['kff1005'], $torqueID);
			}
		}
	}
?>