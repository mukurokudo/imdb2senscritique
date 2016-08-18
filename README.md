# imdb2senscritique

A PHP script updating your [SensCritique](http://www.senscritique.com/) ratings based on an [IMDB](http://www.imdb.com/) export.

### requirements

A running PHP 5+ server with CURL

### Usage

 - install these files
 - export the IMDB list you want by clicking onto the button "Export this list" at the end of your IMDB list
 - move the generated csv file into the /web folder
 - update the following 3 variables in the imdb2senscritique.php file : 
```php
$imdbRatings = "./web/exempleFile.csv"; // the filePath of the imdb generated file
$scEmail = "YOUR_EMAIL_ADDRESS"; // your senscritique email
$scPwd = "YOUR_PASSWORD"; // your senscritique password
```
 - you can uncomment the following line if you are processing a large file
```php
ini_set('max_execution_time', 0);
 ```
### Credits
 - script logic by [Bahanix](https://github.com/Bahanix)
 - simple_html_dom by [samacs](https://github.com/samacs)
