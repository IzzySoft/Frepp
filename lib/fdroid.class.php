<?php
require_once('xmlconv.class.php');

/** Handle F-Droid repositories
 * @class fdroid extends xmlconv
 * @author Andreas Itzchak Rehberg
 * @copyright © 2016 by Andreas Itzchak Rehberg, protected under the GPLv2
 * @webpage https://github.com/IzzySoft/prepaf
 */
class fdroid extends xmlconv {

  /** directory the repo's files reside in
   * @property protected string repoDir
   */
  protected $repoDir;

  /** Whether to index for full-text-search.
   *  As this requires quite a big index (for larger repos), it's off by default.
   *  use self::setFTS(bool) to switch.
   * @property protected ftsEnabled
   */
  protected $ftsEnabled = false;
  /** Index for Full Text Search (FTS).
   *  This is an array of app_key=>app_json
   * @attribute protected ftsIndex
   */
  protected $ftsIndex = [];

  /** The repo data as object
   * @attribute protected object data
   */
  protected $data;

  /** Available/used categories
   * @attribute protected array cats
   */
  protected $cats;
  /** Available/used licenses
   * @attribute protected array licenses
   */
  protected $licenses = [];

  /** package_names and their index key in data->application.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute appIds
   */
  protected $appIds = [];
  /** app_names and their index key in data->application.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute protected appNames
   */
  protected $appNames = [];
  /** app_lastbuild and their index key in data->application.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute protected appBuilds
   */
  protected $appBuilds = [];
  /** app_repoAdded and their index key in data->application.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute protected appAddeds
   */
  protected $appAddeds = [];
  /** app_repoUpdate and their index key in data->application.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute protected appUpdates
   */
  protected $appUpdates = [];
  /** app_authors and their index key in data->application.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute protected autNames
   */
  protected $autNames = [];
  /** licenses with the index keys of apps (in data->application) being in them.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute protected appLicenses
   */
  protected $appLicenses = [];
  /** categories with the index keys of apps (in data->application) being in them.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute protected appCats
   */
  protected $appCats = [];

  /** How many apps we have.
   *  This is basically a shortcut to self::data->repo->{'@attributes'}->appcount
   * @attribute protected int appcount
   */
  protected $appcount;

  /** number of hits if we hadn't limited search results
   * @attribute protected int fullHits
   */
  protected $fullHits = 0;

  /** Default limit for pager (how many entries to return at maximum)
   * @attribute protected limit
   */
  protected $limit = 0;

  /** Constructor: Load the repo
   * @constructor fdroid
   * @param optional int limit how many apps to return. This sets the default used by the apps.
   * @param string dir the repository's dir (where the index.xml resides)
   */
  public function __construct($dir, $limit=0) {
    $this->repoDir = $dir;
    $this->data = $this->xml2obj($dir.'/index.xml');
    $this->cats = explode("\n",trim(file_get_contents($dir.'/categories.txt')));
    sort($this->cats);
    $this->data->repo->{'@attributes'}->appcount = count($this->data->application);
    $this->appcount = $this->data->repo->{'@attributes'}->appcount;
    $this->fullHits = $this->appcount;
    $this->setLimit($limit);
  }

  /** Set the default pager limit
   * @method public setLimit
   * @param opt int limit value to set the limit to (default: 0, i.e. no limit)
   * @see limit
   */
  public function setLimit($limit=0) {
    (int) $limit;
    if ( is_int($limit) ) $this->limit = abs($limit);
  }

  /** Get the default pager limit
   * @method public getLimit
   * @see limit
   */
  public function getLimit($limit=0) {
    return $this->limit;
  }

  /** Enable/Disable index for Full Text Search.
   *  Quite a big index (with the entire apps data as JSON), so you might wish
   *  to think twice if you're running a larger repo :)
   * @method setFTS
   * @param bool value TRUE to activate, FALSE to deactivate
   */
  public function setFTS($val) {
    $this->ftsEnabled = (bool) $val;
  }

