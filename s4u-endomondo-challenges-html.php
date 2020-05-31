<?php
class S4U_Endomondo_Challenges_Html {
    public function ShowAdminHtmlForm() {
?>
<h1>Endomondo uitdaging(en)</h1>
<h2>Log hieronder in om je uitdaging(en) op te halen.</h2>
<form method="post">
<input type="hidden" name="admin_action" value="endomondo_login" />
<table class="form-table" role="presentation">
	<tbody><tr>
		<th><label for="category_base">E-mailadres</label></th>
		<td> <input name="endomondo_un" id="endomondo_un" type="text" value="" class="regular-text code"></td>
	</tr>
	<tr>
		<th><label for="tag_base">Wachtwoord</label></th>
		<td> <input name="endomondo_pw" id="endomondo_pw" type="password" value="" class="regular-text code"></td>
	</tr>
    <tr>
        <th>&nbsp;</th>
        <td><input type="submit" value="Inloggen / Profiel koppelen" />
    </tr>
	</tbody>
</table>
</form>
<form method="post">
	<input type="hidden" name="admin_action" value="endomondo_clean" />
	<table class="form-table" role="presentation">
	<tbody><tr>
        <th>&nbsp;</th>
        <td><input type="submit" value="Opgeslagen profiel wissen" />
    </tr>
	</tbody>
	</table>	
</form>
<h2>Jouw profiel</h2>
<table class="form-table" role="presentation">
	<tbody>
<?php 
		$profileData = get_option('s4u_endomondo_challenge_profiledata');
		$hasAProfile = ($profileData != "");
		if ($hasAProfile):
			$profileObject = $this->ProfileDataToObject($profileData);
			$profilePicture = $profileObject->avatar_url;
			$name = $profileObject->first_name . " " . $profileObject->last_name;?>
		<tr><th>
<?php
			if ($profilePicture != "") :
?>
	<img src="<?php echo $profilePicture;?>" />
<?php        
			endif;?>
			</th>		
			<td><?php echo $name;?></td>
		</tr>
<?php
		else:
?>
	<tr><th>Er is (nog) geen profieldata bekend.</th></tr>
<?php		
		endif;
?>
	</tbody>
</table>
<?php
		if ($hasAProfile) :
			$challengesJson = get_option('s4u_endomondo_challenges');
			if ($challengesJson == false) {
				$challengesJson = '[]';				
			}
			if ($challengesJson == '[]') {
				$challengesObject = [];
				// 11-steden-walks 2020
				$defaultItems = [43129667,43160669,43164778,43164846,43166019,43166057,43165966,43166079,43166158,43166107,43165993,43164820,43164850,43164848,43165943,43166123];

				foreach ($defaultItems as $i) {
					array_push($challengesObject, array('id' => $i));
				}
				update_option('s4u_endomondo_challenges', json_encode($challengesObject));
				$challengesJson = get_option('s4u_endomondo_challenges');
			}
			$challengesObject = json_decode($challengesJson);
		?>
<h2>Jouw uitdagingen</h2>
<form method="post">
	<input type="hidden" name="admin_action" value="endomondo_fetchchallenges" />
</form>	
	<table class="form-table" role="presentation">
	<thead>
		<tr><th>ID evenement</th><th>Naam</th><th style=\"width:20px\">Aantal deelnemers</th><th>Doe je mee?</th><th>Jouw afstand</th><th>Startdatum</th><th>&nbsp;</th></tr>
	</thead>
	<tbody>
	<?php 
	$startdateValues = get_option('s4u_endomondo_challenge_startdates');
	$challengesStartDateObject = null;
	if ($startdateValues !== false) {
		$challengesStartDateObject = json_decode(get_option('s4u_endomondo_challenge_startdates'));
	}
	for ($k=0; $k < count($challengesObject); $k++) {
?><tr>
<th>
<?php
	$item = $challengesObject[$k]->id;
	if (isset($challengesObject[$k]->picture)) {
		$picture = $challengesObject[$k]->picture;
		if (isset($picture->url)) {
			$item = "<img src=\"".$picture->url."\" alt=\"".$challengesObject[$k]->id."\" />";
		}
	}
?>	
	<span><?php echo $item;?></span>
</th>
<td><?php echo $challengesObject[$k]->name;?></td>
<td>
<?php 
$status = "Onbekend";
if (isset($challengesObject[$k]->member_count)) {
	$status = $challengesObject[$k]->member_count;
}
echo $status;
?>
<td>
<?php 
$status = "Onbekend";
if (isset($challengesObject[$k]->viewer_joined)) {
	switch(intval($challengesObject[$k]->viewer_joined)) {
		case 0: $status = "Doet niet mee";
			break;
		case 1: $status = "Doet mee";
			break;
	}
}
echo $status;
?>
</td>
<?php
$mydistance = 0;
if (isset($challengesObject[$k]->mydistance)) {
	$mydistance = $challengesObject[$k]->mydistance;
}
?>
<td><?php echo $mydistance;?></td>
<td>
<?php
if ($status == "Doet mee") {
	$day = "01";
	$month = "01";
	$year = date('Y');
	$hour = "00";
	$minute = "00";

	if ($challengesStartDateObject != null) {
		for ($z=0; $z < count($challengesStartDateObject); $z++) {
			if ($challengesStartDateObject[$z]->id == $challengesObject[$k]->id) {
				$parts = explode(' ', $challengesStartDateObject[$z]->date);
				$dmy = explode('-', $parts[0]);
				$day = $dmy[2];
				$month = $dmy[1];
				$year = $dmy[0];
				$hm = explode(':', $parts[1]);
				$hour = $hm[0];
				$minute = $hm[1];
			}
		}	
	}
?>
<form method="post">
<input type="hidden" name="admin_action" value="endomondo_savechallengestartdate" />
<input type="hidden" name="challenge_id" value="<?php echo $challengesObject[$k]->id;?>" />
<select name="day">
<?php for ($a=1; $a < 32; $a++) {
	$selected = ($day == $a ? "selected=\"selected\"" : "");
?><option <?php echo $selected;?> value="<?php echo $a;?>"><?php echo $a;?></option><?php
}?>
</select>&nbsp;
<?php echo $this->GetMonthDropDown($month);?>
&nbsp;
<select name="year">
<?php 
$tillValue = date('Y') - 10;
for ($a=date('Y'); $a > $tillValue; $a--) {
	$selected = ($year == $a ? "selected=\"selected\"" : "");
?><option <?php echo $selected;?> value="<?php echo $a;?>"><?php echo $a;?></option><?php
}?>
</select><br/><br/>
<select name="hour">
<?php for ($a=0; $a < 24; $a++) {
	$z = ($a < 10 ? "0".$a : $a);
	$selected = ($hour == $z ? "selected=\"selected\"" : "");
?><option <?php echo $selected;?> value="<?php echo $z;?>"><?php echo $z;?></option><?php
}?>
</select>&nbsp;:&nbsp;
<select name="minute">
<?php for ($a=0; $a < 60; $a++) {
	$z = ($a < 10 ? "0".$a : $a);
	$selected = ($minute == $z ? "selected=\"selected\"" : "");
?><option <?php echo $selected;?> value="<?php echo $z;?>"><?php echo $z;?></option><?php
}?>
</select>&nbsp;<input type="submit" value="Datum opslaan" />
</form>
<?php	
}
?>
</td>
<td>
	<form method="post"><input type="hidden" name="admin_action" value="endomondo_fetchchallengedata" /><input type="hidden" name="challenge_id" value="<?php echo $challengesObject[$k]->id;?>" /><input type="submit" value="Bijwerken" /></form>
	<br/>
	<form method="post"><input type="hidden" name="admin_action" value="endomondo_removesinglechallenge" /><input type="hidden" name="challenge_id" value="<?php echo $challengesObject[$k]->id;?>" /><input type="submit" value="Verwijderen" /></form>
</td>
	</tr>
<?php		
	}
?>	
	</tbody>
	</table>	
<h2>Jouw work-outs</h2>
<table class="form-table" role="presentation">
<tr>
<td>
<form method="post">
	<input type="hidden" name="admin_action" value="endomondo_fetchworkouts" />
	<input type="submit" value="Work-outs opvragen" />
</form>
</td>
<td>
<form method="post">
	<input type="hidden" name="admin_action" value="endomondo_updatemydistancebyworkouts" />
	<input type="submit" value="Werk mijn gelopen afstanden bij" />
</form>
</td>
</tr>
</table>
<table class="form-table" role="presentation">
	<thead>
		<tr><th>Start</th><th>Duur (uur:minuten)</th><th>Afstand (km)</th><th>Type</th><th>&nbsp;</th></tr>
	</thead>
	<tbody>
<?php $workouts = get_option('s4u_endomondo_challenge_workouts');
if ($workouts != false) {
	$workouts = json_decode($workouts);
	for ($k=0; $k < count($workouts); $k++) {
		$duration = $workouts[$k]->duration;
		$duration_hour = floor($workouts[$k]->duration / 3600);
		$duration -= ($duration_hour * 3600);
		$duration_minutes = floor($duration / 60);
?><tr>
	<th><?php echo $workouts[$k]->local_start_time;?></th>
	<td><?php echo $duration_hour < 10 ? "0".$duration_hour:$duration_hour;?>:<?php echo $duration_minutes < 10 ? "0".$duration_minutes : $duration_minutes;?></td>
	<td><?php echo $workouts[$k]->distance;?></td>
	<td><?php echo $this->GetSportDescription($workouts[$k]->sport);?></td>
	<td></td>
</tr>
<?php
	}
}
?>
	</tbody>
	</table>
		<?php
		endif; // has a profile
	}
	
