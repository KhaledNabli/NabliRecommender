<?php

// Block all GET Requests
//if($_SERVER['REQUEST_METHOD'] == "GET")
//	die("Hier gib es nichts zu sehen");


$mysql = new mysqli("localhost", "admin", "SASpw1", "recommender");



// read request parameters
$settings = array();
$settings["coldstart"] 	= empty($_POST['userid']) ? true : false;
$settings["userid"] 	= empty($_POST['userid']) ? mt_rand(1000000, 9999999) : intval($_POST['userid']);
$settings["format"] 	= empty($_POST['format']) ? "JSON" : $_POST['format'];
$settings["max_offers"] = empty($_POST['max_offers']) ? 39 : intval($_POST['max_offers']);
$settings["max_clusters"] = empty($_POST['max_clusters']) ? 5 : intval($_POST['max_clusters']);
$settings["action"] 	= empty($_POST['action']) ? "recommend" : $_POST['action'];
$settings["offerid"] 	= empty($_POST['offerid']) ? null : $_POST['offerid'];
$settings["rate"] 		= empty($_POST['rate']) ? null : $_POST['rate'];
$settings["w_rate"]		= empty($_POST['w_rate']) ? 1 : $_POST['w_rate'];
$settings["w_review"]	= empty($_POST['w_review']) ? 0.01 : $_POST['w_review'];
$settings["w_contact"]	= empty($_POST['w_contact']) ? -0.1 : $_POST['w_contact'];
$settings["w_response"]	= empty($_POST['w_response']) ? -100 : $_POST['w_response'];
$settings["w_age"]		= empty($_POST['w_age']) ? -0.5 : $_POST['w_age'];
$settings["w_occurance"]= empty($_POST['w_occurance']) ? 0.1 : $_POST['w_occurance'];
$settings["w_distance"]	= empty($_POST['w_distance']) ? 0.1 : $_POST['w_distance'];











if($settings["coldstart"]) {
    $mysql->query("CALL `addNewUser`(" . $settings["userid"]  . ")");
	$mysql->query("CALL `update_knn_clusters` (" . $settings["userid"]  . ", " . $settings["max_clusters"] . ")");
}

if($settings["action"] == "reset") {
	$mysql->query("delete from `user_contact_history` WHERE UserID = '". $settings["userid"] ."'");
	$mysql->query("delete from `user_response_history` WHERE UserID = '". $settings["userid"] ."'");
	$mysql->query("delete from `user_profile` WHERE UserID = '". $settings["userid"] ."'");
	$mysql->query("CALL `addNewUser`(" . $settings["userid"]  . ")");
	$mysql->query("CALL `update_knn_clusters` (" . $settings["userid"]  . ", " . $settings["max_clusters"] . ")");
}

