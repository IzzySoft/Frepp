## PHP Repo Parser for F-Droid
This repo contains a PHP class to parse and use the `index.xml` files of a local
F-Droid repository. It is still in its early stage, so not much documentation
for now. Feel free to use it with your own projects as long as the license fits.

To get started, you might wish to take a look at the
[`doc/example.php`](doc/example.php) file which gives a short walk-through.
All source code is also documented using the
[Javadoc](https://en.wikipedia.org/wiki/Javadoc) style
[PHPDoc](https://en.wikipedia.org/wiki/PHPDoc). So take a look at the latter
Wikipedia page for possibilities to generate an API reference, if you need it.

**NOTE:** This class is intended to be used with a *local* repository. I've just
tested it against my own (with less than 70 apps in), where speed/processing-time
seems acceptable. It (currently) won't work with a remote repository, or with
just the `index.xml` downloaded – and will probably get pretty slow if used
against a large repository. Feedback is always welcome, of course :)


### License
This project uses the GPLv2 license as stated in the [LICENSE](LICENSE) file.
