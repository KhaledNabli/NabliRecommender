<?php


//$mysql = new mysqli("localhost", "admin", "SASpw1", "recommender");
$mysql = new mysqli("rdbms.strato.de", "U1408197", "SASpw1", "DB1408197");

$offerId = $_GET["oid"];

$picQuery = $mysql->query("SELECT Poster FROM `offer_detail` where OfferID = $offerId" );
$resultRow = $picQuery->fetch_assoc();
$pic = $resultRow["Poster"];


	if (is_readable($pic)) {
		// get the filename extension
		$ext = substr($pic, -3);
		// set the MIME type
		switch ($ext) {
			case 'jpg':
				$mime = 'image/jpeg';
				break;
			case 'gif':
				$mime = 'image/gif';
				break;
			case 'png':
				$mime = 'image/png';
				break;
			default:
				$mime = false;
		}
		// if a valid MIME type exists, display the image
		// by sending appropriate headers and streaming the file
		
		if ($mime) {
			header('Content-type: '.$mime);
			header('Content-length: '.filesize($pic));
			$file =  fopen($pic, 'rb');
			if ($file) {
				fpassthru($file);
				exit;
			}
		}
	} else {
		header("HTTP/1.0 303 See Other");
		header("Location: $pic");
	}
?>