  /** Get the repository meta data
   * @method getMeta
   * @return object metaData. properties: strings icon (fileName), name, pubkey, timestamp, url, version, int appcount, str description
   */
  public function getMeta() {
    $meta = $this->data->repo->{'@attributes'};
    $meta->description = $this->data->repo->description;
    return $meta;
  }

  /** Get used categories
   * @method getCats
   * @return array cats array[0..n] of string, alphabetically sorted
   */
  public function getCats() {
    return $this->cats;
  }

  /** Get used licenses
   * @method getLicenses
   * @return array licenses array[0..n] of string, alphabetically sorted
   */
  public function getLicenses() {
    return $this->licenses;
  }

  /** add some indexes on the app list for easier access
   * @method index
   */
  public function index() {
    foreach($this->data->application as $key=>$app) {
      unset($this->data->application[$key]->{'@attributes'}); // obsolete, only id
      $this->appIds[$app->id] = $key;
      $this->appNames[$app->name] = $key;
      foreach(explode(',',$app->categories) as $ac) {
        if ( !isset($this->appCats[$ac])) $this->appCats[$ac] = [];
        $this->appCats[$ac][] = $key;
      }
      if ( isset($app->author) ) {
        if ( !isset($this->autNames[$app->author]) ) $this->autNames[$app->author] = [];
        $this->autNames[$app->author][] = $key;
      }
      if ( isset($app->license) ) {
        if ( !isset($this->appLicenses[$app->license]) ) $this->appLicenses[$app->license] = [];
        $this->appLicenses[$app->license][] = $key;
        $this->licenses[] = $app->license;
      }
      // now walk get the last modification (i.e. "app update") of the newest file
      if ( is_array($app->package) ) {
        for($i=0;$i<count($app->package);++$i) $this->data->application[$key]->package[$i]->built = date('Y-m-d',filemtime($this->repoDir.'/'.$app->package[$i]->apkname));
        $this->data->application[$key]->lastbuild = $app->package[0]->built;
      } else {
        $this->data->application[$key]->lastbuild = date('Y-m-d',filemtime($this->repoDir.'/'.$app->package->apkname));
        $this->data->application[$key]->package->built = $this->data->application[$key]->lastbuild;
      }
      if ( !isset($this->appBuilds[$app->lastbuild]) ) $this->appBuilds[$app->lastbuild] = [];
      $this->appBuilds[$app->lastbuild][] = $key;
      if ( !isset($this->appAddeds[$app->added]) ) $this->appAddeds[$app->added] = [];
      $this->appAddeds[$app->added][] = $key;
      if ( !isset($this->appUpdates[$app->added]) ) $this->appUpdates[$app->added] = [];
      $this->appUpdates[$app->lastupdated][] = $key;
      if ( $this->ftsEnabled ) $this->ftsIndex[$key] = strtolower(json_encode($app));
    }
    krsort($this->appBuilds); // newest first
    $this->licenses = array_unique($this->licenses);
    sort($this->licenses);
  }

  /** Get number of results regardless of $limit used
   * @method getFullHits
   * @return int fullHits number of available results to the last query if no $limit applied
   */
  function getFullHits() {
    return $this->fullHits;
  }

  /** Get the entire applist as-is (i.e. ordered by names)
   * @method getAppList
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @verbatim
   *   each app with:  id (package_name), added (date), lastupdated (date), name (app_name), summary, icon, desc, license,
   *                   categories (CSV), category (the first one), web + source + tracker + changelog (URLs),
   *                   marketversion (object), marketvercode, antifeatures, requirements ("root"), package (object OR array[0..n] of objects)
   *                   lastbuild (YYYY-MM-DD; only when indexed)
   *                   → added, lastupdated: relates to the repo
   *   package object: version, versioncode, apkname (fileName), hash, sig, size (bytes), sdkver, added (date),
   *                   permissions (CSV), nativecode (CSV), features (CSV)
   *                   → added, lastupdated: relates to the repo – no details of the file date (need to take that from files)
   */
  function getAppList($start=0,$limit=null) {
    $this->fullHits = $this->appcount;
    if ( $limit===null ) $limit = $this->limit;
    if ( $limit==0 && $start==0 ) return $this->data->application; // get all
    if ( $limit==0 ) $max = $this->appcount;
    else $max = $start + $limit;
    $apps = [];
    for ($i=$start;$i<=$max;++$i) {
      if ( $i==$max || $i==$this->appcount ) break;
      $apps[] = $this->data->application[$i];
    }
    return $apps;
  }