switch ($settings["action"]) {
    case "rate":
        $mysql->query("INSERT INTO `user_response_history` (`UserID`, `OfferID`, `Rate`, `DateTime`) VALUES ('" . $settings["userid"]  . "', '" . $settings["offerid"] . "', '" . $settings["rate"] . "', NOW())");
        $mysql->query("CALL `update_user_profile` (" . $settings["userid"]  . ")");
        $mysql->query("CALL `update_knn_clusters` (" . $settings["userid"]  . ", " . $settings["max_clusters"] . ")");

    case "recommend":
    default:
        // read history
        $userHistory = getUserHistory($settings["userid"], $mysql);
		
		$mysql->query("SET SQL_BIG_SELECTS=1");
		$mysql->query("CALL `prepare_recommendations`(".$settings["userid"].", ".$settings["max_offers"].");");
		$recoQuery = "SELECT a.*, ((Rate*".$settings["w_rate"]." + Reviews*".$settings["w_review"]." + (Occurance*".$settings["w_occurance"].") + (Age*".$settings["w_age"].") + (Showed*".$settings["w_contact"].") + (Responded*".$settings["w_response"]."))/(Distance*".$settings["w_distance"]."))". 
		" as Points FROM `tmp_user_recommendation` a WHERE a.UserID = ". $settings["userid"] .
		" ORDER BY Points DESC LIMIT ". $settings["max_offers"];
        $movieResult = $mysql->query($recoQuery);

        $movieCount = 0;
        $recommendedList = array();
		
        while ($row = $movieResult->fetch_assoc()) {
            $recommendedList[$movieCount]['id'] = intval($row["OfferID"]);
            $recommendedList[$movieCount]['cluster'] = $row["Cluster"];
			$recommendedList[$movieCount]['cluster_distance'] = $row["Distance"];
			$recommendedList[$movieCount]['cluster_rate'] = $row["Rate"];
			$recommendedList[$movieCount]['cluster_reviews'] = $row["Reviews"];
			$recommendedList[$movieCount]['rate_age'] = $row["Age"];
			$recommendedList[$movieCount]['occurance'] = $row["Occurance"];
			$recommendedList[$movieCount]['showed'] = $row["Showed"];
			$recommendedList[$movieCount]['responded'] = $row["Responded"];
            $recommendedList[$movieCount]['title'] = utf8_encode($row["Title"]);
			//$recommendedList[$movieCount]['rating'] = ($row["Rating"]);
			//$recommendedList[$movieCount]['genre'] = utf8_encode($row["Genre"]);
			//$recommendedList[$movieCount]['releaseDate'] = ($row["Released"]);
			//$recommendedList[$movieCount]['director'] = utf8_encode($row["Director"]);
			//$recommendedList[$movieCount]['writer'] = explode(", ", utf8_encode($row["Writer"]));
			//$recommendedList[$movieCount]['cast'] = explode(", ", utf8_encode($row["Cast"]));
			//$recommendedList[$movieCount]['imdbRate'] = floatval($row["Imdb_rating"]);
			//$recommendedList[$movieCount]['imdbVotes'] = floatval($row["Imdb_votes"]);
			$recommendedList[$movieCount]['categories'] = explode("|", utf8_encode($row["OfferDetailCategories"]));
            $recommendedList[$movieCount]['year'] = intval($row["OfferDetailYear"]);
            //$recommendedList[$movieCount]['reviews_in_cluster'] = intval($row["REVIEWS"]);
            //$recommendedList[$movieCount]['rate_in_cluster'] = floatval($row["AVG_Rate"]);
            //$recommendedList[$movieCount]['cluster_distance'] = floatval($row["distance"]);
			//$recommendedList[$movieCount]['movie_distance'] = floatval($row["AVG_DISTANCE"]);
			//$recommendedList[$movieCount]['prob'] = floatval($row["Prob"]);
			$recommendedList[$movieCount]['points'] = floatval($row["Points"]);
			$recommendedList[$movieCount]['picture'] = $row["Poster"];
            //$recommendedList[$movieCount]['plot'] = utf8_encode($row["Plot"]);
			//$recommendedList[$movieCount]['fullPlot'] = utf8_encode($row["FullPlot"]);
			//$recommendedList[$movieCount]['similarOffers'] = getSimilarOffers(intval($row["OfferID"]), $mysql, 20);
            $movieCount++;
        }
		
		// read own cluster statistics
        $ownClusterResult = $mysql->query("SELECT `UserID`, `QUOTE_of_IMAX_Movie`, `QUOTE_of_Action_Movie`, `QUOTE_of_Adventure_Movie`, `QUOTE_of_Animation_Movie`, `QUOTE_of_Children_Movie`, `QUOTE_of_Comedy_Movie`, `QUOTE_of_Crime_Movie`, `QUOTE_of_Documentary_Movie`, `QUOTE_of_Drama_Movie`, `QUOTE_of_Fantasy_Movie`, `QUOTE_of_FilmNoir_Movie`, `QUOTE_of_Horror_Movie`, `QUOTE_of_Musical_Movie`, `QUOTE_of_Mystery_Movie`, `QUOTE_of_Romance_Movie`, `QUOTE_of_SciFi_Movie`, `QUOTE_of_Thriller_Movie`, `QUOTE_of_War_Movie`, `QUOTE_of_Western_Movie`, `AVG_of_IMAX_Rate`, `AVG_of_Action_Rate`, `AVG_of_Adventure_Rate`, `AVG_of_Animation_Rate`, `AVG_of_Children_Rate`, `AVG_of_Comedy_Rate`, `AVG_of_Crime_Rate`, `AVG_of_Documentary_Rate`, `AVG_of_Drama_Rate`, `AVG_of_Fantasy_Rate`, `AVG_of_FilmNoir_Rate`, `AVG_of_Horror_Rate`, `AVG_of_Musical_Rate`, `AVG_of_Mystery_Rate`, `AVG_of_Romance_Rate`, `AVG_of_SciFi_Rate`, `AVG_of_Thriller_Rate`, `AVG_of_War_Rate`, `AVG_of_Western_Rate` FROM `user_profile` WHERE `UserID` = " . $settings["userid"] );
        
		$row = $ownClusterResult->fetch_assoc();
        $clusterLabels = "You";
        $clusterQuoteData = floor(100 * $row['QUOTE_of_Action_Movie']) . "," . floor(100 * $row['QUOTE_of_Adventure_Movie']) . "," . floor(100 * $row['QUOTE_of_Animation_Movie']) . "," . floor(100 * $row['QUOTE_of_Children_Movie']) . "," . floor(100 * $row['QUOTE_of_Comedy_Movie']) . "," . floor(100 * $row['QUOTE_of_Crime_Movie']) . "," . floor(100 * $row['QUOTE_of_Documentary_Movie']) . "," . floor(100 * $row['QUOTE_of_Drama_Movie']) . "," . floor(100 * $row['QUOTE_of_Fantasy_Movie']) . "," . floor(100 * $row['QUOTE_of_FilmNoir_Movie']) . "," . floor(100 * $row['QUOTE_of_Horror_Movie']) . "," . floor(100 * $row['QUOTE_of_Musical_Movie']) . "," . floor(100 * $row['QUOTE_of_Mystery_Movie']) . "," . floor(100 * $row['QUOTE_of_Romance_Movie']) . "," . floor(100 * $row['QUOTE_of_SciFi_Movie']) . "," . floor(100 * $row['QUOTE_of_Thriller_Movie']) . "," . floor(100 * $row['QUOTE_of_War_Movie']) . "," . floor(100 * $row['QUOTE_of_Western_Movie']);
        $clusterRateData = floor(20 * $row['AVG_of_Action_Rate']) . "," . floor(20 * $row['AVG_of_Adventure_Rate']) . "," . floor(20 * $row['AVG_of_Animation_Rate']) . "," . floor(20 * $row['AVG_of_Children_Rate']) . "," . floor(20 * $row['AVG_of_Comedy_Rate']) . "," . floor(20 * $row['AVG_of_Crime_Rate']) . "," . floor(20 * $row['AVG_of_Documentary_Rate']) . "," . floor(20 * $row['AVG_of_Drama_Rate']) . "," . floor(20 * $row['AVG_of_Fantasy_Rate']) . "," . floor(20 * $row['AVG_of_FilmNoir_Rate']) . "," . floor(20 * $row['AVG_of_Horror_Rate']) . "," . floor(20 * $row['AVG_of_Musical_Rate']) . "," . floor(20 * $row['AVG_of_Mystery_Rate']) . "," . floor(20 * $row['AVG_of_Romance_Rate']) . "," . floor(20 * $row['AVG_of_SciFi_Rate']) . "," . floor(20 * $row['AVG_of_Thriller_Rate']) . "," . floor(20 * $row['AVG_of_War_Rate']) . "," . floor(20 * $row['AVG_of_Western_Rate']);
			
        // read next cluster statistics
        $clusterResult = $mysql->query("SELECT a.Cluster, round(Distance,2) as Distance , `AVG_of_Rate`, `COUNT_of_Users`, `COUNT_of_Movies`, `QUOTE_of_IMAX_Movie`, `QUOTE_of_Action_Movie`, `QUOTE_of_Adventure_Movie`, `QUOTE_of_Animation_Movie`, `QUOTE_of_Children_Movie`, `QUOTE_of_Comedy_Movie`, `QUOTE_of_Crime_Movie`, `QUOTE_of_Documentary_Movie`, `QUOTE_of_Drama_Movie`, `QUOTE_of_Fantasy_Movie`, `QUOTE_of_FilmNoir_Movie`, `QUOTE_of_Horror_Movie`, `QUOTE_of_Musical_Movie`, `QUOTE_of_Mystery_Movie`, `QUOTE_of_Romance_Movie`, `QUOTE_of_SciFi_Movie`, `QUOTE_of_Thriller_Movie`, `QUOTE_of_War_Movie`, `QUOTE_of_Western_Movie`, `AVG_of_IMAX_Rate`, `AVG_of_Action_Rate`, `AVG_of_Adventure_Rate`, `AVG_of_Animation_Rate`, `AVG_of_Children_Rate`, `AVG_of_Comedy_Rate`, `AVG_of_Crime_Rate`, `AVG_of_Documentary_Rate`, `AVG_of_Drama_Rate`, `AVG_of_Fantasy_Rate`, `AVG_of_FilmNoir_Rate`, `AVG_of_Horror_Rate`, `AVG_of_Musical_Rate`, `AVG_of_Mystery_Rate`, `AVG_of_Romance_Rate`, `AVG_of_SciFi_Rate`, `AVG_of_Thriller_Rate`, `AVG_of_War_Rate`, `AVG_of_Western_Rate`  FROM `user_knn_clusters` a, cluster_detail b where a.Cluster = b.Cluster AND UserID = " . $settings["userid"]  . " ORDER by Distance LIMIT " . $settings["max_clusters"]);
        $nclusterCount = 0;

        $nextCluster = array();

        while ($row = $clusterResult->fetch_assoc()) {
            $nextCluster[$nclusterCount]['SegmentID'] = intval($row["Cluster"]);
            $nextCluster[$nclusterCount]['Distance'] = floatval($row["Distance"]);
            $nextCluster[$nclusterCount]['UserCount'] = intval($row["COUNT_of_Users"]);
            $nextCluster[$nclusterCount]['MovieCount'] = intval($row["COUNT_of_Movies"]);
            $nextCluster[$nclusterCount]['AvgRate'] = floatval($row["AVG_of_Rate"]);
            $nextCluster[$nclusterCount]['Action_Quote'] = floor(100 * $row['QUOTE_of_Action_Movie']);
            $nextCluster[$nclusterCount]['Adventure_Quote'] = floor(100 * $row['QUOTE_of_Adventure_Movie']);
            $nextCluster[$nclusterCount]['Animation_Quote'] = floor(100 * $row['QUOTE_of_Animation_Movie']);
            $nextCluster[$nclusterCount]['Children_Quote'] = floor(100 * $row['QUOTE_of_Children_Movie']);
            $nextCluster[$nclusterCount]['Comedy_Quote'] = floor(100 * $row['QUOTE_of_Comedy_Movie']);
            $nextCluster[$nclusterCount]['Crime_Quote'] = floor(100 * $row['QUOTE_of_Crime_Movie']);
            $nextCluster[$nclusterCount]['Documentary_Quote'] = floor(100 * $row['QUOTE_of_Documentary_Movie']);
            $nextCluster[$nclusterCount]['Drama_Quote'] = floor(100 * $row['QUOTE_of_Drama_Movie']);
            $nextCluster[$nclusterCount]['Fantasy_Quote'] = floor(100 * $row['QUOTE_of_Fantasy_Movie']);
            $nextCluster[$nclusterCount]['FilmNoir_Quote'] = floor(100 * $row['QUOTE_of_FilmNoir_Movie']);
            $nextCluster[$nclusterCount]['Horror_Quote'] = floor(100 * $row['QUOTE_of_Horror_Movie']);
            $nextCluster[$nclusterCount]['Musical_Quote'] = floor(100 * $row['QUOTE_of_Musical_Movie']);
            $nextCluster[$nclusterCount]['Mystery_Quote'] = floor(100 * $row['QUOTE_of_Mystery_Movie']);
            $nextCluster[$nclusterCount]['Romance_Quote'] = floor(100 * $row['QUOTE_of_Romance_Movie']);
            $nextCluster[$nclusterCount]['SciFi_Quote'] = floor(100 * $row['QUOTE_of_SciFi_Movie']);
            $nextCluster[$nclusterCount]['Thriller_Quote'] = floor(100 * $row['QUOTE_of_Thriller_Movie']);
            $nextCluster[$nclusterCount]['War_Quote'] = floor(100 * $row['QUOTE_of_War_Movie']);
            $nextCluster[$nclusterCount]['Western_Quote'] = floor(100 * $row['QUOTE_of_Western_Movie']);
            //$nextCluster[$nclusterCount]['IMAX_Rate'] = floor(20 * $row['AVG_of_IMAX_Rate']);
            $nextCluster[$nclusterCount]['Action_Rate'] = floor(20 * $row['AVG_of_Action_Rate']);
            $nextCluster[$nclusterCount]['Adventure_Rate'] = floor(20 * $row['AVG_of_Adventure_Rate']);
            $nextCluster[$nclusterCount]['Animation_Rate'] = floor(20 * $row['AVG_of_Animation_Rate']);
            $nextCluster[$nclusterCount]['Children_Rate'] = floor(20 * $row['AVG_of_Children_Rate']);
            $nextCluster[$nclusterCount]['Comedy_Rate'] = floor(20 * $row['AVG_of_Comedy_Rate']);
            $nextCluster[$nclusterCount]['Crime_Rate'] = floor(20 * $row['AVG_of_Crime_Rate']);
            $nextCluster[$nclusterCount]['Documentary_Rate'] = floor(20 * $row['AVG_of_Documentary_Rate']);
            $nextCluster[$nclusterCount]['Drama_Rate'] = floor(20 * $row['AVG_of_Drama_Rate']);
            $nextCluster[$nclusterCount]['Fantasy_Rate'] = floor(20 * $row['AVG_of_Fantasy_Rate']);
            $nextCluster[$nclusterCount]['FilmNoir_Rate'] = floor(20 * $row['AVG_of_FilmNoir_Rate']);
            $nextCluster[$nclusterCount]['Horror_Rate'] = floor(20 * $row['AVG_of_Horror_Rate']);
            $nextCluster[$nclusterCount]['Musical_Rate'] = floor(20 * $row['AVG_of_Musical_Rate']);
            $nextCluster[$nclusterCount]['Mystery_Rate'] = floor(20 * $row['AVG_of_Mystery_Rate']);
            $nextCluster[$nclusterCount]['Romance_Rate'] = floor(20 * $row['AVG_of_Romance_Rate']);
            $nextCluster[$nclusterCount]['SciFi_Rate'] = floor(20 * $row['AVG_of_SciFi_Rate']);
            $nextCluster[$nclusterCount]['Thriller_Rate'] = floor(20 * $row['AVG_of_Thriller_Rate']);
            $nextCluster[$nclusterCount]['War_Rate'] = floor(20 * $row['AVG_of_War_Rate']);
            $nextCluster[$nclusterCount]['Western_Rate'] = floor(20 * $row['AVG_of_Western_Rate']);

            $clusterLabels = $clusterLabels . "|" . "Segment " . $nextCluster[$nclusterCount]['SegmentID'];
            $clusterQuoteData = $clusterQuoteData . "|" . $nextCluster[$nclusterCount]['Action_Quote'] . "," . $nextCluster[$nclusterCount]['Adventure_Quote'] . "," . $nextCluster[$nclusterCount]['Animation_Quote'] . "," . $nextCluster[$nclusterCount]['Children_Quote'] . "," . $nextCluster[$nclusterCount]['Comedy_Quote'] . "," . $nextCluster[$nclusterCount]['Crime_Quote'] . "," . $nextCluster[$nclusterCount]['Documentary_Quote'] . "," . $nextCluster[$nclusterCount]['Drama_Quote'] . "," . $nextCluster[$nclusterCount]['Fantasy_Quote'] . "," . $nextCluster[$nclusterCount]['FilmNoir_Quote'] . "," . $nextCluster[$nclusterCount]['Horror_Quote'] . "," . $nextCluster[$nclusterCount]['Musical_Quote'] . "," . $nextCluster[$nclusterCount]['Mystery_Quote'] . "," . $nextCluster[$nclusterCount]['Romance_Quote'] . "," . $nextCluster[$nclusterCount]['SciFi_Quote'] . "," . $nextCluster[$nclusterCount]['Thriller_Quote'] . "," . $nextCluster[$nclusterCount]['War_Quote'] . "," . $nextCluster[$nclusterCount]['Western_Quote'];
			
            $clusterRateData = $clusterRateData . "|" . $nextCluster[$nclusterCount]['Action_Rate'] . "," . $nextCluster[$nclusterCount]['Adventure_Rate'] . "," . $nextCluster[$nclusterCount]['Animation_Rate'] . "," . $nextCluster[$nclusterCount]['Children_Rate'] . "," . $nextCluster[$nclusterCount]['Comedy_Rate'] . "," . $nextCluster[$nclusterCount]['Crime_Rate'] . "," . $nextCluster[$nclusterCount]['Documentary_Rate'] . "," . $nextCluster[$nclusterCount]['Drama_Rate'] . "," . $nextCluster[$nclusterCount]['Fantasy_Rate'] . "," . $nextCluster[$nclusterCount]['FilmNoir_Rate'] . "," . $nextCluster[$nclusterCount]['Horror_Rate'] . "," . $nextCluster[$nclusterCount]['Musical_Rate'] . "," . $nextCluster[$nclusterCount]['Mystery_Rate'] . "," . $nextCluster[$nclusterCount]['Romance_Rate'] . "," . $nextCluster[$nclusterCount]['SciFi_Rate'] . "," . $nextCluster[$nclusterCount]['Thriller_Rate'] . "," . $nextCluster[$nclusterCount]['War_Rate'] . "," . $nextCluster[$nclusterCount]['Western_Rate'];

            $nclusterCount++;
        }
		
        $quoteChart = "http://chart.googleapis.com/chart?chxl=0:|Action|Adventure|Animation|Children|Comedy|Crime|Documentary|Drama|Fantasy|FilmNoir|Horror|Musical|Mystery|Romance|SciFi|Thriller|War|Western&chxs=0,676767,11.5,1,lt,000000&chxt=x&chs=650x450&cht=r&chco=FF0000,FF9900,3366CC,008000,49188F&chg=5,5,2,2&chls=4|2|2|2|2&chma=|0,5&chtt=Customer Profile&chts=676767,20&chd=t:" . $clusterQuoteData . "&chdl=" . $clusterLabels;
        $rateChart = "http://chart.googleapis.com/chart?chxl=0:|Action|Adventure|Animation|Children|Comedy|Crime|Documentary|Drama|Fantasy|FilmNoir|Horror|Musical|Mystery|Romance|SciFi|Thriller|War|Western&chxs=0,676767,11.5,1,lt,000000&chxt=x&chs=650x450&cht=r&chco=FF0000,FF9900,3366CC,008000,49188F&chg=5,5,2,2&chls=4|2|2|2|2&chma=|0,5&chtt=Rating Profile&chts=676767,20&chd=t:" . $clusterRateData . "&chdl=" . $clusterLabels;

        $Response = array("recoQuery" => $recoQuery, "Settings" => $settings, "Recommendations" => $recommendedList, "QuoteChart" => $quoteChart, "RateChart" => $rateChart, "RatedMovies" => $userHistory);

        echo stringify($Response, $settings["format"]);
		flush();
		
        break;
}


