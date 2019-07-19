<!DOCTYPE html>
<html>
<head>
    <title>hQuery Examples folder</title>
</head>
<body>
    <h3>Select an example to run:</h3>
    <hr />
    <ul>
        <?php
        $dir = opendir(__DIR__);
        if ($dir) {
            while ($d = readdir($dir)) {
                if ($d == '.' || $d == '..') continue;
                if (strrchr($d, '.') === '.php' && $d != basename(__FILE__)) {
                    $b = basename($d, '.php');
                    echo "<li><a href='$d'>$b</a></li>";
                }
            }
            closedir($dir);
        }
        ?>
    </ul>
    <hr />
</body>
</html>
