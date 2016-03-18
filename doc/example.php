<?php
/**
 * This file has some examples of how to use the class. No fancy interface or
 * pretty-formatted output – just some simple examples to get you started.
 */

// First, we need to include the class file
require_once('../lib/fdroid.class.php');

// Now we create an instance of the class. As parameters this expects the path
// where your repository's index.xml resides, and a default value for the
// "pager" (how many items per page you want to display). You will need to
// adjust at least the first parameter if you want to see the whole thing
// in action.
$fdroid = new fdroid('./repo',10);

// Now lets see what repo we've got here:
$meta = $fdroid->getMeta();
// the MetaData object returned has the properties icon, name, pubkey, timestamp,
// url, version, appcount and description:
echo 'Repository "'.$meta->name.'" ('.$meta->url.") offers ".$meta->appcount." apps.\n";

// Wanna see what categories are covered?
$cats = $fdroid->getCats();
echo "The following categories are covered: ".implode(', ',$cats)."\n";

// We can directly access apps by their package name or their display name using
// e.g.
//
// * $fdroid->getAppById('com.adguard.android'));
// * $fdroid->getAppByName('Adguard');
//
// each app has the following properties (note that not all of them are always set):
//
//     id (package_name), added (date), lastupdated (date), name (app_name), summary,
//     icon, desc, license, categories (CSV), category (the first one),
//     web + source + tracker + changelog (URLs), marketversion (object), marketvercode,
//     antifeatures, requirements ("root"), package (object OR array[0..n] of objects)
//     --> added, lastupdated: relates to the repo, not to the app!
//
// The package itself again is an object:
//
//     version, versioncode, apkname (fileName), hash, sig, size (bytes), sdkver, added (date),
//     permissions (CSV), nativecode (CSV), features (CSV)
//     --> added, lastupdated: relates to the repo – no details of the file date (need to take that from files)
//
// But how to know what apps are there?
//
// * $fdroid->getAppList()              gives a list of all apps.
// * $fdroid->getAppsByCat($category)   gives all apps from that category
// * $fdroid->getAppsAddedSince($date)  gives all apps added to the repo on $date or later
// * $fdroid->getAppsUpdatedSince($date)      all apps updated in the repo on $date or later
// * $fdroid->getAppsBuildSince($date)  gives all apps built by their devs on $date or later
//
// $fdroid->getAppsBuildSince($date) requires that your APK files have the correct time stamps.
// All the above methods support two additional parameters for "paging" (e.g. how many results
// you want to show per page, and where to start). Both are integers: $start and $limit. The
// latter we've already set when initializing our class, so we can skip it most of the time.
//
// Now you can also search for apps. This requires to have the class setup a specific index first,
// so we tell the class it should do so. Without that index, you'd always get an empty result set.
$fdroid->setFTS(true);
$apps = $fdroid->searchApps('block');
echo count($apps)." apps match the search string 'block'.\n";

// You want a list of all apps from a specific category which have been added to the repo
// since a given date? The class has a method for that, too:
$apps = $fdroid->intersect(
    $fdroid->getAppsByCat('None',0,0),
    $fdroid->getAppsAddedSince('2016-03-10',0,0)
);
echo count($apps)." apps without category have been added since March 10, 2016.\n";
// Hints here:
// * the category "None" is a special one holding all apps without category
// * note I've added $start = $limit = 0 to the "inner calls". Paging will be
//   done in the "intersect()" method.
// * $fdroid->
exit;
?>