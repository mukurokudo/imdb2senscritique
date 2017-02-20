# imdb2senscritique

A PHP script updating your [SensCritique](http://www.senscritique.com/) ratings based on a [IMDB](http://www.imdb.com/)-export CSV file.

### Usage

- Copy the files on a server (with PHP5+ and CURL).
- Visit `/imdb2senscritique.php`.

### Settings

- **IMDB file** : a CSV file in IMDB-export format, exported from IMDB or converted with [imdbify](https://github.com/nliautaud/moviescheckstools#imdbify).
- **Start item** : number of the first item to process.
- **Item number** : number of items to process. Several hundreds of items can take a while. Lower the value in case of server timeout.
- **SC email / password** : email and password registered on SensCritique.
- **Overwrite** : update existing ratings with new ones. Disable to add only new ones.

### Credits
 - script logic by [phorque](https://github.com/phorque) / [Bahanix](https://github.com/Bahanix) / [mukurokudo](https://github.com/mukurokudo)
 - simple_html_dom by [samacs](https://github.com/samacs)
