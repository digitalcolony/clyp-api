<?php
// Report all errors
error_reporting(E_ALL);
set_time_limit(0);

$target_dir = "uploads/";
$ini_array = parse_ini_file("../soundboard/soundboard.ini");
$mp3directory = $ini_array['folder'];

$drop_dir = "../soundboard/".$mp3directory.'/';


if(isset($_POST["submit"])) {
	$password = trim($_POST['password']);
	if($password=="shovel"){
		$thisFileUploaded = basename($_FILES["fileToUpload"]["name"]);
		
		// move drops directly to the soundboard
		
		if($audioType = $_POST["AudioType"]=="Drop")
		{
			$target_file = $drop_dir . $thisFileUploaded;
			echo "<p>DROP!</p>";
		}
		else {
			$target_file = $target_dir . $thisFileUploaded;
		}
		//echo "<h3>".$target_file."</h3>";
		//echo "<h3>".$thisFileUploaded."</h3>";
		//echo "<h3>".$_FILES['fileToUpload']['tmp_name']."</h3>";
				
		
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $_FILES['fileToUpload']['tmp_name']);
				
		if ($mime == 'audio/mpeg'){
			if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
				echo "<p>The file <strong>". basename( $_FILES["fileToUpload"]["name"]). "</strong> has been uploaded.</p>";
			
				// post to clyp
				$mp3FilePath = realpath($target_file);
				// echo "<p>Path: ".$mp3FilePath."</p>";
				$url = "https://upload.clyp.it/upload";
				$data = array('Filedata'=> new \CURLFile($mp3FilePath));
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				$response = curl_exec($ch);
				$error = curl_error($ch);
				  //echo "<p>CURL Error: ".$error."</p>";
				curl_close ($ch);
			
				$jsonResponse = json_decode($response);
				//echo "<p>JSON: ".$response."</p>";
				$status = $jsonResponse->Successful;
				if($status ==1)
				{
					insertToDB($jsonResponse);
				} else {
					echo "<p>Clyp Response: ".$response."</p>";
				}
			
			} else {
				echo "<p>Sorry, there was an error uploading your file.</p>";
			}
		} else {
			echo "<p>***Only upload MP3 files less than 100 MB. Other file types are not supported.</p>";
		}

		
	}
	else{
		echo "<p>bad password</p>";		
	}

}

function postToClyp($mp3FilePath){	
	$url = "https://upload.clyp.it/upload";

	$data = array('Filedata'=> new \CURLFile($mp3FilePath));
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	//curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
	$response = curl_exec($ch);
	$error = curl_error($ch);
	curl_close ($ch);
	//echo "<p>CURL error: ".$error."</p>";
	return $response;
}

function insertToDB($audio){
	$servername = "*******";
	$username = "*******";
	$password = "*******";
	$dbname = "*******";

	// Create connection
	$conn = new mysqli($servername, $username, $password, $dbname);
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	} else {
		//  echo "connected!";
	}
	$audioFileId = $audio->AudioFileId;
	$title = $audio->Title;
	$title = $conn->real_escape_string($title);
	$description = $audio -> Description;
	$description = $conn->real_escape_string($description);
	$duration = $audio->Duration;
	$url = $audio->Url;
	$mp3url = $audio -> Mp3Url;
	$dateCreated = $audio->DateCreated;
	$audioType = $_POST["AudioType"];
	$sql = "";
	if($audioType=="Show"){	
		// Clean up title 
		$title = str_replace('Neil Rogers - ','',$title);
		// Find Show Date 
		$showDate = str_replace('Neil Rogers Show (','',$title);
    	$showDate = str_replace(')','',$showDate);
		date_default_timezone_set('America/New_York');
    	$showDate = strtotime($showDate);
		$showDateDate = date('Y-m-d',$showDate);
		
		$sql = "INSERT INTO clyp (AudioFileId, Title, Description, Duration, url, mp3url, datecreated, AudioType, ShowDateDate)
	    	VALUES ('".$audioFileId."','".$title."','".$description."',".$duration.",'".$url."','".$mp3url."','".$dateCreated."','".$audioType."', '".$showDateDate."')";			
	} else {
		$sql = "INSERT INTO clyp (AudioFileId, Title, Description, Duration, url, mp3url, datecreated, AudioType)
	    	VALUES ('".$audioFileId."','".$title."','".$description."',".$duration.",'".$url."','".$mp3url."','".$dateCreated."','".$audioType."')";

	}
		
	//echo "<br>SQL: ".$sql ;
	if ($conn->query($sql) === TRUE) {
		echo "<p>UPLOADED: ".$title."</p>";
		echo "<p>CLYP: <a href='".$url."' target='_blank'>".$url."</a></p>";
		echo "<p>MP3: <a href='".$mp3url."' target='_blank'>".$mp3url."</a></p>";
	} else {
		echo "Error: " . $sql . "<br>" . $conn->error;
	}
	$conn->close();
	//ob_flush();
	flush();

}
?>
<!DOCTYPE html>
<html>
<head><title>Upload Audio v4</title></head>
<body>
<p><em>Use this FORM to upload audio to Clyp. Make sure the ID3 is complete before using. </em></p>
<form action="upload-clyp.php" method="post" enctype="multipart/form-data">
    Select audio to upload:
    <input type="file" name="fileToUpload" id="fileToUpload">
    <select name="AudioType" id="AudioType">
    	<option value="Drop">Drop (Soundboard)</option>
    	<option value="Short">Short</option>
    	<option value="Show">Show</option>
    </select>
    <input type="password" name="password" id="password" maxlength="15">
    <input type="submit" value="Upload Audio" name="submit">
</form>

</body>
</html>