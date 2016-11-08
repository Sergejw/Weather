<?php

class Query {

    /** @var null Instance of this object. */
    private static $instance = NULL;

    /** @var string Base url of query. */
    private static $BASE_URL = "http://api.openweathermap.org/data/2.5/weather?q=";

    /** @var string Key for query. */
    private static $KEY = "";

    /** @var string Format for query. */
    private static $MODE = "&mode=xml";

    /** @var string Units for query. */
    private static $UNITS = "&units=metric";

    /** @var array Umlauts for replace in user input.*/
    private static $SEARCH = array('ä', 'Ä', 'ö', 'Ö', 'ü', 'Ü', 'ß', ' ');

    /** @var array ASCII for umlauts in user input.*/
    private static $REPLACE = array('ae', 'ae', 'oe', 'oe', 'ue', 'ue', 'ss', '');

    /** @var string Allowed input characters.*/
    private static $REGEX = '/^[a-z, ]*$/i';

    /** @var string City part for request.*/
    private $city;

    /** @var string Country part for request.*/
    private $country;

    private $xml;

    private $pdo;



    private $response = array("response_state" => "", "city" => "", "country" => "", "min" => "", "max" => "", "temp" => "");

    const READY = 0;
    const INSERT = 1;
    const UPDATE = 2;

    private $server = "localhost";
    private $user = "";
    private $pass = "";
    private $database = "";

    function __construct() {
        $this->pdo = new PDO('mysql:host='.$this->server.';dbname='.$this->database, $this->user, $this->pass);
    }



    /**
     *  Returns instance of this object.
     *  @return null|Query Instance of this object.
     */
    static public function getInstance() {
        return (self::$instance === NULL) ? self::$instance = new Query() : self::$instance;
    }


    /**
     *  Queries openweather API and returns result.
     *  @return null|SimpleXMLElement Result of query.
     */
    private function getAPIResponse() {
        $query = self::$BASE_URL . $this->city . "," . $this->country . self::$KEY . self::$MODE . self::$UNITS;
        $file_content = file_get_contents($query);

        return ($file_content[0] != '<') ? null : new SimpleXMLElement($file_content);
    }

    /**
     * @param $input
     * @return array
     */
    public function getWeather($input)
    {
        // check input
        if (!$this->isValid($input))
            return null;

        // INSERT = init state which means that database don't
        // contains this data (new response)
        $this->response["response_state"] = self::INSERT;
        $this->createResponseFromDatabase();

        // database has no or corrupt data
        if ($this->response["response_state"] != self::READY)
        {
            if (($api = $this->getAPIResponse()) == null)
                return null;

            $this->response["city"]     = $api->city['name'];
            $this->response["country"]  = $api->city->country;
            $this->response["min"]      = $api->temperature['min'];
            $this->response["max"]      = $api->temperature['max'];
            $this->response["temp"]     = $api->temperature['value'];
echo $api->temperature['min'];
            echo $api->temperature['max'];
            echo $api->temperature['value'];
            if ($this->response["response_state"] == self::INSERT)
                $this->insertResponseIntoDatabase();

            elseif ($this->response["response_state"] == self::UPDATE)
                $this->updateDBContent();
        }

        return $this->response;
    }


    /**
     *
     */
    private function updateDBContent()
    {
        $sql = "UPDATE weather SET min = :min, max = :max, temp = :temp, stamp = NOW() WHERE city = :city";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':min',    $this->response['min']);
        $stmt->bindParam(':max',    $this->response['max']);
        $stmt->bindParam(':temp',   $this->response['temp']);
        $stmt->bindParam(':city',   $this->response['city']);
        $stmt->execute();
    }


    /**
     *  Returns an integer which represents the difference in hours of two
     *  different dates. Reference point is current date.
     *
     *  @param $date Date which will be compared with current date/time.
     *  @return string Difference in hours.
     */
    function getDateDifferenceInHours($date)
    {
        $first_datetime = date_create($date);
        $second_datetime = date_create(date('Y-m-d H:i:s'));
        $diff = date_diff($first_datetime, $second_datetime);

        return $diff->format('%h'); // %h = difference in hours
    }


    /**
     *  Inserts content of response into database which was provided by api call
     *  (openweathermap) and is non-existent within database.
     */
    private function insertResponseIntoDatabase()
    {
        $sql = "INSERT INTO weather(city, country, min, max, stamp, temp) 
                VALUES (:city, :country, :min, :max, NOW(), :temp)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':city',       $this->response['city']);
        $stmt->bindParam(':country',    $this->response['country']);
        $stmt->bindParam(':min',        $this->response['min']);
        $stmt->bindParam(':max',        $this->response['max']);
        $stmt->bindParam(':temp',       $this->response['temp']);
        $stmt->execute();
    }


    /**
     *  Creates response from database. The function creates/sets response states
     *  into class variable '$response' depending on availability of data. If no
     *  data was found than response state stays same (INSERT) as init state
     *  within getWeather() function. At outdated data the state will be (UPDATE)
     *  what means that after api call (openweathermap) this data must be updated.
     *  If usable data exists so this function creates full response for
     *  presentation. Response state will be (READY).
     */
    private function createResponseFromDatabase()
    {
        $sql = "SELECT * FROM weather WHERE city='" . $this->city . "'";

        $stmt = $this->pdo->prepare($sql);
        $stmt ->execute();

        // return due no data/entry in database for this city
        // response_state still init state (INSERT) see: getWeather() function
        if ($stmt->rowCount() <= 0)
            return;

        $row = $stmt->fetch();

        // compare timestamp database content and now
        // difference > 2 means unusable data
        if ($this->getDateDifferenceInHours($row['stamp']) > 1) // 2 = hours
        {
            // data exists in database but older than 2 hours
            // update this data after api call
            $this->response["response_state"] = self::UPDATE;
        }
        else
        {
            $this->response["city"]     = $row['city'];
            $this->response["country"]  = $row['country'];
            $this->response["min"]      = $row['min'];
            $this->response["max"]      = $row['max'];
            $this->response["temp"]     = $row['temp'];

            // data exists in database and its now content of response
            // which can be represented for user
            $this->response["response_state"] = self::READY;
        }
    }


    /**
     *  Checks user input for not allowed characters. Allowed characters are
     *  (a-zA-Z,whitespace). If input valid the function init local class variables
     *  city and if given country and returns true else the function returns false.
     *
     *  @param $input User input.
     *  @return bool Valid user input or not.
     */
    private function isValid($input)
    {
        // replace german umlauts and whitespace cause only ascii allowed
        // german letters was requirement by prof
        $input = str_replace(self::$SEARCH, self::$REPLACE, $input);

        // more than one comma or other chars than a-z
        if (substr_count($input, ",") > 1 || !preg_match(self::$REGEX, $input))
            return false;

        // split input in city and country if necessary
        if (strpos($input, ','))
        {
            $a = explode(',', $input);
            $this->city = $a[0];
            $this->country = $a[1];
        }
        else
        {
            $this->city = $input;
            $this->country = 'DE';
        }

        return true;
    }
}