function getSimilarOffers($offerID, $connection, $limit = 5) {
	$similarOffers = array();
	$movieResult = $connection->query("SELECT b.*,a.* FROM `offer_association` a, offer_detail b WHERE a.src = '".$offerID."' and a.dst = b.offerId GROUP BY a.dst ORDER BY CONF desc LIMIT ". $limit);
	$offerCount = 0;

	while ($row = $movieResult->fetch_assoc()) {
		$similarOffers[$offerCount]['id'] = intval($row["OfferID"]);
		$similarOffers[$offerCount]['title'] = utf8_encode($row["Title"]);
		//$similarOffers[$offerCount]['rating'] = ($row["Rating"]);
		//$similarOffers[$offerCount]['genre'] = utf8_encode($row["Genre"]);
		//$similarOffers[$offerCount]['releaseDate'] = ($row["Released"]);
		//$similarOffers[$offerCount]['director'] = utf8_encode($row["Director"]);
		//$similarOffers[$offerCount]['writer'] = explode(", ", utf8_encode($row["Writer"]));
		//$similarOffers[$offerCount]['cast'] = explode(", ", utf8_encode($row["Cast"]));
		//$similarOffers[$offerCount]['imdbRate'] = floatval($row["Imdb_rating"]);
		//$similarOffers[$offerCount]['imddVotes'] = floatval($row["Imdb_votes"]);
		$similarOffers[$offerCount]['categories'] = explode("|", utf8_encode($row["OfferDetailCategories"]));
		$similarOffers[$offerCount]['year'] = intval($row["OfferDetailYear"]);
		//$similarOffers[$offerCount]['prob'] = floatval($row["CONF"]);
		$similarOffers[$offerCount]['picture'] = $row["Poster"];
		//$similarOffers[$offerCount]['plot'] = utf8_encode($row["Plot"]);
		//$similarOffers[$offerCount]['fullPlot'] = utf8_encode($row["FullPlot"]);
		$offerCount++;
	}

	if($offerCount == 0)
		return null;

	return $similarOffers;
}


