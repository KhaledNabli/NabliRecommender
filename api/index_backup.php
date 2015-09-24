<?php

session_start();

// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers:        {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    exit(0);
}

// handle session options and generate user id;


$mysql = new mysqli("rdbms.strato.de", "U1408197", "SASpw1", "DB1408197");


// Read Settings
if (!empty($_GET['user']))
    $_SESSION['userid'] = $_GET['user'];
if (!empty($_GET['listsize']))
    $_SESSION['nmovies'] = $_GET['listsize'];
if (!empty($_GET['clustersize']))
    $_SESSION['nclusters'] = $_GET['clustersize'];
if (!empty($_GET['format']))
    $_SESSION['format'] = $_GET['format'];

if (empty($_SESSION['format']))
    $_SESSION['format'] = "json";
if (empty($_SESSION['nclusters']))
    $_SESSION['nclusters'] = 3;
if (empty($_SESSION['nmovies']))
    $_SESSION['nmovies'] = 20;
if (empty($_SESSION['userid'])) {
    $_SESSION['userid'] = mt_rand(1000000, 9999999);
    $mysql->query("CALL `cluster_user` (" . $_SESSION['userid'] . ", " . $_SESSION['nclusters'] . ")");
}


switch (@$_GET['action']) {
    case "reset":
        $_SESSION['userid'] = null;
        $_SESSION['nclusters'] = null;
        $_SESSION['nmovies'] = null;
        break;

    case "rate":
        $rate = $_GET['rate'];
        $movieid = $_GET['movie'];

        $mysql->query("INSERT INTO `demo_rating` (`UserID`, `MovieID`, `Rate`, `DateTime`) VALUES ('" . $_SESSION['userid'] . "', '" . $movieid . "', '" . $rate . "', NOW())");
        $mysql->query("CALL `update_abt` (" . $_SESSION['userid'] . ")");
        $mysql->query("CALL `cluster_user` (" . $_SESSION['userid'] . ", " . $_SESSION['nclusters'] . ")");

    //break;

    case "recommend":
    default:
        // read history
        $historyResult = $mysql->query("SELECT `MovieID`, `Rate`, `DateTime` FROM `demo_rating` WHERE `UserID` = " . $_SESSION['userid']);
        $historyList = array();
        $historyCount = 0;
        while ($row = $historyResult->fetch_assoc()) {
            $historyList[$historyCount]['movieId'] = intval($row["MovieID"]);
            $historyList[$historyCount]['rate'] = intval($row["Rate"]);
            $historyList[$historyCount]['dttm'] = $row["DateTime"];
            $historyCount++;
        }


        // read recommendations
        $movieResult = $mysql->query("select DISTINCT a.UserID, a.cluster, MovieID, Title, Year, categories, Poster, max(Reviews) as Reviews, max(AVG_Rate) as Rate, Prob, Distance, (Prob/Distance) as Points from in_memory_cluster a, cluster_recommendations b where a.cluster = b.cluster and a.UserID = " . $_SESSION['userid'] . " and MovieID not in (select DISTINCT MovieID from demo_rating c WHERE c.UserID = a.UserID) GROUP BY a.UserID, MovieID, Title, Year order by Points DESC LIMIT " . $_SESSION['nmovies']);
        $movieCount = 0;
        $recommendedList = array();

        while ($row = $movieResult->fetch_assoc()) {
            $recommendedList[$movieCount]['id'] = intval($row["MovieID"]);
            $recommendedList[$movieCount]['nextSegmentID'] = intval($row["cluster"]);
            $recommendedList[$movieCount]['title'] = $row["Title"];
            $recommendedList[$movieCount]['picture'] = $row["Poster"];
            $recommendedList[$movieCount]['categories'] = explode("|", $row["categories"]);
            $recommendedList[$movieCount]['year'] = intval($row["Year"]);
            $recommendedList[$movieCount]['reviews'] = intval($row["Reviews"]);
            $recommendedList[$movieCount]['rate'] = floatval($row["Rate"]);
            $recommendedList[$movieCount]['prob'] = floatval($row["Prob"]);
            $recommendedList[$movieCount]['distance'] = floatval($row["Distance"]);
            $recommendedList[$movieCount]['points'] = floatval($row["Points"]);
            $movieCount++;
        }

        // read own cluster statistics
        $ownClusterResult = $mysql->query("SELECT `UserID`, `QUOTE_of_IMAX_Movie`, `QUOTE_of_Action_Movie`, `QUOTE_of_Adventure_Movie`, `QUOTE_of_Animation_Movie`, `QUOTE_of_Children_Movie`, `QUOTE_of_Comedy_Movie`, `QUOTE_of_Crime_Movie`, `QUOTE_of_Documentary_Movie`, `QUOTE_of_Drama_Movie`, `QUOTE_of_Fantasy_Movie`, `QUOTE_of_FilmNoir_Movie`, `QUOTE_of_Horror_Movie`, `QUOTE_of_Musical_Movie`, `QUOTE_of_Mystery_Movie`, `QUOTE_of_Romance_Movie`, `QUOTE_of_SciFi_Movie`, `QUOTE_of_Thriller_Movie`, `QUOTE_of_War_Movie`, `QUOTE_of_Western_Movie`, `AVG_of_IMAX_Rate`, `AVG_of_Action_Rate`, `AVG_of_Adventure_Rate`, `AVG_of_Animation_Rate`, `AVG_of_Children_Rate`, `AVG_of_Comedy_Rate`, `AVG_of_Crime_Rate`, `AVG_of_Documentary_Rate`, `AVG_of_Drama_Rate`, `AVG_of_Fantasy_Rate`, `AVG_of_FilmNoir_Rate`, `AVG_of_Horror_Rate`, `AVG_of_Musical_Rate`, `AVG_of_Mystery_Rate`, `AVG_of_Romance_Rate`, `AVG_of_SciFi_Rate`, `AVG_of_Thriller_Rate`, `AVG_of_War_Rate`, `AVG_of_Western_Rate` FROM `demo_user` WHERE `UserID` = " . $_SESSION['userid']);
        $row = $ownClusterResult->fetch_assoc();
        $clusterLabels = "You";
        $clusterQuoteData = floor(100 * $row['QUOTE_of_Action_Movie']) . "," . floor(100 * $row['QUOTE_of_Adventure_Movie']) . "," . floor(100 * $row['QUOTE_of_Animation_Movie']) . "," . floor(100 * $row['QUOTE_of_Children_Movie']) . "," . floor(100 * $row['QUOTE_of_Comedy_Movie']) . "," . floor(100 * $row['QUOTE_of_Crime_Movie']) . "," . floor(100 * $row['QUOTE_of_Documentary_Movie']) . "," . floor(100 * $row['QUOTE_of_Drama_Movie']) . "," . floor(100 * $row['QUOTE_of_Fantasy_Movie']) . "," . floor(100 * $row['QUOTE_of_FilmNoir_Movie']) . "," . floor(100 * $row['QUOTE_of_Horror_Movie']) . "," . floor(100 * $row['QUOTE_of_Musical_Movie']) . "," . floor(100 * $row['QUOTE_of_Mystery_Movie']) . "," . floor(100 * $row['QUOTE_of_Romance_Movie']) . "," . floor(100 * $row['QUOTE_of_SciFi_Movie']) . "," . floor(100 * $row['QUOTE_of_Thriller_Movie']) . "," . floor(100 * $row['QUOTE_of_War_Movie']) . "," . floor(100 * $row['QUOTE_of_Western_Movie']);
        $clusterRateData = floor(20 * $row['AVG_of_Action_Rate']) . "," . floor(20 * $row['AVG_of_Adventure_Rate']) . "," . floor(20 * $row['AVG_of_Animation_Rate']) . "," . floor(20 * $row['AVG_of_Children_Rate']) . "," . floor(20 * $row['AVG_of_Comedy_Rate']) . "," . floor(20 * $row['AVG_of_Crime_Rate']) . "," . floor(20 * $row['AVG_of_Documentary_Rate']) . "," . floor(20 * $row['AVG_of_Drama_Rate']) . "," . floor(20 * $row['AVG_of_Fantasy_Rate']) . "," . floor(20 * $row['AVG_of_FilmNoir_Rate']) . "," . floor(20 * $row['AVG_of_Horror_Rate']) . "," . floor(20 * $row['AVG_of_Musical_Rate']) . "," . floor(20 * $row['AVG_of_Mystery_Rate']) . "," . floor(20 * $row['AVG_of_Romance_Rate']) . "," . floor(20 * $row['AVG_of_SciFi_Rate']) . "," . floor(20 * $row['AVG_of_Thriller_Rate']) . "," . floor(20 * $row['AVG_of_War_Rate']) . "," . floor(20 * $row['AVG_of_Western_Rate']);

        // read next cluster statistics
        $clusterResult = $mysql->query("SELECT a.Cluster, round(Distance,2) as Distance , `AVG_of_Rate`, `COUNT_of_Users`, `COUNT_of_Movies`, `QUOTE_of_IMAX_Movie`, `QUOTE_of_Action_Movie`, `QUOTE_of_Adventure_Movie`, `QUOTE_of_Animation_Movie`, `QUOTE_of_Children_Movie`, `QUOTE_of_Comedy_Movie`, `QUOTE_of_Crime_Movie`, `QUOTE_of_Documentary_Movie`, `QUOTE_of_Drama_Movie`, `QUOTE_of_Fantasy_Movie`, `QUOTE_of_FilmNoir_Movie`, `QUOTE_of_Horror_Movie`, `QUOTE_of_Musical_Movie`, `QUOTE_of_Mystery_Movie`, `QUOTE_of_Romance_Movie`, `QUOTE_of_SciFi_Movie`, `QUOTE_of_Thriller_Movie`, `QUOTE_of_War_Movie`, `QUOTE_of_Western_Movie`, `AVG_of_IMAX_Rate`, `AVG_of_Action_Rate`, `AVG_of_Adventure_Rate`, `AVG_of_Animation_Rate`, `AVG_of_Children_Rate`, `AVG_of_Comedy_Rate`, `AVG_of_Crime_Rate`, `AVG_of_Documentary_Rate`, `AVG_of_Drama_Rate`, `AVG_of_Fantasy_Rate`, `AVG_of_FilmNoir_Rate`, `AVG_of_Horror_Rate`, `AVG_of_Musical_Rate`, `AVG_of_Mystery_Rate`, `AVG_of_Romance_Rate`, `AVG_of_SciFi_Rate`, `AVG_of_Thriller_Rate`, `AVG_of_War_Rate`, `AVG_of_Western_Rate`  FROM `in_memory_cluster` a, cluster_detail b where a.Cluster = b.Cluster AND UserID = " . $_SESSION['userid'] . " ORDER by Distance LIMIT " . $_SESSION['nclusters']);
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

        $DebugInfo = array("USER_ID" => $_SESSION['userid'], "N_CLUSTERS" => $_SESSION['nclusters'], "N_MOVIES" => $_SESSION['nmovies']);
        $Response = array("Recommendations" => $recommendedList, "NNClusters" => $nextCluster, "QuoteChart" => $quoteChart, "RateChart" => $rateChart, "RatedMovies" => $historyList, "DebugInfo" => $DebugInfo);

        echo stringify($Response);

        break;
}

function stringify($response) {
    if ($_SESSION["format"] == "xml") {
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
