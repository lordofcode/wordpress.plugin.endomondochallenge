<?php
class S4U_Endomondo_Challenges_Admin {
    private $_ErrorMessage = "";
    public function HasError() {
        return ($_ErrorMessage != "");
    }
    public function GetError() {
        return $_ErrorMessage;
    }

    public function ProcessPost($postAction) {
        try {        
            switch($postAction) {
                case 'endomondo_login':
                    $stubLocation = str_replace("s4u-endomondo-challenges-admin.php", "userdata-stub.php", __FILE__);
                    $saveLocation = str_replace("s4u-endomondo-challenges-admin.php", "userdata.php", __FILE__);

                    $un = $_POST['endomondo_un'];
                    $pw = $_POST['endomondo_pw'];
                    if ($un != '' && $pw != '') {
                        $stubFile = file_get_contents($stubLocation);
                        $stubFile = str_replace("#USERNAME#", $_POST['endomondo_un'], $stubFile);
                        $stubFile = str_replace("#PASSWORD#", $_POST['endomondo_pw'], $stubFile);
                        file_put_contents($saveLocation, $stubFile);
                    }
                    $this->Login();
                break;
                case 'endomondo_clean':
                    $this->CleanData();
                break;
                case 'endomondo_fetchchallengedata':
                    $result = $this->FetchChallengeData(true, $_POST['challenge_id']);
                    if ($result !== false) {
                        $modifiedChallengesObject = [];
                        $challengesObject = json_decode(get_option('s4u_endomondo_challenges'));
                        for ($k=0; $k < count($challengesObject); $k++) {
                            if ($challengesObject[$k]->id == $result->id) {
                                array_push($modifiedChallengesObject, $result);
                            }
                            else{
                                array_push($modifiedChallengesObject, $challengesObject[$k]);
                            }
                        }
                        update_option('s4u_endomondo_challenges', json_encode($modifiedChallengesObject));
                    }
                break;
                case 'endomondo_removesinglechallenge':
                    $toRemove = intval($_POST['challenge_id']);
                    if ($toRemove <= 0) return;
                    if ($result !== false) {
                        $modifiedChallengesObject = [];
                        $challengesObject = json_decode(get_option('s4u_endomondo_challenges'));
                        for ($k=0; $k < count($challengesObject); $k++) {
                            if ($challengesObject[$k]->id == $toRemove) {
                                continue;
                            }
                            array_push($modifiedChallengesObject, $challengesObject[$k]);
                        }
                        update_option('s4u_endomondo_challenges', json_encode($modifiedChallengesObject));
                    }                
                break;
                case 'endomondo_fetchworkouts':
                    $this->FetchWorkouts(true);
                break;
                case 'endomondo_savechallengestartdate':
                    $this->SaveChallengeStartDate($_POST['challenge_id']);
                break;
                case 'endomondo_updatemydistancebyworkouts':
                    $this->UpdateMyDistances();
                break;
                case 'crontask':
                    $doFetch = true;
                    $cronLastFetch = intval(get_option('s4u_endomondo_cron_fetchchworkouts'));
                    if ($cronLastFetch != 0) {
                        $margin = mktime() - $cronLastFetch;
                        if ($margin < 3600) {
                            $doFetch = false;
                        }
                    }
                    if ($doFetch) {
                        update_option('s4u_endomondo_cron_fetchchworkouts', mktime());
                        $this->FetchWorkouts(true);
                    }
                    $doFetch = true;
                    $cronLastUpdate = intval(get_option('s4u_endomondo_cron_lastupdate'));
                    if ($cronLastUpdate != 0) {
                        $margin = mktime() - $cronLastUpdate;
                        if ($margin < 3600) {
                            $doFetch = false;
                        }
                    }
                    if ($doFetch) {
                        update_option('s4u_endomondo_cron_lastupdate', mktime());
                        $this->UpdateMyDistances();
                    }
                break;
            }
        }
        catch(exception $e) {
            $_ErrorMessage = "Fout opgetreden: " . $e;
        }

    }

