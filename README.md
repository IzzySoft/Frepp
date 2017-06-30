## PHP Repo Parser for F-Droid
This repo contains a PHP class to parse and use the `index.xml` files of an
F-Droid repository. I'm using it regularly now for more than a year, so it
should be quite stable – though it's far from being perfect. Not much
documentation for now. Feel free to use it with your own projects as long as
the license fits.

To get started, you might wish to take a look at the
[`doc/example.php`](doc/example.php) file which gives a short walk-through.
All source code is also documented using the
[Javadoc](https://en.wikipedia.org/wiki/Javadoc) style
[PHPDoc](https://en.wikipedia.org/wiki/PHPDoc). So take a look at the latter
Wikipedia page for possibilities to generate an API reference, if you need it.

Oh: In case you wonder about the name. Originally I've named the project
"Prepaf" (Php REpo PArser for Fdroid). But as not even I myself could remember
that name, I decided for something simpler: Frepp, which means as much as
"Fdroid REpo Parser (library for) PHP".

### Notes
* This class was intended to be used with a *local* repository. I'm using it
  with my own (with a little more than 300 apps in), where speed and
  processing-time seem acceptable.
* While it will not work with a remote repository, it will work with just the
  `index.xml` downloaded – with a few limitations: as the `.apk` files are not
  there then, their timestamps cannot be evaluated for the last-built-time, and
  since the categories.txt won't be available, it cannot be evaluated either).
  A quick test with F-Droid's own `index.xml` (1,700+ apps) showed speed still
  seems reasonable.
* Special addendum: If e.g. with a major update of fdroidserver, its app cache
  might get rebuild, that means the date an app was added to the repo gets lost.
  To take caution for that, you can keep that in the `Maintainer Notes` block
  using a line in the format `AppAdded:YYYY-MM-DD`. The library checks that and
  takes it with preference.

### License
This project uses the GPLv2 license as stated in the [LICENSE](LICENSE) file.