function getUserHistory($userId, $connection, $limit = 20) {
	$historyResult = $connection->query("select * from `user_response_history` WHERE UserID = '". $userId ."'");;
    $historyList = array();
    $historyCount = 0;
    while ($row = $historyResult->fetch_assoc()) {
        $historyList[$historyCount]['offerID'] = intval($row["OfferID"]);
        $historyList[$historyCount]['rate'] = intval($row["Rate"]);
        $historyList[$historyCount]['dttm'] = $row["DateTime"];
		$historyList[$historyCount]['similar'] = getSimilarOffers(intval($row["OfferID"]),  $connection);
        $historyCount++;
    }
	return $historyList;
}























function stringify($response, $format) {
    if ($format == "xml") {
        @header('Content-type: application/xml');
        return XMLSerializer::generateValidXmlFromArray($response);
    } else {
        @header('Content-type: application/json');
        return indent(json_encode($response));
    }
}

/**
 * Indents a flat JSON string to make it more human-readable.
 *
 * @param string $json The original JSON string to process.
 *
 * @return string Indented version of the original JSON string.
 */
function indent($json) {
    $result = '';
    $pos = 0;
    $strLen = strlen($json);
    $indentStr = '  ';
    $newLine = "\n";
    $prevChar = '';
    $outOfQuotes = true;

    for ($i = 0; $i <= $strLen; $i++) {
        // Grab the next character in the string.
        $char = substr($json, $i, 1);
        // Are we inside a quoted string?
        if ($char == '"' && $prevChar != '\\') {
            $outOfQuotes = !$outOfQuotes;
            // If this character is the end of an element,
            // output a new line and indent the next line.
        } else if (($char == '}' || $char == ']') && $outOfQuotes) {
            $result .= $newLine;
            $pos --;
            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }
        // Add the character to the result string.
        $result .= $char;
        // If the last character was the beginning of an element,
        // output a new line and indent the next line.
        if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
            $result .= $newLine;
            if ($char == '{' || $char == '[') {
                $pos ++;
            }
            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }
        $prevChar = $char;
    }
    return $result;
}

class XMLSerializer {
    // functions adopted from http://www.sean-barton.co.uk/2009/03/turning-an-array-or-object-into-xml-using-php/

    public static function generateValidXmlFromObj(stdClass $obj, $node_block = 'nodes', $node_name = 'node') {
        $arr = get_object_vars($obj);
        return self::generateValidXmlFromArray($arr, $node_block, $node_name);
    }

    public static function generateValidXmlFromArray($array, $node_block = 'items', $node_name = 'item') {
        $xml = '<?xml version="1.0" encoding="UTF-8" ?>';

        $xml .= '<' . $node_block . '>';
        $xml .= self::generateXmlFromArray($array, $node_name);
        $xml .= '</' . $node_block . '>';

        return $xml;
    }

    private static function generateXmlFromArray($array, $node_name) {
        $xml = '';
        if (is_array($array) || is_object($array)) {
            foreach ($array as $key => $value) {
                if (is_numeric($key)) {
                    $key = $node_name;
                }

                $xml .= '<' . $key . '>' . self::generateXmlFromArray($value, $node_name) . '</' . $key . '>';
            }
        } else {
            $xml = htmlspecialchars($array, ENT_QUOTES);
        }
        return $xml;
    }
}