    private function Login() {
        include_once("userdata.php");
        $endomondoUserCredentialsObject = null;
        if (class_exists('EndomondoUserCredentials')) {
            $endomondoUserCredentialsObject = new EndomondoUserCredentials();
            
        }
        else {
            return false;
        }

        $email = $endomondoUserCredentialsObject->GetUsername();
        $result = false;
        $csfrToken = "-";
        $http = new WP_Http;
        $baseUrl = 'https://www.endomondo.com/';

        $csfrToken = $this->GenerateCSRFToken(null);
        $args = array('method' => 'POST', 'headers' => [
            'Content-Type' => 'application/json',
            'Cookie' => 'CSRF_TOKEN=' . $csfrToken,
            'X-CSRF-TOKEN' => $csfrToken,
        ], 'body' => '{"email":"'.$email.'","password":"'.$endomondoUserCredentialsObject->GetPassword().'","remember":true}');
        $response = $http->request($baseUrl.'rest/session', $args);

        if (isset($response["cookies"])){
            update_option('s4u_endomondo_challenge_cookies', $response["cookies"]);            
        }

        try {
            $responseObject = json_decode($response["body"]);
            if ($responseObject->email == $email) {
                $result = true;
                update_option('s4u_endomondo_challenge_profiledata', $response["body"]);
            }
        }
        catch(exception $e) {
            $_ErrorMessage = $response["body"];
        }
        return $result;
    }

    private function CleanData() {
        update_option('s4u_endomondo_challenge_cookies', '');
        update_option('s4u_endomondo_challenge_profiledata', '');
        $userDataLocation = str_replace("s4u-endomondo-challenges-admin.php", "userdata.php", __FILE__);        
        unlink($userDataLocation);
    }

    private function FetchWorkouts($firstCall) {
        $http = new WP_Http;
        $baseUrl = 'https://www.endomondo.com/';
        $profileData = get_option('s4u_endomondo_challenge_profiledata');
        $profileObject = json_decode($profileData);

        $csfrToken = $this->GenerateCSRFToken($profileObject->id);
        $cookieString = '';
        $userToken = '';
        foreach(get_option('s4u_endomondo_challenge_cookies') as $cookie) {
            if (strtoupper($cookie->name) == "USER_TOKEN") {
                $userToken = str_replace("%22", "\"", urlencode($cookie->value));
                $cookieString .= $cookie->name.'='.$userToken.';Path=/;Domain=www.endomondo.com;Secure;HttpOnly';
            }
        }

        $args = array('method' => 'GET', 'headers' => [
            'Content-Type' => 'application/json',
            'Cookie' => 'CSRF_TOKEN=' . $csfrToken . '; ' . $cookieString,
            'X-CSRF-TOKEN' => $csfrToken,            
        ]);        
        $response = $http->request($baseUrl.'rest/v1/users/'.$profileObject->id.'/workouts/history?expand=workout:abs,trainingplan:abs,laps:ref,user:abs&limit=100', $args);

        if ($firstCall && intval($response['response']['code']) == 401) {
            if ($this->Login()) {
                return $this->FetchWorkouts(false);
            }
        }

        $workoutValues = get_option('s4u_endomondo_challenge_workouts');
        $pushed = false;
        $modifiedWorkoutObject = [];
        $workoutObject = ($workoutValues !== false ? json_decode(get_option('s4u_endomondo_challenge_workouts')) : []);
        $modifiedWorkoutObject = $this->BuildDistinctWorkoutArray($modifiedWorkoutObject, $workoutObject);
        $workouts = json_decode($response['body']);
        $modifiedWorkoutObject = $this->BuildDistinctWorkoutArray($modifiedWorkoutObject, $workouts->data);
        update_option('s4u_endomondo_challenge_workouts', json_encode($modifiedWorkoutObject));
       
    }

    private function BuildDistinctWorkoutArray($initialArray, $dataArray) {
        if (is_array($initialArray) && is_array($dataArray)) {
            $resultArray  = [];
            for ($a=0; $a < count($dataArray); $a++) {
                $exists = false;            
                for ($b=0; $b < count($initialArray); $b++) {
                    if ($dataArray[$a]->id == $initialArray[$b]->id) {
                        $exists = true;
                        break;
                    }
                }
                if ($exists == false) {
                    array_push($resultArray, $dataArray[$a]);
                }           
            }
            for ($a=0; $a < count($initialArray); $a++) {
                $exists = false;            
                for ($b=0; $b < count($resultArray); $b++) {
                    if ($initialArray[$a]->id == $resultArray[$b]->id) {
                        $exists = true;
                        break;
                    }
                }
                if ($exists == false) {
                    array_push($resultArray, $initialArray[$a]);
                }           
            }        
            return $resultArray;
        }
        return $initialArray;
    }