  /** Get all apps of a given category
   * @method getAppsByCat
   * @param str cat category
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  function getAppsByCat($cat,$start=0,$limit=null) {
    $this->fullHits = count($this->appCats[$cat]);
    if ( !in_array($cat,$this->cats) ) return array();
    if ( empty($this->appIds) ) $this->index();
    if ( $limit===null ) $limit = $this->limit;
    if ( $limit==0 ) $max = count($this->appCats[$cat]);
    else $max = $start + $limit;
    $apps = [];
    for ($i=$start;$i<=$max,$i<count($this->appCats[$cat]);++$i) {
      if ( $i>=$max ) break;
      else $apps[] = $this->data->application[$this->appCats[$cat][$i]];
    }
    return $apps;
  }

  /** Get all apps by a given author
   * @method getAppsByAuthor
   * @param str author name of the author
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  public function getAppsByAuthor($author,$start=0,$limit=null) {
    if ( empty($this->autNames) ) $this->index();
    if ( $limit===null ) $limit = $this->limit;
    if ( $limit==0 ) $max = count($this->autNames[$author]);
    else $max = $start + $limit;
    $this->fullHits = 0;
    $apps = []; $i=0;
    foreach( $this->autNames[$author] as $aut ) {
      if ( $i>=$max ) ++$this->fullHits;
      elseif ( $i < $start ) { ++$this->fullHits; continue; }
      else { $apps[] = $this->data->application[$aut]; ++$this->fullHits; }
    }
    return $apps;
  }

  /** Get all apps using a given license
   * @method getAppsByLicense
   * @param string license
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  public function getAppsByLicense($license,$start=0,$limit=null) {
    if (empty ($this->appLicenses) ) $this->index();
    if ( $limit===null ) $limit = $this->limit;
    if ( $limit==0 ) $max = count($this->appLicenses[$license]);
    else $max = $start + $limit;
    $this->fullHits = count($this->appLicenses[$license]);
    $apps = [];
    for ($i=$start;$i<=$max,$i<count($this->appLicenses[$license]);++$i) {
      if ( $i>=$max ) break;
      else { $apps[] = $this->data->application[$this->appLicenses[$license][$i]]; }
    }
    return $apps;
  }

  /** Helper to filter app by dates with pager-limit
   * @method protected filterDateRange
   * @param str date 'YYYY-MM-DD'
   * @param int limit how many apps to return. Default is to return all (limit=NULL).
   * @param int start position to start with when paging. Default is 0 (the first app).
   * @param ref array list appList to filter
   * @return array apps array[0..n] of object app
   */
  protected function filterDateRange($date,$limit,$start,&$list) {
    if ( empty($this->appIds) ) $this->index();
    if ( $limit==0 ) $max = $this->appcount;
    else $max = $start + $limit;
    $this->fullHits = 0;
    $i = 0;
    $apps = [];
    foreach($list as $d=>$a) {
      if ( strtotime($d) >= strtotime($date) ) foreach($a as $b) {
        ++$i;
        if ( $i<=$start ) { ++$this->fullHits; continue; }
        elseif ( $i>$max ) ++$this->fullHits;
        else { $apps[] = $this->data->application[$b]; ++$this->fullHits; }
      }
      else continue;
    }
    return $apps;
  }