	public function ShowAdminError($errorMessage) {
?><p><b><?php echo $errorMessage;?></b></p>
<?php		
	}

	private function ProfileDataToObject($json) {
		return json_decode($json);
	}

	private function GetMonthDropDown($month) {
		$result = "<select name=\"month\">";
		$months = ["januari","februari","maart","april","mei","juni","juli","augustus","september","oktober","november","december"];
		for ($a=1; $a < 13; $a++) {
			$selected = ($month == $a ? "selected=\"selected\"" : "");
			$result .= "<option ".$selected." value=\"".$a."\">".$months[$a-1]."</option>";
		}
		$result .= "</select>";
		return $result;
	}

	private function GetSportDescription($sportId) {
		switch($sportId) {
			case 21: return "Cycling (Indoor)";
			case 2: return "Cycling (Sport)";
			case 1: return "Cycling (Transport)";
			case 15: return "Golfing";
			case 16: return "Hiking";
			case 9: return "Kayaking";
			case 10: return "Kite surfing";
			case 3: return "Mountain biking";
			case 17: return "Orienteering";
			case 19: return "Riding";
			case 4: return "Roller skating";
			case 5: return "Roller skiing";
			case 11: return "Rowing";
			case 0: return "Running";
			case 12: return "Sailing";
			case 6: return "Skiing (Cross country)";
			case 7: return "Skiing (Downhill)";
			case 8: return "Snowboarding";
			case 20: return "Swimming";
			case 18: return "Walking";
			case 14: return "Walking (Fitness)";
			case 13: return "Windsurfing";
			case 22: return "Other";
			case 23: return "Aerobics";
			case 24: return "Badminton";
			case 25: return "Baseball";
			case 26: return "Basketball";
			case 27: return "Boxing";
			case 104: return "Canicross";
			case 87: return "Circuit Training";
			case 93: return "Climbing";
			case 28: return "Climbing stairs";
			case 29: return "Cricket";
			case 31: return "Dancing";
			case 30: return "Elliptical training";
			case 32: return "Fencing";
			case 99: return "Floorball";
			case 33: return "Football (American)";
			case 34: return "Football (Rugby)";
			case 35: return "Football (Soccer)";
			case 49: return "Gymnastics";
			case 36: return "Handball";
			case 37: return "Hockey";
			case 100: return "Ice skating";
			case 95: return "Kick scooter";
			case 48: return "Martial arts";
			case 105: return "Paddle tennis";
			case 106: return "Paragliding";
			case 38: return "Pilates";
			case 39: return "Polo";
			case 102: return "Rope jumping";
			case 98: return "Rowing (indoors)";
			case 88: return "Running (Treadmill)";
			case 40: return "Scuba diving";
			case 89: return "Skateboarding";
			case 101: return "Ski Touring";
			case 91: return "Snowshoeing";
			case 41: return "Squash";
			case 96: return "Stand Up Paddling";
			case 50: return "Step counter";
			case 103: return "Stretching";
			case 90: return "Surfing";
			case 42: return "Table tennis";
			case 43: return "Tennis";
			case 97: return "Trail Running";
			case 44: return "Volleyball (Beach)";
			case 45: return "Volleyball (Indoor)";
			case 94: return "Walking (Treadmill)";
			case 46: return "Weight training";
			case 92: return "Wheelchair";
			case 47: return "Yoga";
		}
		return $sportId;
	}