    private function FetchChallengeData($firstCall, $ChallengeId) {
        if (intval($ChallengeId) <= 0) return false;

        $http = new WP_Http;
        $baseUrl = 'https://www.endomondo.com/';
        $profileData = get_option('s4u_endomondo_challenge_profiledata');
        $profileObject = json_decode($profileData);

        $csfrToken = $this->GenerateCSRFToken($profileObject->id);
        $cookieString = '';
        $userToken = '';
        foreach(get_option('s4u_endomondo_challenge_cookies') as $cookie) {
            if (strtoupper($cookie->name) == "USER_TOKEN") {
                $userToken = str_replace("%22", "\"", urlencode($cookie->value));
                $cookieString .= $cookie->name.'='.$userToken.';Path=/;Domain=www.endomondo.com;Secure;HttpOnly';
            }
        }

        $args = array('method' => 'GET', 'headers' => [
            'Content-Type' => 'application/json',
            'Cookie' => 'CSRF_TOKEN=' . $csfrToken . '; ' . $cookieString,
            'X-CSRF-TOKEN' => $csfrToken,            
        ]); 
        $failed = false;  
        try {     
            $response = $http->request($baseUrl . 'rest/v1/challenges/'.$ChallengeId, $args);
            
            $challengeObject = json_decode($response['body']);

            if (is_array($challengeObject->errors)) {
                if ($firstCall)  {
                    if ($this->Login()) {
                        return $this->FetchChallengeData(false, $ChallengeId);
                    }
                }
            }

            if ($challengeObject->expand == "abs") {
                return $challengeObject;
            }
        }
        catch(Exception $e) {
        }
        return false;
    }

    private function GenerateCSRFToken($user)
    {
        $http = new WP_Http;
        $csfrToken = '';
        $baseUrl = 'https://www.endomondo.com/';
        $detailUrl = 'login';
        if ($user != null) {
            $detailUrl = 'users/'.$user;
        }

        // first do a GET to get the CSRF_TOKEN
        $response = $http->request($baseUrl.$detailUrl, array('method' => 'GET'));
        if (isset($response["cookies"])){
            foreach ($response["cookies"] as $cookie) {
                if (strtoupper($cookie->name) == "CSRF_TOKEN") {
                    $csfrToken = $cookie->value;
                }
            }
        } 
        return $csfrToken;       
    }
    
    private function SaveChallengeStartDate($challengeId) {
        $thisChallenge = array('id' => $challengeId, 'date' => $_POST['year'].'-'.$_POST['month'].'-'.$_POST['day'].' '.$_POST['hour'].':'.$_POST['minute']);
        $modifiedChallengesObject = array();

        $startdateValues = get_option('s4u_endomondo_challenge_startdates');
        $pushed = false;
        if ($startdateValues !== false) {
            $challengesObject = json_decode(get_option('s4u_endomondo_challenge_startdates'));
            for ($k=0; $k < count($challengesObject); $k++) {
                if ($challengesObject[$k]->id == $thisChallenge->id) {
                    $pushed = true;
                    array_push($modifiedChallengesObject, $thisChallenge);
                }
                else{
                    array_push($modifiedChallengesObject, $challengesObject[$k]);
                }
            }
        }
        if ($pushed == false) {
            array_push($modifiedChallengesObject, $thisChallenge);
        }
        update_option('s4u_endomondo_challenge_startdates', json_encode($modifiedChallengesObject));

    }

    private function UpdateMyDistances() {
		$challengesJson = get_option('s4u_endomondo_challenges');
		if ($challengesJson !== '') {	
			$challengesObject = json_decode($challengesJson);
			for ($k=0; $k < count($challengesObject); $k++) {
				$userJoined = false;
				if (isset($challengesObject[$k]->viewer_joined)) {
					switch(intval($challengesObject[$k]->viewer_joined)) {
						case 1: $userJoined = true;
							break;
					}
				}
				if ($userJoined) {
                    $afstand = $this->GetTotalDistance($challengesObject[$k]->sports, $challengesObject[$k]->id);
                    $challengesObject[$k]->mydistance = $afstand;
				}
            }
            update_option('s4u_endomondo_challenges', json_encode($challengesObject));            
		}        
    }

	private function GetTotalDistance($sport, $challengeid) {
		$result = 0;

		$startdateValues = get_option('s4u_endomondo_challenge_startdates');
		$challengesStartDateObject = ($startdateValues !== false) ? json_decode(get_option('s4u_endomondo_challenge_startdates')) : [];
	
		$startdate = "";
		for ($k=0; $k < count($challengesStartDateObject); $k++) {
			if ($challengesStartDateObject[$k]->id == $challengeid) {
				$startdate = $challengesStartDateObject[$k]->date;
				break;
			}
		}

		if ($startdate != "") {
			$personalStart = strtotime($startdate);
			$workouts = get_option('s4u_endomondo_challenge_workouts');
			if ($workouts != false) {
				$workouts = json_decode($workouts);
				for ($k=0; $k < count($workouts); $k++) {
					$workoutStart = strtotime($workouts[$k]->local_start_time);
					if ($workoutStart >= $personalStart) {
						$result += $workouts[$k]->distance;
					}
				}
			}			
		}
		return round($result,2);
	}    
}
?>