  /** Get all apps build on a given date or later
   * @method getAppsBuildSince
   * @param str date 'YYYY-MM-DD'
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  public function getAppsBuildSince($date,$start=0,$limit=null) {
    if ( $limit===null ) $limit = $this->limit;
    return $this->filterDateRange($date,$limit,$start,$this->appBuilds);
  }

  /** Get all apps added to the repo on a given date or later
   * @method getAppsAddedSince
   * @param str date 'YYYY-MM-DD'
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  public function getAppsAddedSince($date,$start=0,$limit=null) {
    if ( $limit===null ) $limit = $this->limit;
    return $this->filterDateRange($date,$limit,$start,$this->appAddeds);
  }

  /** Get all apps whos repo entry was updated on a given date or later
   * @method getAppsUpdatedSince
   * @param str date 'YYYY-MM-DD'
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  public function getAppsUpdatedSince($date,$start=0,$limit=null) {
    if ( $limit===null ) $limit = $this->limit;
    return $this->filterDateRange($date,$limit,$start,$this->appUpdates);
  }

  /** Search for apps matching a keyword/string anywhere (name, desc, etc.)
   *  Passed string is taken literally, and matched case-insensitive. No fuzzy-logic
   *  or any other sophisticated stuff for now.
   * @method searchApps
   * @param string keyword Keyword/String to search for
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  public function searchApps($keyword,$start=0,$limit=null) {
    $this->fullHits = 0;
    if ( !$this->ftsEnabled ) return array(); // not indexed, no search
    if ( empty($this->ftsIndex) ) $this->index();
    if ( $limit===null ) $limit = $this->limit;
    if ( $limit==0 ) $max = $this->appcount;
    else $max = $start + $limit;
    $keyword = strtolower($keyword);
    $apps = [];
    $i=0;
    foreach ($this->ftsIndex as $key=>$val) {
      if ( $i==$this->appcount ) break;
      elseif ( $i>=$max && strpos($val,$keyword)!==false ) { ++$i; ++$this->fullHits; }
      elseif (strpos($val,$keyword)!==false) {
        if ( $i<$start ) {
          ++$i;
          ++$this->fullHits;
          continue;
        } else {
          $apps[] = $this->data->application[$key];
          ++$i;
          ++$this->fullHits;
        }
      }
    }
    return $apps;
  }

  /** Intersecting two app lists (returning only apps present in both).
   *  This can be used to select apps meeting multiple criteria, e.g.
   *  fdroid->intersect(fdroid->searchApps('foobar'),fdroid->getAppsByCat('System'))
   *  would only return apps from the System category mentioning 'foobar'.
   * @method intersect
   * @param array apps1
   * @param array apps2
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  public function intersect($arr1,$arr2,$start=0,$limit=null) {
    if ( $limit===null ) $limit = $this->limit;
    if ( empty($arr1) || empty($arr2) ) return [];
    $apps = [];
    foreach($arr1 as $a) {
      foreach($arr2 as $b) {
        if ( $a->id == $b->id ) $apps[] = $a;
      }
    }
    $this->fullHits = count($apps);
    if ($start==0 && $limit==0) return $apps;
    else return array_slice($apps,$start,$start+$limit);
  }

  /** Get data for an app by its package_name
   * @method getAppById
   * @param string id package_name
   * @return object app
   * @see getAppList
   */
  public function getAppById($id) {
    if ( empty($this->appIds) ) $this->index();
    if ( !isset($this->data->application[$this->appIds[$id]]) ) return new stdClass();
    return $this->data->application[$this->appIds[$id]];
  }

  /** Get data for an app by its app_name
   * @method getAppByName
   * @param string id package_name
   * @return object app
   * @see getAppList
   */
  public function getAppByName($id) {
    if ( empty($this->appIds) ) $this->index();
    if ( !isset($this->data->application[$this->appNames[$id]]) ) return new stdClass();
    return $this->data->application[$this->appNames[$id]];
  }
}
?>