<?php
    include_once 'config.php';
    include_once 'Query.php';

    $query = Query::getInstance();

    if (!empty($_GET)) {
        $weather = $query->getWeather(filter_input(INPUT_GET, 'city', FILTER_SANITIZE_STRING));

        if (!empty($weather)) {
            $temp = $weather['temp'];
            $low = $weather['min'];
            $high = $weather['max'];
            $city = $weather['city'];
            $country = $weather['country'];
        }
    }
?>

<!DOCTYPE html> 
<html>
    <head>
        <title>Wetter</title>
        <meta charset="UTF-8">
        <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css'>
        <link rel="stylesheet" type="text/css" href="style.css">
    </head>
    <body>
        <div class="div">
            <h1>Wetter</h1>
            <form action="index.php" method="get" >
                <input id="eingabe" type="text" name="city" value="<?php if (!empty($city)){echo $city .", ". $country;}?>"  
                       onblur="value='<?php if (!empty($city)){echo $city .", ". $country;}?>'" placeholder="Berlin, DE"">
                <input id="button" type="submit" value=">">
            </form>
            <?php if (!empty($weather)) :?>
                <div class="back">
                    <div class="leftCol">
                        <?php echo " " . $temp."°C"; ?>
                    </div>
                    <div class="rightCol">
                        <span class="blau"><?php echo $low . "°C"; ?></span>
                        <span>/</span>
                        <span class="rot"><?php echo $high . "°C"; ?><br></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="back">
                    <p>Eingabe konnte nicht verabeitet werden.</p>
                </div>
            <?php endif; ?>

        </div>
    </body>
</html>