	public function ShowSideBarWidget() {
		$challengesJson = get_option('s4u_endomondo_challenges');
		if ($challengesJson !== '') {	
			$challengesObject = json_decode($challengesJson);
			for ($k=0; $k < count($challengesObject); $k++) {
				$userJoined = false;
				if (isset($challengesObject[$k]->viewer_joined)) {
					$userJoined = (intval($challengesObject[$k]->viewer_joined) == 1);
				}
				if ($userJoined) {
					$item = "<div class=\"widget\"><h5 class=\"widget-title\">".$challengesObject[$k]->name."</h5>";
					if (isset($challengesObject[$k]->picture)) {
						$picture = $challengesObject[$k]->picture;
						if (isset($picture->url)) {
							$item .= "<img src=\"".$picture->url."\" /><br/>";
						}
					}
					if (isset($challengesObject[$k]->member_count)) {
						$item .= "<p>Aantal deelnemers: <b>" . $challengesObject[$k]->member_count."</b></p>";
					}
					$afstand = 0;
					if (isset($challengesObject[$k]->mydistance)) {
						$afstand = $challengesObject[$k]->mydistance;
					}
					$item .= "<p>Mijn gelopen afstand: <b>".$afstand." km</b></p>";
					$item .= "</div>";
					echo $item;
				}
			}
		}		
	}
}
?>